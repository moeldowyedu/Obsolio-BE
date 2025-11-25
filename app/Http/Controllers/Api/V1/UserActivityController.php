<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\QueryUserActivityRequest;
use App\Models\UserActivity;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserActivityController extends Controller
{
    /**
     * @OA\Get(
     *     path="/activities",
     *     summary="List all activities",
     *     description="Get a paginated list of user activities",
     *     operationId="getActivities",
     *     tags={"Activities"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(QueryUserActivityRequest $request): JsonResponse
    {
        $query = UserActivity::query()
            ->where('tenant_id', tenant('id'))
            ->with(['user:id,name,email', 'organization:id,name'])
            ->latest('created_at');

        // If user is not auditor/admin, show only their own activities
        if (!$request->user()->can('audit-activities')) {
            $query->where('user_id', $request->user()->id);
        }

        // Apply filters
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->has('activity_type')) {
            $query->where('activity_type', $request->activity_type);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_sensitive')) {
            $query->where('is_sensitive', $request->boolean('is_sensitive'));
        }

        if ($request->has('requires_audit')) {
            $query->where('requires_audit', $request->boolean('requires_audit'));
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $activities = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $activities,
            'meta' => [
                'total_activities' => $activities->total(),
                'current_page' => $activities->currentPage(),
                'per_page' => $activities->perPage(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/activities/{id}",
     *     summary="Get activity details",
     *     operationId="getActivity",
     *     tags={"Activities"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Activity ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Activity not found")
     * )
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorize('view-activities');

        $query = UserActivity::where('tenant_id', tenant('id'))
            ->where('id', $id)
            ->with(['user:id,name,email', 'organization:id,name']);

        // If user is not auditor/admin, show only their own activity
        if (!$request->user()->can('audit-activities')) {
            $query->where('user_id', $request->user()->id);
        }

        $activity = $query->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $activity,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/activities/user/{userId}",
     *     summary="Get user activities",
     *     operationId="getUserActivities",
     *     tags={"Activities"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function byUser(Request $request, string $userId): JsonResponse
    {
        $this->authorize('view-activities');

        // Users can only view their own activities unless they have audit permission
        if ($userId !== $request->user()->id && !$request->user()->can('audit-activities')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view other users activities',
            ], 403);
        }

        $query = UserActivity::query()
            ->where('tenant_id', tenant('id'))
            ->where('user_id', $userId)
            ->with(['user:id,name,email', 'organization:id,name'])
            ->latest('created_at');

        // Apply filters
        if ($request->has('activity_type')) {
            $query->where('activity_type', $request->activity_type);
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $activities = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/activities/export",
     *     summary="Export activities",
     *     operationId="exportActivities",
     *     tags={"Activities"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Activities exported successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function export(QueryUserActivityRequest $request): JsonResponse
    {
        $this->authorize('export-activities');

        try {
            $query = UserActivity::query()
                ->where('tenant_id', tenant('id'))
                ->with(['user:id,name,email', 'organization:id,name']);

            // If user is not auditor/admin, export only their own activities
            if (!$request->user()->can('audit-activities')) {
                $query->where('user_id', $request->user()->id);
            }

            // Apply same filters as index method
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('organization_id')) {
                $query->where('organization_id', $request->organization_id);
            }

            if ($request->has('activity_type')) {
                $query->where('activity_type', $request->activity_type);
            }

            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $activities = $query->latest('created_at')->limit(10000)->get();

            // Generate CSV data (in production, use Laravel Excel or similar)
            $csvData = $this->generateCSV($activities);

            // Log the export activity
            UserActivity::create([
                'tenant_id' => tenant('id'),
                'user_id' => $request->user()->id,
                'organization_id' => $request->user()->organization_id ?? null,
                'activity_type' => 'export',
                'action' => 'read',
                'entity_type' => 'UserActivity',
                'entity_id' => null,
                'description' => 'Exported ' . $activities->count() . ' activities to CSV',
                'metadata' => [
                    'count' => $activities->count(),
                    'filters' => $request->only(['user_id', 'organization_id', 'activity_type', 'date_from', 'date_to']),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'success',
                'requires_audit' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Activities exported successfully',
                'data' => [
                    'filename' => 'activities_export_' . now()->format('Y-m-d_His') . '.csv',
                    'csv_data' => $csvData,
                    'total_records' => $activities->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export activities',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/sessions",
     *     summary="List active sessions",
     *     operationId="getSessions",
     *     tags={"Sessions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function sessions(Request $request): JsonResponse
    {
        $this->authorize('view-sessions');

        $query = UserSession::query()
            ->where('tenant_id', tenant('id'))
            ->with('user:id,name,email')
            ->latest('last_activity_at');

        // Users can only view their own sessions unless they have audit permission
        if (!$request->user()->can('audit-activities')) {
            $query->where('user_id', $request->user()->id);
        }

        // Apply filters
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('device_type')) {
            $query->where('device_type', $request->device_type);
        }

        if ($request->has('date_from')) {
            $query->where('started_at', '>=', $request->date_from);
        }

        $sessions = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $sessions,
            'meta' => [
                'total_sessions' => $sessions->total(),
                'active_sessions' => UserSession::where('tenant_id', tenant('id'))
                    ->where('is_active', true)
                    ->count(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/sessions/active",
     *     summary="Get active sessions",
     *     operationId="getActiveSessions",
     *     tags={"Sessions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function activeSessions(Request $request): JsonResponse
    {
        $this->authorize('view-sessions');

        $query = UserSession::query()
            ->where('tenant_id', tenant('id'))
            ->where('is_active', true)
            ->with('user:id,name,email')
            ->latest('last_activity_at');

        // Users can only view their own sessions unless they have audit permission
        if (!$request->user()->can('audit-activities')) {
            $query->where('user_id', $request->user()->id);
        }

        // Apply filters
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $sessions = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $sessions,
            'meta' => [
                'total_active_sessions' => $sessions->total(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/sessions/{id}/terminate",
     *     summary="Terminate session",
     *     operationId="terminateSession",
     *     tags={"Sessions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Session ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Session terminated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function terminateSession(Request $request, string $id): JsonResponse
    {
        $this->authorize('terminate-sessions');

        try {
            $query = UserSession::where('tenant_id', tenant('id'))
                ->where('id', $id);

            // Users can only terminate their own sessions unless they have admin permission
            if (!$request->user()->can('audit-activities')) {
                $query->where('user_id', $request->user()->id);
            }

            $session = $query->firstOrFail();

            if (!$session->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session is already terminated',
                ], 400);
            }

            $session->update([
                'is_active' => false,
                'ended_at' => now(),
                'duration_seconds' => now()->diffInSeconds($session->started_at),
            ]);

            // Log the session termination
            UserActivity::create([
                'tenant_id' => tenant('id'),
                'user_id' => $request->user()->id,
                'organization_id' => $request->user()->organization_id ?? null,
                'activity_type' => 'logout',
                'action' => 'update',
                'entity_type' => 'UserSession',
                'entity_id' => $session->id,
                'description' => 'Terminated session for user: ' . $session->user->name,
                'metadata' => [
                    'session_id' => $session->session_id,
                    'duration_seconds' => $session->duration_seconds,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'success',
                'is_sensitive' => true,
                'requires_audit' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Session terminated successfully',
                'data' => $session->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to terminate session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate CSV data from activities.
     */
    private function generateCSV($activities): string
    {
        $csv = "ID,User,Organization,Activity Type,Action,Entity Type,Entity ID,Description,Status,IP Address,Created At\n";

        foreach ($activities as $activity) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $activity->id,
                $activity->user->name ?? 'N/A',
                $activity->organization->name ?? 'N/A',
                $activity->activity_type,
                $activity->action,
                $activity->entity_type ?? 'N/A',
                $activity->entity_id ?? 'N/A',
                str_replace(',', ';', $activity->description ?? 'N/A'),
                $activity->status,
                $activity->ip_address ?? 'N/A',
                $activity->created_at->toDateTimeString()
            );
        }

        return $csv;
    }
}
