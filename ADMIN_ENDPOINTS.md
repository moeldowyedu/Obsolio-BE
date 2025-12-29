# Admin Console API Endpoints

**Base Path:** `/api/v1/admin`
**Authentication:** Required (JWT + `system_admin` middleware)
**Guard:** `console`

---

## 1. TENANT MANAGEMENT

### List & Search
```
GET    /api/v1/admin/tenants
GET    /api/v1/admin/tenants/statistics
GET    /api/v1/admin/tenants/{id}
```

### CRUD Operations
```
POST   /api/v1/admin/tenants
PUT    /api/v1/admin/tenants/{id}
DELETE /api/v1/admin/tenants/{id}
```

### Status Management
```
PUT    /api/v1/admin/tenants/{id}/status
POST   /api/v1/admin/tenants/{id}/deactivate
POST   /api/v1/admin/tenants/{id}/reactivate
```

### Subscription Management
```
PUT    /api/v1/admin/tenants/{id}/subscription
GET    /api/v1/admin/tenants/{id}/subscription-history
POST   /api/v1/admin/tenants/{id}/extend-trial
```

---

## 2. USER MANAGEMENT

```
GET    /api/v1/admin/users
POST   /api/v1/admin/users
GET    /api/v1/admin/users/{id}
PUT    /api/v1/admin/users/{id}
DELETE /api/v1/admin/users/{id}
```

---

## 3. ORGANIZATION MANAGEMENT

### List & Statistics
```
GET    /api/v1/admin/organizations
GET    /api/v1/admin/organizations/statistics
GET    /api/v1/admin/organizations/{id}
```

### CRUD Operations
```
POST   /api/v1/admin/organizations
PUT    /api/v1/admin/organizations/{id}
```

### Status Management
```
POST   /api/v1/admin/organizations/{id}/deactivate
POST   /api/v1/admin/organizations/{id}/reactivate
```

---

## 4. SUBSCRIPTION MANAGEMENT

### List & Statistics
```
GET    /api/v1/admin/subscriptions
GET    /api/v1/admin/subscriptions/statistics
GET    /api/v1/admin/subscriptions/{id}
```

### CRUD Operations
```
POST   /api/v1/admin/subscriptions
PUT    /api/v1/admin/subscriptions/{id}
DELETE /api/v1/admin/subscriptions/{id}
```

### Actions
```
POST   /api/v1/admin/subscriptions/{id}/cancel
```

---

## 5. SUBSCRIPTION PLANS

```
GET    /api/v1/admin/subscription-plans
POST   /api/v1/admin/subscription-plans
PUT    /api/v1/admin/subscription-plans/{id}
DELETE /api/v1/admin/subscription-plans/{id}
```

---

## 6. AGENT CATEGORIES

```
GET    /api/v1/admin/agent-categories
POST   /api/v1/admin/agent-categories
PUT    /api/v1/admin/agent-categories/{id}
DELETE /api/v1/admin/agent-categories/{id}
```

---

## 7. AGENTS (Global Management)

### List & Search
```
GET    /api/v1/admin/agents
GET    /api/v1/admin/agents/{id}
```

### CRUD Operations
```
POST   /api/v1/admin/agents
PUT    /api/v1/admin/agents/{id}
DELETE /api/v1/admin/agents/{id}
```

### Bulk Actions
```
POST   /api/v1/admin/agents/bulk-activate
POST   /api/v1/admin/agents/bulk-deactivate
```

---

## 8. AGENT ENDPOINTS (Webhooks Configuration)

```
GET    /api/v1/admin/agent-endpoints
POST   /api/v1/admin/agent-endpoints
GET    /api/v1/admin/agent-endpoints/{id}
PUT    /api/v1/admin/agent-endpoints/{id}
DELETE /api/v1/admin/agent-endpoints/{id}
```

---

## 9. AGENT RUNS (Global Monitoring)

```
GET    /api/v1/admin/agent-runs
GET    /api/v1/admin/agent-runs/{id}
```

---

## 10. IMPERSONATION

### Manage Impersonations
```
GET    /api/v1/admin/impersonations
GET    /api/v1/admin/impersonations/{id}
POST   /api/v1/admin/impersonations/{id}/end
```

### Start Impersonation
```
POST   /api/v1/admin/tenants/{tenantId}/impersonations/start
```

---

## 11. ANALYTICS & REPORTS

```
GET    /api/v1/admin/analytics/overview
GET    /api/v1/admin/analytics/revenue
GET    /api/v1/admin/analytics/agents
```

---

## 12. ACTIVITY & AUDIT LOGS

```
GET    /api/v1/admin/activity-logs
GET    /api/v1/admin/impersonation-logs
```

---

## 13. ROLES & PERMISSIONS (Console)

### Permissions Catalog
```
GET    /api/v1/admin/permissions
```

### Roles Management
```
GET    /api/v1/admin/roles
```

---

## Summary

| Category | Endpoints | Description |
|----------|-----------|-------------|
| **Tenant Management** | 12 | Full CRUD + status + subscription management |
| **User Management** | 5 | Console user CRUD |
| **Organization Management** | 6 | CRUD + status management |
| **Subscription Management** | 7 | CRUD + statistics + cancellation |
| **Subscription Plans** | 4 | CRUD for pricing plans |
| **Agent Categories** | 4 | Category management |
| **Agents** | 7 | Global agent management + bulk actions |
| **Agent Endpoints** | 5 | Webhook configuration |
| **Agent Runs** | 2 | Global execution monitoring |
| **Impersonation** | 4 | Console → Tenant impersonation |
| **Analytics** | 3 | Business intelligence |
| **Audit Logs** | 2 | Activity tracking |
| **RBAC** | 2 | Console permissions & roles |

**Total Admin Endpoints:** **63**

---

## Authorization

All admin endpoints require:
1. **JWT Authentication** (`jwt.auth` middleware)
2. **System Admin Role** (`system_admin` middleware)
3. **Console Permissions** (`guard_name='console'`)

### Required Console Permissions:

#### Tenant Management
- `console.tenants.view`
- `console.tenants.create`
- `console.tenants.update`
- `console.tenants.delete`
- `console.tenants.manage_subscription`

#### User Management
- `console.users.view`
- `console.users.create`
- `console.users.update`
- `console.users.delete`

#### Agent Management
- `console.agents.view`
- `console.agents.create`
- `console.agents.update`
- `console.agents.delete`

#### Impersonation
- `support.impersonate`

#### Analytics
- `console.analytics.view`

---

## Controllers

| Controller | File Path |
|------------|-----------|
| **TenantManagementController** | `App\Http\Controllers\Api\V1\Admin\TenantManagementController` |
| **AdminController** | `App\Http\Controllers\Api\V1\AdminController` |
| **AdminOrganizationController** | `App\Http\Controllers\Api\V1\Admin\AdminOrganizationController` |
| **AdminSubscriptionController** | `App\Http\Controllers\Api\V1\Admin\AdminSubscriptionController` |
| **AdminAgentCategoryController** | `App\Http\Controllers\Api\V1\Admin\AdminAgentCategoryController` |
| **AdminAgentEndpointsController** | `App\Http\Controllers\Api\V1\Admin\AdminAgentEndpointsController` |
| **AdminAgentRunsController** | `App\Http\Controllers\Api\V1\Admin\AdminAgentRunsController` |
| **AdminImpersonationController** | `App\Http\Controllers\Api\V1\Admin\AdminImpersonationController` |

---

**Last Updated:** 2025-12-29
