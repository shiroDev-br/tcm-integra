<?php

namespace App\Models;

use App\Enums\OrganizacaoStatus;
use App\Jobs\OrganizarPrestacao;
use Database\Factories\OrganizacaoFactory;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pedido de organização de uma prestação de contas: os arquivos enviados
 * viram um zip com as pastas, nomes e ordem de páginas exigidos pelo TCM.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $entidade
 * @property OrganizacaoStatus $status
 * @property string|null $arquivo_saida
 * @property list<string>|null $avisos
 * @property string|null $erro
 * @property Carbon|null $iniciado_em
 * @property Carbon|null $concluido_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, OrganizacaoArquivo> $arquivos
 */
#[Fillable(['user_id', 'entidade', 'status', 'arquivo_saida', 'avisos', 'erro', 'iniciado_em', 'concluido_em'])]
class Organizacao extends Model
{
    /** @use HasFactory<OrganizacaoFactory> */
    use HasFactory;

    protected $table = 'organizacoes';

    private const DISCO = 'local';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganizacaoStatus::class,
            'avisos' => 'array',
            'iniciado_em' => 'datetime',
            'concluido_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrganizacaoArquivo, $this>
     */
    public function arquivos(): HasMany
    {
        return $this->hasMany(OrganizacaoArquivo::class);
    }

    /**
     * Only requests still waiting for or running in the queue.
     *
     * @param  Builder<Organizacao>  $query
     */
    #[Scope]
    protected function emAndamento(Builder $query): void
    {
        $query->whereIn('status', [OrganizacaoStatus::Pendente, OrganizacaoStatus::Processando]);
    }

    /**
     * Registra o pedido, guarda os arquivos enviados e coloca a organização na fila.
     *
     * @param  list<UploadedFile>  $uploads
     */
    public static function iniciar(?int $userId, ?string $entidade, array $uploads): self
    {
        $organizacao = static::query()->create([
            'user_id' => $userId,
            'entidade' => blank($entidade) ? null : trim($entidade),
            'status' => OrganizacaoStatus::Pendente,
        ]);

        foreach ($uploads as $upload) {
            $upload->storeAs($organizacao->diretorioDeEntrada(), basename($upload->getClientOriginalName()), self::DISCO);
        }

        OrganizarPrestacao::dispatch($organizacao);

        return $organizacao;
    }

    /**
     * Listagem mais recente primeiro, paginada por cursor.
     *
     * @return CursorPaginator<int, static>
     */
    public static function paginarListagem(int $porPagina = 10): CursorPaginator
    {
        return static::query()
            ->with('user:id,name')
            ->withCount('arquivos')
            ->orderByDesc('id')
            ->cursorPaginate($porPagina);
    }

    /**
     * Detalhe com os arquivos gerados agrupados por pasta.
     */
    public static function comArquivos(int $id): self
    {
        return static::query()
            ->with(['arquivos' => fn (HasMany $arquivos) => $arquivos->orderBy('pasta')->orderBy('nome')])
            ->findOrFail($id);
    }

    /**
     * Whether any request is still in the queue, so the listing keeps polling.
     */
    public static function existeEmAndamento(): bool
    {
        return static::query()->emAndamento()->exists();
    }

    public function marcarProcessando(): void
    {
        $this->update([
            'status' => OrganizacaoStatus::Processando,
            'iniciado_em' => now(),
            'erro' => null,
        ]);
    }

    /**
     * Grava o resultado e os arquivos gerados de uma vez.
     *
     * @param  list<array{pasta: string, nome: string, paginas: int, tamanho_bytes: int, avisos: list<string>}>  $arquivos
     * @param  list<string>  $avisos
     */
    public function concluir(string $entidade, string $arquivoSaida, array $arquivos, array $avisos): void
    {
        DB::transaction(function () use ($entidade, $arquivoSaida, $arquivos, $avisos) {
            $this->arquivos()->delete();
            $this->arquivos()->createMany($arquivos);

            $this->update([
                'entidade' => $entidade,
                'status' => OrganizacaoStatus::Concluida,
                'arquivo_saida' => $arquivoSaida,
                'avisos' => $avisos,
                'concluido_em' => now(),
            ]);
        });
    }

    public function falhar(string $erro): void
    {
        $this->update([
            'status' => OrganizacaoStatus::Falhou,
            'erro' => $erro,
            'concluido_em' => now(),
        ]);
    }

    /**
     * Remove o pedido e tudo o que foi guardado em disco para ele.
     */
    public function excluirComArquivos(): void
    {
        Storage::disk(self::DISCO)->deleteDirectory($this->diretorio());

        $this->delete();
    }

    public function diretorio(): string
    {
        return 'organizacoes/'.$this->id;
    }

    public function diretorioDeEntrada(): string
    {
        return $this->diretorio().'/entrada';
    }

    public function diretorioDeTrabalho(): string
    {
        return $this->diretorio().'/trabalho';
    }

    /**
     * Caminho absoluto de um caminho relativo ao disco dos pedidos.
     */
    public function caminhoAbsoluto(string $caminho): string
    {
        return Storage::disk(self::DISCO)->path($caminho);
    }

    public function possuiArquivoDeSaida(): bool
    {
        if ($this->arquivo_saida === null) {
            return false;
        }

        return Storage::disk(self::DISCO)->exists($this->arquivo_saida);
    }

    /**
     * Nome do zip entregue no download.
     */
    public function nomeParaDownload(): string
    {
        return ($this->entidade ?? 'prestacao-de-contas').'.zip';
    }

    public function disco(): string
    {
        return self::DISCO;
    }
}
