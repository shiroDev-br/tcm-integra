<?php

use App\Enums\OrganizacaoStatus;
use App\Enums\PastaDaPrestacao;
use App\Jobs\OrganizarPrestacao;
use App\Models\Organizacao;
use App\Services\Organizador\AjustadorDeTamanho;
use App\Services\Organizador\DocumentoDeSaida;
use App\Services\Organizador\OrganizadorDePrestacao;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * End-to-end runs with the real qpdf, Ghostscript and pdftotext (OCR off).
 */
function ferramentasDePdfInstaladas(): bool
{
    foreach (['qpdf', 'gs', 'pdftotext'] as $binario) {
        if (! Process::run(['which', $binario])->successful()) {
            return false;
        }
    }

    return true;
}

/**
 * Text PDF generated with Ghostscript, one page per item.
 *
 * @param  list<string>  $paginas
 */
function pdfDeTeste(string $destino, array $paginas): string
{
    File::ensureDirectoryExists(dirname($destino));
    $postscript = "%!PS\n";

    foreach ($paginas as $texto) {
        $postscript .= "/Helvetica findfont 10 scalefont setfont\n";

        foreach (explode("\n", $texto) as $linha => $conteudo) {
            $postscript .= sprintf("40 %d moveto (%s) show\n", 750 - $linha * 14, addcslashes($conteudo, '()\\'));
        }

        $postscript .= "showpage\n";
    }

    file_put_contents($destino.'.ps', $postscript);
    Process::run(['gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-sDEVICE=pdfwrite', '-sOutputFile='.$destino, $destino.'.ps'])->throw();
    File::delete($destino.'.ps');

    return $destino;
}

/**
 * @return list<string>
 */
function entradasDoZip(string $caminho): array
{
    $zip = new ZipArchive;
    $zip->open($caminho);
    $nomes = [];

    for ($indice = 0; $indice < $zip->numFiles; $indice++) {
        $nomes[] = $zip->getNameIndex($indice);
    }

    $zip->close();
    sort($nomes);

    return $nomes;
}

beforeEach(function () {
    Storage::fake('local');
    config(['organizador.ocr' => false]);
});

test('organiza o zip enviado em pastas, nomes e ordem do TCM', function () {
    $organizacao = Organizacao::factory()->create(['entidade' => null]);
    $temporario = storage_path('framework/testing/organizador-'.uniqid());

    $empenho = 'EMPENHO: 2501060007 / 2025 No SUB-EMPENHO: 8';
    $lote = pdfDeTeste($temporario.'/lote.pdf', [
        "CAMARA MUNICIPAL DE TESTE\nNOTA DE LIQUIDACAO\n{$empenho}",
        "CAMARA MUNICIPAL DE TESTE\nORDEM DE PAGAMENTO PROCESSO ADM\n{$empenho}\nFORNECEDOR\nNome: 27 - COELBA - COMPANHIA DE ELETRICIDADE\nEndereco: RUA A\nRECIBO No PROCESSO PAGTO: 202501220006 / 2025",
        "2a Via - Comprovante de Pagamento de Boleto\nVia Internet Banking CAIXA",
        "CAMARA MUNICIPAL DE TESTE\nNOTA DE SUB-EMPENHO\n{$empenho}",
        "CAMARA MUNICIPAL DE TESTE\nNOTA DE EMPENHO\nEMPENHO: 2501060007 / 2025",
        'DANFE - DOCUMENTO AUXILIAR DA NOTA FISCAL DE ENERGIA ELETRICA',
    ]);
    $contrato = pdfDeTeste($temporario.'/contrato.pdf', ["CONTRATO No 001/2025\nCONTRATO QUE ENTRE SI CELEBRAM"]);
    $recibo = pdfDeTeste($temporario.'/recibo.pdf', ['RECIBO DE ENTREGA DE PRESTACAO DE CONTAS']);

    $zip = new ZipArchive;
    $zip->open($temporario.'/envio.zip', ZipArchive::CREATE);
    $zip->addFile($lote, 'CM de Teste/PROCESSO PAGAMENTO - ok/lote.pdf');
    $zip->addFile($contrato, 'CM de Teste/diversos/CONTRATO 001-2025.pdf');
    $zip->addFile($contrato, 'CM de Teste/diversos/copia do contrato.pdf');
    $zip->addFile($recibo, 'CM de Teste/reciboPrestacaoContas.pdf');
    $zip->addFromString('__MACOSX/CM de Teste/._lote.pdf', 'lixo');
    $zip->addFromString('../fora.pdf', 'zip-slip');
    $zip->close();
    Storage::disk('local')->put($organizacao->diretorioDeEntrada().'/envio.zip', file_get_contents($temporario.'/envio.zip'));

    (new OrganizarPrestacao($organizacao))->handle(app(OrganizadorDePrestacao::class));
    $organizacao->refresh();

    expect($organizacao->status)->toBe(OrganizacaoStatus::Concluida)
        ->and($organizacao->entidade)->toBe('CM de Teste')
        ->and($organizacao->avisos)->toBe(['"diversos/copia do contrato.pdf" ignorado: é idêntico a "diversos/CONTRATO 001-2025.pdf".'])
        ->and(entradasDoZip($organizacao->caminhoAbsoluto($organizacao->arquivo_saida)))->toBe([
            'CM de Teste/',
            'CM de Teste/CONTRATOS E ADITIVOS/',
            'CM de Teste/CONTRATOS E ADITIVOS/CONTRATO 001-2025.pdf',
            'CM de Teste/LICITAÇÃO/',
            'CM de Teste/LICITAÇÃO/PREGÃO/',
            'CM de Teste/PROCESSO PAGAMENTO/',
            'CM de Teste/PROCESSO PAGAMENTO/PP 202501220006 COELBA.pdf',
            'CM de Teste/reciboPrestacaoContas.pdf',
        ]);

    $processo = $organizacao->arquivos()->where('pasta', PastaDaPrestacao::ProcessoPagamento)->sole();
    expect($processo->paginas)->toBe(6)->and($processo->avisos)->toBe([]);

    $zipFinal = new ZipArchive;
    $zipFinal->open($organizacao->caminhoAbsoluto($organizacao->arquivo_saida));
    file_put_contents($temporario.'/pp.pdf', $zipFinal->getFromName('CM de Teste/PROCESSO PAGAMENTO/PP 202501220006 COELBA.pdf'));
    $zipFinal->close();

    $texto = Process::run(['pdftotext', $temporario.'/pp.pdf', '-'])->output();
    $posicoes = array_map(fn (string $titulo) => strpos($texto, $titulo), ['ORDEM DE PAGAMENTO', 'NOTA DE LIQUIDACAO', 'NOTA DE EMPENHO', 'NOTA DE SUB-EMPENHO', 'DANFE', 'Comprovante de Pagamento']);

    expect($posicoes)->not->toContain(false)
        ->and($posicoes)->toBe(collect($posicoes)->sort()->values()->all());

    Storage::disk('local')->assertMissing($organizacao->diretorioDeEntrada().'/envio.zip');
    File::deleteDirectory($temporario);
})->skip(fn () => ! ferramentasDePdfInstaladas(), 'qpdf, Ghostscript ou pdftotext não instalados');

test('divide em partes o documento acima do limite por arquivo', function () {
    config(['organizador.limite_arquivo_kb' => 1, 'organizador.compressao' => []]);
    $temporario = storage_path('framework/testing/organizador-'.uniqid());
    $pdf = pdfDeTeste($temporario.'/longo.pdf', ['Pagina um', 'Pagina dois', 'Pagina tres']);

    $documento = new DocumentoDeSaida(PastaDaPrestacao::InexEDispensas, 'INEX 001-2025 - ACJUS', [
        ['arquivo' => $pdf, 'pagina' => 1],
        ['arquivo' => $pdf, 'pagina' => 2],
        ['arquivo' => $pdf, 'pagina' => 3],
    ]);

    $gerados = app(AjustadorDeTamanho::class)->gerar($documento, $temporario.'/saida', $temporario.'/tmp');

    expect(array_map(fn ($gerado) => $gerado->nome, $gerados))->toBe([
        'INEX 001-2025 - ACJUS - Parte 1.pdf',
        'INEX 001-2025 - ACJUS - Parte 2.pdf',
        'INEX 001-2025 - ACJUS - Parte 3.pdf',
    ])
        ->and($gerados[0]->avisos)->toContain('Dividido em 3 partes para respeitar o limite de 1KB.');

    File::deleteDirectory($temporario);
})->skip(fn () => ! ferramentasDePdfInstaladas(), 'qpdf, Ghostscript ou pdftotext não instalados');
