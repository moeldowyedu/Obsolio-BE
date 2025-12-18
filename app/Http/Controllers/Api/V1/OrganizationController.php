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
     *
     * @OA\Get(
     *     path="/organizations",
     *     summary="List organizations",
     *     operationId="getOrganizations",
     *     tags={"Organizations"},
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
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="string", format="uuid", example="98765432-1234-1234-1234-1234567890ab"),
     *                     @OA\Property(property="name", type="string", example="Acme Corp"),
     *                     @OA\Property(property="short_name", type="string", example="ACME"),
     *                     @OA\Property(property="phone", type="string", example="+1234567890"),
     *                     @OA\Property(property="industry", type="string", example="Technology"),
     *                     @OA\Property(property="company_size", type="string", example="100-500"),
     *                     @OA\Property(property="country", type="string", example="USA"),
     *                     @OA\Property(property="timezone", type="string", example="UTC"),
     *                     @OA\Property(property="logo_url", type="string", example="https://example.com/logo.png"),
     *                     @OA\Property(property="description", type="string", example="We make everything."),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="users_count", type="integer", example=10)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(): AnonymousResourceCollection
    {
        $organizations = Organization::where('tenant_id', tenant('id'))
            ->withCount(['users'])
            ->paginate(request('per_page', 15));

        return OrganizationResource::collection($organizations);
    }

    /**
     * Store a newly created organization.
     */
    /**
     * @OA\Post(
     *     path="/organizations",
     *     summary="Create organization",
     *     operationId="createOrganization",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Acme Corp"),
     *             @OA\Property(property="short_name", type="string", example="ACME"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="industry", type="string"),
     *             @OA\Property(property="company_size", type="string"),
     *             @OA\Property(property="country", type="string"),
     *             @OA\Property(property="timezone", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="logo_url", type="string", format="uri")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Organization created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", format="uuid", example="98765432-1234-1234-1234-1234567890ab"),
     *                 @OA\Property(property="name", type="string", example="Acme Corp"),
     *                 @OA\Property(property="short_name", type="string", example="ACME"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890"),
     *                 @OA\Property(property="industry", type="string", example="Technology"),
     *                 @OA\Property(property="company_size", type="string", example="100-500"),
     *                 @OA\Property(property="country", type="string", example="USA"),
     *                 @OA\Property(property="timezone", type="string", example="UTC"),
     *                 @OA\Property(property="logo_url", type="string", example="https://example.com/logo.png"),
     *                 @OA\Property(property="description", type="string", example="We make everything."),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="users_count", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = Organization::create([
            'tenant_id' => tenant('id'),
            ...$request->validated(),
        ]);

        activity()
            ->performedOn($organization)
            ->causedBy(auth()->user())
            ->log('Organization created');

        return (new OrganizationResource($organization))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified organization.
     */
    /**
     * @OA\Get(
     *     path="/organizations/{organization}",
     *     summary="Get organization details",
     *     operationId="getOrganization",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", format="uuid", example="98765432-1234-1234-1234-1234567890ab"),
     *                 @OA\Property(property="name", type="string", example="Acme Corp"),
     *                 @OA\Property(property="short_name", type="string", example="ACME"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890"),
     *                 @OA\Property(property="industry", type="string", example="Technology"),
     *                 @OA\Property(property="company_size", type="string", example="100-500"),
     *                 @OA\Property(property="country", type="string", example="USA"),
     *                 @OA\Property(property="timezone", type="string", example="UTC"),
     *                 @OA\Property(property="logo_url", type="string", example="https://example.com/logo.png"),
     *                 @OA\Property(property="description", type="string", example="We make everything."),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="users_count", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Organization not found")
     * )
     */
    public function show(Organization $organization): OrganizationResource
    {
        $this->authorize('view', $organization);

        $organization->loadCount(['users']);

        return new OrganizationResource($organization);
    }

    /**
     * Update the specified organization.
     */
    /**
     * @OA\Put(
     *     path="/organizations/{organization}",
     *     summary="Update organization",
     *     operationId="updateOrganization",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Acme Corp"),
     *             @OA\Property(property="short_name", type="string", example="ACME"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="industry", type="string"),
     *             @OA\Property(property="company_size", type="string"),
     *             @OA\Property(property="country", type="string"),
     *             @OA\Property(property="timezone", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="logo_url", type="string", format="uri"),
     *             @OA\Property(property="settings", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", format="uuid", example="98765432-1234-1234-1234-1234567890ab"),
     *                 @OA\Property(property="name", type="string", example="Acme Corp"),
     *                 @OA\Property(property="short_name", type="string", example="ACME"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890"),
     *                 @OA\Property(property="industry", type="string", example="Technology"),
     *                 @OA\Property(property="company_size", type="string", example="100-500"),
     *                 @OA\Property(property="country", type="string", example="USA"),
     *                 @OA\Property(property="timezone", type="string", example="UTC"),
     *                 @OA\Property(property="logo_url", type="string", example="https://example.com/logo.png"),
     *                 @OA\Property(property="description", type="string", example="We make everything."),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="users_count", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization): OrganizationResource
    {
        $this->authorize('update', $organization);

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
     */
    /**
     * @OA\Delete(
     *     path="/organizations/{organization}",
     *     summary="Delete organization",
     *     operationId="deleteOrganization",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Organization deleted successfully"
     *     ),
     *     @OA\Response(response=404, description="Organization not found")
     * )
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
     * Switch the current organization context.
     */
    /**
     * @OA\Post(
     *     path="/organizations/{organization}/switch",
     *     summary="Switch organization context",
     *     operationId="switchOrganization",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization switched successfully",
     *         @OA\JsonContent(type="object")
     *     )
     * )
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
