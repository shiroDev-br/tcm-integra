<?php

use App\Enums\OrganizacaoStatus;
use App\Enums\PastaDaPrestacao;
use App\Jobs\OrganizarPrestacao;
use App\Livewire\Organizacoes\Index;
use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Small but valid zip, so the upload passes the mime validation.
 */
function zipDeTeste(string $nome): UploadedFile
{
    $caminho = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive;
    $zip->open($caminho, ZipArchive::OVERWRITE);
    $zip->addFromString('CM de Teste/documento.pdf', '%PDF-1.4');
    $zip->close();

    return UploadedFile::fake()->createWithContent($nome, (string) file_get_contents($caminho));
}

beforeEach(function () {
    Storage::fake('local');
});

test('visitantes sao redirecionados para o login', function () {
    $this->get(route('organizacoes.index'))->assertRedirect(route('login'));
});

test('usuario autenticado abre o organizador', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('organizacoes.index'))
        ->assertOk()
        ->assertSee('Organizar arquivos');
});

test('envio guarda os arquivos e coloca a organizacao na fila', function () {
    Queue::fake();
    $usuario = User::factory()->create();

    Livewire::actingAs($usuario)
        ->test(Index::class)
        ->call('novo')
        ->set('entidade', 'CM de Teste')
        ->set('arquivos', [zipDeTeste('CM de Teste.zip')])
        ->call('enviar')
        ->assertHasNoErrors()
        ->assertSet('formAberto', false);

    $organizacao = Organizacao::query()->sole();

    expect($organizacao->user_id)->toBe($usuario->id)
        ->and($organizacao->entidade)->toBe('CM de Teste')
        ->and($organizacao->status)->toBe(OrganizacaoStatus::Pendente);

    Storage::disk('local')->assertExists($organizacao->diretorioDeEntrada().'/CM de Teste.zip');
    Queue::assertPushedOn('organizador', OrganizarPrestacao::class, fn (OrganizarPrestacao $job) => $job->organizacao->is($organizacao));
});

test('envio exige ao menos um arquivo zip ou pdf', function () {
    Queue::fake();

    Livewire::test(Index::class)
        ->call('novo')
        ->call('enviar')
        ->assertHasErrors(['arquivos' => 'required'])
        ->set('arquivos', [UploadedFile::fake()->createWithContent('planilha.txt', 'texto')])
        ->call('enviar')
        ->assertHasErrors(['arquivos.0' => 'mimes']);

    Queue::assertNothingPushed();
});

test('detalhes listam os arquivos gerados e os avisos', function () {
    $organizacao = Organizacao::factory()->concluida()->create(['avisos' => ['"x.pdf" ignorado: é idêntico a "y.pdf".']]);
    $organizacao->arquivos()->create([
        'pasta' => PastaDaPrestacao::ProcessoPagamento,
        'nome' => 'PP 202501220006 COELBA.pdf',
        'paginas' => 6,
        'tamanho_bytes' => 1677312,
        'avisos' => ['Sem Comprovante de Pagamento.'],
    ]);

    Livewire::test(Index::class)
        ->call('verDetalhes', $organizacao->id)
        ->assertSet('detalheAberto', true)
        ->assertSee('PROCESSO PAGAMENTO')
        ->assertSee('PP 202501220006 COELBA.pdf')
        ->assertSee('1638 KB')
        ->assertSee('Sem Comprovante de Pagamento.')
        ->assertSee('ignorado: é idêntico');
});

test('download entrega o zip organizado com o nome da entidade', function () {
    $this->actingAs(User::factory()->create());
    $organizacao = Organizacao::factory()->concluida()->create(['entidade' => 'CM de Teste']);
    Storage::disk('local')->put($organizacao->arquivo_saida, 'zip');

    $this->get(route('organizacoes.download', $organizacao))
        ->assertOk()
        ->assertDownload('CM de Teste.zip');
});

test('download sem zip gerado responde 404', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('organizacoes.download', Organizacao::factory()->create()))->assertNotFound();
});

test('exclusao remove o registro e os arquivos em disco', function () {
    $organizacao = Organizacao::factory()->concluida()->create();
    Storage::disk('local')->put($organizacao->diretorio().'/saida.zip', 'zip');

    Livewire::test(Index::class)
        ->call('confirmarExclusao', $organizacao->id)
        ->call('excluir');

    expect(Organizacao::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($organizacao->diretorio().'/saida.zip');
});

test('falha no job marca a organizacao como falhou com o motivo', function () {
    $organizacao = Organizacao::factory()->create();

    (new OrganizarPrestacao($organizacao))->failed(new RuntimeException('Nenhum PDF encontrado nos arquivos enviados.'));

    expect($organizacao->refresh()->status)->toBe(OrganizacaoStatus::Falhou)
        ->and($organizacao->erro)->toBe('Nenhum PDF encontrado nos arquivos enviados.');
});
