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
- `/marketplace/*` - Public marketplace for browsing agents
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

Public endpoints (auth, marketplace) do not require authentication.
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
 *     url="/api/v1",
 *     description="OBSOLIO API v1"
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
 *     name="Marketplace",
 *     description="Public marketplace endpoints for browsing agents"
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
 */
abstract class Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
    use \Illuminate\Foundation\Validation\ValidatesRequests;
}
