<?php

use App\Models\Term;

beforeEach(function () {
    seedTcmBase();
    fakeTcmHttp();
});

test('health e placeholder sao publicos', function () {
    $this->getJson('/api/health')->assertOk()->assertExactJson(['status' => 'ok']);

    $raiz = $this->get('/');
    $raiz->assertOk();
    $raiz->assertSee('TCM Scrapping');
});

test('edicao atual vem da fonte', function () {
    $this->withToken(superadminToken());

    $this->getJson('/api/edicao')
        ->assertOk()
        ->assertJsonPath('id', 22622)
        ->assertJsonPath('numero', 2899);
});

test('crud de termos', function () {
    $this->withToken(superadminToken());

    $this->getJson('/api/termos')->assertOk()->assertJsonCount(11);

    $criado = $this->postJson('/api/termos', [
        'termo' => 'Nova Empresa',
        'tipo' => 'empresa',
        'variacoes' => ['Nova Empreendimentos'],
    ]);
    $criado->assertCreated();
    $termoId = (int) $criado->json('id');
    expect($criado->json('variacoes'))->toBe(['Nova Empreendimentos']);

    $this->postJson('/api/termos', ['termo' => 'Nova Empresa'])->assertStatus(409);

    $this->putJson("/api/termos/{$termoId}", [
        'termo' => 'Nova Empresa',
        'tipo' => 'empresa',
        'ativo' => false,
    ])->assertOk()->assertJsonPath('ativo', false);

    $this->putJson('/api/termos/9999', ['termo' => 'xy'])->assertNotFound();

    $this->deleteJson("/api/termos/{$termoId}")->assertNoContent();
    $this->deleteJson("/api/termos/{$termoId}")->assertNotFound();
});

test('buscar retorna ocorrencias e resumo', function () {
    onlyMaiquinique();
    $this->withToken(superadminToken());

    $resp = $this->postJson('/api/buscar', ['inicio' => DI_TESTE, 'fim' => DI_TESTE]);
    $resp->assertOk();
    $corpo = $resp->json();

    expect($corpo['edicoes'][0]['id'])->toBe(22622);
    expect($corpo['ocorrencias'])->toHaveCount(1);
    expect($corpo['ocorrencias'][0]['termo'])->toBe('Maiquinique');
    expect($corpo['ocorrencias'][0]['pdf_url'])->toEndWith('/22622/2');
    expect($corpo['resumo']['geral']['termos_com_ocorrencia'])->toBe(1);
    expect($corpo['resumo']['geral']['texto'])->toContain('**Maiquinique**');
    expect($corpo['meta']['requests'])->toBe(2);

    $cache = $this->getJson('/api/ocorrencias?inicio='.DI_TESTE.'&fim='.DI_TESTE)
        ->assertOk()
        ->json();
    expect($cache)->toHaveCount(1);
    expect($cache[0]['termo'])->toBe('Maiquinique');

    $resumo = $this->getJson('/api/resumo?inicio='.DI_TESTE.'&fim='.DI_TESTE)
        ->assertOk()
        ->json();
    expect($resumo['geral']['ocorrencias'])->toBe(1);
    expect($resumo['por_termo'][0]['termo'])->toBe('Maiquinique');

    $ordenados = array_values($corpo['ocorrencias'][0]['processos']);
    sort($ordenados, SORT_STRING);
    expect($resumo['por_termo'][0]['processos'])->toBe($ordenados);
    expect($resumo['por_termo'][0]['texto'])
        ->toContain('**'.$resumo['por_termo'][0]['processos'][0].'**');

    $processos = $this->getJson('/api/processos?inicio='.DI_TESTE.'&fim='.DI_TESTE)
        ->assertOk()
        ->json();
    expect($processos)->not->toBeEmpty();
    // único processo da página que é de fato de Maiquinique
    expect($processos[0]['numero'])->toBe('10030e25');

    foreach ($processos as $processo) {
        expect($processo['encontrado'])->toBeTrue();
    }
});

test('termo inativado sai dos resultados', function () {
    onlyMaiquinique();
    $this->withToken(superadminToken());

    $this->postJson('/api/buscar', ['inicio' => DI_TESTE, 'fim' => DI_TESTE])->assertOk();
    $this->getJson('/api/ocorrencias?inicio='.DI_TESTE.'&fim='.DI_TESTE)
        ->assertOk()
        ->assertJsonCount(1);

    Term::query()->where('termo', 'Maiquinique')->update(['ativo' => false]);

    $this->getJson('/api/ocorrencias?inicio='.DI_TESTE.'&fim='.DI_TESTE)
        ->assertOk()
        ->assertExactJson([]);

    $resumo = $this->getJson('/api/resumo?inicio='.DI_TESTE.'&fim='.DI_TESTE)
        ->assertOk()
        ->json();
    expect($resumo['geral']['ocorrencias'])->toBe(0);
    expect($resumo['por_termo'])->toBe([]);

    $this->getJson('/api/processos?inicio='.DI_TESTE.'&fim='.DI_TESTE)
        ->assertOk()
        ->assertExactJson([]);

    $nova = $this->postJson('/api/buscar', ['inicio' => DI_TESTE, 'fim' => DI_TESTE]);
    $nova->assertOk();
    expect($nova->json('ocorrencias'))->toBe([]);
});

test('buscar com periodo invalido responde 422 no corpo', function () {
    $this->withToken(superadminToken());

    $this->postJson('/api/buscar', [])
        ->assertStatus(422)
        ->assertJsonPath('detail.0.loc.0', 'body')
        ->assertJsonPath('detail.0.loc.1', '_periodo');
});

test('edicoes por periodo', function () {
    $this->withToken(superadminToken());

    // a fixture e de 30/09/2026; "1 dia" depende do relogio, entao usa 1100
    $edicoes = $this->getJson('/api/edicoes?dias=1')->assertOk()->json();
    expect($edicoes)->toBeArray();

    $antigo = $this->getJson('/api/edicoes?dias=1100')->assertOk()->json();
    expect($antigo)->not->toBeEmpty();
    expect($antigo[0]['id'])->toBe(22622);
});

test('rotas de dados exigem token', function () {
    $rotas = [
        ['get', '/api/termos'],
        ['post', '/api/buscar'],
        ['get', '/api/resumo?periodo=hoje'],
        ['get', '/api/usuarios'],
        ['get', '/api/auth/me'],
    ];

    foreach ($rotas as [$metodo, $caminho]) {
        $resp = $metodo === 'post'
            ? $this->postJson($caminho, [])
            : $this->getJson($caminho);

        $resp->assertStatus(401)
            ->assertExactJson(['detail' => 'Não autenticado'])
            ->assertHeader('WWW-Authenticate', 'Bearer');
    }

    $this->getJson('/api/health')->assertOk();
    $this->get('/')->assertOk();
});
