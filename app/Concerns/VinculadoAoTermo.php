<?php

namespace App\Concerns;

use App\Models\Term;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Models that belong to a monitored term through the `termo_id` column.
 */
trait VinculadoAoTermo
{
    /**
     * Termo (município/empresa) dono do registro.
     *
     * @return BelongsTo<Term, $this>
     */
    public function termo(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'termo_id');
    }

    /**
     * Only rows of the given term; an empty id matches all.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function doTermo(Builder $query, string $termoId): void
    {
        if ($termoId === '') {
            return;
        }

        $query->where($query->qualifyColumn('termo_id'), (int) $termoId);
    }
}
