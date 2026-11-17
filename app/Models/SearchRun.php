<?php

namespace App\Models;

use App\Enums\SearchStatus;
use App\Services\Clock;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property list<array{termo_id: int, termo: string, ocorrencias: int, processos: int}>|null $termos_encontrados
 */
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
        'termos_encontrados',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => SearchStatus::class,
        'termos' => 'integer',
        'requests' => 'integer',
        'duracao_ms' => 'integer',
        'termos_encontrados' => 'array',
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

    /**
     * Latest collection that covered today (manual or automatic): the "search of the day".
     */
    public static function daColetaDeHoje(): ?self
    {
        return static::query()->where('periodo_fim', Clock::today())->recentes()->first();
    }

    /**
     * Ids of the terms this collection found that the user has not opened since it ran.
     *
     * @return list<int>
     */
    public function termosNaoVistosPor(User $user): array
    {
        $encontrados = array_column($this->listaTermosEncontrados(), 'termo_id');

        if ($encontrados === []) {
            return [];
        }

        $vistos = $user->termosVistos()
            ->whereIn('termos.id', $encontrados)
            ->wherePivot('visto_em', '>=', $this->getRawOriginal('created_at'))
            ->pluck('termos.id')
            ->all();

        return array_values(array_diff($encontrados, $vistos));
    }

    /**
     * Terms found in this collection, as a plain list.
     *
     * @return list<array{termo_id: int, termo: string, ocorrencias: int, processos: int}>
     */
    public function listaTermosEncontrados(): array
    {
        return $this->termos_encontrados ?? [];
    }

    /**
     * One-line notice of which terms this collection found.
     */
    public function avisoTermosEncontrados(): string
    {
        return self::avisoDosEncontrados($this->listaTermosEncontrados());
    }

    /**
     * One-line notice of which terms were found (toast and console output).
     *
     * @param  list<array{termo_id: int, termo: string, ocorrencias: int, processos: int}>  $encontrados
     */
    public static function avisoDosEncontrados(array $encontrados): string
    {
        if ($encontrados === []) {
            return 'Nenhum cadastro encontrado no Diário Oficial.';
        }

        $nomes = array_map(
            fn (array $termo): string => "{$termo['termo']} ({$termo['ocorrencias']})",
            $encontrados,
        );

        return trans_choice('{1} Cadastro encontrado|[2,*] Cadastros encontrados', count($nomes)).': '.implode(', ', $nomes).'.';
    }
}
