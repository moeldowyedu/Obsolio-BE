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
     * This endpoint returns the organization associated with the current tenant.
     * Each tenant has exactly one organization (1:1 relationship).
     * 
     * @OA\Get(
     *     path="/api/v1/tenant/organization",
     *     summary="Get current tenant's organization",
     *     description="Returns the organization details for the authenticated tenant. Tenant = Organization (1:1 relationship).",
     *     operationId="getTenantOrganization",
     *     tags={"Tenant - Organization"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="tenant_id", type="string", example="acme"),
     *                 @OA\Property(property="name", type="string", example="Acme Corporation"),
     *                 @OA\Property(property="short_name", type="string", example="ACME", nullable=true),
     *                 @OA\Property(property="industry", type="string", example="Technology", nullable=true),
     *                 @OA\Property(property="company_size", type="string", example="50-100", nullable=true),
     *                 @OA\Property(property="country", type="string", example="USA", nullable=true),
     *                 @OA\Property(property="phone", type="string", example="+1234567890", nullable=true),
     *                 @OA\Property(property="timezone", type="string", example="UTC"),
     *                 @OA\Property(property="logo_url", type="string", nullable=true),
     *                 @OA\Property(property="description", type="string", nullable=true),
     *                 @OA\Property(property="settings", type="object", nullable=true),
     *                 @OA\Property(property="users_count", type="integer", example=5),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Organization not found")
     * )
     */
    public function showCurrent(): OrganizationResource
    {
        $organization = tenant()->organization;

        if (!$organization) {
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
     *     summary="Update current tenant's organization",
     *     description="Update organization details for the authenticated tenant. Tenant = Organization (1:1 relationship).",
     *     operationId="updateTenantOrganization",
     *     tags={"Tenant - Organization"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, example="Acme Corporation"),
     *             @OA\Property(property="short_name", type="string", maxLength=50, example="ACME", nullable=true),
     *             @OA\Property(property="industry", type="string", maxLength=100, example="Technology", nullable=true),
     *             @OA\Property(property="company_size", type="string", maxLength=50, example="50-100", nullable=true),
     *             @OA\Property(property="country", type="string", maxLength=100, example="USA", nullable=true),
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+1234567890", nullable=true),
     *             @OA\Property(property="timezone", type="string", maxLength=50, example="UTC", nullable=true),
     *             @OA\Property(property="logo_url", type="string", format="binary", description="Logo file upload", nullable=true),
     *             @OA\Property(property="description", type="string", example="A leading technology company", nullable=true),
     *             @OA\Property(property="settings", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization updated successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Organization not found"),
     *     @OA\Response(response=422, description="Validation error")
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
