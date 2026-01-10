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
use App\Http\Controllers\Api\V1\Admin\TenantManagementController;
use App\Http\Controllers\Api\V1\SubscriptionPlanController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\AgentExecutionController;
use App\Http\Controllers\Api\V1\MarketplaceController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\Tenant\TenantMembershipController;

/*
|--------------------------------------------------------------------------
| API Routes - Reorganized Structure
|--------------------------------------------------------------------------
| Structure:
| - /api/v1/auth/*           - Public authentication
| - /api/v1/marketplace/*    - Public marketplace
| - /api/v1/admin/*          - System admin console
| - /api/v1/tenant/*         - Tenant dashboard
| - /api/v1/webhooks/*       - External callbacks
| - /api/v1/payments/*       - Payment processing
|--------------------------------------------------------------------------
*/

// =============================================================================
// API WELCOME
// =============================================================================
Route::prefix('v1')->group(function () {
    Route::get('/', function () {
        return response()->json([
            'message' => 'Welcome to OBSOLIO API v1',
            'version' => '1.0.0',
            'documentation' => url('/api/documentation'),
        ]);
    });
});

// =============================================================================
// PUBLIC AUTHENTICATION ENDPOINTS
// =============================================================================
Route::prefix('v1/auth')->group(function () {

    // Registration & Login
    Route::post('/register', function (Illuminate\Http\Request $request) {
        \Log::info('Hit /register route closure', $request->all());
        return app(AuthController::class)->register($request);
    });
    Route::post('/login', [AuthController::class, 'login']);

    // Password Management
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Tenant Lookup & Subdomain
    Route::post('/lookup-tenant', [AuthController::class, 'lookupTenant']);
    Route::post('/check-subdomain', [AuthController::class, 'checkSubdomain']);
    Route::get('/tenants/check-availability/{subdomain}', [AuthController::class, 'checkAvailability']);
    Route::get('/tenants/find-by-subdomain/{subdomain}', [TenantController::class, 'findBySubdomain']);

    // Email Verification
    Route::get('/verify-email/{id}/{hash}', [VerificationController::class, 'verify'])
        ->name('verification.verify');
    Route::post('/verify-email/{id}/{hash}', [VerificationController::class, 'verify']);
    Route::post('/resend-verification', [VerificationController::class, 'resend']);
});

// =============================================================================
// PUBLIC MARKETPLACE ENDPOINTS
// =============================================================================
Route::prefix('v1/marketplace')->group(function () {

    // Browse Agents
    Route::get('/agents', [MarketplaceController::class, 'index']);
    Route::get('/agents/featured', [MarketplaceController::class, 'featured']);
    Route::get('/agents/{id}', [MarketplaceController::class, 'show']);

    // Categories
    Route::get('/categories', [MarketplaceController::class, 'categories']);
    Route::get('/categories/{category}/agents', [MarketplaceController::class, 'byCategory']);

    // Statistics
    Route::get('/stats', [MarketplaceController::class, 'stats']);
});

// =============================================================================
// PUBLIC SUBSCRIPTION PLANS (Pricing Page)
// =============================================================================
Route::prefix('v1')->group(function () {
    Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index']);
    Route::get('/subscription-plans/{id}', [SubscriptionPlanController::class, 'show']);
});

// =============================================================================
// PUBLIC TENANT LOOKUP (No Auth/Tenancy Middleware)
// =============================================================================
Route::prefix('v1/tenants')->group(function () {
    Route::get('/find-by-subdomain/{subdomain}', [TenantController::class, 'findBySubdomain']);
    Route::get('/check-availability/{subdomain}', [AuthController::class, 'checkAvailability']);
});

// =============================================================================
// ADMIN ENDPOINTS (System Console)
// =============================================================================
Route::prefix('v1/admin')->middleware(['jwt.auth', 'system_admin'])->group(function () {

    // =========================================================================
    // TENANT MANAGEMENT
    // =========================================================================
    Route::prefix('tenants')->group(function () {
        Route::get('/', [TenantManagementController::class, 'index']);
        Route::post('/', [TenantManagementController::class, 'store']);
        Route::get('/statistics', [TenantManagementController::class, 'statistics']);
        Route::get('/{id}', [TenantManagementController::class, 'show']);
        Route::put('/{id}', [TenantManagementController::class, 'update']);
        Route::delete('/{id}', [TenantManagementController::class, 'destroy']);
        Route::post('/{id}/deactivate', [TenantManagementController::class, 'deactivate']);
        Route::post('/{id}/reactivate', [TenantManagementController::class, 'reactivate']);
        Route::put('/{id}/status', [TenantManagementController::class, 'updateStatus']);
        Route::put('/{id}/subscription', [TenantManagementController::class, 'changeSubscription']);
        Route::get('/{id}/subscription-history', [TenantManagementController::class, 'subscriptionHistory']);
        Route::post('/{id}/extend-trial', [TenantManagementController::class, 'extendTrial']);
    });

    // =========================================================================
    // USER MANAGEMENT
    // =========================================================================
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminController::class, 'listUsers']);
        Route::post('/', [AdminController::class, 'createUser']);
        Route::get('/{id}', [AdminController::class, 'getUser']);
        Route::put('/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/{id}', [AdminController::class, 'deleteUser']);
    });

    // =========================================================================
    // AGENT CATEGORIES
    // =========================================================================
    Route::prefix('agent-categories')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\Admin\AdminAgentCategoryController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\V1\Admin\AdminAgentCategoryController::class, 'store']);
        Route::put('/{id}', [\App\Http\Controllers\Api\V1\Admin\AdminAgentCategoryController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Admin\AdminAgentCategoryController::class, 'destroy']);
    });

    // =========================================================================
    // AGENTS (Global Management)
    // =========================================================================
    Route::prefix('agents')->group(function () {
        Route::get('/', [AdminController::class, 'listAgents']);
        Route::post('/', [AdminController::class, 'createAgent']);
        Route::get('/{id}', [AdminController::class, 'getAgent']);
        Route::put('/{id}', [AdminController::class, 'updateAgent']);
        Route::delete('/{id}', [AdminController::class, 'deleteAgent']);
        Route::post('/bulk-activate', [AdminController::class, 'bulkActivateAgents']);
        Route::post('/bulk-deactivate', [AdminController::class, 'bulkDeactivateAgents']);

        // Agent Categories Management (Pivot)
        Route::get('/{id}/categories', [AdminController::class, 'getAgentCategories']);
        Route::post('/{id}/categories', [AdminController::class, 'addAgentCategories']);
        Route::put('/{id}/categories', [AdminController::class, 'syncAgentCategories']);
        Route::patch('/{id}/categories', [AdminController::class, 'syncAgentCategories']);
        Route::delete('/{id}/categories', [AdminController::class, 'removeAgentCategories']);
    });

    // =========================================================================
    // AGENT ENDPOINTS (Webhooks Configuration)
    // =========================================================================
    Route::prefix('agent-endpoints')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\Admin\AdminAgentEndpointsController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\V1\Admin\AdminAgentEndpointsController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\Admin\AdminAgentEndpointsController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\V1\Admin\AdminAgentEndpointsController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Admin\AdminAgentEndpointsController::class, 'destroy']);
    });

    // =========================================================================
    // AGENT RUNS (Global Monitoring)
    // =========================================================================
    Route::prefix('agent-runs')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\Admin\AdminAgentRunsController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\Admin\AdminAgentRunsController::class, 'show']);
    });

    // =========================================================================
    // ORGANIZATION MANAGEMENT
    // =========================================================================
    Route::prefix('organizations')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\Admin\AdminOrganizationController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\V1\Admin\AdminOrganizationController::class, 'store']);
        Route::get('/statistics', [\App\Http\Controllers\Api\V1\Admin\AdminOrganizationController::class, 'statistics']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\Admin\AdminOrganizationController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\V1\Admin\AdminOrganizationController::class, 'update']);
        Route::post('/{id}/deactivate', [\App\Http\Controllers\Api\V1\Admin\AdminOrganizationController::class, 'deactivate']);
        Route::post('/{id}/reactivate', [\App\Http\Controllers\Api\V1\Admin\AdminOrganizationController::class, 'reactivate']);
    });

    // =========================================================================
    // SUBSCRIPTION INSTANCE MANAGEMENT
    // =========================================================================
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\Admin\AdminSubscriptionController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\V1\Admin\AdminSubscriptionController::class, 'store']);
        Route::get('/statistics', [\App\Http\Controllers\Api\V1\Admin\AdminSubscriptionController::class, 'statistics']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\Admin\AdminSubscriptionController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\V1\Admin\AdminSubscriptionController::class, 'update']);
        Route::post('/{id}/cancel', [\App\Http\Controllers\Api\V1\Admin\AdminSubscriptionController::class, 'cancel']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Admin\AdminSubscriptionController::class, 'destroy']);
    });

    // =========================================================================
    // SUBSCRIPTION PLANS MANAGEMENT
    // =========================================================================
    Route::prefix('subscription-plans')->group(function () {
        Route::get('/', [AdminController::class, 'listPlans']);
        Route::post('/', [AdminController::class, 'createPlan']);
        Route::put('/{id}', [AdminController::class, 'updatePlan']);
        Route::delete('/{id}', [AdminController::class, 'deletePlan']);
    });

    // =========================================================================
    // ANALYTICS & REPORTS
    // =========================================================================
    Route::prefix('analytics')->group(function () {
        Route::get('/overview', [AdminController::class, 'analyticsOverview']);
        Route::get('/revenue', [AdminController::class, 'revenueAnalytics']);
        Route::get('/agents', [AdminController::class, 'agentAnalytics']);
    });

    // =========================================================================
    // ACTIVITY & AUDIT LOGS
    // =========================================================================
    Route::get('/activity-logs', [AdminController::class, 'activityLogs']);
    Route::get('/impersonation-logs', [AdminController::class, 'impersonationLogs']);

    // =========================================================================
    // IMPERSONATION (Support Access)
    // =========================================================================
    Route::prefix('impersonations')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\Admin\AdminImpersonationController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\Admin\AdminImpersonationController::class, 'show']);
        Route::post('/{id}/end', [\App\Http\Controllers\Api\V1\Admin\AdminImpersonationController::class, 'end']);
    });

    Route::prefix('tenants/{tenantId}/impersonations')->group(function () {
        Route::post('/start', [\App\Http\Controllers\Api\V1\Admin\AdminImpersonationController::class, 'start']);
    });

    // =========================================================================
    // CONSOLE PERMISSIONS & ROLES
    // =========================================================================
    Route::prefix('permissions')->group(function () {
        Route::get('/', [AdminController::class, 'listPermissions']);
    });

    Route::prefix('roles')->group(function () {
        Route::get('/', [AdminController::class, 'listRoles']);
    });
});

// =============================================================================
// TENANT ENDPOINTS (Tenant Dashboard)
// =============================================================================
Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'jwt.auth',
    'tenant.status'
])->prefix('v1/tenant')->group(function () {

    // =========================================================================
    // PROFILE & AUTHENTICATION
    // =========================================================================
    Route::get('/profile', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh-token', [AuthController::class, 'refresh']);

    // =========================================================================
    // TENANT SETTINGS
    // =========================================================================
    Route::get('/settings', [TenantController::class, 'show']);
    Route::put('/settings', [TenantController::class, 'update']);

    // Tenant Setup (Post-Registration)
    Route::prefix('setup')->group(function () {
        Route::get('/status', [TenantSetupController::class, 'checkSetupStatus']);
        Route::post('/organization', [TenantSetupController::class, 'setupOrganization']);
        // Route::post('/personal', [TenantSetupController::class, 'setupPersonal']); // Removed
    });

    // =========================================================================
    // DASHBOARD & STATISTICS
    // =========================================================================
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'dashboardStats']);
        Route::get('/overview', [DashboardController::class, 'dashboardStats']);
    });

    // =========================================================================
    // ORGANIZATIONS
    // =========================================================================
    // =========================================================================
    // ORGANIZATION (Single Entity per Tenant)
    // =========================================================================
    Route::prefix('organization')->group(function () {
        Route::get('/', [OrganizationController::class, 'showCurrent']);
        Route::put('/', [OrganizationController::class, 'updateCurrent']);
        Route::post('/', [OrganizationController::class, 'updateCurrent']); // Alias for file uploads
    });

    // =========================================================================
    // AGENTS (Tenant's Installed Agents)
    // =========================================================================
    Route::prefix('agents')->group(function () {
        Route::get('/', [AgentController::class, 'index']);
        Route::get('/{id}', [AgentController::class, 'show']);
        Route::post('/{id}/install', [AgentController::class, 'install']);
        Route::delete('/{id}/uninstall', [AgentController::class, 'uninstall']);
        Route::post('/{id}/toggle-status', [AgentController::class, 'toggleStatus']);
        Route::post('/{id}/run', [AgentExecutionController::class, 'run']);
        Route::post('/{id}/record-usage', [AgentController::class, 'recordUsage']);
    });

    // =========================================================================
    // AGENT RUNS (Tenant's Execution History)
    // =========================================================================
    Route::prefix('agent-runs')->group(function () {
        Route::get('/', [AgentExecutionController::class, 'index']);
        Route::get('/{run_id}', [AgentExecutionController::class, 'getRunStatus']);
        Route::get('/{run_id}/status', [AgentExecutionController::class, 'getRunStatus']);
    });

    // =========================================================================
    // SUBSCRIPTIONS
    // =========================================================================
    Route::prefix('subscription')->group(function () {
        Route::get('/current', [SubscriptionController::class, 'current']);
        Route::post('/subscribe', [SubscriptionController::class, 'store']);
        Route::put('/change-plan', [SubscriptionController::class, 'changePlan']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/resume', [SubscriptionController::class, 'resume']);
        Route::get('/history', [SubscriptionController::class, 'history']);
        Route::get('/recommendations', [SubscriptionPlanController::class, 'recommendations']);
    });

    // =========================================================================
    // BILLING & INVOICES
    // =========================================================================
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

    // =========================================================================
    // ACTIVITIES & SESSIONS
    // =========================================================================
    Route::prefix('activities')->group(function () {
        Route::get('/', [UserActivityController::class, 'index']);
        Route::get('/{id}', [UserActivityController::class, 'show']);
        Route::get('/user/{userId}', [UserActivityController::class, 'byUser']);
    });

    Route::prefix('sessions')->group(function () {
        Route::get('/', [UserActivityController::class, 'sessions']);
        Route::get('/active', [UserActivityController::class, 'activeSessions']);
        Route::post('/{id}/terminate', [UserActivityController::class, 'terminateSession']);
    });

    // =========================================================================
    // MEMBERSHIPS (Tenant User Management)
    // =========================================================================
    Route::prefix('memberships')->group(function () {
        Route::get('/', [TenantMembershipController::class, 'index']);
        Route::post('/invite', [TenantMembershipController::class, 'invite']);
        Route::post('/{userId}/activate', [TenantMembershipController::class, 'activate']);
        Route::post('/{userId}/suspend', [TenantMembershipController::class, 'suspend']);
        Route::post('/{userId}/reactivate', [TenantMembershipController::class, 'reactivate']);
        Route::delete('/{userId}', [TenantMembershipController::class, 'destroy']);
    });

    // =========================================================================
    // ROLES & PERMISSIONS (Tenant-Scoped)
    // =========================================================================
    Route::prefix('roles')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\Tenant\TenantRoleController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\V1\Tenant\TenantRoleController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\Tenant\TenantRoleController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\V1\Tenant\TenantRoleController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\V1\Tenant\TenantRoleController::class, 'destroy']);
    });

    Route::prefix('permissions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\Tenant\TenantRoleController::class, 'listPermissions']);
    });
});

// =============================================================================
// PRICING & BILLING API (Phase 5 - New Pricing Infrastructure)
// =============================================================================

// Public Routes (No Auth)
Route::prefix('v1/pricing')->group(function () {
    // Subscription Plans
    Route::get('/plans', [App\Http\Controllers\Api\SubscriptionController::class, 'plans']);

    // Agent Marketplace (Public Catalog)
    Route::get('/agents/marketplace', [App\Http\Controllers\Api\AgentMarketplaceController::class, 'publicCatalog']);
});

// Paymob Webhook (No Auth - Verified by Signature)
Route::post('/v1/webhooks/paymob', [App\Http\Controllers\Api\BillingController::class, 'paymobWebhook'])
    ->name('webhooks.paymob');

// Protected Routes (Require Auth + Tenant Context)
Route::prefix('v1/pricing')
    ->middleware(['jwt.auth', 'smart_tenancy'])
    ->group(function () {

        // =========================================================================
        // SUBSCRIPTION MANAGEMENT
        // =========================================================================
        Route::prefix('subscriptions')->group(function () {
            Route::get('/current', [App\Http\Controllers\Api\SubscriptionController::class, 'current']);
            Route::post('/create', [App\Http\Controllers\Api\SubscriptionController::class, 'create']);
            Route::post('/upgrade', [App\Http\Controllers\Api\SubscriptionController::class, 'upgrade']);
            Route::post('/cancel', [App\Http\Controllers\Api\SubscriptionController::class, 'cancel']);
            Route::post('/reactivate', [App\Http\Controllers\Api\SubscriptionController::class, 'reactivate']);
            Route::get('/history', [App\Http\Controllers\Api\SubscriptionController::class, 'history']);
        });

        // =========================================================================
        // AGENT MARKETPLACE
        // =========================================================================
        Route::prefix('agents')->group(function () {
            Route::get('/marketplace', [App\Http\Controllers\Api\AgentMarketplaceController::class, 'index']);
            Route::get('/marketplace/{agent}', [App\Http\Controllers\Api\AgentMarketplaceController::class, 'show']);
            Route::post('/subscribe/{agent}', [App\Http\Controllers\Api\AgentMarketplaceController::class, 'subscribe']);
            Route::post('/unsubscribe/{agent}', [App\Http\Controllers\Api\AgentMarketplaceController::class, 'unsubscribe']);
            Route::get('/my-agents', [App\Http\Controllers\Api\AgentMarketplaceController::class, 'myAgents']);
            Route::get('/available-slots', [App\Http\Controllers\Api\AgentMarketplaceController::class, 'availableSlots']);
            Route::post('/can-add/{agent}', [App\Http\Controllers\Api\AgentMarketplaceController::class, 'canAddAgent']);
        });

        // =========================================================================
        // BILLING & INVOICES
        // =========================================================================
        Route::prefix('billing')->group(function () {
            Route::get('/invoices', [App\Http\Controllers\Api\BillingController::class, 'invoices']);
            Route::get('/invoices/{invoice}', [App\Http\Controllers\Api\BillingController::class, 'invoice']);
            Route::get('/invoices/{invoice}/download', [App\Http\Controllers\Api\BillingController::class, 'downloadInvoice']);
            Route::get('/upcoming', [App\Http\Controllers\Api\BillingController::class, 'upcomingInvoice']);
            Route::post('/payment-method', [App\Http\Controllers\Api\BillingController::class, 'updatePaymentMethod']);
            Route::post('/invoices/{invoice}/retry', [App\Http\Controllers\Api\BillingController::class, 'retryPayment']);
        });

        // =========================================================================
        // USAGE TRACKING
        // =========================================================================
        Route::prefix('usage')->group(function () {
            Route::get('/current', [App\Http\Controllers\Api\UsageController::class, 'current']);
            Route::get('/history', [App\Http\Controllers\Api\UsageController::class, 'history']);
            Route::get('/by-agent', [App\Http\Controllers\Api\UsageController::class, 'byAgent']);
            Route::get('/agent/{agent}', [App\Http\Controllers\Api\UsageController::class, 'agentUsage']);
            Route::get('/trend', [App\Http\Controllers\Api\UsageController::class, 'dailyTrend']);
        });
    });

// =============================================================================
// INCLUDE SEPARATE ROUTE FILES
// =============================================================================
// Paymob routes moved to BillingController@paymobWebhook
// require __DIR__ . '/paymob_routes.php';


// =============================================================================
// BACKWARD COMPATIBILITY ROUTES (DEPRECATED - Will be removed in v2)
// =============================================================================
// These routes maintain backward compatibility with existing integrations
// They will be removed in API v2.0
Route::prefix('v1')->middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // Legacy tenant routes
    Route::get('/tenants', [TenantController::class, 'index'])->middleware('jwt.auth');
    // Legacy tenant routes
    Route::get('/tenants', [TenantController::class, 'index'])->middleware('jwt.auth');
    // Route::get('/tenants/find-by-subdomain/{subdomain}', [TenantController::class, 'findBySubdomain']); // Moved to public group

    Route::post('/tenants/resend-verification/{subdomain}', [TenantController::class, 'resendVerification']);

    // Legacy auth routes (redirect to new structure)
    Route::middleware(['jwt.auth', 'tenant.status'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        // Legacy dashboard
        Route::get('/dashboard/stats', [DashboardController::class, 'dashboardStats']);

        // Legacy organizations - REMOVED
        // The correct endpoint is /api/v1/tenant/organization (1:1 relationship)
        // Route::apiResource('organizations', OrganizationController::class);
        // Route::get('/organizations/{id}/dashboard', [OrganizationController::class, 'dashboard']);

        // Legacy activities
        Route::get('/activities', [UserActivityController::class, 'index']);
        Route::get('/activities/{id}', [UserActivityController::class, 'show']);
        Route::get('/activities/user/{userId}', [UserActivityController::class, 'byUser']);
        Route::get('/sessions', [UserActivityController::class, 'sessions']);
        Route::get('/sessions/active', [UserActivityController::class, 'activeSessions']);
        Route::post('/sessions/{id}/terminate', [UserActivityController::class, 'terminateSession']);

        // Legacy subscriptions
        Route::prefix('subscriptions')->group(function () {
            Route::get('/current', [SubscriptionController::class, 'current']);
            Route::post('/', [SubscriptionController::class, 'store']);
            Route::put('/change-plan', [SubscriptionController::class, 'changePlan']);
            Route::post('/cancel', [SubscriptionController::class, 'cancel']);
            Route::post('/resume', [SubscriptionController::class, 'resume']);
            Route::get('/history', [SubscriptionController::class, 'history']);
        });

        Route::get('/subscription-plans/recommendations', [SubscriptionPlanController::class, 'recommendations']);

        // Legacy agents
        Route::prefix('agents')->group(function () {
            Route::get('/', [AgentController::class, 'index']);
            Route::get('/{id}', [AgentController::class, 'show']);
            Route::post('/{id}/install', [AgentController::class, 'install']);
            Route::delete('/{id}/uninstall', [AgentController::class, 'uninstall']);
            Route::post('/{id}/toggle-status', [AgentController::class, 'toggleStatus']);
            Route::post('/{id}/record-usage', [AgentController::class, 'recordUsage']);
            Route::post('/{id}/run', [AgentExecutionController::class, 'run']);
        });

        Route::prefix('agent-runs')->group(function () {
            Route::get('/{run_id}', [AgentExecutionController::class, 'getRunStatus']);
        });

        // Legacy webhooks
        Route::post('/webhooks/agents/callback', [AgentExecutionController::class, 'callback'])
            ->withoutMiddleware(['jwt.auth', 'tenant.status']);

        // Legacy billing
        Route::prefix('billing')->group(function () {
            Route::get('/invoices', [BillingController::class, 'invoices']);
            Route::get('/invoices/{id}', [BillingController::class, 'showInvoice']);
            Route::post('/payment-methods', [BillingController::class, 'addPaymentMethod']);
            Route::post('/payment-methods/{id}/set-default', [BillingController::class, 'setDefaultPaymentMethod']);
            Route::delete('/payment-methods/{id}', [BillingController::class, 'deletePaymentMethod']);
        });

        // Legacy tenant
        Route::get('/tenant', [TenantController::class, 'show']);
        Route::put('/tenant', [TenantController::class, 'update']);
        Route::get('/tenants/{tenant}', [TenantController::class, 'show']);
        Route::put('/tenants/{tenant}', [TenantController::class, 'update']);

        // Legacy tenant setup
        Route::get('/tenant-setup/status', [TenantSetupController::class, 'checkSetupStatus']);
        Route::post('/tenant-setup/organization', [TenantSetupController::class, 'setupOrganization']);
        Route::post('/tenant-setup/personal', [TenantSetupController::class, 'setupPersonal']);
    });
});

// Legacy marketplace (already public, just moved)
Route::prefix('v1')->group(function () {
    Route::get('/marketplace', [MarketplaceController::class, 'index']);
    Route::get('/marketplace/featured', [MarketplaceController::class, 'featured']);
    Route::get('/marketplace/categories', [MarketplaceController::class, 'categories']);
    Route::get('/marketplace/category/{category}', [MarketplaceController::class, 'byCategory']);
    Route::get('/marketplace/stats', [MarketplaceController::class, 'stats']);
});
