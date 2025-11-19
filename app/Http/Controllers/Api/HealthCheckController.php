<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    /**
     * Basic health check (lightweight - for load balancer)
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Detailed health check with all dependencies
     */
    public function detailed(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        $allHealthy = collect($checks)->every(fn($check) => $check['healthy']);

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
        ], $allHealthy ? 200 : 503);
    }

    /**
     * Readiness probe (for Kubernetes)
     */
    public function ready(): JsonResponse
    {
        $ready = $this->checkDatabase()['healthy'] &&
                 $this->checkRedis()['healthy'];

        return response()->json([
            'ready' => $ready,
            'timestamp' => now()->toIso8601String(),
        ], $ready ? 200 : 503);
    }

    /**
     * Liveness probe (for Kubernetes)
     */
    public function alive(): JsonResponse
    {
        return response()->json([
            'alive' => true,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Check database connection
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $latency = $this->measureLatency(fn() => DB::select('SELECT 1'));

            return [
                'healthy' => true,
                'latency_ms' => round($latency, 2),
                'connection' => DB::connection()->getName(),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Redis connection
     */
    private function checkRedis(): array
    {
        try {
            $latency = $this->measureLatency(fn() => Redis::ping());

            return [
                'healthy' => true,
                'latency_ms' => round($latency, 2),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache functionality
     */
    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . time();
            $value = 'test';

            Cache::put($key, $value, 10);
            $retrieved = Cache::get($key);
            Cache::forget($key);

            return [
                'healthy' => $retrieved === $value,
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue connection
     */
    private function checkQueue(): array
    {
        try {
            $connection = config('queue.default');
            $size = Redis::llen('queues:default');

            return [
                'healthy' => true,
                'connection' => $connection,
                'pending_jobs' => $size,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check storage accessibility
     */
    private function checkStorage(): array
    {
        try {
            $path = storage_path('app/health_check.txt');
            file_put_contents($path, 'test');
            $content = file_get_contents($path);
            unlink($path);

            return [
                'healthy' => $content === 'test',
                'disk' => config('filesystems.default'),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Measure operation latency in milliseconds
     */
    private function measureLatency(callable $operation): float
    {
        $start = microtime(true);
        $operation();
        return (microtime(true) - $start) * 1000;
    }
}
