<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\TenantAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentController extends Controller
{
    /**
     * Get tenant's installed agents.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $agents = $tenant->agents()
            ->withPivot(['status', 'activated_at', 'usage_count'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $agents,
        ]);
    }

    /**
     * Get specific agent details.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $agent = Agent::findOrFail($id);

        $tenantAgent = TenantAgent::where('tenant_id', $tenant->id)
            ->where('agent_id', $id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'agent' => $agent,
                'installation' => $tenantAgent,
                'is_installed' => !is_null($tenantAgent),
            ],
        ]);
    }

    /**
     * Install an agent.
     */
    public function install(Request $request, string $id): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $agent = Agent::findOrFail($id);

        // Check if already installed
        $existing = TenantAgent::where('tenant_id', $tenant->id)
            ->where('agent_id', $id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Agent already installed',
            ], 400);
        }

        try {
            $tenantAgent = TenantAgent::create([
                'tenant_id' => $tenant->id,
                'agent_id' => $agent->id,
                'status' => 'active',
                'purchased_at' => now(),
                'activated_at' => now(),
            ]);

            // Increment install count
            $agent->incrementInstalls();

            return response()->json([
                'success' => true,
                'message' => 'Agent installed successfully',
                'data' => $tenantAgent->load('agent'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to install agent',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Uninstall an agent.
     */
    public function uninstall(Request $request, string $id): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $tenantAgent = TenantAgent::where('tenant_id', $tenant->id)
            ->where('agent_id', $id)
            ->firstOrFail();

        try {
            $tenantAgent->delete();

            return response()->json([
                'success' => true,
                'message' => 'Agent uninstalled successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to uninstall agent',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle agent status (activate/deactivate).
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $tenantAgent = TenantAgent::where('tenant_id', $tenant->id)
            ->where('agent_id', $id)
            ->firstOrFail();

        $newStatus = $tenantAgent->status === 'active' ? 'inactive' : 'active';

        $tenantAgent->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Agent status updated',
            'data' => [
                'status' => $newStatus,
            ],
        ]);
    }

    /**
     * Record agent usage.
     */
    public function recordUsage(Request $request, string $id): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $tenantAgent = TenantAgent::where('tenant_id', $tenant->id)
            ->where('agent_id', $id)
            ->where('status', 'active')
            ->firstOrFail();

        $tenantAgent->incrementUsage();

        return response()->json([
            'success' => true,
            'message' => 'Usage recorded',
            'data' => [
                'usage_count' => $tenantAgent->usage_count,
                'last_used_at' => $tenantAgent->last_used_at,
            ],
        ]);
    }
}