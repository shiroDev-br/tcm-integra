<?php

namespace App\Http\Middleware;

use App\Exceptions\Unauthenticated;
use App\Models\PanelUser;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApi
{
    /**
     * Validates the Bearer token and reloads the account on every request
     * (removed/deactivated accounts lose access immediately).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            throw new Unauthenticated;
        }

        $claims = app(JwtService::class)->decode($matches[1]);

        $usuario = PanelUser::query()
            ->where('username', $claims['sub'])
            ->where('ativo', true)
            ->first();

        if (! $usuario instanceof PanelUser) {
            throw new Unauthenticated;
        }

        $request->setUserResolver(fn () => $usuario);
        $request->attributes->set(PanelUser::class, $usuario);

        return $next($request);
    }
}
