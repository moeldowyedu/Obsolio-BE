<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\UserActivityController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TenantSetupController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Controllers\Api\Auth\VerificationController;

/*
|--------------------------------------------------------------------------
| API Routes - Multi-Tenancy Structure
|--------------------------------------------------------------------------
*/

// =============================================================================
// CENTRAL DOMAIN ROUTES (obsolio.com, localhost)
// =============================================================================

Route::prefix('v1')->group(function () {

    // =========================================================================
    // API WELCOME (No Auth)
    // =========================================================================
    Route::get('/', function () {
        return response()->json([
            'message' => 'Welcome to OBSOLIO API v1',
            'version' => '1.0.0',
            'documentation' => url('/api/documentation'),
        ]);
    });

    // =========================================================================
    // EMAIL VERIFICATION (Public - No Auth Required)
    // =========================================================================
    Route::get('verify-email/{id}/{hash}', [VerificationController::class, 'verify'])
        ->name('verification.verify');
    Route::post('verify-email/{id}/{hash}', [VerificationController::class, 'verify']);
    Route::post('resend-verification', [VerificationController::class, 'resend']);

    // =========================================================================
    // PUBLIC AUTHENTICATION (Central Domain Only)
    // =========================================================================
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/auth/check-subdomain', [AuthController::class, 'checkSubdomain']);
    Route::post('/auth/lookup-tenant', [AuthController::class, 'lookupTenant'])
        ->withoutMiddleware(['tenancy.domain', 'tenancy.prevent_central', 'tenancy.header']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('jwt.auth');
    Route::get('/tenants/check-availability/{subdomain}', [AuthController::class, 'checkAvailability']);

    // =========================================================================
    // TENANT VERIFICATION HELPERS (Public)
    // =========================================================================
    Route::get('/tenants/find-by-subdomain/{subdomain}', [TenantController::class, 'findBySubdomain']);
    Route::post('/tenants/resend-verification/{subdomain}', [TenantController::class, 'resendVerification']);
});

// =============================================================================
// TENANT ROUTES (subdomain.obsolio.com)
// =============================================================================

Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('v1')->group(function () {

    // =========================================================================
    // TENANT AUTHENTICATION (Requires JWT + Tenant Context)
    // =========================================================================
    Route::middleware(['jwt.auth', 'tenant.status'])->group(function () {

        // Auth Management
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        // =====================================================================
        // TENANT SETUP (Post-Registration)
        // =====================================================================
        Route::get('/tenant-setup/status', [TenantSetupController::class, 'checkSetupStatus']);
        Route::post('/tenant-setup/organization', [TenantSetupController::class, 'setupOrganization']);
        Route::post('/tenant-setup/personal', [TenantSetupController::class, 'setupPersonal']);

        // =====================================================================
        // TENANT MANAGEMENT
        // =====================================================================
        Route::get('/tenant', [TenantController::class, 'show']);
        Route::put('/tenant', [TenantController::class, 'update']);
        Route::get('/tenants/{tenant}', [TenantController::class, 'show']);
        Route::put('/tenants/{tenant}', [TenantController::class, 'update']);

        // =====================================================================
        // DASHBOARD & ANALYTICS
        // =====================================================================
        Route::get('/dashboard/stats', [DashboardController::class, 'dashboardStats']);

        // =====================================================================
        // ORGANIZATIONS & HIERARCHY
        // =====================================================================
        Route::apiResource('organizations', OrganizationController::class);
        Route::get('/organizations/{id}/dashboard', [OrganizationController::class, 'dashboard']);

        // =====================================================================
        // ACTIVITIES & MONITORING
        // =====================================================================
        Route::get('/activities', [UserActivityController::class, 'index']);
        Route::get('/activities/{id}', [UserActivityController::class, 'show']);
        Route::get('/activities/user/{userId}', [UserActivityController::class, 'byUser']);
        Route::get('/sessions', [UserActivityController::class, 'sessions']);
        Route::get('/sessions/active', [UserActivityController::class, 'activeSessions']);
        Route::post('/sessions/{id}/terminate', [UserActivityController::class, 'terminateSession']);
    });
});

// =============================================================================
// SYSTEM ADMIN ROUTES (console.obsolio.com)
// =============================================================================

Route::prefix('v1/admin')->middleware(['jwt.auth', 'system_admin'])->group(function () {
    Route::get('/tenants', [TenantController::class, 'indexAdmin']);
    Route::put('/tenants/{id}', [TenantController::class, 'updateAdmin']);
    Route::delete('/tenants/{id}', [TenantController::class, 'deleteAdmin']);
    Route::post('/tenants/{id}/suspend', [TenantController::class, 'suspend']);
    Route::post('/tenants/{id}/activate', [TenantController::class, 'activate']);
});