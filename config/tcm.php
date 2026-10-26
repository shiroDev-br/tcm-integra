<?php

$origins = env('TCM_ALLOW_ORIGINS', 'http://localhost:3000,http://localhost:8000');

if (is_string($origins)) {
    $origins = str_starts_with(trim($origins), '[')
        ? (json_decode($origins, true) ?: [])
        : array_values(array_filter(array_map('trim', explode(',', $origins))));
}

/**
 * Accepts "10" or "10/minute" (original system format) and returns requests per minute.
 */
$perMinute = function (string $key, string $default): int {
    $raw = (string) env($key, $default);

    return max(1, (int) preg_replace('/\D+/', '', $raw));
};

return [

    // EGBANET source
    'base_url' => env('TCM_BASE_URL', 'https://egbanet.egba.ba.gov.br'),
    'subtheme' => env('TCM_SUBTEMA', 'tcm'),
    'user_agent' => env('TCM_USER_AGENT', 'tcm-scrapping/0.1 (monitoramento do Diario Oficial TCM-BA)'),
    'http_timeout' => (float) env('TCM_HTTP_TIMEOUT', 30.0),
    'http_retries' => (int) env('TCM_HTTP_RETRIES', 2),
    'max_pages' => (int) env('TCM_MAX_PAGINAS', 20),
    'search_concurrency' => (int) env('TCM_CONCORRENCIA_BUSCA', 3),
    'tcm_concurrency' => (int) env('TCM_CONCORRENCIA_TCM', 10),
    'process_lookup_timeout' => (float) env('TCM_CONSULTA_TIMEOUT', 10.0),
    'search_time_limit' => (int) env('TCM_BUSCA_TIME_LIMIT', 180),

    // enrichment
    'context_chars' => (int) env('TCM_CONTEXT_CHARS', 400),
    'act_window_chars' => (int) env('TCM_ATOS_WINDOW_CHARS', 1500),

    // process ↔ term evidence on the page (see LocalizadorDeProcessos)
    'link_window_chars' => (int) env('TCM_VINCULO_JANELA_CHARS', 300),

    // deadlines read from the acts (see ExtratorDePrazo)
    'deadline_window_chars' => (int) env('TCM_PRAZO_JANELA_CHARS', 350),
    'inspectorate_notice_days' => (int) env('TCM_PRAZO_NOTIFICACAO_INSPETORIA_DIAS', 15),

    // process lookup (www.tcm.ba.gov.br portal — server-side POST)
    'process_lookup_url' => env('TCM_CONSULTA_PROCESSO_URL', 'https://www.tcm.ba.gov.br/controle-social/consulta-processual/'),
    'process_lookup_proxy' => env('TCM_CONSULTA_PROCESSO_PROXY', ''),

    // search terms
    'terms_json' => env('TCM_TERMOS_JSON', 'database/seeders/termos.json'),

    // authentication
    'secret_key' => env('TCM_SECRET_KEY', ''),
    'token_expiry_minutes' => (int) env('TCM_TOKEN_EXPIRA_MINUTOS', 480),
    'max_login_attempts' => (int) env('TCM_MAX_TENTATIVAS_LOGIN', 5),
    'lockout_minutes' => (int) env('TCM_BLOQUEIO_MINUTOS', 15),
    'superadmin_user' => env('TCM_SUPERADMIN_USER', 'superadmin'),
    'superadmin_password' => env('TCM_SUPERADMIN_SENHA', ''),

    // security / operation
    'environment' => env('TCM_AMBIENTE', 'development'),
    'allowed_origins' => $origins,
    'rate_limits' => [
        'search' => $perMinute('TCM_RATE_LIMIT_BUSCA', '10/minute'),
        'login' => $perMinute('TCM_RATE_LIMIT_LOGIN', '10/minute'),
        'terms' => $perMinute('TCM_RATE_LIMIT_TERMOS', '30/minute'),
        'read' => $perMinute('TCM_RATE_LIMIT_LEITURA', '120/minute'),
    ],
    'timezone' => env('TCM_FUSOHORARIO', 'America/Bahia'),

];
