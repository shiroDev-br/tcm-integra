<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Unauthenticated;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\PanelUser;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    /**
     * Lists the panel accounts (never includes the password).
     */
    public function index(): JsonResponse
    {
        return UserResource::collection($this->users->list())->response();
    }

    /**
     * Creates an admin account (the role never comes from the client).
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        return (new UserResource($this->users->createAdmin($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Toggles `ativo` and/or changes the password (superadmin only).
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        return new JsonResponse($this->users->update($this->operator($request), $id, $request->validated()));
    }

    /**
     * Removes an admin account (never self, never superadmin).
     */
    public function destroy(Request $request, int $id): Response
    {
        $this->users->delete($this->operator($request), $id);

        return response()->noContent();
    }

    private function operator(Request $request): PanelUser
    {
        $usuario = $request->attributes->get(PanelUser::class);

        return $usuario instanceof PanelUser
            ? $usuario
            : throw new Unauthenticated;
    }
}
