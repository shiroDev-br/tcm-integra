<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Unauthenticated;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\PanelUser;
use App\Services\JwtService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserService $users,
        private readonly JwtService $jwt,
    ) {}

    /**
     * Authenticates and issues the token (single message for any failure).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = $this->users->authenticate($request->validated());

        return response()->json(
            $this->jwt->create((string) $usuario->username, $usuario->role)
        );
    }

    /**
     * Identity of the presented token — only username and role.
     */
    public function me(Request $request): JsonResponse
    {
        $usuario = $request->attributes->get(PanelUser::class);

        if (! $usuario instanceof PanelUser) {
            throw new Unauthenticated;
        }

        return response()->json([
            'username' => (string) $usuario->username,
            'role' => $usuario->role->value,
        ]);
    }
}
