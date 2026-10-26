<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Text search (LIKE) over a set of model columns.
 */
trait Pesquisavel
{
    /**
     * Matches rows where any of the columns contains the search text; an empty search matches all.
     *
     * @param  Builder<static>  $query
     * @param  list<string>  $colunas
     */
    #[Scope]
    protected function pesquisar(Builder $query, string $busca, array $colunas): void
    {
        if ($busca === '') {
            return;
        }

        $padrao = '%'.$busca.'%';

        $query->where(function (Builder $grupo) use ($colunas, $padrao): void {
            foreach ($colunas as $coluna) {
                $grupo->orWhere($grupo->qualifyColumn($coluna), 'like', $padrao);
            }
        });
    }
}
