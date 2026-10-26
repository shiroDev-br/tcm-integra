<?php

namespace App\Models;

use App\Concerns\Pesquisavel;
use App\Enums\TermType;
use App\Enums\VereditoDoPortal;
use App\Services\Clock;
use App\Services\EnrichService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Process extends Model
{
    use Pesquisavel;

    protected $table = 'processos';

    protected $primaryKey = 'numero';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'numero',
        'encontrado',
        'data_entrada',
        'autor',
        'complemento',
        'municipio',
        'situacao',
        'situacao_data',
        'meio',
        'andamento',
        'consultado_em',
        'prazo_vencimento',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'encontrado' => 'boolean',
        'andamento' => 'array',
    ];

    public $timestamps = false;

    /**
     * Period links between this process and the terms that cited it.
     *
     * @return HasMany<TermProcess, $this>
     */
    public function termoProcessos(): HasMany
    {
        return $this->hasMany(TermProcess::class, 'processo_numero', 'numero');
    }

    /**
     * Deadlines published for this process, one per edition.
     *
     * @return HasMany<Prazo, $this>
     */
    public function prazos(): HasMany
    {
        return $this->hasMany(Prazo::class, 'processo_numero', 'numero');
    }

    /**
     * Deadline of the latest act (the one that sets prazo_vencimento).
     *
     * @return HasOne<Prazo, $this>
     */
    public function prazoAtual(): HasOne
    {
        return $this->hasOne(Prazo::class, 'processo_numero', 'numero')->latestOfMany('data_vencimento');
    }

    /**
     * Filter by the TCM lookup result: "localizado", "nao-localizado" or empty for all.
     *
     * @param  Builder<Process>  $query
     */
    #[Scope]
    protected function situacaoDaConsulta(Builder $query, string $situacao): void
    {
        if ($situacao === '') {
            return;
        }

        $query->where('encontrado', $situacao === 'localizado');
    }

    /**
     * Only processes cited by the given term; an empty id matches all.
     *
     * @param  Builder<Process>  $query
     */
    #[Scope]
    protected function citadosPeloTermo(Builder $query, string $termoId): void
    {
        if ($termoId === '') {
            return;
        }

        $query->whereHas('termoProcessos', fn (Builder $vinculo) => $vinculo->where('termo_id', (int) $termoId));
    }

    /**
     * Open deadlines first (closest due date first), then expired ones, then processes
     * without a deadline. The group depends on today, so it is computed in a derived
     * table: cursor pagination only orders by real columns.
     *
     * @param  Builder<Process>  $query
     */
    #[Scope]
    protected function porPrazoMaisCurto(Builder $query): void
    {
        $comGrupo = static::query()
            ->select('processos.*')
            ->selectRaw('CASE WHEN prazo_vencimento IS NULL THEN 2 WHEN prazo_vencimento < ? THEN 1 ELSE 0 END AS grupo_prazo', [Clock::today()])
            ->selectRaw("COALESCE(prazo_vencimento, '') AS ordem_prazo");

        $query->fromSub($comGrupo, $this->getTable())
            ->orderBy('grupo_prazo')
            ->orderBy('ordem_prazo')
            ->orderBy('numero');
    }

    /**
     * Process listing ordered by the closest deadline, paginated by cursor.
     *
     * @param  array{busca: string, termo: string, situacao: string}  $filtros
     * @return CursorPaginator<int, static>
     */
    public static function paginarListagem(array $filtros, int $porPagina = 15): CursorPaginator
    {
        return static::query()
            ->with('prazoAtual')
            ->pesquisar($filtros['busca'], ['numero', 'autor', 'situacao'])
            ->citadosPeloTermo($filtros['termo'])
            ->situacaoDaConsulta($filtros['situacao'])
            ->porPrazoMaisCurto()
            ->cursorPaginate($porPagina)
            ->withQueryString();
    }

    /**
     * What the portal data says about this process belonging to the term (any spelling).
     * Only a municipality term can be contradicted, by a different reported municipality.
     * Without a municipality, the portal also confirms when its author/manager is written
     * in the same Diário Oficial record that mentions the term.
     *
     * @param  list<string>  $grafias
     */
    public function vereditoPara(TermType $tipo, array $grafias, string $trechoDoDiario = ''): VereditoDoPortal
    {
        $padroes = array_filter(array_map(EnrichService::termPattern(...), $grafias));
        $menciona = fn (?string $texto): bool => $texto !== null
            && array_filter($padroes, fn (string $padrao): bool => preg_match($padrao, $texto) === 1) !== [];

        if ($tipo === TermType::Municipio && filled($this->municipio)) {
            return $menciona($this->municipio) ? VereditoDoPortal::Confirma : VereditoDoPortal::Contradiz;
        }

        if ($menciona($this->autor) || $menciona($this->complemento) || $this->autorEstaNoTrecho($trechoDoDiario)) {
            return VereditoDoPortal::Confirma;
        }

        return VereditoDoPortal::Neutro;
    }

    /**
     * Whether any author listed by the portal ("NAME, OTHER NAME") is written in the text;
     * single-word authors are ignored, they are too ambiguous.
     */
    private function autorEstaNoTrecho(string $trecho): bool
    {
        if ($trecho === '' || blank($this->autor)) {
            return false;
        }

        foreach (explode(',', (string) $this->autor) as $nome) {
            $padrao = str_contains(trim($nome), ' ') ? EnrichService::termPattern($nome) : null;

            if ($padrao !== null && preg_match($padrao, $trecho) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Unsaved process built from a TcmProcessClient lookup result.
     *
     * @param  array<string, mixed>  $consulta
     */
    public static function daConsulta(array $consulta): self
    {
        return new self([
            'numero' => $consulta['numero'],
            'encontrado' => (bool) $consulta['encontrado'],
            'data_entrada' => $consulta['data_entrada'],
            'autor' => $consulta['autor'],
            'complemento' => $consulta['complemento'],
            'municipio' => $consulta['municipio'],
            'situacao' => $consulta['situacao'],
            'situacao_data' => $consulta['situacao_data'],
            'meio' => $consulta['meio'],
            'andamento' => $consulta['andamento'],
            'consultado_em' => $consulta['consultado_em'],
        ]);
    }

    /**
     * Upserts looked-up processes in one statement (refreshes the portal data on conflict).
     *
     * @param  iterable<Process>  $processos
     */
    public static function registrarConsultas(iterable $processos): void
    {
        $linhas = [];

        foreach ($processos as $processo) {
            $linhas[] = [
                'numero' => $processo->numero,
                'encontrado' => $processo->encontrado ? 1 : 0,
                'data_entrada' => $processo->data_entrada,
                'autor' => $processo->autor,
                'complemento' => $processo->complemento,
                'municipio' => $processo->municipio,
                'situacao' => $processo->situacao,
                'situacao_data' => $processo->situacao_data,
                'meio' => $processo->meio,
                'andamento' => json_encode($processo->andamento, JSON_UNESCAPED_UNICODE),
                'consultado_em' => $processo->consultado_em,
            ];
        }

        if ($linhas === []) {
            return;
        }

        $colunas = array_values(array_diff(array_keys($linhas[0]), ['numero']));
        static::query()->upsert($linhas, ['numero'], $colunas);
    }

    /**
     * Process for the detail view: term links and deadlines (latest first) eager loaded.
     */
    public static function comDetalhes(string $numero): ?static
    {
        return static::query()
            ->with([
                'termoProcessos.termo',
                'prazos' => fn (HasMany $prazos) => $prazos->orderByDesc('data_vencimento'),
            ])
            ->find($numero);
    }

    /**
     * Copies the latest due date of each process onto its row (single UPDATE with a subquery).
     *
     * @param  list<string>  $numeros
     */
    public static function atualizarPrazoVencimento(array $numeros): void
    {
        if ($numeros === []) {
            return;
        }

        static::query()
            ->whereIn('numero', $numeros)
            ->update([
                'prazo_vencimento' => Prazo::query()
                    ->selectRaw('MAX(data_vencimento)')
                    ->whereColumn('prazos.processo_numero', 'processos.numero'),
            ]);
    }

    /**
     * Progress entries reported by the TCM, as a plain list.
     *
     * @return list<array<string, mixed>>
     */
    public function andamentoItens(): array
    {
        return array_values((array) ($this->andamento ?? []));
    }

    /**
     * Term links without repeating the same term and period.
     *
     * @return Collection<int, TermProcess>
     */
    public function vinculosPorPeriodo(): Collection
    {
        return collect($this->termoProcessos)
            ->unique(fn (TermProcess $vinculo): string => $vinculo->termo_id.'|'.$vinculo->periodo_inicio)
            ->values();
    }
}
