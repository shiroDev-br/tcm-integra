<?php

use App\Http\Middleware\AuthenticateApi;
use App\Http\Middleware\EnsureSuperadmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.api' => AuthenticateApi::class,
            'role' => EnsureSuperadmin::class,
        ]);

        $middleware->api(append: [
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Validação no formato FastAPI: {"detail": [{"loc": [...], "msg": "..."}]}
        $exceptions->render(function (ValidationException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            $prefixo = $request->isMethod('GET') ? 'query' : 'body';
            $detail = [];

            foreach ($exception->errors() as $campo => $mensagens) {
                $loc = array_merge(
                    [$prefixo],
                    array_map(
                        fn (string $parte) => ctype_digit($parte) ? (int) $parte : $parte,
                        explode('.', (string) $campo)
                    ),
                );

                $detail[] = [
                    'loc' => $loc,
                    'msg' => (string) ($mensagens[0] ?? 'valor inválido'),
                    'type' => 'value_error',
                ];
            }

            return response()->json(['detail' => $detail], 422);
        });

        // Rate limit no formato da API original: {"detail": "Too many requests."}
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['detail' => 'Too many requests.'], 429, $exception->getHeaders());
        });
    })->create();
