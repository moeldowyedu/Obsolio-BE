<?php

namespace App\Swagger;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="OrganizationResponse",
 *     type="object",
 *     title="Organization Response",
 *     @OA\Property(property="id", type="string", format="uuid", example="98765432-1234-1234-1234-1234567890ab"),
 *     @OA\Property(property="name", type="string", example="Acme Corp"),
 *     @OA\Property(property="short_name", type="string", example="ACME"),
 *     @OA\Property(property="phone", type="string", example="+1234567890"),
 *     @OA\Property(property="industry", type="string", example="Technology"),
 *     @OA\Property(property="company_size", type="string", example="100-500"),
 *     @OA\Property(property="country", type="string", example="USA"),
 *     @OA\Property(property="timezone", type="string", example="UTC"),
 *     @OA\Property(property="logo_url", type="string", example="https://example.com/logo.png"),
 *     @OA\Property(property="description", type="string", example="We make everything."),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="users_count", type="integer", example=10)
 * )
 *
 * @OA\Schema(
 *     schema="TenantResponse",
 *     type="object",
 *     title="Tenant Response",
 *     @OA\Property(property="id", type="string", example="my-org"),
 *     @OA\Property(property="name", type="string", example="My Organization"),
 *     @OA\Property(property="short_name", type="string", example="MYORG"),
 *     @OA\Property(property="type", type="string", example="organization"),
 *     @OA\Property(property="status", type="string", example="active"),
 *     @OA\Property(property="subdomain_preference", type="string", example="my-org"),
 *     @OA\Property(property="subdomain_activated_at", type="string", format="date-time", example="2023-10-27T10:00:00.000000Z"),
 *     @OA\Property(property="is_on_trial", type="boolean", example=true),
 *     @OA\Property(property="trial_ends_at", type="string", format="date-time", example="2023-11-03T10:00:00.000000Z"),
 *     @OA\Property(property="domains", type="array", @OA\Items(type="string", example="my-org.obsolio.com")),
 *     @OA\Property(property="logo_url", type="string", example="https://example.com/logo.png")
 * )
 */
class Schemas
{
}
