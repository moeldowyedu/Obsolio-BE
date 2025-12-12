<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class MetricsController extends Controller
{
    /**
     * Expose metrics in Prometheus format
     */
    public function __invoke(): Response
    {
        $metrics = $this->collectMetrics();
        $output = $this->formatPrometheusMetrics($metrics);

        return response($output, 200)
            ->header('Content-Type', 'text/plain; version=0.0.4');
    }

    /**
     * Collect all system metrics
     */
    private function collectMetrics(): array
    {
        return [
            'system' => $this->getSystemMetrics(),
            'application' => $this->getApplicationMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'queue' => $this->getQueueMetrics(),
        ];
    }

    /**
     * Get system-level metrics
     */
    private function getSystemMetrics(): array
    {
        return [
            'memory_usage_bytes' => memory_get_usage(true),
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'cpu_load_average' => sys_getloadavg()[0] ?? 0,
        ];
    }

    /**
     * Get application metrics
     */
    private function getApplicationMetrics(): array
    {
        // Aggregate request metrics from cache
        $requestMetrics = $this->aggregateRequestMetrics();

        return [
            'http_requests_total' => $requestMetrics['total'] ?? 0,
            'http_request_duration_seconds' => $requestMetrics['avg_duration'] ?? 0,
            'http_requests_by_status' => $requestMetrics['by_status'] ?? [],
        ];
    }

    /**
     * Get database metrics
     */
    private function getDatabaseMetrics(): array
    {
        try {
            $connectionName = config('database.default');
            $tableCount = count(DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = ?', [config("database.connections.{$connectionName}.database")]));

            return [
                'database_connections_active' => 1, // TODO: Get actual connection count
                'database_tables_count' => $tableCount,
                'database_queries_total' => DB::getQueryLog() ? count(DB::getQueryLog()) : 0,
            ];
        } catch (\Exception $e) {
            return [
                'database_connections_active' => 0,
                'database_error' => 1,
            ];
        }
    }

    /**
     * Get cache metrics
     */
    private function getCacheMetrics(): array
    {
        try {
            $info = Redis::info('stats');

            return [
                'cache_hits_total' => $info['keyspace_hits'] ?? 0,
                'cache_misses_total' => $info['keyspace_misses'] ?? 0,
                'cache_keys_total' => Redis::dbSize(),
            ];
        } catch (\Exception $e) {
            return [
                'cache_error' => 1,
            ];
        }
    }

    /**
     * Get queue metrics
     */
    private function getQueueMetrics(): array
    {
        try {
            $queues = ['default', 'high', 'webhooks', 'notifications', 'workflows'];
            $metrics = [];

            foreach ($queues as $queue) {
                $size = Redis::llen("queues:{$queue}");
                $metrics["queue_{$queue}_size"] = $size;
            }

            // Failed jobs count
            $metrics['queue_failed_jobs_total'] = DB::table('failed_jobs')->count();

            return $metrics;
        } catch (\Exception $e) {
            return [
                'queue_error' => 1,
            ];
        }
    }

    /**
     * Aggregate request metrics from cache
     */
    private function aggregateRequestMetrics(): array
    {
        $currentMinute = date('YmdHi');
        $keys = [];

        // Get metrics from last 5 minutes
        for ($i = 0; $i < 5; $i++) {
            $time = date('YmdHi', strtotime("-{$i} minutes"));
            $keys[] = "metrics:requests:{$time}";
        }

        $total = 0;
        $totalDuration = 0;
        $byStatus = [];

        foreach ($keys as $key) {
            $data = json_decode(Cache::get($key, '[]'), true);

            foreach ($data as $metric) {
                $total++;
                $totalDuration += $metric['http_request_duration_milliseconds'] ?? 0;
                $status = $metric['http_request_status'] ?? 'unknown';
                $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            }
        }

        return [
            'total' => $total,
            'avg_duration' => $total > 0 ? $totalDuration / $total / 1000 : 0, // Convert to seconds
            'by_status' => $byStatus,
        ];
    }

    /**
     * Format metrics in Prometheus exposition format
     */
    private function formatPrometheusMetrics(array $metrics): string
    {
        $output = "# OBSOLIO Application Metrics\n\n";

        // System metrics
        $output .= "# HELP obsolio_memory_usage_bytes Current memory usage in bytes\n";
        $output .= "# TYPE obsolio_memory_usage_bytes gauge\n";
        $output .= "obsolio_memory_usage_bytes {$metrics['system']['memory_usage_bytes']}\n\n";

        $output .= "# HELP obsolio_memory_peak_bytes Peak memory usage in bytes\n";
        $output .= "# TYPE obsolio_memory_peak_bytes gauge\n";
        $output .= "obsolio_memory_peak_bytes {$metrics['system']['memory_peak_bytes']}\n\n";

        $output .= "# HELP obsolio_cpu_load_average Current CPU load average\n";
        $output .= "# TYPE obsolio_cpu_load_average gauge\n";
        $output .= "obsolio_cpu_load_average {$metrics['system']['cpu_load_average']}\n\n";

        // Application metrics
        $output .= "# HELP obsolio_http_requests_total Total HTTP requests\n";
        $output .= "# TYPE obsolio_http_requests_total counter\n";
        $output .= "obsolio_http_requests_total {$metrics['application']['http_requests_total']}\n\n";

        $output .= "# HELP obsolio_http_request_duration_seconds Average request duration\n";
        $output .= "# TYPE obsolio_http_request_duration_seconds gauge\n";
        $output .= "obsolio_http_request_duration_seconds {$metrics['application']['http_request_duration_seconds']}\n\n";

        // Database metrics
        foreach ($metrics['database'] as $key => $value) {
            $output .= "# TYPE obsolio_{$key} gauge\n";
            $output .= "obsolio_{$key} {$value}\n\n";
        }

        // Cache metrics
        foreach ($metrics['cache'] as $key => $value) {
            $output .= "# TYPE obsolio_{$key} gauge\n";
            $output .= "obsolio_{$key} {$value}\n\n";
        }

        // Queue metrics
        foreach ($metrics['queue'] as $key => $value) {
            $output .= "# TYPE obsolio_{$key} gauge\n";
            $output .= "obsolio_{$key} {$value}\n\n";
        }

        return $output;
    }
}
