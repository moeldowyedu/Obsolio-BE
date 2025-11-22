<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function dashboardStats(): JsonResponse
    {
        try {
            $user = auth('api')->user();

            $stats = [
                'users' => [
                    'total' => User::where('tenant_id', $user->tenant_id)->count(),
                    'active' => User::where('tenant_id', $user->tenant_id)->where('status', 'active')->count(),
                ],
                'agents' => [
                    'total' => Agent::where('tenant_id', $user->tenant_id)->count(),
                    'active' => Agent::where('tenant_id', $user->tenant_id)->where('is_active', true)->count(),
                ],
                'workflows' => [
                    'total' => Workflow::where('tenant_id', $user->tenant_id)->count(),
                    'active' => Workflow::where('tenant_id', $user->tenant_id)->where('status', 'active')->count(),
                ],
                'organizations' => [
                    'total' => Organization::where('tenant_id', $user->tenant_id)->count(),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard stats',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
