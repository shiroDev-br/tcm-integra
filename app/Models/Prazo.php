<?php

namespace App\Models;

use App\Enums\ContagemDePrazo;
use App\Services\Clock;
use App\Services\EnrichService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Response deadline published in the Diário Oficial for a process.
 *
 * @property int $id
 * @property string $processo_numero
 * @property int $edicao_id
 * @property int $pagina
 * @property int $dias
 * @property ContagemDePrazo $contagem
 * @property string $data_inicio
 * @property string $data_vencimento
 * @property bool $estimado start is not the publication (e.g. electronic notice) or holidays were not counted
 * @property string $trecho
 * @property string $created_at
 */
#[Fillable(['processo_numero', 'edicao_id', 'pagina', 'dias', 'contagem', 'data_inicio', 'data_vencimento', 'estimado', 'trecho', 'created_at'])]
class Prazo extends Model
{
    protected $table = 'prazos';

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dias' => 'integer',
            'pagina' => 'integer',
            'contagem' => ContagemDePrazo::class,
            'estimado' => 'boolean',
        ];
    }

    /**
     * Edition that published the act.
     *
     * @return BelongsTo<Edition, $this>
     */
    public function edicao(): BelongsTo
    {
        return $this->belongsTo(Edition::class, 'edicao_id');
    }

    /**
     * Persists the deadlines read from one page (idempotent per process and edition).
     *
     * @param  array<string, array{dias: int, contagem: ContagemDePrazo, a_partir_da_publicacao: bool, trecho: string}>  $extraidos  by process number
     */
    public static function registrar(array $extraidos, int $edicaoId, int $pagina, string $dataEdicao): void
    {
        if ($extraidos === [] || $dataEdicao === '') {
            return;
        }

        $inicio = CarbonImmutable::parse($dataEdicao);
        $agora = Clock::nowUtcIso();
        $linhas = [];

        foreach ($extraidos as $numero => $prazo) {
            $linhas[] = [
                'processo_numero' => (string) $numero,
                'edicao_id' => $edicaoId,
                'pagina' => $pagina,
                'dias' => $prazo['dias'],
                'contagem' => $prazo['contagem']->value,
                'data_inicio' => $inicio->format('Y-m-d'),
                'data_vencimento' => $prazo['contagem']->vencimento($inicio, $prazo['dias'])->format('Y-m-d'),
                'estimado' => ! $prazo['a_partir_da_publicacao'] || $prazo['contagem'] === ContagemDePrazo::Uteis,
                'trecho' => $prazo['trecho'],
                'created_at' => $agora,
            ];
        }

        DB::table('prazos')->insertOrIgnore($linhas);
    }

    /**
     * Days left until the due date (negative once it has passed).
     */
    public function diasRestantes(): int
    {
        return (int) CarbonImmutable::parse(Clock::today())->diffInDays(CarbonImmutable::parse($this->data_vencimento), false);
    }

    public function isVencido(): bool
    {
        return $this->diasRestantes() < 0;
    }

    /**
     * Remaining time shown in the interface.
     */
    public function situacao(): string
    {
        $dias = $this->diasRestantes();

        if ($dias < 0) {
            return 'Vencido há '.trans_choice('{1} :count dia|[2,*] :count dias', -$dias);
        }

        if ($dias === 0) {
            return 'Vence hoje';
        }

        return 'Vence em '.trans_choice('{1} :count dia|[2,*] :count dias', $dias);
    }

    /**
     * Color of the flux:badge: the closer the due date, the warmer.
     */
    public function badgeColor(): string
    {
        $dias = $this->diasRestantes();

        if ($dias < 0) {
            return 'zinc';
        }

        if ($dias <= 3) {
            return 'red';
        }

        if ($dias <= 7) {
            return 'amber';
        }

        return 'green';
    }

    /**
     * Link to the page of the Diário Oficial that published the act.
     */
    public function pdfUrl(): string
    {
        return EnrichService::pdfUrl($this->edicao_id, $this->pagina);
    }
}
