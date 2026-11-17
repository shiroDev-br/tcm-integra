<?php

/*
|--------------------------------------------------------------------------
| Organizador de prestação de contas
|--------------------------------------------------------------------------
|
| Regras da planilha "Formatação" do TCM: cada PDF com no máximo 5000KB,
| cada página com no máximo 500KB e todo PDF pesquisável (com texto).
|
*/

return [

    'limite_arquivo_kb' => (int) env('ORGANIZADOR_LIMITE_ARQUIVO_KB', 5000),

    'limite_pagina_kb' => (int) env('ORGANIZADOR_LIMITE_PAGINA_KB', 500),

    'upload_max_kb' => (int) env('ORGANIZADOR_UPLOAD_MAX_KB', 204800),

    /** Desligue onde o ocrmypdf não estiver instalado; os PDFs seguem com o texto que já têm. */
    'ocr' => (bool) env('ORGANIZADOR_OCR', true),

    /** Proteção contra zip-bomba: soma máxima dos PDFs extraídos dos zips enviados. */
    'limite_extraido_mb' => (int) env('ORGANIZADOR_LIMITE_EXTRAIDO_MB', 2048),

    /** Idioma do tesseract usado pelo ocrmypdf. */
    'idioma_ocr' => env('ORGANIZADOR_IDIOMA_OCR', 'por'),

    /** Páginas com menos caracteres que isso são tratadas como imagem sem texto. */
    'minimo_caracteres_texto' => 20,

    /** Tamanho máximo do nome do fornecedor usado no nome do arquivo do processo. */
    'tamanho_nome_fornecedor' => 40,

    /** Tempo máximo (segundos) de cada chamada às ferramentas de PDF. */
    'timeout_ferramenta' => (int) env('ORGANIZADOR_TIMEOUT_FERRAMENTA', 600),

    'binarios' => [
        'qpdf' => env('ORGANIZADOR_QPDF', 'qpdf'),
        'pdftotext' => env('ORGANIZADOR_PDFTOTEXT', 'pdftotext'),
        'ghostscript' => env('ORGANIZADOR_GHOSTSCRIPT', 'gs'),
        'ocrmypdf' => env('ORGANIZADOR_OCRMYPDF', 'ocrmypdf'),
    ],

    /**
     * Níveis de compressão do Ghostscript, do mais leve ao mais agressivo.
     * Usados em sequência até o arquivo caber nos limites.
     */
    'compressao' => [
        ['preset' => '/ebook', 'dpi' => 150],
        ['preset' => '/screen', 'dpi' => 110],
    ],

];
