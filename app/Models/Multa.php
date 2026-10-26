<?php

namespace App\Models;

use App\Concerns\VinculadoAoTermo;
use App\Enums\MultaStatus;
use Database\Factories\MultaFactory;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $termo_id
 * @property string $gestor
 * @property string $processo_numero
 * @property Carbon $data_multa
 * @property Carbon $data_vencimento
 * @property string $valor
 * @property MultaStatus $situacao
 * @property string|null $comprovante
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Term|null $termo
 */
#[Fillable(['termo_id', 'gestor', 'processo_numero', 'data_multa', 'data_vencimento', 'valor', 'situacao', 'comprovante'])]
class Multa extends Model
{
    /** @use HasFactory<MultaFactory> */
    use HasFactory;

    use VinculadoAoTermo;

    protected $table = 'multas';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_multa' => 'date',
            'data_vencimento' => 'date',
            'valor' => 'decimal:2',
            'situacao' => MultaStatus::class,
        ];
    }

    /**
     * Only fines still waiting for payment.
     *
     * @param  Builder<Multa>  $query
     */
    #[Scope]
    protected function pendente(Builder $query): void
    {
        $query->where('situacao', MultaStatus::Pendente);
    }

    /**
     * Only fines with the given status; an empty status matches all.
     *
     * @param  Builder<Multa>  $query
     */
    #[Scope]
    protected function daSituacao(Builder $query, string $situacao): void
    {
        if ($situacao === '') {
            return;
        }

        $query->where('situacao', $situacao);
    }

    /**
     * Search by process number, manager or municipality name.
     *
     * @param  Builder<Multa>  $query
     */
    #[Scope]
    protected function comBusca(Builder $query, string $busca): void
    {
        if ($busca === '') {
            return;
        }

        $padrao = '%'.$busca.'%';

        $query->where(fn (Builder $grupo) => $grupo
            ->where('processo_numero', 'like', $padrao)
            ->orWhere('gestor', 'like', $padrao)
            ->orWhereHas('termo', fn (Builder $termo) => $termo->pesquisar($busca, ['termo'])));
    }

    /**
     * Fine listing, newest first, paginated by cursor.
     *
     * @param  array{busca: string, termo: string, situacao: string}  $filtros
     * @return CursorPaginator<int, static>
     */
    public static function paginarListagem(array $filtros, int $porPagina = 10): CursorPaginator
    {
        return static::query()
            ->with('termo')
            ->comBusca($filtros['busca'])
            ->doTermo($filtros['termo'])
            ->daSituacao($filtros['situacao'])
            ->orderByDesc('data_multa')
            ->orderByDesc('id')
            ->cursorPaginate($porPagina)
            ->withQueryString();
    }

    /**
     * Whether the fine is still pending after its due date.
     */
    public function isVencida(): bool
    {
        if ($this->situacao !== MultaStatus::Pendente) {
            return false;
        }

        return $this->data_vencimento->isPast();
    }
}
