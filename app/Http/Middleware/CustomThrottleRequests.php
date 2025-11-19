<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CustomThrottleRequests
{
    /**
     * The rate limiter instance.
     */
    protected RateLimiter $limiter;

    /**
     * Create a new request throttler.
     */
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $tier = 'default'): Response
    {
        $key = $this->resolveRequestSignature($request);

        [$maxAttempts, $decayMinutes] = $this->resolveRateLimits($tier, $request);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            Log::warning('Rate limit exceeded', [
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'tier' => $tier,
                'retry_after' => $retryAfter,
            ]);

            return $this->buildRateLimitResponse($maxAttempts, $retryAfter);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * Resolve rate limits based on tier and user
     */
    protected function resolveRateLimits(string $tier, Request $request): array
    {
        $user = $request->user();

        // Enterprise tier - highest limits
        if ($tier === 'enterprise' || $user?->hasRole('enterprise')) {
            return [10000, 1]; // 10k per minute
        }

        // Professional tier
        if ($tier === 'professional' || $user?->hasRole('professional')) {
            return [1000, 1]; // 1k per minute
        }

        // Free tier or default
        if ($tier === 'free') {
            return [100, 1]; // 100 per minute
        }

        // Default tier
        return [500, 1]; // 500 per minute
    }

    /**
     * Resolve request signature.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();

        if ($user) {
            // Rate limit by user ID (more generous for authenticated users)
            return 'throttle:user:' . $user->id;
        }

        // Rate limit by IP for unauthenticated requests
        return 'throttle:ip:' . $request->ip();
    }

    /**
     * Calculate remaining attempts.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->limiter->attempts($key));
    }

    /**
     * Build rate limit exceeded response.
     */
    protected function buildRateLimitResponse(int $maxAttempts, int $retryAfter): Response
    {
        return response()->json([
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $retryAfter,
            'limit' => $maxAttempts,
        ], 429)->header('Retry-After', $retryAfter)
            ->header('X-RateLimit-Limit', $maxAttempts)
            ->header('X-RateLimit-Remaining', 0);
    }

    /**
     * Add rate limit headers to response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        return $response
            ->header('X-RateLimit-Limit', $maxAttempts)
            ->header('X-RateLimit-Remaining', $remainingAttempts);
    }
}
