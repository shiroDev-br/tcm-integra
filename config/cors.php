<?php

$origins = env('TCM_ALLOW_ORIGINS', 'http://localhost:3000,http://localhost:8000');

if (is_string($origins)) {
    $origins = str_starts_with(trim($origins), '[')
        ? (json_decode($origins, true) ?: [])
        : array_values(array_filter(array_map('trim', explode(',', $origins))));
}

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Origins controladas por TCM_ALLOW_ORIGINS (CSV ou JSON), com credenciais
    | habilitadas e apenas os métodos/header usados pelo front.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],

    'allowed_origins' => $origins ?: ['http://localhost:8000'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
