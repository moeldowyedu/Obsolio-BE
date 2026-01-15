<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="OBSOLIO API Documentation",
 *     version="1.0.0",
 *     description="
# OBSOLIO API - Organized Endpoint Structure

This API provides a clear separation between different user contexts:

## Endpoint Organization

### Public Endpoints
- `/auth/*` - Authentication and registration
- `/marketplace/*` - Public AgentX for browsing agents
- `/subscription-plans` - Pricing information

### Admin Endpoints (System Console)
- `/admin/*` - System administration operations
  - Tenant management
  - User management
  - Agent management (global)
  - Analytics and reports
  - Audit logs

### Tenant Endpoints (Dashboard)
- `/tenant/*` - Tenant-specific operations
  - Profile and settings
  - Dashboard and statistics
  - Organizations
  - Agents (installed)
  - Subscriptions and billing
  - Activities and sessions

### Webhooks
- `/webhooks/*` - External callback endpoints

## Authentication

Most endpoints require JWT Bearer token authentication. Include the token in the Authorization header:

```
Authorization: Bearer <your-jwt-token>
```

Public endpoints (auth, AgentX) do not require authentication.
",
 *     @OA\Contact(
 *         email="admin@obsolio.com",
 *         name="OBSOLIO Support"
 *     ),
 *     @OA\License(
 *         name="Proprietary",
 *         url="https://obsolio.com/terms"
 *     )
 * )
 * @OA\Server(
 *     url="https://console.obsolio.com/api/v1",
 *     description="Admin Console (Production)"
 * )
 * @OA\Server(
 *     url="https://{tenant}.obsolio.com/api/v1",
 *     description="Tenant Dashboard (Production)",
 *     @OA\ServerVariable(
 *         serverVariable="tenant",
 *         default="demo",
 *         description="Your tenant subdomain (e.g., acme-corp)"
 *     )
 * )
 * @OA\Server(
 *     url="http://localhost:8000/api/v1",
 *     description="Local Development"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your JWT token in the format: Bearer {token}"
 * )
 * @OA\Tag(
 *     name="Authentication",
 *     description="Public authentication endpoints"
 * )
 * @OA\Tag(
 *     name="AgentX",
 *     description="Public AgentX endpoints for browsing agents"
 * )
 * @OA\Tag(
 *     name="Pricing",
 *     description="Public pricing and subscription plans"
 * )
 * @OA\Tag(
 *     name="Admin - Tenants",
 *     description="System admin: Tenant management"
 * )
 * @OA\Tag(
 *     name="Admin - Users",
 *     description="System admin: User management"
 * )
 * @OA\Tag(
 *     name="Admin - Agents",
 *     description="System admin: Global agent management"
 * )
 * @OA\Tag(
 *     name="Admin - Agent Runs",
 *     description="System admin: Global agent execution monitoring"
 * )
 * @OA\Tag(
 *     name="Admin - Organizations",
 *     description="System admin: Organization management"
 * )
 * @OA\Tag(
 *     name="Admin - Subscriptions",
 *     description="System admin: Subscription instance management"
 * )
 * @OA\Tag(
 *     name="Admin - Analytics",
 *     description="System admin: Analytics and reports"
 * )
 * @OA\Tag(
 *     name="Tenant - Profile",
 *     description="Tenant dashboard: User profile and authentication"
 * )
 * @OA\Tag(
 *     name="Tenant - Settings",
 *     description="Tenant dashboard: Tenant settings and configuration"
 * )
 * @OA\Tag(
 *     name="Tenant - Dashboard",
 *     description="Tenant dashboard: Statistics and overview"
 * )
 * @OA\Tag(
 *     name="Tenant - Organizations",
 *     description="Tenant dashboard: Organization management"
 * )
 * @OA\Tag(
 *     name="Tenant - Agents",
 *     description="Tenant dashboard: Installed agent management"
 * )
 * @OA\Tag(
 *     name="Tenant - Agent Runs",
 *     description="Tenant dashboard: Agent execution history"
 * )
 * @OA\Tag(
 *     name="Tenant - Subscriptions",
 *     description="Tenant dashboard: Subscription management"
 * )
 * @OA\Tag(
 *     name="Tenant - Billing",
 *     description="Tenant dashboard: Invoices and payment methods"
 * )
 * @OA\Tag(
 *     name="Tenant - Activities",
 *     description="Tenant dashboard: Activity logs and sessions"
 * )
 * @OA\Tag(
 *     name="Webhooks",
 *     description="External callback endpoints"
 * )
 * @OA\Tag(
 *     name="Admin - Agent Categories",
 *     description="System admin: Agent category management"
 * )
 * @OA\Tag(
 *     name="Admin - Agent Endpoints",
 *     description="System admin: Agent webhook endpoint configuration"
 * )
 * @OA\Tag(
 *     name="Admin - Subscription Plans",
 *     description="System admin: Subscription plan management"
 * )
 * @OA\Tag(
 *     name="Admin - Activity Logs",
 *     description="System admin: Activity and audit logs"
 * )
 * @OA\Tag(
 *     name="Tenant - Users",
 *     description="Tenant dashboard: User management within tenant"
 * )
 * @OA\Tag(
 *     name="Tenant - Roles",
 *     description="Tenant dashboard: Role management"
 * )
 * @OA\Tag(
 *     name="Tenant - Permissions",
 *     description="Tenant dashboard: Permission management"
 * )
 * @OA\Tag(
 *     name="Tenant - Sessions",
 *     description="Tenant dashboard: Active session management"
 * )
 *
 * @OA\Schema(
 *     schema="SuccessResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Operation completed successfully"),
 *     @OA\Property(property="data", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="An error occurred"),
 *     @OA\Property(property="error", type="string", example="Error details")
 * )
 *
 * @OA\Schema(
 *     schema="ValidationErrorResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="The given data was invalid"),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         example={"email": {"The email field is required"}}
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PaginatedResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(property="data", type="array", @OA\Items(type="object")),
 *         @OA\Property(property="current_page", type="integer", example=1),
 *         @OA\Property(property="last_page", type="integer", example=10),
 *         @OA\Property(property="per_page", type="integer", example=20),
 *         @OA\Property(property="total", type="integer", example=200),
 *         @OA\Property(property="from", type="integer", example=1),
 *         @OA\Property(property="to", type="integer", example=20)
 *     )
 * )
 *
 * @OA\Response(
 *     response="Unauthorized",
 *     description="Unauthenticated - Invalid or missing JWT token",
 *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 * )
 *
 * @OA\Response(
 *     response="Forbidden",
 *     description="Forbidden - Insufficient permissions",
 *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 * )
 *
 * @OA\Response(
 *     response="NotFound",
 *     description="Resource not found",
 *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 * )
 *
 * @OA\Response(
 *     response="ValidationError",
 *     description="Validation error - Invalid input data",
 *     @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
 * )
 *
 * @OA\Response(
 *     response="ServerError",
 *     description="Internal server error",
 *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 * )
 */
abstract class Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
    use \Illuminate\Foundation\Validation\ValidatesRequests;
}
