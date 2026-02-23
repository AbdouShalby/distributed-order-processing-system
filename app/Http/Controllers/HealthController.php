<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    /**
     * GET /api/health
     */
    public function __invoke(): JsonResponse
    {
        $services = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $allHealthy = ! in_array('disconnected', $services);

        return response()->json([
            'status' => $allHealthy ? 'ok' : 'degraded',
            'services' => $services,
            'timestamp' => now()->toIso8601String(),
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();

            return 'connected';
        } catch (\Throwable) {
            return 'disconnected';
        }
    }

    private function checkRedis(): string
    {
        try {
            Redis::ping();

            return 'connected';
        } catch (\Throwable) {
            return 'disconnected';
        }
    }
}
