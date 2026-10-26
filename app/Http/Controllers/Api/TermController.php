<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TermRequest;
use App\Http\Resources\TermResource;
use App\Services\TermService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TermController extends Controller
{
    public function __construct(
        private readonly TermService $terms,
    ) {}

    /**
     * Lists the monitored search terms (id asc).
     */
    public function index(Request $request): JsonResponse
    {
        return TermResource::collection(
            $this->terms->list($request->boolean('somente_ativos'))
        )->response();
    }

    /**
     * Registers a term (409 when it already exists).
     */
    public function store(TermRequest $request): JsonResponse
    {
        return (new TermResource($this->terms->create($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Replaces a term (text, type, active flag and variations).
     */
    public function update(TermRequest $request, int $id): JsonResponse
    {
        return (new TermResource($this->terms->update($id, $request->validated())))
            ->response();
    }

    /**
     * Deletes a term (occurrences cascade).
     */
    public function destroy(int $id): Response
    {
        $this->terms->delete($id);

        return response()->noContent();
    }
}
