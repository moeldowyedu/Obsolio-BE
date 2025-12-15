<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEngineRequest;
use App\Http\Requests\UpdateEngineRequest;
use App\Http\Resources\EngineResource;
use App\Models\Engine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EngineController extends Controller
{
    /**
     * Display a listing of engines.
     * Read-only for tenants, shows all active engines.
     */
    /**
     * @OA\Get(
     *     path="/engines",
     *     summary="List AI engines",
     *     description="Read-only for tenants, shows all active engines",
     *     operationId="getEngines",
     *     tags={"Engines"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function index(): AnonymousResourceCollection
    {
        $query = Engine::query();

        // Only show active engines for non-admin users
        if (!auth()->user()?->hasRole('super-admin')) {
            $query->where('is_active', true);
        }

        $engines = $query
            ->withCount(['rubrics'])
            ->paginate(request('per_page', 15));

        return EngineResource::collection($engines);
    }

    /**
     * Store a newly created engine.
     * Admin only.
     */
    /**
     * @OA\Post(
     *     path="/engines",
     *     summary="Create new engine",
     *     description="Admin only",
     *     operationId="createEngine",
     *     tags={"Engines"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Engine created successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreEngineRequest $request): JsonResponse
    {
        $this->authorize('create', Engine::class);

        $engine = Engine::create($request->validated());

        activity()
            ->performedOn($engine)
            ->causedBy(auth()->user())
            ->log('Engine created');

        $engine->loadCount(['rubrics']);

        return (new EngineResource($engine))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified engine.
     */
    /**
     * @OA\Get(
     *     path="/engines/{engine}",
     *     summary="Get engine details",
     *     operationId="getEngine",
     *     tags={"Engines"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="engine",
     *         in="path",
     *         description="Engine ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Engine not found")
     * )
     */
    public function show(Engine $engine): EngineResource
    {
        $this->authorize('view', $engine);

        $engine->loadCount(['rubrics'])
            ->load(['rubrics']);

        return new EngineResource($engine);
    }

    /**
     * Update the specified engine.
     * Admin only.
     */
    /**
     * @OA\Put(
     *     path="/engines/{engine}",
     *     summary="Update engine",
     *     description="Admin only",
     *     operationId="updateEngine",
     *     tags={"Engines"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="engine",
     *         in="path",
     *         description="Engine ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Engine updated successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateEngineRequest $request, Engine $engine): EngineResource
    {
        $this->authorize('update', $engine);

        $engine->update($request->validated());

        activity()
            ->performedOn($engine)
            ->causedBy(auth()->user())
            ->log('Engine updated');

        $engine->loadCount(['rubrics']);

        return new EngineResource($engine);
    }

    /**
     * Remove the specified engine.
     * Admin only.
     */
    /**
     * @OA\Delete(
     *     path="/engines/{engine}",
     *     summary="Delete engine",
     *     description="Admin only",
     *     operationId="deleteEngine",
     *     tags={"Engines"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="engine",
     *         in="path",
     *         description="Engine ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Engine deleted successfully"
     *     ),
     *     @OA\Response(response=404, description="Engine not found")
     * )
     */
    public function destroy(Engine $engine): JsonResponse
    {
        $this->authorize('delete', $engine);

        activity()
            ->performedOn($engine)
            ->causedBy(auth()->user())
            ->log('Engine deleted');

        $engine->delete();

        return response()->json(null, 204);
    }
}
