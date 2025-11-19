<?php

use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\AgentExecutionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EngineController;
use App\Http\Controllers\Api\V1\HITLApprovalController;
use App\Http\Controllers\Api\V1\JobFlowController;
use App\Http\Controllers\Api\V1\MarketplaceController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\WorkflowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health & Monitoring endpoints (no auth required)
Route::get('/health', [HealthCheckController::class, 'index']); // Lightweight health check
Route::get('/health/detailed', [HealthCheckController::class, 'detailed']); // Detailed health check
Route::get('/health/ready', [HealthCheckController::class, 'ready']); // Kubernetes readiness probe
Route::get('/health/alive', [HealthCheckController::class, 'alive']); // Kubernetes liveness probe
Route::get('/metrics', MetricsController::class); // Prometheus metrics

// Public routes
Route::prefix('v1')->group(function () {
    // Authentication
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

    // Public marketplace
    Route::get('/marketplace', [MarketplaceController::class, 'index']);
    Route::get('/marketplace/{id}', [MarketplaceController::class, 'show']);

    // Public engines list
    Route::get('/engines', [EngineController::class, 'index']);
});

// Protected routes
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Organizations
    Route::apiResource('organizations', OrganizationController::class);
    Route::get('/organizations/{id}/dashboard', [OrganizationController::class, 'dashboard']);

    // Branches
    Route::apiResource('branches', BranchController::class);
    Route::get('/organizations/{organizationId}/branches', [BranchController::class, 'byOrganization']);

    // Departments
    Route::apiResource('departments', DepartmentController::class);
    Route::get('/organizations/{organizationId}/departments', [DepartmentController::class, 'byOrganization']);
    Route::get('/branches/{branchId}/departments', [DepartmentController::class, 'byBranch']);

    // Projects
    Route::apiResource('projects', ProjectController::class);
    Route::get('/departments/{departmentId}/projects', [ProjectController::class, 'byDepartment']);
    Route::put('/projects/{id}/status', [ProjectController::class, 'updateStatus']);

    // Teams
    Route::apiResource('teams', TeamController::class);
    Route::post('/teams/{id}/members', [TeamController::class, 'addMember']);
    Route::delete('/teams/{id}/members/{userId}', [TeamController::class, 'removeMember']);

    // Users
    Route::apiResource('users', UserController::class);
    Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);
    Route::post('/users/{id}/assign', [UserController::class, 'assign']);
    Route::get('/users/{id}/assignments', [UserController::class, 'assignments']);

    // Engines & Rubrics
    Route::get('/engines/{id}', [EngineController::class, 'show']);
    Route::apiResource('engines.rubrics', EngineController::class);
    Route::post('/engines/{id}/rubrics', [EngineController::class, 'createRubric']);
    Route::put('/engines/{engineId}/rubrics/{id}', [EngineController::class, 'updateRubric']);
    Route::delete('/engines/{engineId}/rubrics/{id}', [EngineController::class, 'deleteRubric']);

    // Agents
    Route::apiResource('agents', AgentController::class);
    Route::post('/agents/{id}/publish', [AgentController::class, 'publish']);
    Route::post('/agents/{id}/clone', [AgentController::class, 'clone']);
    Route::get('/agents/{id}/executions', [AgentController::class, 'executions']);
    Route::post('/agents/{id}/execute', [AgentController::class, 'execute']);

    // Job Flows
    Route::apiResource('job-flows', JobFlowController::class);
    Route::put('/job-flows/{id}/status', [JobFlowController::class, 'updateStatus']);
    Route::post('/job-flows/{id}/trigger', [JobFlowController::class, 'trigger']);
    Route::get('/job-flows/{id}/stats', [JobFlowController::class, 'stats']);

    // Workflows
    Route::apiResource('workflows', WorkflowController::class);
    Route::post('/workflows/{id}/execute', [WorkflowController::class, 'execute']);
    Route::get('/workflows/{id}/executions', [WorkflowController::class, 'executions']);
    Route::get('/workflows/executions/{executionId}', [WorkflowController::class, 'executionDetails']);

    // HITL Approvals
    Route::apiResource('hitl-approvals', HITLApprovalController::class)->only(['index', 'show']);
    Route::post('/hitl-approvals/{id}/approve', [HITLApprovalController::class, 'approve']);
    Route::post('/hitl-approvals/{id}/reject', [HITLApprovalController::class, 'reject']);
    Route::post('/hitl-approvals/{id}/escalate', [HITLApprovalController::class, 'escalate']);
    Route::get('/hitl-approvals/pending', [HITLApprovalController::class, 'pending']);

    // Agent Executions
    Route::get('/executions', [AgentExecutionController::class, 'index']);
    Route::get('/executions/{id}', [AgentExecutionController::class, 'show']);
    Route::get('/executions/{id}/logs', [AgentExecutionController::class, 'logs']);
    Route::post('/executions/{id}/cancel', [AgentExecutionController::class, 'cancel']);

    // Marketplace (authenticated)
    Route::post('/marketplace', [MarketplaceController::class, 'store']);
    Route::put('/marketplace/{id}', [MarketplaceController::class, 'update']);
    Route::delete('/marketplace/{id}', [MarketplaceController::class, 'destroy']);
    Route::post('/marketplace/{id}/purchase', [MarketplaceController::class, 'purchase']);
    Route::get('/marketplace/my-listings', [MarketplaceController::class, 'myListings']);
    Route::get('/marketplace/my-purchases', [MarketplaceController::class, 'myPurchases']);

    // Webhooks
    Route::apiResource('webhooks', WebhookController::class);
    Route::post('/webhooks/{id}/test', [WebhookController::class, 'test']);

    // Subscriptions & Billing
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::get('/subscriptions/current', [SubscriptionController::class, 'current']);
    Route::post('/subscriptions/subscribe', [SubscriptionController::class, 'subscribe']);
    Route::post('/subscriptions/cancel', [SubscriptionController::class, 'cancel']);
    Route::get('/subscriptions/usage', [SubscriptionController::class, 'usage']);
    Route::get('/subscriptions/usage/{date}', [SubscriptionController::class, 'usageByDate']);

    // Analytics & Dashboard
    Route::get('/dashboard/stats', [AuthController::class, 'dashboardStats']);
    Route::get('/analytics/agents', [AgentController::class, 'analytics']);
    Route::get('/analytics/executions', [AgentExecutionController::class, 'analytics']);
});
