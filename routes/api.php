<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\WorkflowController;
use App\Http\Controllers\Api\V1\MarketplaceController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\UserActivityController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\HealthCheckController;
use App\Http\Controllers\Api\V1\MetricsController;
use App\Http\Controllers\Api\V1\TenantSetupController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| API Routes - Multi-Tenancy Structure
|--------------------------------------------------------------------------
|
| Routes are separated into:
| 1. CENTRAL ROUTES - Accessed from obsolio.com (public, no tenant context)
| 2. TENANT ROUTES - Accessed from {subdomain}.obsolio.com (tenant-specific)
|
*/

// =============================================================================
// CENTRAL DOMAIN ROUTES (obsolio.com, localhost)
// =============================================================================
// These routes work ONLY on central domain (no subdomain)
// Used for: Registration, Login, Public Marketplace

Route::prefix('v1')->group(function () {

    // =========================================================================
    // PUBLIC AUTHENTICATION (Central Domain Only)
    // =========================================================================
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/auth/check-subdomain', [AuthController::class, 'checkSubdomain']); // Added check-subdomain
    Route::get('/tenants/check-availability/{subdomain}', [AuthController::class, 'checkAvailability']); // Added checkAvailability

    // Email Verification Routes (No Auth Required)
    Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
        ->middleware(['signed'])
        ->name('verification.verify');
    Route::post('/auth/email/resend', [AuthController::class, 'resendVerification'])
        ->name('verification.resend');

    // =========================================================================
    // PUBLIC MARKETPLACE (Central Domain Only)
    // =========================================================================
    Route::get('/marketplace', [MarketplaceController::class, 'index']);
    Route::get('/marketplace/{id}', [MarketplaceController::class, 'show']);
    Route::get('/marketplace/search', [MarketplaceController::class, 'search']);
    Route::get('/marketplace/categories', [MarketplaceController::class, 'categories']);

    // =========================================================================
    // HEALTH CHECKS & MONITORING (No Auth)
    // =========================================================================
    Route::get('/health', [HealthCheckController::class, 'index']);
    Route::get('/health/detailed', [HealthCheckController::class, 'detailed']);
    Route::get('/health/ready', [HealthCheckController::class, 'ready']);
    Route::get('/health/alive', [HealthCheckController::class, 'alive']);
    Route::get('/metrics', MetricsController::class);

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
});

// =============================================================================
// TENANT ROUTES (subdomain.obsolio.com)
// =============================================================================
// These routes work ONLY on tenant subdomains (e.g., acme.obsolio.com)
// Middleware automatically initializes tenant context based on subdomain

Route::middleware([
    InitializeTenancyByDomain::class,           // ✅ Initialize tenant from subdomain
    PreventAccessFromCentralDomains::class,     // ✅ Block access from central domain
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

        Route::apiResource('branches', BranchController::class);
        Route::get('/organizations/{organizationId}/branches', [BranchController::class, 'byOrganization']);

        Route::apiResource('departments', DepartmentController::class);
        Route::get('/organizations/{organizationId}/departments', [DepartmentController::class, 'byOrganization']);
        Route::get('/branches/{branchId}/departments', [DepartmentController::class, 'byBranch']);

        Route::apiResource('projects', ProjectController::class);
        Route::get('/departments/{departmentId}/projects', [ProjectController::class, 'byDepartment']);
        Route::put('/projects/{id}/status', [ProjectController::class, 'updateStatus']);

        Route::apiResource('teams', TeamController::class);
        Route::post('/teams/{id}/members', [TeamController::class, 'addMember']);
        Route::delete('/teams/{id}/members/{userId}', [TeamController::class, 'removeMember']);

        // =====================================================================
        // AGENTS & WORKFLOWS
        // =====================================================================
        Route::apiResource('agents', AgentController::class);
        Route::post('/agents/{id}/execute', [AgentController::class, 'execute']);
        Route::get('/agents/{id}/executions', [AgentController::class, 'executions']);
        Route::get('/analytics/agents', [AgentController::class, 'analytics']);

        Route::apiResource('workflows', WorkflowController::class);
        Route::post('/workflows/{id}/execute', [WorkflowController::class, 'execute']);
        Route::get('/workflows/{id}/executions', [WorkflowController::class, 'executions']);

        // =====================================================================
        // INTEGRATIONS
        // =====================================================================
        // Route::apiResource('api-keys', IntegrationController::class);
        // Route::post('/api-keys/{id}/regenerate', [IntegrationController::class, 'regenerate']);

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
        // BILLING & SUBSCRIPTIONS
        // =====================================================================
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::get('/subscriptions/current', [SubscriptionController::class, 'current']);
        Route::post('/subscriptions/subscribe', [SubscriptionController::class, 'subscribe']);
        Route::post('/subscriptions/cancel', [SubscriptionController::class, 'cancel']);
        Route::get('/subscriptions/usage', [SubscriptionController::class, 'usage']);

        // =====================================================================
        // TENANT MARKETPLACE (Authenticated)
        // =====================================================================
        Route::post('/marketplace/{id}/purchase', [MarketplaceController::class, 'purchase']);
        Route::get('/marketplace/my-purchases', [MarketplaceController::class, 'myPurchases']);
    });
});

// =============================================================================
// SYSTEM ADMIN ROUTES (console.obsolio.com)
// =============================================================================
// Special routes for system administrators (Godfather)

Route::prefix('v1/admin')->middleware(['jwt.auth', 'system_admin'])->group(function () {
    Route::get('/tenants', [TenantController::class, 'indexAdmin']);
    Route::put('/tenants/{id}', [TenantController::class, 'updateAdmin']);
    Route::delete('/tenants/{id}', [TenantController::class, 'deleteAdmin']);
    Route::post('/tenants/{id}/suspend', [TenantController::class, 'suspend']);
    Route::post('/tenants/{id}/activate', [TenantController::class, 'activate']);
});
