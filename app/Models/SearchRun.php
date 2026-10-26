<?php

namespace App\Models;

use App\Enums\SearchStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SearchRun extends Model
{
    protected $table = 'buscas';

    /** @var list<string> */
    protected $fillable = [
        'periodo_inicio',
        'periodo_fim',
        'termos',
        'requests',
        'duracao_ms',
        'status',
        'erro',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => SearchStatus::class,
        'termos' => 'integer',
        'requests' => 'integer',
        'duracao_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    /**
     * Most recent collections first.
     *
     * @param  Builder<SearchRun>  $query
     */
    #[Scope]
    protected function recentes(Builder $query): void
    {
        $query->orderByDesc('created_at');
    }
}
