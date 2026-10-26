<?php

beforeEach(fn () => seedTcmBase());

test('resposta do login e minima', function () {
    $resp = $this->postJson('/api/auth/login', [
        'username' => config('tcm.superadmin_user'),
        'password' => config('tcm.superadmin_password'),
    ]);

    $resp->assertOk();
    $corpo = $resp->json();

    expect(array_keys($corpo))->toHaveCount(2);
    expect($corpo)->toHaveKeys(['access_token', 'token_type']);
    expect($corpo['token_type'])->toBe('bearer');
});

test('erro de login unico nao enumera contas', function () {
    $senha = (string) config('tcm.superadmin_password');

    $inexistente = $this->postJson('/api/auth/login', [
        'username' => 'nao-existe-zzz',
        'password' => $senha,
    ]);
    $senhaErrada = $this->postJson('/api/auth/login', [
        'username' => config('tcm.superadmin_user'),
        'password' => 'senha-errada-123',
    ]);

    $inexistente->assertStatus(401);
    $senhaErrada->assertStatus(401);
    expect($inexistente->json())->toBe($senhaErrada->json());
    expect($inexistente->json())->toBe(['detail' => 'Credenciais inválidas']);
    $inexistente->assertHeader('WWW-Authenticate', 'Bearer');
    expect($inexistente->getContent())->not->toContain($senha);
});

test('lockout apos tentativas e mesma mensagem', function () {
    $max = (int) config('tcm.max_login_attempts');

    for ($i = 0; $i < $max; $i++) {
        $resp = $this->postJson('/api/auth/login', [
            'username' => config('tcm.superadmin_user'),
            'password' => 'senha-errada',
        ]);
        $resp->assertStatus(401);
        expect($resp->json())->toBe(['detail' => 'Credenciais inválidas']);
    }

    $resp = $this->postJson('/api/auth/login', [
        'username' => config('tcm.superadmin_user'),
        'password' => config('tcm.superadmin_password'),
    ]);
    $resp->assertStatus(401);
    expect($resp->json())->toBe(['detail' => 'Credenciais inválidas']);
});

test('rate limit no login', function () {
    $limite = (int) config('tcm.max_login_attempts') + 7;
    $ultimo = null;

    for ($i = 0; $i < $limite; $i++) {
        $ultimo = $this->postJson('/api/auth/login', [
            'username' => 'qualquer-usuario',
            'password' => 'senha',
        ]);

        if ($ultimo->status() === 429) {
            break;
        }
    }

    expect($ultimo)->not->toBeNull();
    expect($ultimo->status())->toBe(429);
    $ultimo->assertExactJson(['detail' => 'Too many requests.']);
});

test('me devolve apenas username e role', function () {
    $this->withToken(superadminToken());

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertExactJson([
            'username' => config('tcm.superadmin_user'),
            'role' => 'superadmin',
        ]);
});

test('token invalido e expirado retornam 401', function () {
    $segredo = (string) config('tcm.secret_key');
    $super = (string) config('tcm.superadmin_user');

    $this->withHeaders(['Authorization' => 'Bearer nao-e-jwt'])
        ->getJson('/api/auth/me')
        ->assertStatus(401);

    $expirado = forgeJwt(
        ['sub' => $super, 'role' => 'superadmin', 'exp' => time() - 60],
        $segredo
    );
    $this->withHeaders(['Authorization' => 'Bearer '.$expirado])
        ->getJson('/api/auth/me')
        ->assertStatus(401)
        ->assertExactJson(['detail' => 'Não autenticado']);

    $outraChave = forgeJwt(
        ['sub' => $super, 'role' => 'superadmin', 'exp' => time() + 3600],
        'outra-chave-'.str_repeat('y', 32)
    );
    $this->withHeaders(['Authorization' => 'Bearer '.$outraChave])
        ->getJson('/api/auth/me')
        ->assertStatus(401);
});

test('superadmin cria admin sem vazar senha', function () {
    $this->withToken(superadminToken());

    $criado = $this->postJson('/api/usuarios', [
        'username' => 'operador.1',
        'password' => 'senha-do-admin-1',
    ]);
    $criado->assertCreated();

    $corpo = $criado->json();
    expect($corpo['role'])->toBe('admin');
    expect($corpo['ativo'])->toBeTrue();
    expect(array_keys($corpo))->toBe(['id', 'username', 'role', 'ativo', 'criado_em']);
    expect($criado->getContent())->not->toContain('senha');

    $lista = $this->getJson('/api/usuarios')->assertOk();
    expect($lista->getContent())->not->toContain('senha');
    expect(collect($lista->json())->pluck('username')->all())->toContain('operador.1');

    $this->postJson('/api/usuarios', [
        'username' => 'operador.1',
        'password' => 'outra-senha-123',
    ])->assertStatus(409);
});

test('papel nao vem do cliente', function () {
    $this->withToken(superadminToken());

    $resp = $this->postJson('/api/usuarios', [
        'username' => 'golpista',
        'password' => 'senha-segura-1',
        'role' => 'superadmin',
        'ativo' => true,
    ]);

    $resp->assertCreated();
    expect($resp->json('role'))->toBe('admin');
});

test('admin autentica e usa o painel', function () {
    $this->withToken(superadminToken());
    $this->postJson('/api/usuarios', [
        'username' => 'analista',
        'password' => 'senha-analista-1',
    ])->assertCreated();

    $login = $this->postJson('/api/auth/login', [
        'username' => 'analista',
        'password' => 'senha-analista-1',
    ]);
    $login->assertOk();
    $this->withToken((string) $login->json('access_token'));

    $this->getJson('/api/termos')->assertOk();
    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertExactJson(['username' => 'analista', 'role' => 'admin']);

    $this->postJson('/api/usuarios', [
        'username' => 'outro',
        'password' => 'senha-segura-2',
    ])->assertStatus(403)->assertExactJson(['detail' => 'Acesso negado']);

    $this->getJson('/api/usuarios')->assertStatus(403);
});

test('admin nao gerencia contas nem forja papel', function () {
    $super = superadminToken();
    $this->withToken($super);
    $this->postJson('/api/usuarios', [
        'username' => 'intruso',
        'password' => 'senha-intruso-1',
    ])->assertCreated();

    $login = $this->postJson('/api/auth/login', [
        'username' => 'intruso',
        'password' => 'senha-intruso-1',
    ]);
    $login->assertOk();
    $tokenIntruso = (string) $login->json('access_token');

    $lista = $this->getJson('/api/usuarios')->assertOk()->json();
    $superId = (int) collect($lista)->firstWhere('role', 'superadmin')['id'];

    $this->withToken($tokenIntruso);
    $this->postJson('/api/usuarios', [
        'username' => 'wannabe',
        'password' => 'senha-wannabe-1',
        'role' => 'superadmin',
    ])->assertStatus(403);

    $this->putJson("/api/usuarios/{$superId}", ['ativo' => false])->assertStatus(403);
    $this->deleteJson("/api/usuarios/{$superId}")->assertStatus(403);

    $forjado = forgeJwt(
        ['sub' => 'intruso', 'role' => 'superadmin', 'exp' => time() + 3600],
        'chave-de-atacante-'.str_repeat('z', 32)
    );
    $this->withHeaders(['Authorization' => 'Bearer '.$forjado])
        ->postJson('/api/usuarios', [
            'username' => 'fantasma',
            'password' => 'senha-fantasma-1',
        ])
        ->assertStatus(401)
        ->assertExactJson(['detail' => 'Não autenticado']);
});

test('superadmin nao se desativa nem se exclui', function () {
    $this->withToken(superadminToken());

    $lista = $this->getJson('/api/usuarios')->assertOk()->json();
    $superId = (int) collect($lista)->firstWhere('role', 'superadmin')['id'];

    $this->putJson("/api/usuarios/{$superId}", ['ativo' => false])
        ->assertStatus(403)
        ->assertExactJson(['detail' => 'operação não permitida']);

    $this->deleteJson("/api/usuarios/{$superId}")->assertStatus(403);

    $this->putJson('/api/usuarios/99999', ['ativo' => false])->assertNotFound();
});

test('admin desativado perde acesso na hora', function () {
    $super = superadminToken();
    $this->withToken($super);
    $this->postJson('/api/usuarios', [
        'username' => 'temporario',
        'password' => 'senha-temporaria-1',
    ])->assertCreated();

    $login = $this->postJson('/api/auth/login', [
        'username' => 'temporario',
        'password' => 'senha-temporaria-1',
    ]);
    $login->assertOk();
    $token = (string) $login->json('access_token');

    $this->withToken($token)->getJson('/api/auth/me')->assertOk();

    $lista = $this->withToken($super)->getJson('/api/usuarios')->assertOk()->json();
    $alvo = (int) collect($lista)->firstWhere('username', 'temporario')['id'];

    $this->withToken($super)->putJson("/api/usuarios/{$alvo}", ['ativo' => false])->assertOk();

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertStatus(401)
        ->assertExactJson(['detail' => 'Não autenticado']);
});
