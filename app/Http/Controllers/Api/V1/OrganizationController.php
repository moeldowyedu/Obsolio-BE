<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Annotations as OA;

/**
 */
class OrganizationController extends Controller
{
    /**
     * Display a listing of organizations.
     * (Deprecated/Internal Use Only)
     */
    public function index(): AnonymousResourceCollection
    {
        $organizations = Organization::where('tenant_id', tenant('id'))
            ->paginate(request('per_page', 15));

        return OrganizationResource::collection($organizations);
    }




    /**
     * Display the specified organization.
     * (Deprecated/Internal Use Only)
     */
    public function show(Organization $organization): OrganizationResource
    {
        $this->authorize('view', $organization);

        $organization->loadCount(['users']);

        return new OrganizationResource($organization);
    }

    /**
     * Update the specified organization.
     * (Deprecated/Internal Use Only)
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization): OrganizationResource
    {
        // $this->authorize('update', $organization);

        $data = $request->validated();

        if ($request->hasFile('logo_url')) {
            $path = $request->file('logo_url')->store('organizations/logos', 'public');
            $data['logo_url'] = \Illuminate\Support\Facades\Storage::url($path);
        }

        $organization->update($data);

        activity()
            ->performedOn($organization)
            ->causedBy(auth()->user())
            ->log('Organization updated');

        return new OrganizationResource($organization);
    }

    /**
     * Remove the specified organization.
     * (Deprecated/Internal Use Only)
     */
    public function destroy(Organization $organization): JsonResponse
    {
        $this->authorize('delete', $organization);

        activity()
            ->performedOn($organization)
            ->causedBy(auth()->user())
            ->log('Organization deleted');

        $organization->delete();

        return response()->json(null, 204);
    }



    /**
     * Get the current tenant's organization details.
     * 
     * @OA\Get(
     *     path="/api/v1/tenant/organization",
     *     summary="Get current organization",
     *     operationId="getCurrentOrganization",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="name", type="string", example="Acme Corp"),
     *                 @OA\Property(property="short_name", type="string", example="ACME"),
     *                 @OA\Property(property="logo_url", type="string"),
     *                 @OA\Property(property="users_count", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function showCurrent(): OrganizationResource
    {
        $organization = tenant()->organization;

        if (!$organization) {
            // Fallback or Auto-Create? 
            // For now, return 404 or create default? 
            // Given the registration flow ensures one, 404 implies data corruption.
            abort(404, 'Organization not found for this tenant.');
        }

        $organization->loadCount(['users']);

        return new OrganizationResource($organization);
    }

    /**
     * Update the current tenant's organization details.
     * 
     * @OA\Put(
     *     path="/api/v1/tenant/organization",
     *     summary="Update current organization",
     *     operationId="updateCurrentOrganization",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="short_name", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="logo_url", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization updated successfully"
     *     )
     * )
     */
    public function updateCurrent(UpdateOrganizationRequest $request): OrganizationResource
    {
        $organization = tenant()->organization;

        if (!$organization) {
            abort(404, 'Organization not found for this tenant.');
        }

        // $this->authorize('update', $organization);

        $data = $request->validated();

        if ($request->hasFile('logo_url')) {
            $path = $request->file('logo_url')->store('organizations/logos', 'public');
            $data['logo_url'] = \Illuminate\Support\Facades\Storage::url($path);
        }

        $organization->update($data);

        activity()
            ->performedOn($organization)
            ->causedBy(auth()->user())
            ->log('Organization updated');

        return new OrganizationResource($organization);
    }

    // Keep legacy methods for admin reference or specific use cases if needed, 
    // but the routes pointing to them (index, store, destroy) have been removed from tenant API.

    /**
     * Switch the current organization context.
     * ... legacy ...
     */
    public function switch(Organization $organization): OrganizationResource
    {
        $this->authorize('view', $organization);

        session(['current_organization_id' => $organization->id]);

        activity()
            ->performedOn($organization)
            ->causedBy(auth()->user())
            ->log('Organization switched');

        $organization->loadCount(['users']);

        return new OrganizationResource($organization);
    }
}
