<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Public liveness check; the SPA calls it to prove the API wiring works.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'API is up',
            'data' => [
                'status' => 'ok',
                'version' => 'v1',
                'time' => now()->toIso8601String(),
            ],
        ]);
    }
}
