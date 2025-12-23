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
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\SubscriptionPlanController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\MarketplaceController;
use App\Http\Controllers\Api\V1\BillingController;

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

    // =========================================================================
    // SUBSCRIPTION PLANS (Public - for Pricing Page)
    // =========================================================================
    Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index']);
    Route::get('/subscription-plans/{id}', [SubscriptionPlanController::class, 'show']);

    // =========================================================================
    // MARKETPLACE (Public - Browse Agents)
    // =========================================================================
    Route::get('/marketplace', [MarketplaceController::class, 'index']);
    Route::get('/marketplace/featured', [MarketplaceController::class, 'featured']);
    Route::get('/marketplace/categories', [MarketplaceController::class, 'categories']);
    Route::get('/marketplace/category/{category}', [MarketplaceController::class, 'byCategory']);
    Route::get('/marketplace/stats', [MarketplaceController::class, 'stats']);
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

        // =====================================================================
        // AUTH MANAGEMENT
        // =====================================================================
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

        // =====================================================================
        // SUBSCRIPTION MANAGEMENT
        // =====================================================================
        Route::prefix('subscriptions')->group(function () {
            Route::get('/current', [SubscriptionController::class, 'current']);
            Route::post('/', [SubscriptionController::class, 'store']);
            Route::put('/change-plan', [SubscriptionController::class, 'changePlan']);
            Route::post('/cancel', [SubscriptionController::class, 'cancel']);
            Route::post('/resume', [SubscriptionController::class, 'resume']);
            Route::get('/history', [SubscriptionController::class, 'history']);
        });

        // Subscription Plan Recommendations (Tenant Context)
        Route::get('/subscription-plans/recommendations', [SubscriptionPlanController::class, 'recommendations']);

        // =====================================================================
        // AGENT MANAGEMENT
        // =====================================================================
        Route::prefix('agents')->group(function () {
            Route::get('/', [AgentController::class, 'index']); // My installed agents
            Route::get('/{id}', [AgentController::class, 'show']);
            Route::post('/{id}/install', [AgentController::class, 'install']);
            Route::delete('/{id}/uninstall', [AgentController::class, 'uninstall']);
            Route::post('/{id}/toggle-status', [AgentController::class, 'toggleStatus']);
            Route::post('/{id}/record-usage', [AgentController::class, 'recordUsage']);
        });

        // =====================================================================
        // BILLING
        // =====================================================================
        Route::prefix('billing')->group(function () {
            // Invoices
            Route::get('/invoices', [BillingController::class, 'invoices']);
            Route::get('/invoices/{id}', [BillingController::class, 'showInvoice']);

            // Payment Methods
            Route::get('/payment-methods', [BillingController::class, 'paymentMethods']);
            Route::post('/payment-methods', [BillingController::class, 'addPaymentMethod']);
            Route::post('/payment-methods/{id}/set-default', [BillingController::class, 'setDefaultPaymentMethod']);
            Route::delete('/payment-methods/{id}', [BillingController::class, 'deletePaymentMethod']);
        });
    });
});

// =============================================================================
// SYSTEM ADMIN ROUTES (console.obsolio.com)
// =============================================================================

Route::prefix('v1/admin')->middleware(['jwt.auth', 'system_admin'])->group(function () {

    // =========================================================================
    // TENANT MANAGEMENT
    // =========================================================================
    Route::get('/tenants', [TenantController::class, 'indexAdmin']);
    Route::get('/tenants/{id}', [TenantController::class, 'showAdmin']);
    Route::put('/tenants/{id}', [TenantController::class, 'updateAdmin']);
    Route::delete('/tenants/{id}', [TenantController::class, 'deleteAdmin']);
    Route::post('/tenants/{id}/suspend', [TenantController::class, 'suspend']);
    Route::post('/tenants/{id}/activate', [TenantController::class, 'activate']);

    // =========================================================================
    // USER MANAGEMENT
    // =========================================================================
    Route::get('/users', [AdminController::class, 'listUsers']);
    Route::get('/users/{id}', [AdminController::class, 'getUser']);
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);

    // =========================================================================
    // SUBSCRIPTION PLANS MANAGEMENT
    // =========================================================================
    Route::get('/subscription-plans', [AdminController::class, 'listPlans']);
    Route::post('/subscription-plans', [AdminController::class, 'createPlan']);
    Route::put('/subscription-plans/{id}', [AdminController::class, 'updatePlan']);
    Route::delete('/subscription-plans/{id}', [AdminController::class, 'deletePlan']);

    // =========================================================================
    // AGENTS MANAGEMENT
    // =========================================================================
    Route::get('/agents', [AdminController::class, 'listAgents']);
    Route::post('/agents', [AdminController::class, 'createAgent']);
    Route::put('/agents/{id}', [AdminController::class, 'updateAgent']);
    Route::delete('/agents/{id}', [AdminController::class, 'deleteAgent']);

    // =========================================================================
    // ANALYTICS & REPORTS
    // =========================================================================
    Route::get('/analytics/overview', [AdminController::class, 'analyticsOverview']);
    Route::get('/analytics/revenue', [AdminController::class, 'revenueAnalytics']);
    Route::get('/analytics/agents', [AdminController::class, 'agentAnalytics']);

    // =========================================================================
    // ACTIVITY LOGS
    // =========================================================================
    Route::get('/activity-logs', [AdminController::class, 'activityLogs']);
    Route::get('/impersonation-logs', [AdminController::class, 'impersonationLogs']);
});