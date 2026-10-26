<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Domain error of the TCM system: responds in the original API shape
 * (`{"detail": "..."}`), with a generic mask in production when applicable.
 */
abstract class DomainError extends Exception
{
    protected int $status = 500;

    /** Message exposed in production when the original one is sensitive. */
    protected ?string $generic = null;

    /** @var array<string, string> Extra response headers (e.g. WWW-Authenticate). */
    protected array $headers = [];

    public function render(Request $request): JsonResponse
    {
        $detail = $this->getMessage();

        if ($this->generic !== null && config('tcm.environment') === 'production') {
            $detail = $this->generic;
        }

        return response()->json(['detail' => $detail], $this->status, $this->headers);
    }
}
