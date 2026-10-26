<?php

use App\Models\Term;
use Database\Seeders\SuperAdminSeeder;
use Database\Seeders\TermsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Day recorded in the fixtures (source test suite). */
const DI_TESTE = '2026-09-30';

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function fixtureJson(string $nome): array
{
    $caminho = base_path('tests/Fixtures/'.$nome);

    return json_decode((string) file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR);
}

function fixtureHtml(string $nome): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/'.$nome));
}

/**
 * Seeds the search terms (when empty) and the superadmin account.
 */
function seedTcmBase(): void
{
    app(TermsSeeder::class)->run();
    app(SuperAdminSeeder::class)->run();
}

/**
 * Only the term Maiquinique stays active (keeps collections cheap and predictable).
 */
function onlyMaiquinique(): void
{
    Term::query()->update(['ativo' => false]);
    Term::query()->where('termo', 'Maiquinique')->update(['ativo' => true]);
}

/**
 * Logs in as the seeded superadmin through the API and returns the token.
 */
function superadminToken(): string
{
    $resposta = test()->postJson('/api/auth/login', [
        'username' => config('tcm.superadmin_user'),
        'password' => config('tcm.superadmin_password'),
    ]);
    $resposta->assertOk();

    return (string) $resposta->json('access_token');
}

/**
 * Authorization header for the API helpers.
 *
 * @return array<string, string>
 */
function bearer(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

/**
 * Forges a JWT with an arbitrary key/claims (signature and expiry checks).
 */
function forgeJwt(array $claims, string $key): string
{
    $header = base64urlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
    $payload = base64urlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
    $assinatura = base64urlEncode(hash_hmac('sha256', "{$header}.{$payload}", $key, true));

    return "{$header}.{$payload}.{$assinatura}";
}

function base64urlEncode(string $dados): string
{
    return rtrim(strtr(base64_encode($dados), '+/', '-_'), '=');
}

/**
 * Routes EGBANET and TCM-BA requests to the recorded fixtures, mirroring the
 * original MockEgba/MockTcm: records every call for later assertions.
 *
 * @param  array{falhar_em?: string|null, tcm_inexistente?: list<string>, tcm_quebrar?: list<string>, tcm_html?: string}  $opcoes
 * @return object{egba: list<string>, tcm: list<string>}
 */
function fakeTcmHttp(array $opcoes = []): object
{
    $falharEm = $opcoes['falhar_em'] ?? null;
    $tcmInexistente = $opcoes['tcm_inexistente'] ?? [];
    $tcmQuebrar = $opcoes['tcm_quebrar'] ?? [];
    $tcmHtml = $opcoes['tcm_html'] ?? 'consulta_processo_09768e26.html';

    $registrador = (object) ['egba' => [], 'tcm' => []];
    $egbaHost = parse_url((string) config('tcm.base_url'), PHP_URL_HOST);
    $consulta = (string) config('tcm.process_lookup_url');

    Http::fake(function ($request) use ($registrador, $egbaHost, $consulta, $falharEm, $tcmInexistente, $tcmQuebrar, $tcmHtml) {
        $url = $request->url();
        $host = parse_url($url, PHP_URL_HOST);
        $caminho = (string) parse_url($url, PHP_URL_PATH);

        if ($host === $egbaHost) {
            $registrador->egba[] = $url;

            if (str_contains($caminho, 'edicoes_from_data.json')) {
                return Http::response(fixtureJson('edicoes_from_data.json'));
            }

            if (str_contains($caminho, 'ultimas_edicoes.json')) {
                return Http::response(fixtureJson('ultimas_edicoes.json'));
            }

            if (str_contains($caminho, '/busca/busca/buscar/query/')) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $consultaQuery);
                $termo = trim((string) ($consultaQuery['q'] ?? ''), '"');

                if ($falharEm !== null && str_contains($termo, $falharEm)) {
                    return Http::response('erro interno', 500);
                }

                preg_match('#/query/(\d+)/#', $caminho, $paginaMatch);
                $pagina = (int) ($paginaMatch[1] ?? 0);
                preg_match('#di:(\d{4}-\d{2}-\d{2})/df:(\d{4}-\d{2}-\d{2})#', $caminho, $intervalo);

                if ($termo === 'Maiquinique') {
                    $dados = fixtureJson($pagina === 0
                        ? 'busca_maiquinique_p0.json'
                        : 'busca_maiquinique_p1.json');
                    $di = $intervalo[1] ?? DI_TESTE;
                    $df = $intervalo[2] ?? DI_TESTE;
                    $hits = array_values(array_filter(
                        $dados['hits']['hits'],
                        fn (array $h): bool => $di <= $h['_source']['data'] && $h['_source']['data'] <= $df
                    ));
                    // the source filters by date server-side: total follows the slice
                    $dados['hits']['hits'] = $hits;
                    $dados['hits']['total'] = count($hits);

                    return Http::response($dados);
                }

                return Http::response([
                    'took' => 1,
                    'timed_out' => false,
                    'hits' => ['total' => 0, 'max_score' => null, 'hits' => []],
                    'aggregations' => [],
                    'loggedCredit' => true,
                ]);
            }

            return Http::response(['detail' => 'rota desconhecida'], 404);
        }

        if (str_starts_with($url, $consulta)) {
            $dados = $request->data();
            $numero = (string) ($dados['proc'] ?? '');
            $registrador->tcm[] = $numero;

            if (in_array($numero, $tcmQuebrar, true)) {
                return Http::response('erro interno', 500);
            }

            if (in_array($numero, $tcmInexistente, true)) {
                return Http::response(fixtureHtml('consulta_processo_inexistente.html'), 200, ['Content-Type' => 'text/html']);
            }

            return Http::response(fixtureHtml($tcmHtml), 200, ['Content-Type' => 'text/html']);
        }

        return Http::response(['detail' => 'rota desconhecida'], 404);
    });

    return $registrador;
}
