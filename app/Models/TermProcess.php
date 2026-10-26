<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * termo ↔ processo link per period — composite primary key is not supported
 * by Eloquent; always persist via query builder / insertOrIgnore.
 */
class TermProcess extends Model
{
    protected $table = 'termo_processos';

    /** @var list<string> */
    protected $fillable = [
        'termo_id',
        'processo_numero',
        'periodo_inicio',
        'periodo_fim',
        'created_at',
    ];

    public $timestamps = false;

    /**
     * Term that cited the process.
     *
     * @return BelongsTo<Term, $this>
     */
    public function termo(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'termo_id');
    }

    /**
     * Process referenced by the link.
     *
     * @return BelongsTo<Process, $this>
     */
    public function processo(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'processo_numero', 'numero');
    }
}
