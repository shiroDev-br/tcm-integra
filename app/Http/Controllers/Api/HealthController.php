<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Public liveness endpoint.
     */
    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
