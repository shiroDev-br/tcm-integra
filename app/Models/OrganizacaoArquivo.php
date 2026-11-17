<?php

namespace App\Models;

use App\Enums\PastaDaPrestacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PDF gerado dentro do zip organizado.
 *
 * @property int $id
 * @property int $organizacao_id
 * @property PastaDaPrestacao $pasta
 * @property string $nome
 * @property int $paginas
 * @property int $tamanho_bytes
 * @property list<string>|null $avisos
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organizacao $organizacao
 */
#[Fillable(['organizacao_id', 'pasta', 'nome', 'paginas', 'tamanho_bytes', 'avisos'])]
class OrganizacaoArquivo extends Model
{
    protected $table = 'organizacao_arquivos';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pasta' => PastaDaPrestacao::class,
            'paginas' => 'integer',
            'tamanho_bytes' => 'integer',
            'avisos' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Organizacao, $this>
     */
    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }

    /**
     * Caminho do arquivo dentro da pasta da entidade.
     */
    public function caminho(): string
    {
        return ltrim($this->pasta->caminho().'/'.$this->nome, '/');
    }

    /**
     * Tamanho em KB, como a planilha do TCM mede.
     */
    public function tamanhoEmKb(): int
    {
        return (int) ceil($this->tamanho_bytes / 1024);
    }
}
