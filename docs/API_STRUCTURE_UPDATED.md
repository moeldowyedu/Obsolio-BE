# OBSOLIO API Structure - Updated Documentation

## Overview

The OBSOLIO API documentation has been professionally organized to clearly distinguish between Admin Console and Tenant Dashboard endpoints. This document explains the new structure and how to access the documentation.

---

## 🎯 Documentation Access

### Complete API Documentation
**URL:** `/api/documentation`
**Description:** Full API documentation with all endpoints

### Admin Console Documentation
**URL:** `/api/documentation/admin`
**Description:** System administration endpoints for `console.obsolio.com`
**Audience:** System administrators managing the entire SaaS platform

### Tenant Dashboard Documentation
**URL:** `/api/documentation/tenant`
**Description:** Tenant-specific endpoints for `*.obsolio.com`
**Audience:** Tenant users managing their organization

---

## 🏗️ API Architecture

### Domain Structure

```
┌─────────────────────────────────────────────────────────┐
│                    OBSOLIO API v1                        │
└─────────────────────────────────────────────────────────┘
                           │
          ┌────────────────┼────────────────┐
          │                │                │
    ┌─────▼─────┐    ┌────▼────┐    ┌─────▼─────┐
    │  Public   │    │  Admin  │    │  Tenant   │
    │ Endpoints │    │ Console │    │ Dashboard │
    └───────────┘    └─────────┘    └───────────┘
         │                │                │
    ┌────▼────┐     ┌────▼────┐     ┌────▼────┐
    │ /auth/* │     │/admin/* │     │/tenant/*│
    │/pricing*│     │         │     │         │
    │/market* │     │         │     │         │
    └─────────┘     └─────────┘     └─────────┘
```

### Endpoint Namespaces

| Namespace | Domain | Purpose | Authentication |
|-----------|--------|---------|----------------|
| `/api/v1/auth/*` | Public | Registration, login, password reset | None |
| `/api/v1/pricing/*` | Public | Pricing plans, public marketplace | None |
| `/api/v1/marketplace/*` | Public | Browse agents, categories | None |
| `/api/v1/admin/*` | console.obsolio.com | System administration | JWT + admin role |
| `/api/v1/tenant/*` | *.obsolio.com | Tenant operations | JWT + tenant context |

---

## 📊 Server Configuration

The API supports multiple server environments:

### Admin Console (Production)
```
URL: https://console.obsolio.com/api/v1
Purpose: System administration
Access: System administrators only
```

### Tenant Dashboard (Production)
```
URL: https://{tenant}.obsolio.com/api/v1
Purpose: Tenant-specific operations
Variables:
  - tenant: Your subdomain (e.g., "acme-corp")
```

### Local Development
```
URL: http://localhost:8000/api/v1
Purpose: Development and testing
```

---

## 🔐 Authentication

### Bearer Token Authentication

All authenticated endpoints require a JWT Bearer token:

```http
Authorization: Bearer <your-jwt-token>
```

### Security Scheme
- **Type:** HTTP Bearer
- **Scheme:** Bearer
- **Format:** JWT
- **Header:** Authorization

### Obtaining Tokens

1. **Register:** `POST /api/v1/auth/register`
2. **Login:** `POST /api/v1/auth/login`
3. **Verify Email:** `GET /api/v1/auth/verify-email/{id}/{hash}`
4. **Use Token:** Include in `Authorization` header for all subsequent requests

---

## 📋 Endpoint Organization

### Public Endpoints (No Authentication)

#### Authentication (`/auth/*`)
- User registration and login
- Email verification
- Password reset
- Tenant lookup

#### Pricing (`/pricing/*`)
- **NEW:** `GET /api/v1/pricing/plans` - Get all subscription plans
- **NEW:** `GET /api/v1/pricing/plans/{id}` - Get specific plan
- **DEPRECATED:** `GET /api/v1/subscription-plans` (Use `/pricing/plans` instead)
- **DEPRECATED:** `GET /api/v1/subscription-plans/{id}` (Use `/pricing/plans/{id}` instead)

#### Marketplace (`/marketplace/*`)
- Browse agents
- View featured agents
- Search by category
- View marketplace statistics

---

### Admin Console Endpoints (`/admin/*`)

**Base URL:** `https://console.obsolio.com/api/v1/admin`
**Authentication:** Required (System Admin Role)

#### Tenant Management (`/admin/tenants/*`)
- List all tenants
- View tenant details
- Create/update/delete tenants
- Manage tenant subscriptions
- Activate/deactivate tenants
- View subscription history

#### User Management (`/admin/users/*`)
- List all users (across all tenants)
- Create/update/delete users
- Manage user permissions
- View user activity

#### Agent Management (`/admin/agents/*`)
- Global agent catalog
- Create/update/delete agents
- Manage agent categories
- Configure agent endpoints
- View agent runs (all tenants)

#### Organizations (`/admin/organizations/*`)
- List all organizations
- Manage organization settings
- View organization statistics

#### Subscriptions (`/admin/subscriptions/*`)
- Manage subscription instances
- Override subscription settings
- View subscription analytics

#### Analytics (`/admin/analytics/*`)
- System-wide statistics
- Revenue reports
- Usage metrics
- Tenant analytics

---

### Tenant Dashboard Endpoints (`/tenant/*` and tenant-specific)

**Base URL:** `https://{tenant}.obsolio.com/api/v1`
**Authentication:** Required (Tenant JWT Token)

#### Profile & Authentication
- `GET /api/v1/tenant/profile` - Get user profile
- `PUT /api/v1/tenant/profile` - Update profile
- `POST /api/v1/tenant/change-password` - Change password

#### Dashboard & Statistics
- `GET /api/v1/tenant/dashboard` - Dashboard overview
- `GET /api/v1/tenant/stats` - Statistics

#### Organizations
- `GET /api/v1/tenant/organizations` - List tenant organizations
- `POST /api/v1/tenant/organizations` - Create organization
- `PUT /api/v1/tenant/organizations/{id}` - Update organization

#### Agents
- `GET /api/v1/tenant/agents` - List installed agents
- `POST /api/v1/tenant/agents/install` - Install agent
- `DELETE /api/v1/tenant/agents/{id}` - Uninstall agent
- `PUT /api/v1/tenant/agents/{id}/configure` - Configure agent

#### Agent Runs
- `GET /api/v1/tenant/agent-runs` - List agent executions
- `POST /api/v1/tenant/agent-runs` - Execute agent
- `GET /api/v1/tenant/agent-runs/{id}` - Get execution details

#### Subscriptions & Billing
- `GET /api/v1/pricing/subscriptions/current` - Current subscription
- `POST /api/v1/pricing/subscriptions/upgrade` - Upgrade plan
- `POST /api/v1/pricing/subscriptions/cancel` - Cancel subscription
- `GET /api/v1/tenant/invoices` - List invoices
- `GET /api/v1/tenant/payment-methods` - Payment methods

#### Team Management
- `GET /api/v1/tenant/users` - List team members
- `POST /api/v1/tenant/users/invite` - Invite user
- `DELETE /api/v1/tenant/users/{id}` - Remove user
- `PUT /api/v1/tenant/users/{id}/role` - Update user role

#### Roles & Permissions
- `GET /api/v1/tenant/roles` - List roles
- `POST /api/v1/tenant/roles` - Create role
- `PUT /api/v1/tenant/roles/{id}` - Update role
- `GET /api/v1/tenant/permissions` - List permissions

#### Activity & Sessions
- `GET /api/v1/tenant/activities` - Activity log
- `GET /api/v1/tenant/sessions` - Active sessions
- `DELETE /api/v1/tenant/sessions/{id}` - Revoke session

---

## 🏷️ Swagger Tags

The API documentation is organized with the following tags:

### Public Tags
- **Authentication** - Registration, login, password management
- **Pricing** - Subscription plans and pricing
- **Marketplace** - Public agent marketplace

### Admin Console Tags
- **Admin - Tenants** - Tenant management
- **Admin - Users** - User management
- **Admin - Agents** - Global agent management
- **Admin - Agent Runs** - Agent execution monitoring
- **Admin - Organizations** - Organization management
- **Admin - Subscriptions** - Subscription management
- **Admin - Analytics** - Reports and analytics
- **Admin - Agent Categories** - Category management
- **Admin - Agent Endpoints** - Endpoint configuration
- **Admin - Subscription Plans** - Plan management
- **Admin - Activity Logs** - Audit logs

### Tenant Dashboard Tags
- **Tenant - Profile** - User profile
- **Tenant - Settings** - Tenant settings
- **Tenant - Dashboard** - Overview and stats
- **Tenant - Organizations** - Organization management
- **Tenant - Agents** - Installed agents
- **Tenant - Agent Runs** - Execution history
- **Tenant - Subscriptions** - Subscription management
- **Tenant - Billing** - Invoices and payments
- **Tenant - Activities** - Activity logs
- **Tenant - Users** - Team management
- **Tenant - Roles** - Role management
- **Tenant - Permissions** - Permission management
- **Tenant - Sessions** - Session management

---

## 🔄 Deprecated Endpoints

The following endpoints are deprecated and will be removed in v2.0:

### Migration Required

| Old Endpoint | New Endpoint | Status | Removal Date |
|--------------|--------------|--------|--------------|
| `GET /api/v1/subscription-plans` | `GET /api/v1/pricing/plans` | Deprecated | v2.0 |
| `GET /api/v1/subscription-plans/{id}` | `GET /api/v1/pricing/plans/{id}` | Deprecated | v2.0 |

### Deprecation Headers

Deprecated endpoints return the following headers:

```http
X-API-Deprecated: true
X-API-Deprecation-Info: Use /api/v1/pricing/plans instead. This endpoint will be removed in v2.0
```

### Migration Guide

See [API_ENDPOINT_MIGRATION.md](./API_ENDPOINT_MIGRATION.md) for complete migration instructions.

---

## 📖 Response Standards

### Success Response
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { }
}
```

### Error Response
```json
{
  "success": false,
  "message": "An error occurred",
  "error": "Error details"
}
```

### Validation Error Response
```json
{
  "success": false,
  "message": "The given data was invalid",
  "errors": {
    "email": ["The email field is required"]
  }
}
```

### Paginated Response
```json
{
  "success": true,
  "data": {
    "data": [],
    "current_page": 1,
    "last_page": 10,
    "per_page": 20,
    "total": 200,
    "from": 1,
    "to": 20
  }
}
```

---

## 🚀 Generating Swagger Documentation

### Generate All Documentation
```bash
php artisan l5-swagger:generate
```

### Generate Specific Documentation
```bash
# Admin Console only
php artisan l5-swagger:generate admin

# Tenant Dashboard only
php artisan l5-swagger:generate tenant

# Complete (default)
php artisan l5-swagger:generate default
```

### Access Documentation
- **Complete:** http://localhost:8000/api/documentation
- **Admin Console:** http://localhost:8000/api/documentation/admin
- **Tenant Dashboard:** http://localhost:8000/api/documentation/tenant

---

## 🔧 Configuration

Swagger configuration is located in `config/l5-swagger.php`:

### Documentation Sets

1. **default** - Complete API documentation
   - All controllers and endpoints
   - File: `api-docs.json`

2. **admin** - Admin Console documentation
   - Only admin controllers
   - File: `admin-api-docs.json`

3. **tenant** - Tenant Dashboard documentation
   - Only tenant controllers
   - File: `tenant-api-docs.json`

### Environment Variables

```env
L5_SWAGGER_GENERATE_ALWAYS=true    # Auto-generate in development
L5_SWAGGER_USE_ABSOLUTE_PATH=true  # Use absolute URLs
L5_FORMAT_TO_USE_FOR_DOCS=json     # json or yaml
```

---

## 📝 Best Practices

### For API Consumers

1. **Use the Correct Base URL**
   - Admin operations: `console.obsolio.com`
   - Tenant operations: `{tenant}.obsolio.com`

2. **Include Authentication Headers**
   ```http
   Authorization: Bearer <token>
   ```

3. **Migrate from Deprecated Endpoints**
   - Replace `/subscription-plans` with `/pricing/plans`
   - Update before v2.0 release

4. **Handle Rate Limiting**
   - Respect rate limit headers
   - Implement exponential backoff

### For Developers

1. **Add Swagger Annotations**
   - Document all new endpoints
   - Include request/response examples
   - Use appropriate tags

2. **Follow Naming Conventions**
   - Admin endpoints: `/api/v1/admin/*`
   - Tenant endpoints: `/api/v1/tenant/*`
   - Public endpoints: `/api/v1/auth/*`, `/api/v1/pricing/*`, `/api/v1/marketplace/*`

3. **Test Documentation**
   - Generate Swagger docs locally
   - Verify endpoints appear correctly
   - Test Try It Out functionality

---

## 📚 Related Documentation

- [Complete API Documentation](./API_DOCUMENTATION_COMPLETE.md) - Full endpoint reference
- [API Endpoint Migration](./API_ENDPOINT_MIGRATION.md) - Migration guide for deprecated endpoints
- [Default Agent Assignment](./DEFAULT_AGENT_ASSIGNMENT.md) - Auto-assignment feature
- [Agent Migrations Setup](./AGENT_MIGRATIONS_SETUP.md) - Agent tiers infrastructure

---

## ✅ Summary of Changes

### Configuration Updates
- ✅ Added three documentation sets (default, admin, tenant)
- ✅ Configured separate Swagger JSON files
- ✅ Set up distinct documentation URLs

### Controller Annotations
- ✅ Updated base Controller with comprehensive OpenAPI tags
- ✅ Added Admin Console and Tenant Dashboard server URLs
- ✅ Added "Pricing" tag for public pricing endpoints
- ✅ Marked deprecated endpoints with `deprecated=true`

### Endpoint Documentation
- ✅ Documented `/pricing/plans` endpoint (new)
- ✅ Documented `/pricing/plans/{id}` endpoint (new)
- ✅ Marked `/subscription-plans` as deprecated
- ✅ Marked `/subscription-plans/{id}` as deprecated
- ✅ Updated subscription endpoints to use "Tenant - Subscriptions" tag

### Professional Features
- ✅ Server variable for tenant subdomain
- ✅ Deprecation headers documented
- ✅ Complete response schemas
- ✅ Bearer auth security scheme
- ✅ Organized tags by domain (Admin vs Tenant)

---

## 🎯 Next Steps

1. **Generate Documentation**
   ```bash
   php artisan l5-swagger:generate
   ```

2. **Test Endpoints**
   - Access `/api/documentation`
   - Try the Admin Console docs at `/api/documentation/admin`
   - Try the Tenant Dashboard docs at `/api/documentation/tenant`

3. **Update Frontend**
   - Migrate from deprecated endpoints
   - Use new `/pricing/plans` endpoints
   - Update base URLs for admin vs tenant

4. **Monitor Usage**
   - Track deprecation header responses
   - Plan for v2.0 removal of old endpoints

---

**Documentation Version:** 1.1.0
**Last Updated:** 2026-01-15
**Status:** ✅ Production Ready
