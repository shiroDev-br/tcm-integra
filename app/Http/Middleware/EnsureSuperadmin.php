<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Exceptions\Forbidden;
use App\Models\PanelUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperadmin
{
    /**
     * Only the superadmin may manage panel accounts (403 without telling the role).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->attributes->get(PanelUser::class);

        if (! $usuario instanceof PanelUser || $usuario->role !== Role::SuperAdmin) {
            throw new Forbidden;
        }

        return $next($request);
    }
}
