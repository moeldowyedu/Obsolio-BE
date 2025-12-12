<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Structured Logger for better log aggregation and analysis
 *
 * Provides consistent, structured logging across the application
 * with context enrichment and proper categorization.
 */
class StructuredLogger
{
    /**
     * Log levels
     */
    private const LEVEL_DEBUG = 'debug';
    private const LEVEL_INFO = 'info';
    private const LEVEL_NOTICE = 'notice';
    private const LEVEL_WARNING = 'warning';
    private const LEVEL_ERROR = 'error';
    private const LEVEL_CRITICAL = 'critical';
    private const LEVEL_ALERT = 'alert';
    private const LEVEL_EMERGENCY = 'emergency';

    /**
     * Log categories for better organization
     */
    public const CATEGORY_HTTP = 'http';
    public const CATEGORY_DATABASE = 'database';
    public const CATEGORY_QUEUE = 'queue';
    public const CATEGORY_CACHE = 'cache';
    public const CATEGORY_AUTH = 'auth';
    public const CATEGORY_SECURITY = 'security';
    public const CATEGORY_BUSINESS = 'business';
    public const CATEGORY_INTEGRATION = 'integration';
    public const CATEGORY_PERFORMANCE = 'performance';

    /**
     * Log with automatic context enrichment
     */
    public static function log(
        string $level,
        string $message,
        array $context = [],
        ?string $category = null
    ): void {
        $enrichedContext = self::enrichContext($context, $category);

        Log::log($level, $message, $enrichedContext);
    }

    /**
     * Info level log
     */
    public static function info(string $message, array $context = [], ?string $category = null): void
    {
        self::log(self::LEVEL_INFO, $message, $context, $category);
    }

    /**
     * Error level log
     */
    public static function error(string $message, array $context = [], ?string $category = null): void
    {
        self::log(self::LEVEL_ERROR, $message, $context, $category);
    }

    /**
     * Warning level log
     */
    public static function warning(string $message, array $context = [], ?string $category = null): void
    {
        self::log(self::LEVEL_WARNING, $message, $context, $category);
    }

    /**
     * Debug level log
     */
    public static function debug(string $message, array $context = [], ?string $category = null): void
    {
        self::log(self::LEVEL_DEBUG, $message, $context, $category);
    }

    /**
     * Critical level log
     */
    public static function critical(string $message, array $context = [], ?string $category = null): void
    {
        self::log(self::LEVEL_CRITICAL, $message, $context, $category);
    }

    /**
     * Log HTTP request
     */
    public static function httpRequest(
        string $method,
        string $url,
        int $statusCode,
        float $duration,
        array $additionalContext = []
    ): void {
        self::info('HTTP request processed', array_merge([
            'http.method' => $method,
            'http.url' => $url,
            'http.status_code' => $statusCode,
            'http.duration_ms' => round($duration, 2),
        ], $additionalContext), self::CATEGORY_HTTP);
    }

    /**
     * Log database query
     */
    public static function databaseQuery(
        string $query,
        float $duration,
        ?int $rows = null,
        array $bindings = []
    ): void {
        $level = $duration > 1000 ? self::LEVEL_WARNING : self::LEVEL_DEBUG;

        self::log($level, 'Database query executed', [
            'db.query' => $query,
            'db.duration_ms' => round($duration, 2),
            'db.rows' => $rows,
            'db.bindings_count' => count($bindings),
            'slow_query' => $duration > 1000,
        ], self::CATEGORY_DATABASE);
    }

    /**
     * Log queue job
     */
    public static function queueJob(
        string $jobName,
        string $status,
        ?float $duration = null,
        ?string $error = null
    ): void {
        $level = $status === 'failed' ? self::LEVEL_ERROR : self::LEVEL_INFO;

        self::log($level, "Queue job {$status}", [
            'queue.job' => $jobName,
            'queue.status' => $status,
            'queue.duration_ms' => $duration ? round($duration, 2) : null,
            'queue.error' => $error,
        ], self::CATEGORY_QUEUE);
    }

    /**
     * Log cache operation
     */
    public static function cacheOperation(
        string $operation,
        string $key,
        bool $hit,
        ?float $duration = null
    ): void {
        self::debug("Cache {$operation}", [
            'cache.operation' => $operation,
            'cache.key' => $key,
            'cache.hit' => $hit,
            'cache.duration_ms' => $duration ? round($duration, 2) : null,
        ], self::CATEGORY_CACHE);
    }

    /**
     * Log authentication event
     */
    public static function authEvent(
        string $event,
        ?string $userId = null,
        bool $success = true,
        ?string $reason = null
    ): void {
        $level = $success ? self::LEVEL_INFO : self::LEVEL_WARNING;

        self::log($level, "Authentication: {$event}", [
            'auth.event' => $event,
            'auth.user_id' => $userId,
            'auth.success' => $success,
            'auth.reason' => $reason,
        ], self::CATEGORY_AUTH);
    }

    /**
     * Log security event
     */
    public static function securityEvent(
        string $event,
        string $severity = 'medium',
        array $details = []
    ): void {
        $level = match ($severity) {
            'critical' => self::LEVEL_CRITICAL,
            'high' => self::LEVEL_ERROR,
            'medium' => self::LEVEL_WARNING,
            default => self::LEVEL_INFO,
        };

        self::log($level, "Security: {$event}", array_merge([
            'security.event' => $event,
            'security.severity' => $severity,
        ], $details), self::CATEGORY_SECURITY);
    }

    /**
     * Log business event
     */
    public static function businessEvent(
        string $event,
        array $data = []
    ): void {
        self::info("Business event: {$event}", array_merge([
            'business.event' => $event,
        ], $data), self::CATEGORY_BUSINESS);
    }

    /**
     * Log external integration call
     */
    public static function integrationCall(
        string $service,
        string $operation,
        bool $success,
        ?float $duration = null,
        ?string $error = null
    ): void {
        $level = $success ? self::LEVEL_INFO : self::LEVEL_ERROR;

        self::log($level, "Integration: {$service} - {$operation}", [
            'integration.service' => $service,
            'integration.operation' => $operation,
            'integration.success' => $success,
            'integration.duration_ms' => $duration ? round($duration, 2) : null,
            'integration.error' => $error,
        ], self::CATEGORY_INTEGRATION);
    }

    /**
     * Log performance metric
     */
    public static function performance(
        string $metric,
        float $value,
        string $unit = 'ms',
        array $tags = []
    ): void {
        self::info("Performance metric: {$metric}", array_merge([
            'performance.metric' => $metric,
            'performance.value' => round($value, 2),
            'performance.unit' => $unit,
        ], $tags), self::CATEGORY_PERFORMANCE);
    }

    /**
     * Enrich context with standard fields
     */
    private static function enrichContext(array $context, ?string $category): array
    {
        $enriched = [
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'application' => 'obsolio',
            'version' => config('app.version', '1.0.0'),
        ];

        if ($category) {
            $enriched['category'] = $category;
        }

        // Add trace context if available
        if ($traceId = TracingService::getTraceId()) {
            $enriched['trace_id'] = $traceId;
            $enriched['span_id'] = TracingService::getSpanId();
        }

        // Add tenant context
        if ($tenantId = tenant('id')) {
            $enriched['tenant_id'] = $tenantId;
        }

        // Add user context if authenticated
        if (auth()->check()) {
            $enriched['user_id'] = auth()->id();
        }

        // Add request context if in HTTP context
        if (app()->runningInConsole() === false && request()) {
            $enriched['request_id'] = request()->header('X-Request-ID');
            $enriched['ip'] = request()->ip();
            $enriched['user_agent'] = request()->userAgent();
        }

        return array_merge($enriched, $context);
    }
}
