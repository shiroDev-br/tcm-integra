<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class HealthController extends Controller
{
    /**
     * Public liveness endpoint.
     */
    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    /**
     * Home placeholder until the panel frontend is in place.
     */
    public function placeholder(): Response
    {
        return response(
            '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
            .'<title>TCM Scrapping</title></head><body><h1>TCM Scrapping</h1>'
            .'<p>Monitoramento do Diário Oficial do TCM-BA.</p></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
}
