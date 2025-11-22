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
