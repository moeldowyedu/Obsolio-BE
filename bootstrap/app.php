<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // JWT authentication - no need for Sanctum middleware
    
        // Register middleware aliases
        $middleware->alias([
            'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
            'jwt.auth' => \Tymon\JWTAuth\Http\Middleware\Authenticate::class,
            'jwt.refresh' => \Tymon\JWTAuth\Http\Middleware\RefreshToken::class,
            'tenant.status' => \App\Http\Middleware\CheckTenantStatus::class,
            'tenancy.header' => \App\Http\Middleware\InitializeTenancyByHeader::class,
            'system_admin' => \App\Http\Middleware\EnsureIsSystemAdmin::class,
            'check.subdomain' => \App\Http\Middleware\CheckSubdomain::class,

            // ✅ Stancl's Built-in Middleware
            'tenancy.domain' => \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            'tenancy.prevent_central' => \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
        ]);

        // Add global middleware
        $middleware->append(\App\Http\Middleware\CDNHeaders::class);
        $middleware->append(\App\Http\Middleware\CompressResponse::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
