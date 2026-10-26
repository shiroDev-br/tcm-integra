<?php

namespace App\Models;

use App\Concerns\Pesquisavel;
use App\Enums\TermType;
use Database\Factories\TermFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model
{
    /** @use HasFactory<TermFactory> */
    use HasFactory;

    use Pesquisavel;

    protected $table = 'termos';

    /** @var list<string> */
    protected $fillable = [
        'termo',
        'tipo',
        'ativo',
        'variacoes',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tipo' => TermType::class,
        'ativo' => 'boolean',
        'variacoes' => 'array',
    ];

    public $timestamps = false;

    /**
     * Multas manuais vinculadas ao termo.
     *
     * @return HasMany<Multa, $this>
     */
    public function multas(): HasMany
    {
        return $this->hasMany(Multa::class, 'termo_id');
    }

    /**
     * Period links between this term and the processes that cited it.
     *
     * @return HasMany<TermProcess, $this>
     */
    public function termoProcessos(): HasMany
    {
        return $this->hasMany(TermProcess::class, 'termo_id');
    }

    /**
     * Occurrences found for this term.
     *
     * @return HasMany<Occurrence, $this>
     */
    public function ocorrencias(): HasMany
    {
        return $this->hasMany(Occurrence::class, 'termo_id');
    }

    /**
     * Only terms included in the collections.
     *
     * @param  Builder<Term>  $query
     */
    #[Scope]
    protected function ativo(Builder $query): void
    {
        $query->where('ativo', true);
    }

    /**
     * Only municipality terms.
     *
     * @param  Builder<Term>  $query
     */
    #[Scope]
    protected function municipio(Builder $query): void
    {
        $query->where('tipo', TermType::Municipio);
    }

    /**
     * Only terms of the given type; an empty type matches all.
     *
     * @param  Builder<Term>  $query
     */
    #[Scope]
    protected function doTipo(Builder $query, string $tipo): void
    {
        if ($tipo === '') {
            return;
        }

        $query->where('tipo', $tipo);
    }

    /**
     * Term listing of the admin screen.
     *
     * @param  array{busca: string, tipo: string, apenas_ativos: bool}  $filtros
     * @return Collection<int, static>
     */
    public static function listagem(array $filtros): Collection
    {
        return static::query()
            ->pesquisar($filtros['busca'], ['termo'])
            ->doTipo($filtros['tipo'])
            ->when($filtros['apenas_ativos'], fn (Builder $query) => $query->ativo())
            ->orderBy('termo')
            ->get();
    }

    /**
     * Active terms with how many occurrences each one has (term cards).
     *
     * @return Collection<int, static>
     */
    public static function ativosComTotalDeOcorrencias(): Collection
    {
        return static::query()
            ->ativo()
            ->withCount('ocorrencias')
            ->orderBy('termo')
            ->get();
    }

    /**
     * Active terms with how many distinct processes each one has (term cards).
     *
     * @return Collection<int, static>
     */
    public static function ativosComTotalDeProcessos(): Collection
    {
        return static::query()
            ->ativo()
            ->select('termos.*')
            ->selectSub(
                TermProcess::selectRaw('count(distinct processo_numero)')->whereColumn('termo_id', 'termos.id'),
                'processos_count'
            )
            ->orderBy('termo')
            ->get();
    }

    /**
     * Active municipalities ordered by name (fine form and filter).
     *
     * @return Collection<int, static>
     */
    public static function municipiosAtivos(): Collection
    {
        return static::query()->municipio()->ativo()->orderBy('termo')->get();
    }

    /**
     * Search variations as a plain list.
     *
     * @return list<string>
     */
    public function listaVariacoes(): array
    {
        return array_values((array) $this->variacoes);
    }
}
