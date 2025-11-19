<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CircuitBreaker
{
    /**
     * Circuit states
     */
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    /**
     * Configuration
     */
    private int $failureThreshold;
    private int $successThreshold;
    private int $timeout;
    private string $serviceName;

    /**
     * Create a new circuit breaker instance
     */
    public function __construct(
        string $serviceName,
        int $failureThreshold = 5,
        int $successThreshold = 2,
        int $timeout = 60
    ) {
        $this->serviceName = $serviceName;
        $this->failureThreshold = $failureThreshold;
        $this->successThreshold = $successThreshold;
        $this->timeout = $timeout;
    }

    /**
     * Execute operation with circuit breaker protection
     */
    public function call(callable $operation, ?callable $fallback = null): mixed
    {
        $state = $this->getState();

        // If circuit is open, return fallback or throw exception
        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptReset()) {
                $this->setState(self::STATE_HALF_OPEN);
                Log::info("Circuit breaker half-open", ['service' => $this->serviceName]);
            } else {
                Log::warning("Circuit breaker open - request blocked", [
                    'service' => $this->serviceName,
                ]);

                if ($fallback) {
                    return $fallback();
                }

                throw new \RuntimeException("Service {$this->serviceName} is currently unavailable (circuit open)");
            }
        }

        try {
            $result = $operation();
            $this->recordSuccess();
            return $result;

        } catch (\Exception $e) {
            $this->recordFailure();

            Log::error("Circuit breaker recorded failure", [
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'failures' => $this->getFailureCount(),
            ]);

            if ($fallback) {
                return $fallback();
            }

            throw $e;
        }
    }

    /**
     * Record successful operation
     */
    private function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $successCount = $this->incrementSuccessCount();

            if ($successCount >= $this->successThreshold) {
                $this->setState(self::STATE_CLOSED);
                $this->resetCounters();
                Log::info("Circuit breaker closed", ['service' => $this->serviceName]);
            }
        } elseif ($state === self::STATE_CLOSED) {
            // Reset failure count on success
            $this->resetFailureCount();
        }
    }

    /**
     * Record failed operation
     */
    private function recordFailure(): void
    {
        $failureCount = $this->incrementFailureCount();

        if ($failureCount >= $this->failureThreshold) {
            $this->setState(self::STATE_OPEN);
            $this->setOpenTimestamp();

            Log::warning("Circuit breaker opened", [
                'service' => $this->serviceName,
                'failures' => $failureCount,
            ]);
        }
    }

    /**
     * Check if circuit should attempt reset
     */
    private function shouldAttemptReset(): bool
    {
        $openTime = Cache::get($this->getKey('open_timestamp'));

        if (!$openTime) {
            return true;
        }

        return (time() - $openTime) >= $this->timeout;
    }

    /**
     * Get current circuit state
     */
    private function getState(): string
    {
        return Cache::get($this->getKey('state'), self::STATE_CLOSED);
    }

    /**
     * Set circuit state
     */
    private function setState(string $state): void
    {
        Cache::put($this->getKey('state'), $state, 3600);
    }

    /**
     * Get failure count
     */
    private function getFailureCount(): int
    {
        return (int) Cache::get($this->getKey('failures'), 0);
    }

    /**
     * Increment failure count
     */
    private function incrementFailureCount(): int
    {
        $key = $this->getKey('failures');
        $count = $this->getFailureCount() + 1;
        Cache::put($key, $count, 3600);
        return $count;
    }

    /**
     * Reset failure count
     */
    private function resetFailureCount(): void
    {
        Cache::forget($this->getKey('failures'));
    }

    /**
     * Increment success count
     */
    private function incrementSuccessCount(): int
    {
        $key = $this->getKey('successes');
        $count = ((int) Cache::get($key, 0)) + 1;
        Cache::put($key, $count, 3600);
        return $count;
    }

    /**
     * Reset all counters
     */
    private function resetCounters(): void
    {
        Cache::forget($this->getKey('failures'));
        Cache::forget($this->getKey('successes'));
        Cache::forget($this->getKey('open_timestamp'));
    }

    /**
     * Set timestamp when circuit opened
     */
    private function setOpenTimestamp(): void
    {
        Cache::put($this->getKey('open_timestamp'), time(), 3600);
    }

    /**
     * Get cache key for circuit breaker data
     */
    private function getKey(string $suffix): string
    {
        return "circuit_breaker:{$this->serviceName}:{$suffix}";
    }

    /**
     * Get circuit breaker status
     */
    public function getStatus(): array
    {
        return [
            'service' => $this->serviceName,
            'state' => $this->getState(),
            'failure_count' => $this->getFailureCount(),
            'failure_threshold' => $this->failureThreshold,
            'timeout' => $this->timeout,
        ];
    }

    /**
     * Manually reset circuit breaker
     */
    public function reset(): void
    {
        $this->setState(self::STATE_CLOSED);
        $this->resetCounters();
        Log::info("Circuit breaker manually reset", ['service' => $this->serviceName]);
    }
}
