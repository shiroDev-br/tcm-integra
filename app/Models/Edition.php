<?php

namespace App\Models;

use App\Services\FormatadorDeData;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Edition extends Model
{
    protected $table = 'edicoes';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'data',
        'numero',
        'paginas',
        'suplemento',
        'tipo_edicao_nome',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'id' => 'integer',
        'numero' => 'integer',
        'paginas' => 'integer',
        'suplemento' => 'boolean',
    ];

    public $incrementing = false;

    public $timestamps = false;

    /**
     * Occurrences of the searched terms found in this edition.
     *
     * @return HasMany<Occurrence, $this>
     */
    public function ocorrencias(): HasMany
    {
        return $this->hasMany(Occurrence::class, 'edicao_id');
    }

    /**
     * Only editions where an active term was actually found (the source lists every
     * edition of the period, most of them without any searched term).
     *
     * @param  Builder<Edition>  $query
     */
    #[Scope]
    protected function comOcorrenciaDeTermoAtivo(Builder $query): void
    {
        $query->whereHas('ocorrencias.termo', fn (Builder $termo) => $termo->ativo());
    }

    /**
     * Search by date (aaaa-mm-dd or dd/mm/aaaa), edition type or exact number.
     *
     * @param  Builder<Edition>  $query
     */
    #[Scope]
    protected function comBusca(Builder $query, string $busca): void
    {
        if ($busca === '') {
            return;
        }

        $data = FormatadorDeData::paraIso($busca);

        $query->where(fn (Builder $grupo) => $grupo
            ->where('data', 'like', '%'.$data.'%')
            ->orWhere('tipo_edicao_nome', 'like', '%'.$busca.'%')
            ->orWhere('numero', $busca));
    }

    /**
     * Only editions published on or after the date.
     *
     * @param  Builder<Edition>  $query
     */
    #[Scope]
    protected function publicadasAPartirDe(Builder $query, string $data): void
    {
        if ($data === '') {
            return;
        }

        $query->where('data', '>=', $data);
    }

    /**
     * Only editions published on or before the date.
     *
     * @param  Builder<Edition>  $query
     */
    #[Scope]
    protected function publicadasAte(Builder $query, string $data): void
    {
        if ($data === '') {
            return;
        }

        $query->where('data', '<=', $data);
    }

    /**
     * Supplement filter: "sim", "nao" or empty for all.
     *
     * @param  Builder<Edition>  $query
     */
    #[Scope]
    protected function comSuplemento(Builder $query, string $suplemento): void
    {
        if ($suplemento === '') {
            return;
        }

        $query->where('suplemento', $suplemento === 'sim');
    }

    /**
     * Edition listing, newest first, paginated by cursor.
     *
     * @param  array{busca: string, inicio: string, fim: string, suplemento: string}  $filtros
     * @return CursorPaginator<int, static>
     */
    public static function paginarListagem(array $filtros, int $porPagina = 15): CursorPaginator
    {
        return static::query()
            ->with(['ocorrencias' => fn (HasMany $ocorrencias) => $ocorrencias->select('id', 'edicao_id', 'termo_id'), 'ocorrencias.termo:id,termo'])
            ->comOcorrenciaDeTermoAtivo()
            ->comBusca($filtros['busca'])
            ->publicadasAPartirDe($filtros['inicio'])
            ->publicadasAte($filtros['fim'])
            ->comSuplemento($filtros['suplemento'])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->cursorPaginate($porPagina)
            ->withQueryString();
    }

    /**
     * Names of the terms found in this edition (needs `ocorrencias.termo` loaded).
     *
     * @return array<int, string>
     */
    public function termosEncontrados(): array
    {
        return $this->ocorrencias
            ->map(fn (Occurrence $ocorrencia): string => (string) $ocorrencia->termo?->termo)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
