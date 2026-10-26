<?php

namespace App\Models;

use App\Concerns\Pesquisavel;
use App\Concerns\VinculadoAoTermo;
use App\Enums\OccurrenceType;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string|null $edicao_data date of the edition, selected by paginarListagem()
 */
class Occurrence extends Model
{
    use Pesquisavel;
    use VinculadoAoTermo;

    protected $table = 'ocorrencias';

    /** @var list<string> */
    protected $fillable = [
        'edicao_id',
        'pagina',
        'termo_id',
        'trecho',
        'contexto',
        'atos',
        'processos',
        'tipo',
        'ocorrencias_na_pagina',
        'pdf_url',
        'periodo_inicio',
        'periodo_fim',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tipo' => OccurrenceType::class,
        'atos' => 'array',
        'processos' => 'array',
        'ocorrencias_na_pagina' => 'integer',
    ];

    public $timestamps = false;

    /**
     * Edition where the occurrence appeared.
     *
     * @return BelongsTo<Edition, $this>
     */
    public function edicao(): BelongsTo
    {
        return $this->belongsTo(Edition::class, 'edicao_id');
    }

    /**
     * Only occurrences of the given type; an empty type matches all.
     *
     * @param  Builder<Occurrence>  $query
     */
    #[Scope]
    protected function doTipo(Builder $query, string $tipo): void
    {
        if ($tipo === '') {
            return;
        }

        $query->where('ocorrencias.tipo', $tipo);
    }

    /**
     * Only occurrences collected in periods starting on or after the date.
     *
     * @param  Builder<Occurrence>  $query
     */
    #[Scope]
    protected function coletadasAPartirDe(Builder $query, string $data): void
    {
        if ($data === '') {
            return;
        }

        $query->where('ocorrencias.periodo_inicio', '>=', $data);
    }

    /**
     * Only occurrences collected in periods ending on or before the date.
     *
     * @param  Builder<Occurrence>  $query
     */
    #[Scope]
    protected function coletadasAte(Builder $query, string $data): void
    {
        if ($data === '') {
            return;
        }

        $query->where('ocorrencias.periodo_fim', '<=', $data);
    }

    /**
     * Occurrence listing, newest edition first, paginated by cursor.
     *
     * @param  array{termo: string, tipo: string, inicio: string, fim: string, texto: string}  $filtros
     * @return CursorPaginator<int, static>
     */
    public static function paginarListagem(array $filtros, int $porPagina = 20): CursorPaginator
    {
        return static::query()
            ->select('ocorrencias.*', 'edicoes.data as edicao_data')
            ->join('edicoes', 'edicoes.id', '=', 'ocorrencias.edicao_id')
            ->doTermo($filtros['termo'])
            ->doTipo($filtros['tipo'])
            ->coletadasAPartirDe($filtros['inicio'])
            ->coletadasAte($filtros['fim'])
            ->pesquisar($filtros['texto'], ['trecho', 'contexto'])
            ->orderByDesc('edicao_data')
            ->orderBy('ocorrencias.pagina')
            ->orderBy('ocorrencias.id')
            ->cursorPaginate($porPagina)
            ->withQueryString();
    }
}
