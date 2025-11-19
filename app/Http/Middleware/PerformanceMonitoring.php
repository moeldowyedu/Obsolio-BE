<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitoring
{
    /**
     * Performance thresholds (in milliseconds)
     */
    private const SLOW_REQUEST_THRESHOLD = 1000; // 1 second
    private const VERY_SLOW_REQUEST_THRESHOLD = 3000; // 3 seconds

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Add request ID for distributed tracing
        $requestId = $this->generateRequestId();
        $request->headers->set('X-Request-ID', $requestId);

        // Process request
        $response = $next($request);

        // Calculate metrics
        $executionTime = (microtime(true) - $startTime) * 1000; // in milliseconds
        $memoryUsage = memory_get_usage(true) - $startMemory;
        $memoryPeak = memory_get_peak_usage(true);

        // Add performance headers
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Execution-Time', round($executionTime, 2));
        $response->headers->set('X-Memory-Usage', $this->formatBytes($memoryUsage));

        // Log slow requests
        if ($executionTime > self::SLOW_REQUEST_THRESHOLD) {
            $level = $executionTime > self::VERY_SLOW_REQUEST_THRESHOLD ? 'warning' : 'info';

            Log::log($level, 'Slow request detected', [
                'request_id' => $requestId,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'execution_time_ms' => round($executionTime, 2),
                'memory_usage' => $this->formatBytes($memoryUsage),
                'memory_peak' => $this->formatBytes($memoryPeak),
                'user_id' => $request->user()?->id,
                'tenant_id' => tenant('id'),
                'ip' => $request->ip(),
                'status_code' => $response->getStatusCode(),
            ]);
        }

        // Store metrics for Prometheus (if enabled)
        $this->recordMetrics($request, $response, $executionTime, $memoryUsage);

        return $response;
    }

    /**
     * Generate unique request ID
     */
    private function generateRequestId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Format bytes to human-readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }

    /**
     * Record metrics for monitoring systems
     */
    private function recordMetrics(Request $request, Response $response, float $executionTime, int $memoryUsage): void
    {
        // TODO: Implement Prometheus metrics collection
        // This would push metrics to Prometheus pushgateway or expose them via /metrics endpoint

        $metrics = [
            'http_request_duration_milliseconds' => $executionTime,
            'http_request_memory_bytes' => $memoryUsage,
            'http_requests_total' => 1,
            'http_request_status' => $response->getStatusCode(),
            'labels' => [
                'method' => $request->method(),
                'route' => $request->route()?->getName() ?? 'unknown',
                'status' => $response->getStatusCode(),
                'tenant' => tenant('id') ?? 'none',
            ],
        ];

        // Store in cache for metrics endpoint
        $this->storeMetricsInCache($metrics);
    }

    /**
     * Store metrics in cache for /metrics endpoint
     */
    private function storeMetricsInCache(array $metrics): void
    {
        // Store recent metrics in Redis for aggregation
        $key = 'metrics:requests:' . date('YmdHi'); // Per minute
        $existing = json_decode(cache()->get($key, '[]'), true);
        $existing[] = $metrics;
        cache()->put($key, json_encode($existing), 300); // 5 minutes TTL
    }
}
