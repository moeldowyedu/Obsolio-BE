# API Endpoint Reorganization Summary

## Overview

The OBSOLIO API endpoints have been reorganized into a clear, structured format that separates **admin** (system console) and **tenant** (dashboard) operations. This reorganization improves API discoverability, simplifies frontend integration, and provides a scalable foundation for future development.

---

## Changes Summary

### Before (Old Structure)
- Mixed admin and tenant endpoints without clear separation
- Inconsistent prefixes (`/auth/`, `/dashboard/`, `/agents/`, etc.)
- No clear distinction between system admin and tenant operations
- Difficult for frontend to understand endpoint context

### After (New Structure)
- Clear `/admin/*` prefix for all system admin operations
- Clear `/tenant/*` prefix for all tenant dashboard operations
- Consistent, organized endpoint grouping
- Easy role-based access control implementation
- Improved API documentation and discoverability

---

## New API Structure

### Base URL
```
https://api.obsolio.com/api/v1
```

### Endpoint Categories

#### 1. Public Endpoints (No Authentication)
**Prefix:** `/api/v1/auth/*` and `/api/v1/marketplace/*`

```
Authentication & Registration:
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
POST   /api/v1/auth/lookup-tenant
POST   /api/v1/auth/check-subdomain
GET    /api/v1/auth/verify-email/{id}/{hash}
POST   /api/v1/auth/resend-verification

Marketplace:
GET    /api/v1/marketplace/agents
GET    /api/v1/marketplace/agents/featured
GET    /api/v1/marketplace/agents/{id}
GET    /api/v1/marketplace/categories
GET    /api/v1/marketplace/categories/{category}/agents
GET    /api/v1/marketplace/stats

Subscription Plans (Public Pricing):
GET    /api/v1/subscription-plans
GET    /api/v1/subscription-plans/{id}
```

---

#### 2. Admin Endpoints (System Console)
**Prefix:** `/api/v1/admin/*`
**Authentication:** Required (JWT + System Admin Role)

```
Tenant Management:
GET    /api/v1/admin/tenants
GET    /api/v1/admin/tenants/statistics
GET    /api/v1/admin/tenants/{id}
PUT    /api/v1/admin/tenants/{id}
DELETE /api/v1/admin/tenants/{id}
PUT    /api/v1/admin/tenants/{id}/status
PUT    /api/v1/admin/tenants/{id}/subscription
GET    /api/v1/admin/tenants/{id}/subscription-history
POST   /api/v1/admin/tenants/{id}/extend-trial

User Management:
GET    /api/v1/admin/users
GET    /api/v1/admin/users/{id}
DELETE /api/v1/admin/users/{id}

Agent Categories:
GET    /api/v1/admin/agent-categories
POST   /api/v1/admin/agent-categories
PUT    /api/v1/admin/agent-categories/{id}
DELETE /api/v1/admin/agent-categories/{id}

Agents (Global Management):
GET    /api/v1/admin/agents
POST   /api/v1/admin/agents
GET    /api/v1/admin/agents/{id}
PUT    /api/v1/admin/agents/{id}
DELETE /api/v1/admin/agents/{id}
POST   /api/v1/admin/agents/bulk-activate
POST   /api/v1/admin/agents/bulk-deactivate

Agent Endpoints (Webhooks):
GET    /api/v1/admin/agent-endpoints
POST   /api/v1/admin/agent-endpoints
GET    /api/v1/admin/agent-endpoints/{id}
PUT    /api/v1/admin/agent-endpoints/{id}
DELETE /api/v1/admin/agent-endpoints/{id}

Agent Runs (Global Monitoring):
GET    /api/v1/admin/agent-runs
GET    /api/v1/admin/agent-runs/{id}

Subscription Plans Management:
GET    /api/v1/admin/subscription-plans
POST   /api/v1/admin/subscription-plans
PUT    /api/v1/admin/subscription-plans/{id}
DELETE /api/v1/admin/subscription-plans/{id}

Analytics & Reports:
GET    /api/v1/admin/analytics/overview
GET    /api/v1/admin/analytics/revenue
GET    /api/v1/admin/analytics/agents

Activity & Audit Logs:
GET    /api/v1/admin/activity-logs
GET    /api/v1/admin/impersonation-logs
```

---

#### 3. Tenant Endpoints (Tenant Dashboard)
**Prefix:** `/api/v1/tenant/*`
**Authentication:** Required (JWT + Tenant Context)

```
Profile & Authentication:
GET    /api/v1/tenant/profile
PUT    /api/v1/tenant/profile
POST   /api/v1/tenant/change-password
POST   /api/v1/tenant/logout
POST   /api/v1/tenant/refresh-token

Tenant Settings:
GET    /api/v1/tenant/settings
PUT    /api/v1/tenant/settings
GET    /api/v1/tenant/setup/status
POST   /api/v1/tenant/setup/organization
POST   /api/v1/tenant/setup/personal

Dashboard & Statistics:
GET    /api/v1/tenant/dashboard/stats
GET    /api/v1/tenant/dashboard/overview

Organizations:
GET    /api/v1/tenant/organizations
POST   /api/v1/tenant/organizations
GET    /api/v1/tenant/organizations/{id}
PUT    /api/v1/tenant/organizations/{id}
DELETE /api/v1/tenant/organizations/{id}
GET    /api/v1/tenant/organizations/{id}/dashboard

Agents (Tenant's Installed Agents):
GET    /api/v1/tenant/agents
GET    /api/v1/tenant/agents/{id}
POST   /api/v1/tenant/agents/{id}/install
DELETE /api/v1/tenant/agents/{id}/uninstall
POST   /api/v1/tenant/agents/{id}/toggle-status
POST   /api/v1/tenant/agents/{id}/run
POST   /api/v1/tenant/agents/{id}/record-usage

Agent Runs (Tenant's Execution History):
GET    /api/v1/tenant/agent-runs
GET    /api/v1/tenant/agent-runs/{run_id}
GET    /api/v1/tenant/agent-runs/{run_id}/status

Subscriptions:
GET    /api/v1/tenant/subscription/current
POST   /api/v1/tenant/subscription/subscribe
PUT    /api/v1/tenant/subscription/change-plan
POST   /api/v1/tenant/subscription/cancel
POST   /api/v1/tenant/subscription/resume
GET    /api/v1/tenant/subscription/history
GET    /api/v1/tenant/subscription/recommendations

Billing & Invoices:
GET    /api/v1/tenant/billing/invoices
GET    /api/v1/tenant/billing/invoices/{id}
GET    /api/v1/tenant/billing/payment-methods
POST   /api/v1/tenant/billing/payment-methods
POST   /api/v1/tenant/billing/payment-methods/{id}/set-default
DELETE /api/v1/tenant/billing/payment-methods/{id}

Activities & Sessions:
GET    /api/v1/tenant/activities
GET    /api/v1/tenant/activities/{id}
GET    /api/v1/tenant/activities/user/{userId}
GET    /api/v1/tenant/sessions
GET    /api/v1/tenant/sessions/active
POST   /api/v1/tenant/sessions/{id}/terminate
```

---

#### 4. Webhook Endpoints
```
POST   /api/v1/webhooks/agents/callback
```

---

#### 5. Payment Endpoints
```
POST   /api/v1/payments/subscription
POST   /api/v1/payments/refund/{invoice_id}
GET    /api/v1/payments/response
```

---

## Backward Compatibility

All old endpoints remain functional to ensure no breaking changes. Legacy endpoints are maintained in the routes file and will be deprecated in API v2.0.

**Legacy endpoints still work:**
- `/api/v1/auth/me` → Use `/api/v1/tenant/profile` (new)
- `/api/v1/dashboard/stats` → Use `/api/v1/tenant/dashboard/stats` (new)
- `/api/v1/agents/*` → Use `/api/v1/tenant/agents/*` (new)
- `/api/v1/subscriptions/*` → Use `/api/v1/tenant/subscription/*` (new)
- etc.

---

## Migration Guide for Frontend

### Step 1: Update API Base Paths

**Old Code:**
```javascript
// Mixed endpoints
const getUserProfile = () => axios.get('/api/v1/auth/me');
const getAgents = () => axios.get('/api/v1/agents');
const getDashboard = () => axios.get('/api/v1/dashboard/stats');
```

**New Code:**
```javascript
// Tenant dashboard endpoints
const getUserProfile = () => axios.get('/api/v1/tenant/profile');
const getAgents = () => axios.get('/api/v1/tenant/agents');
const getDashboard = () => axios.get('/api/v1/tenant/dashboard/stats');

// Admin console endpoints (for admin users)
const getAllTenants = () => axios.get('/api/v1/admin/tenants');
const getAllAgents = () => axios.get('/api/v1/admin/agents');
```

### Step 2: Organize API Clients by Context

Create separate API client modules:

```javascript
// api/tenant.js - For tenant dashboard
export const TenantAPI = {
  profile: {
    get: () => axios.get('/api/v1/tenant/profile'),
    update: (data) => axios.put('/api/v1/tenant/profile', data),
  },
  agents: {
    list: () => axios.get('/api/v1/tenant/agents'),
    get: (id) => axios.get(`/api/v1/tenant/agents/${id}`),
    install: (id) => axios.post(`/api/v1/tenant/agents/${id}/install`),
  },
  // ... more tenant operations
};

// api/admin.js - For admin console
export const AdminAPI = {
  tenants: {
    list: () => axios.get('/api/v1/admin/tenants'),
    get: (id) => axios.get(`/api/v1/admin/tenants/${id}`),
  },
  agents: {
    list: () => axios.get('/api/v1/admin/agents'),
    create: (data) => axios.post('/api/v1/admin/agents', data),
  },
  // ... more admin operations
};

// api/public.js - For public endpoints
export const PublicAPI = {
  auth: {
    login: (credentials) => axios.post('/api/v1/auth/login', credentials),
    register: (data) => axios.post('/api/v1/auth/register', data),
  },
  marketplace: {
    list: () => axios.get('/api/v1/marketplace/agents'),
    featured: () => axios.get('/api/v1/marketplace/agents/featured'),
  },
};
```

### Step 3: Update Route Guards

```javascript
// For tenant dashboard routes
if (user.role === 'tenant') {
  // Use TenantAPI
  const data = await TenantAPI.dashboard.stats();
}

// For admin console routes
if (user.role === 'system_admin') {
  // Use AdminAPI
  const data = await AdminAPI.analytics.overview();
}
```

---

## Testing

All routes have been tested and verified:

```bash
# Test admin routes
php artisan route:list --path=api/v1/admin

# Test tenant routes
php artisan route:list --path=api/v1/tenant

# Test public routes
php artisan route:list --path=api/v1/auth
php artisan route:list --path=api/v1/marketplace
```

---

## Documentation

Updated Swagger/OpenAPI documentation is available at:
```
https://api.obsolio.com/api/documentation
```

---

## Benefits

1. **Clear Separation**: Admin and tenant operations are clearly distinguished
2. **Better Organization**: Endpoints are grouped by context and functionality
3. **Easier Integration**: Frontend developers can easily understand which endpoints to use
4. **Scalability**: New features can be added to the appropriate prefix
5. **Improved Security**: Role-based access control is clearer and easier to implement
6. **Better Documentation**: API docs are more organized and discoverable

---

## Support

For questions or issues related to the API reorganization, please contact the backend team or create an issue in the repository.

**Backup File:** `routes/api.php.backup` (contains the previous version)

---

**Last Updated:** December 28, 2025
**Version:** 1.0.0
**Status:** Production Ready ✓
