# OBSOLIO API Documentation v1.0

## 🌐 Domain Structure

OBSOLIO uses a multi-tenant architecture with two distinct dashboards:

### **Admin Console Dashboard**
- **Domain:** `console.obsolio.com`
- **Purpose:** System administration and management
- **Access:** System administrators only
- **Endpoints:** `/api/v1/admin/*`

### **Tenant Dashboard**
- **Domain:** `*.obsolio.com` (e.g., `akwadna.obsolio.com`)
- **Purpose:** Tenant-specific operations and management
- **Access:** Tenant users (based on roles and permissions)
- **Endpoints:** `/api/v1/tenant/*`

### **Public Endpoints**
- **Domain:** Any (no authentication required)
- **Purpose:** Registration, marketplace browsing, pricing information
- **Endpoints:** `/api/v1/auth/*`, `/api/v1/marketplace/*`, `/api/v1/pricing/*`

---

## 📋 Table of Contents

1. [Public Endpoints](#public-endpoints)
   - [Authentication](#authentication)
   - [Marketplace](#marketplace)
   - [Pricing & Plans](#pricing--plans)
2. [Admin Console Endpoints](#admin-console-endpoints)
   - [Tenant Management](#tenant-management)
   - [User Management](#user-management)
   - [Agent Management](#agent-management)
   - [Subscription Management](#subscription-management)
   - [Analytics & Reports](#analytics--reports)
   - [Impersonation](#impersonation)
3. [Tenant Dashboard Endpoints](#tenant-dashboard-endpoints)
   - [Profile & Settings](#profile--settings)
   - [Dashboard & Statistics](#dashboard--statistics)
   - [Organization Management](#organization-management)
   - [Agents & Executions](#agents--executions)
   - [Subscriptions & Billing](#subscriptions--billing)
   - [Team Management](#team-management)
   - [Roles & Permissions](#roles--permissions)

---

## 🔓 Public Endpoints

### Authentication

**Base URL:** `https://api.obsolio.com/api/v1/auth`

#### Register New Account

```http
POST /api/v1/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!",
  "phone": "+201234567890",
  "country": "Egypt",
  "tenant_type": "organization",
  "organization_name": "Akwadna",
  "subdomain": "akwadna"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Registration successful. Please verify your email.",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "tenant_id": "akwadna",
      "status": "pending_verification"
    },
    "tenant": {
      "id": "akwadna",
      "name": "Akwadna",
      "subdomain": "akwadna",
      "domain": "akwadna.obsolio.com",
      "status": "pending_verification"
    }
  }
}
```

---

#### Login

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "SecurePassword123!",
  "tenant_id": "akwadna"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "bearer",
    "expires_in": 3600,
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "tenant_id": "akwadna"
    }
  }
}
```

---

#### Verify Email

```http
GET /api/v1/auth/verify-email/{id}/{hash}
```

**Response:**
```json
{
  "success": true,
  "message": "Email verified successfully",
  "data": {
    "user": {
      "id": 1,
      "email_verified_at": "2026-01-15T10:30:00Z",
      "status": "active"
    },
    "subscription": {
      "id": "uuid",
      "plan": "Free Plan",
      "status": "trialing",
      "trial_ends_at": "2026-01-29T10:30:00Z"
    },
    "invoice": {
      "invoice_number": "INV-20260115-ABC12",
      "total": 0.00,
      "status": "paid"
    },
    "assigned_agents": [
      {
        "id": "uuid",
        "name": "Email Assistant",
        "tier": "Basic"
      },
      {
        "id": "uuid",
        "name": "Task Manager",
        "tier": "Basic"
      }
    ]
  }
}
```

---

#### Password Reset

```http
POST /api/v1/auth/forgot-password
Content-Type: application/json

{
  "email": "john@example.com"
}
```

```http
POST /api/v1/auth/reset-password
Content-Type: application/json

{
  "email": "john@example.com",
  "token": "reset-token",
  "password": "NewPassword123!",
  "password_confirmation": "NewPassword123!"
}
```

---

#### Check Subdomain Availability

```http
GET /api/v1/auth/tenants/check-availability/{subdomain}
```

**Response:**
```json
{
  "success": true,
  "available": true,
  "subdomain": "akwadna"
}
```

---

### Marketplace

**Base URL:** `https://api.obsolio.com/api/v1/marketplace`

#### Browse Agents

```http
GET /api/v1/marketplace/agents
```

**Query Parameters:**
- `category` (optional): Filter by category ID
- `tier` (optional): Filter by tier (basic, professional, specialized, enterprise)
- `search` (optional): Search by name or description
- `per_page` (optional): Items per page (default: 15)
- `page` (optional): Page number

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "name": "Email Assistant",
      "slug": "email-assistant",
      "description": "Automate email responses and management",
      "tier": {
        "id": 1,
        "name": "Basic",
        "description": "Simple, repetitive tasks"
      },
      "price_model": "free",
      "monthly_price": 0.00,
      "is_featured": true,
      "categories": ["Productivity", "Communication"]
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 50,
    "per_page": 15
  }
}
```

---

#### Featured Agents

```http
GET /api/v1/marketplace/agents/featured
```

---

#### Agent Details

```http
GET /api/v1/marketplace/agents/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "name": "Email Assistant",
    "slug": "email-assistant",
    "description": "Automate email responses and management",
    "long_description": "Detailed description...",
    "tier": {
      "id": 1,
      "name": "Basic"
    },
    "price_model": "free",
    "monthly_price": 0.00,
    "capabilities": {
      "max_emails_per_day": 100,
      "supports_attachments": true
    },
    "supported_languages": ["en", "ar"],
    "categories": [
      {
        "id": "uuid",
        "name": "Productivity",
        "slug": "productivity"
      }
    ],
    "stats": {
      "total_users": 1250,
      "rating": 4.8
    }
  }
}
```

---

#### Browse Categories

```http
GET /api/v1/marketplace/categories
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "name": "Productivity",
      "slug": "productivity",
      "description": "Boost your team's productivity",
      "agent_count": 15,
      "icon": "icon-url"
    }
  ]
}
```

---

#### Agents by Category

```http
GET /api/v1/marketplace/categories/{category}/agents
```

---

### Pricing & Plans

**Base URL:** `https://api.obsolio.com/api/v1/pricing`

#### List Subscription Plans

```http
GET /api/v1/pricing/plans
```

**Query Parameters:**
- `type` (optional): personal | organization

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "name": "Free Plan",
      "type": "organization",
      "tier": "free",
      "price_monthly": 0.00,
      "price_annual": 0.00,
      "trial_days": 14,
      "max_users": 5,
      "max_agents": 2,
      "included_executions": 1000,
      "features": {
        "custom_agents": false,
        "api_access": true,
        "priority_support": false
      },
      "highlight_features": [
        "2 Pre-built Agents",
        "1,000 Executions/month",
        "Email Support"
      ]
    },
    {
      "id": "uuid",
      "name": "Pro Plan",
      "type": "organization",
      "tier": "pro",
      "price_monthly": 49.99,
      "price_annual": 499.99,
      "savings_percentage": 17,
      "trial_days": 30,
      "max_users": 25,
      "max_agents": 10,
      "included_executions": 10000,
      "overage_price_per_execution": 0.01,
      "features": {
        "custom_agents": true,
        "api_access": true,
        "priority_support": true,
        "analytics": true
      },
      "highlight_features": [
        "10 AI Agents",
        "10,000 Executions/month",
        "Priority Support",
        "Advanced Analytics"
      ]
    }
  ]
}
```

---

#### Plan Details

```http
GET /api/v1/pricing/plans/{id}
```

---

## 🔐 Admin Console Endpoints

**Base URL:** `https://console.obsolio.com/api/v1/admin`
**Authentication:** Required (JWT Token + System Admin Role)
**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

---

### Tenant Management

#### List All Tenants

```http
GET /api/v1/admin/tenants
```

**Query Parameters:**
- `status` (optional): active | inactive | suspended | pending_verification
- `type` (optional): personal | organization
- `search` (optional): Search by name, subdomain, or email
- `per_page` (optional): Default 15
- `sort_by` (optional): created_at | name | status
- `sort_order` (optional): asc | desc

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "akwadna",
      "name": "Akwadna",
      "subdomain": "akwadna",
      "domain": "akwadna.obsolio.com",
      "type": "organization",
      "status": "active",
      "subscription": {
        "plan": "Pro Plan",
        "status": "active",
        "trial_ends_at": null
      },
      "organization": {
        "industry": "Technology",
        "company_size": "11-50"
      },
      "stats": {
        "users_count": 12,
        "agents_count": 8,
        "executions_this_month": 4500
      },
      "created_at": "2026-01-10T10:00:00Z",
      "last_activity": "2026-01-15T14:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 150,
    "per_page": 15
  }
}
```

---

#### Get Tenant Statistics

```http
GET /api/v1/admin/tenants/statistics
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_tenants": 150,
    "active_tenants": 120,
    "trial_tenants": 25,
    "suspended_tenants": 5,
    "by_type": {
      "personal": 50,
      "organization": 100
    },
    "new_this_month": 15,
    "churn_rate": 2.5,
    "avg_subscription_value": 45.50
  }
}
```

---

#### View Tenant Details

```http
GET /api/v1/admin/tenants/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "akwadna",
    "name": "Akwadna",
    "subdomain": "akwadna",
    "domain": "akwadna.obsolio.com",
    "type": "organization",
    "status": "active",
    "created_at": "2026-01-10T10:00:00Z",
    "verified_at": "2026-01-10T10:15:00Z",
    "subscription": {
      "id": "uuid",
      "plan": {
        "id": "uuid",
        "name": "Pro Plan",
        "tier": "pro"
      },
      "status": "active",
      "starts_at": "2026-01-10T10:15:00Z",
      "current_period_end": "2026-02-10T10:15:00Z",
      "billing_cycle": "monthly"
    },
    "organization": {
      "id": "uuid",
      "name": "Akwadna",
      "industry": "Technology",
      "company_size": "11-50",
      "country": "Egypt",
      "timezone": "Africa/Cairo"
    },
    "stats": {
      "users_count": 12,
      "agents_count": 8,
      "executions_total": 45000,
      "executions_this_month": 4500,
      "storage_used_gb": 2.5,
      "api_calls_this_month": 15000
    },
    "owner": {
      "id": 1,
      "name": "John Doe",
      "email": "john@akwadna.com"
    }
  }
}
```

---

#### Create Tenant (Admin)

```http
POST /api/v1/admin/tenants
Content-Type: application/json

{
  "name": "New Company",
  "subdomain": "newcompany",
  "type": "organization",
  "owner_email": "owner@newcompany.com",
  "owner_name": "Jane Smith",
  "owner_phone": "+201234567890",
  "plan_id": "uuid",
  "skip_trial": false
}
```

---

#### Update Tenant

```http
PUT /api/v1/admin/tenants/{id}
Content-Type: application/json

{
  "name": "Updated Name",
  "status": "active"
}
```

---

#### Update Tenant Status

```http
PUT /api/v1/admin/tenants/{id}/status
Content-Type: application/json

{
  "status": "suspended",
  "reason": "Payment failure"
}
```

---

#### Deactivate Tenant

```http
POST /api/v1/admin/tenants/{id}/deactivate
Content-Type: application/json

{
  "reason": "Violation of terms"
}
```

---

#### Reactivate Tenant

```http
POST /api/v1/admin/tenants/{id}/reactivate
```

---

#### Change Tenant Subscription

```http
PUT /api/v1/admin/tenants/{id}/subscription
Content-Type: application/json

{
  "plan_id": "uuid",
  "billing_cycle": "annual",
  "skip_payment": false
}
```

---

#### Extend Trial Period

```http
POST /api/v1/admin/tenants/{id}/extend-trial
Content-Type: application/json

{
  "days": 14,
  "reason": "Customer request"
}
```

---

#### Subscription History

```http
GET /api/v1/admin/tenants/{id}/subscription-history
```

---

### User Management

#### List All Users

```http
GET /api/v1/admin/users
```

**Query Parameters:**
- `tenant_id` (optional): Filter by tenant
- `status` (optional): active | inactive | suspended
- `is_system_admin` (optional): true | false
- `search` (optional): Search by name or email
- `per_page` (optional): Default 15

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@akwadna.com",
      "tenant": {
        "id": "akwadna",
        "name": "Akwadna"
      },
      "status": "active",
      "is_system_admin": false,
      "last_login_at": "2026-01-15T14:30:00Z",
      "created_at": "2026-01-10T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 500,
    "per_page": 15
  }
}
```

---

#### View User Details

```http
GET /api/v1/admin/users/{id}
```

---

#### Create User

```http
POST /api/v1/admin/users
Content-Type: application/json

{
  "name": "New User",
  "email": "newuser@example.com",
  "password": "SecurePassword123!",
  "tenant_id": "akwadna",
  "role": "member",
  "is_system_admin": false
}
```

---

#### Update User

```http
PUT /api/v1/admin/users/{id}
Content-Type: application/json

{
  "name": "Updated Name",
  "status": "active",
  "is_system_admin": false
}
```

---

#### Delete User

```http
DELETE /api/v1/admin/users/{id}
```

---

### Agent Management

#### List All Agents

```http
GET /api/v1/admin/agents
```

**Query Parameters:**
- `tier_id` (optional): Filter by tier
- `is_active` (optional): true | false
- `is_featured` (optional): true | false
- `price_model` (optional): free | subscription | usage_based
- `search` (optional): Search by name
- `per_page` (optional): Default 15

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "name": "Email Assistant",
      "slug": "email-assistant",
      "tier": {
        "id": 1,
        "name": "Basic"
      },
      "price_model": "free",
      "monthly_price": 0.00,
      "is_active": true,
      "is_featured": true,
      "runtime_type": "n8n",
      "stats": {
        "total_subscriptions": 1250,
        "executions_this_month": 45000
      },
      "created_at": "2025-12-01T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 50,
    "per_page": 15
  }
}
```

---

#### Create Agent

```http
POST /api/v1/admin/agents
Content-Type: application/json

{
  "name": "New AI Agent",
  "slug": "new-ai-agent",
  "description": "Short description",
  "long_description": "Detailed description",
  "tier_id": 1,
  "price_model": "free",
  "monthly_price": 0.00,
  "runtime_type": "n8n",
  "execution_timeout_ms": 30000,
  "capabilities": {
    "max_requests_per_day": 100
  },
  "supported_languages": ["en", "ar"],
  "is_active": true,
  "is_featured": false,
  "category_ids": ["uuid1", "uuid2"]
}
```

---

#### Update Agent

```http
PUT /api/v1/admin/agents/{id}
Content-Type: application/json

{
  "name": "Updated Name",
  "is_active": true,
  "is_featured": true
}
```

---

#### Delete Agent

```http
DELETE /api/v1/admin/agents/{id}
```

---

#### Bulk Activate Agents

```http
POST /api/v1/admin/agents/bulk-activate
Content-Type: application/json

{
  "agent_ids": ["uuid1", "uuid2", "uuid3"]
}
```

---

#### Bulk Deactivate Agents

```http
POST /api/v1/admin/agents/bulk-deactivate
Content-Type: application/json

{
  "agent_ids": ["uuid1", "uuid2"]
}
```

---

### Agent Categories

#### List Categories

```http
GET /api/v1/admin/agent-categories
```

---

#### Create Category

```http
POST /api/v1/admin/agent-categories
Content-Type: application/json

{
  "name": "Marketing",
  "slug": "marketing",
  "description": "Marketing automation agents",
  "parent_id": null,
  "display_order": 5,
  "icon": "icon-url"
}
```

---

#### Update Category

```http
PUT /api/v1/admin/agent-categories/{id}
Content-Type: application/json

{
  "name": "Updated Name",
  "display_order": 10
}
```

---

#### Delete Category

```http
DELETE /api/v1/admin/agent-categories/{id}
```

---

### Agent Endpoints Management

#### List Agent Endpoints

```http
GET /api/v1/admin/agent-endpoints
```

**Query Parameters:**
- `agent_id` (optional): Filter by agent
- `type` (optional): trigger | callback
- `is_active` (optional): true | false

---

#### Create Agent Endpoint

```http
POST /api/v1/admin/agent-endpoints
Content-Type: application/json

{
  "agent_id": "uuid",
  "type": "trigger",
  "url": "https://n8n.obsolio.com/webhook/email-assistant",
  "secret": "generated-secret-token",
  "is_active": true
}
```

---

#### Update Agent Endpoint

```http
PUT /api/v1/admin/agent-endpoints/{id}
Content-Type: application/json

{
  "url": "https://new-url.com",
  "is_active": true
}
```

---

#### Delete Agent Endpoint

```http
DELETE /api/v1/admin/agent-endpoints/{id}
```

---

### Agent Runs (Global Monitoring)

#### List All Agent Runs

```http
GET /api/v1/admin/agent-runs
```

**Query Parameters:**
- `agent_id` (optional): Filter by agent
- `tenant_id` (optional): Filter by tenant
- `status` (optional): pending | running | completed | failed
- `date_from` (optional): Start date (YYYY-MM-DD)
- `date_to` (optional): End date (YYYY-MM-DD)
- `per_page` (optional): Default 50

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "agent": {
        "id": "uuid",
        "name": "Email Assistant"
      },
      "tenant": {
        "id": "akwadna",
        "name": "Akwadna"
      },
      "status": "completed",
      "started_at": "2026-01-15T14:30:00Z",
      "completed_at": "2026-01-15T14:30:15Z",
      "duration_ms": 15000,
      "error": null
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 10000,
    "per_page": 50
  }
}
```

---

#### View Run Details

```http
GET /api/v1/admin/agent-runs/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "agent_id": "uuid",
    "tenant_id": "akwadna",
    "status": "completed",
    "input": {
      "email_subject": "Test",
      "recipient": "test@example.com"
    },
    "output": {
      "success": true,
      "message_id": "msg-123"
    },
    "error": null,
    "events": [
      {
        "event": "accepted",
        "created_at": "2026-01-15T14:30:00Z"
      },
      {
        "event": "running",
        "created_at": "2026-01-15T14:30:01Z"
      },
      {
        "event": "completed",
        "created_at": "2026-01-15T14:30:15Z"
      }
    ],
    "created_at": "2026-01-15T14:30:00Z",
    "started_at": "2026-01-15T14:30:01Z",
    "completed_at": "2026-01-15T14:30:15Z"
  }
}
```

---

### Subscription Management

#### List All Subscriptions

```http
GET /api/v1/admin/subscriptions
```

**Query Parameters:**
- `status` (optional): trialing | active | past_due | canceled | suspended
- `plan_id` (optional): Filter by plan
- `tenant_id` (optional): Filter by tenant
- `per_page` (optional): Default 15

---

#### Subscription Statistics

```http
GET /api/v1/admin/subscriptions/statistics
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_subscriptions": 150,
    "active_subscriptions": 120,
    "trial_subscriptions": 25,
    "canceled_subscriptions": 5,
    "by_plan": {
      "free": 50,
      "pro": 70,
      "enterprise": 30
    },
    "mrr": 5000.00,
    "arr": 60000.00,
    "churn_rate": 2.5,
    "new_this_month": 15
  }
}
```

---

#### View Subscription

```http
GET /api/v1/admin/subscriptions/{id}
```

---

#### Update Subscription

```http
PUT /api/v1/admin/subscriptions/{id}
Content-Type: application/json

{
  "status": "active",
  "trial_ends_at": "2026-02-15T00:00:00Z"
}
```

---

#### Cancel Subscription

```http
POST /api/v1/admin/subscriptions/{id}/cancel
Content-Type: application/json

{
  "reason": "Customer request",
  "immediate": false
}
```

---

### Subscription Plans Management

#### List Plans

```http
GET /api/v1/admin/subscription-plans
```

---

#### Create Plan

```http
POST /api/v1/admin/subscription-plans
Content-Type: application/json

{
  "name": "Starter Plan",
  "type": "organization",
  "tier": "starter",
  "price_monthly": 29.99,
  "price_annual": 299.99,
  "trial_days": 14,
  "max_users": 10,
  "max_agents": 5,
  "included_executions": 5000,
  "overage_price_per_execution": 0.005,
  "features": {
    "api_access": true,
    "custom_agents": false
  },
  "highlight_features": [
    "5 AI Agents",
    "5,000 Executions/month",
    "Email Support"
  ],
  "is_active": true,
  "is_published": true,
  "display_order": 2
}
```

---

#### Update Plan

```http
PUT /api/v1/admin/subscription-plans/{id}
Content-Type: application/json

{
  "price_monthly": 34.99,
  "is_published": true
}
```

---

#### Delete Plan

```http
DELETE /api/v1/admin/subscription-plans/{id}
```

---

### Organization Management

#### List Organizations

```http
GET /api/v1/admin/organizations
```

**Query Parameters:**
- `industry` (optional): Filter by industry
- `company_size` (optional): Filter by company size
- `country` (optional): Filter by country
- `search` (optional): Search by name
- `per_page` (optional): Default 15

---

#### Organization Statistics

```http
GET /api/v1/admin/organizations/statistics
```

---

#### View Organization

```http
GET /api/v1/admin/organizations/{id}
```

---

#### Create Organization

```http
POST /api/v1/admin/organizations
Content-Type: application/json

{
  "tenant_id": "akwadna",
  "name": "Akwadna",
  "industry": "Technology",
  "company_size": "11-50",
  "country": "Egypt",
  "timezone": "Africa/Cairo"
}
```

---

#### Update Organization

```http
PUT /api/v1/admin/organizations/{id}
Content-Type: application/json

{
  "industry": "Technology",
  "company_size": "51-200"
}
```

---

#### Deactivate Organization

```http
POST /api/v1/admin/organizations/{id}/deactivate
```

---

#### Reactivate Organization

```http
POST /api/v1/admin/organizations/{id}/reactivate
```

---

### Analytics & Reports

#### Analytics Overview

```http
GET /api/v1/admin/analytics/overview
```

**Response:**
```json
{
  "success": true,
  "data": {
    "tenants": {
      "total": 150,
      "active": 120,
      "new_this_month": 15,
      "growth_rate": 11.1
    },
    "subscriptions": {
      "active": 120,
      "trial": 25,
      "mrr": 5000.00,
      "mrr_growth": 8.5
    },
    "agents": {
      "total_executions": 500000,
      "executions_this_month": 50000,
      "avg_per_tenant": 416.67,
      "most_popular": [
        {
          "name": "Email Assistant",
          "executions": 15000
        }
      ]
    },
    "revenue": {
      "this_month": 5000.00,
      "last_month": 4600.00,
      "growth": 8.7
    }
  }
}
```

---

#### Revenue Analytics

```http
GET /api/v1/admin/analytics/revenue
```

**Query Parameters:**
- `period` (optional): month | quarter | year | custom
- `start_date` (optional): YYYY-MM-DD
- `end_date` (optional): YYYY-MM-DD

**Response:**
```json
{
  "success": true,
  "data": {
    "total_revenue": 60000.00,
    "mrr": 5000.00,
    "arr": 60000.00,
    "by_plan": [
      {
        "plan": "Pro Plan",
        "revenue": 35000.00,
        "subscriptions": 70
      }
    ],
    "trend": [
      {
        "period": "2026-01",
        "revenue": 5000.00
      },
      {
        "period": "2025-12",
        "revenue": 4600.00
      }
    ],
    "projections": {
      "next_month": 5400.00,
      "end_of_year": 65000.00
    }
  }
}
```

---

#### Agent Analytics

```http
GET /api/v1/admin/analytics/agents
```

**Query Parameters:**
- `period` (optional): day | week | month | year
- `agent_id` (optional): Filter by specific agent

**Response:**
```json
{
  "success": true,
  "data": {
    "total_executions": 500000,
    "executions_this_period": 50000,
    "success_rate": 98.5,
    "avg_duration_ms": 5000,
    "by_agent": [
      {
        "agent": {
          "id": "uuid",
          "name": "Email Assistant"
        },
        "executions": 150000,
        "success_rate": 99.2,
        "avg_duration_ms": 3000,
        "unique_users": 1250
      }
    ],
    "by_tier": {
      "basic": 300000,
      "professional": 150000,
      "specialized": 40000,
      "enterprise": 10000
    },
    "trend": [
      {
        "date": "2026-01-15",
        "executions": 2500
      }
    ]
  }
}
```

---

### Activity & Audit Logs

#### Activity Logs

```http
GET /api/v1/admin/activity-logs
```

**Query Parameters:**
- `user_id` (optional): Filter by user
- `tenant_id` (optional): Filter by tenant
- `activity_type` (optional): Filter by type
- `date_from` (optional): Start date
- `date_to` (optional): End date
- `per_page` (optional): Default 50

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@akwadna.com"
      },
      "tenant": {
        "id": "akwadna",
        "name": "Akwadna"
      },
      "activity_type": "subscription.changed",
      "action": "update",
      "entity_type": "Subscription",
      "entity_id": "uuid",
      "description": "Changed subscription from Free to Pro",
      "ip_address": "192.168.1.1",
      "user_agent": "Mozilla/5.0...",
      "created_at": "2026-01-15T14:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 50000,
    "per_page": 50
  }
}
```

---

#### Impersonation Logs

```http
GET /api/v1/admin/impersonation-logs
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "impersonator": {
        "id": 5,
        "name": "Support Admin",
        "email": "support@obsolio.com"
      },
      "tenant": {
        "id": "akwadna",
        "name": "Akwadna"
      },
      "started_at": "2026-01-15T14:00:00Z",
      "ended_at": "2026-01-15T14:30:00Z",
      "duration_minutes": 30,
      "ip_address": "192.168.1.1",
      "reason": "Customer support request"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 100,
    "per_page": 15
  }
}
```

---

### Impersonation (Support Access)

#### List Impersonations

```http
GET /api/v1/admin/impersonations
```

---

#### Start Impersonation

```http
POST /api/v1/admin/tenants/{tenantId}/impersonations/start
Content-Type: application/json

{
  "reason": "Customer support request - Ticket #1234"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "impersonation_id": 1,
    "token": "impersonation-jwt-token",
    "tenant": {
      "id": "akwadna",
      "name": "Akwadna",
      "subdomain": "akwadna"
    },
    "expires_at": "2026-01-15T18:00:00Z",
    "redirect_url": "https://akwadna.obsolio.com?impersonation_token=..."
  }
}
```

---

#### View Impersonation

```http
GET /api/v1/admin/impersonations/{id}
```

---

#### End Impersonation

```http
POST /api/v1/admin/impersonations/{id}/end
```

---

### Permissions & Roles (Console)

#### List Permissions

```http
GET /api/v1/admin/permissions
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "tenants.view",
      "guard_name": "console",
      "description": "View tenant list"
    },
    {
      "id": 2,
      "name": "tenants.manage",
      "guard_name": "console",
      "description": "Manage tenants"
    }
  ]
}
```

---

#### List Roles

```http
GET /api/v1/admin/roles
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Super Admin",
      "guard_name": "console",
      "permissions_count": 106,
      "users_count": 2
    },
    {
      "id": 2,
      "name": "Support",
      "guard_name": "console",
      "permissions_count": 25,
      "users_count": 5
    }
  ]
}
```

---

## 👥 Tenant Dashboard Endpoints

**Base URL:** `https://{tenant}.obsolio.com/api/v1/tenant`
**Example:** `https://akwadna.obsolio.com/api/v1/tenant`
**Authentication:** Required (JWT Token)
**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
X-Tenant-Id: {tenant_id}
```

---

### Profile & Authentication

#### Get Profile

```http
GET /api/v1/tenant/profile
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@akwadna.com",
    "phone": "+201234567890",
    "country": "Egypt",
    "avatar_url": null,
    "tenant": {
      "id": "akwadna",
      "name": "Akwadna",
      "subdomain": "akwadna"
    },
    "roles": [
      {
        "id": 1,
        "name": "Owner"
      }
    ],
    "permissions": [
      "members.invite",
      "agents.execute",
      "billing.manage"
    ],
    "last_login_at": "2026-01-15T14:30:00Z",
    "email_verified_at": "2026-01-10T10:15:00Z"
  }
}
```

---

#### Update Profile

```http
PUT /api/v1/tenant/profile
Content-Type: application/json

{
  "name": "John Smith",
  "phone": "+201234567890",
  "avatar_url": "https://..."
}
```

---

#### Change Password

```http
POST /api/v1/tenant/change-password
Content-Type: application/json

{
  "current_password": "OldPassword123!",
  "new_password": "NewPassword123!",
  "new_password_confirmation": "NewPassword123!"
}
```

---

#### Logout

```http
POST /api/v1/tenant/logout
```

---

#### Refresh Token

```http
POST /api/v1/tenant/refresh-token
```

---

### Tenant Settings

#### Get Settings

```http
GET /api/v1/tenant/settings
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "akwadna",
    "name": "Akwadna",
    "subdomain": "akwadna",
    "domain": "akwadna.obsolio.com",
    "type": "organization",
    "status": "active",
    "created_at": "2026-01-10T10:00:00Z",
    "settings": {
      "timezone": "Africa/Cairo",
      "language": "en",
      "notifications": {
        "email": true,
        "slack": false
      }
    }
  }
}
```

---

#### Update Settings

```http
PUT /api/v1/tenant/settings
Content-Type: application/json

{
  "name": "Akwadna Technologies",
  "settings": {
    "timezone": "Africa/Cairo",
    "language": "ar"
  }
}
```

---

### Dashboard & Statistics

#### Dashboard Stats

```http
GET /api/v1/tenant/dashboard/stats
```

**Response:**
```json
{
  "success": true,
  "data": {
    "subscription": {
      "plan": "Pro Plan",
      "status": "active",
      "next_billing_date": "2026-02-10",
      "trial_ends_at": null
    },
    "agents": {
      "total": 8,
      "active": 7,
      "executions_this_month": 4500,
      "quota_remaining": 5500
    },
    "users": {
      "total": 12,
      "active": 10
    },
    "usage": {
      "executions_used": 4500,
      "executions_included": 10000,
      "percentage_used": 45,
      "overage": 0
    },
    "recent_activity": [
      {
        "type": "agent.executed",
        "agent": "Email Assistant",
        "user": "John Doe",
        "timestamp": "2026-01-15T14:30:00Z"
      }
    ]
  }
}
```

---

#### Dashboard Overview

```http
GET /api/v1/tenant/dashboard/overview
```

---

### Organization Management

#### Get Organization

```http
GET /api/v1/tenant/organization
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "name": "Akwadna",
    "short_name": "AKW",
    "industry": "Technology",
    "company_size": "11-50",
    "country": "Egypt",
    "timezone": "Africa/Cairo",
    "phone": "+201234567890",
    "logo_url": null,
    "description": "AI-powered solutions",
    "settings": {
      "language": "en",
      "currency": "EGP"
    },
    "created_at": "2026-01-10T10:00:00Z"
  }
}
```

---

#### Update Organization

```http
PUT /api/v1/tenant/organization
Content-Type: multipart/form-data

name: Akwadna Technologies
industry: Technology
company_size: 51-200
logo: [file]
```

**Response:**
```json
{
  "success": true,
  "message": "Organization updated successfully",
  "data": {
    "id": "uuid",
    "name": "Akwadna Technologies",
    "logo_url": "https://storage.obsolio.com/logos/akwadna.png"
  }
}
```

---

### Agents & Executions

#### List My Agents

```http
GET /api/v1/tenant/agents
```

**Query Parameters:**
- `status` (optional): active | inactive | expired
- `tier` (optional): Filter by tier
- `search` (optional): Search by name

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "name": "Email Assistant",
      "description": "Automate email responses",
      "tier": {
        "id": 1,
        "name": "Basic"
      },
      "status": "active",
      "purchased_at": "2026-01-10T10:15:00Z",
      "activated_at": "2026-01-10T10:15:00Z",
      "last_used_at": "2026-01-15T14:30:00Z",
      "usage_count": 450,
      "is_default_agent": true
    }
  ]
}
```

---

#### Get Agent Details

```http
GET /api/v1/tenant/agents/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "name": "Email Assistant",
    "description": "Automate email responses and management",
    "long_description": "Detailed description...",
    "tier": {
      "id": 1,
      "name": "Basic",
      "description": "Simple, repetitive tasks"
    },
    "capabilities": {
      "max_emails_per_day": 100,
      "supports_attachments": true
    },
    "supported_languages": ["en", "ar"],
    "status": "active",
    "configuration": {
      "assigned_via": "auto_assignment",
      "plan_name": "Pro Plan"
    },
    "usage": {
      "total_executions": 450,
      "this_month": 45,
      "success_rate": 98.5,
      "avg_duration_ms": 3000
    },
    "recent_runs": [
      {
        "id": "uuid",
        "status": "completed",
        "started_at": "2026-01-15T14:30:00Z",
        "duration_ms": 3000
      }
    ]
  }
}
```

---

#### List Agent Runs

```http
GET /api/v1/tenant/agent-runs
```

**Query Parameters:**
- `agent_id` (optional): Filter by agent
- `status` (optional): pending | running | completed | failed
- `date_from` (optional): YYYY-MM-DD
- `date_to` (optional): YYYY-MM-DD
- `per_page` (optional): Default 20

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "agent": {
        "id": "uuid",
        "name": "Email Assistant"
      },
      "status": "completed",
      "started_at": "2026-01-15T14:30:00Z",
      "completed_at": "2026-01-15T14:30:15Z",
      "duration_ms": 15000,
      "error": null
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 450,
    "per_page": 20
  }
}
```

---

#### Get Run Status

```http
GET /api/v1/tenant/agent-runs/{run_id}
GET /api/v1/tenant/agent-runs/{run_id}/status
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "agent_id": "uuid",
    "status": "completed",
    "input": {
      "email_subject": "Test",
      "recipient": "test@example.com"
    },
    "output": {
      "success": true,
      "message_id": "msg-123",
      "sent_at": "2026-01-15T14:30:15Z"
    },
    "error": null,
    "events": [
      {
        "event": "accepted",
        "created_at": "2026-01-15T14:30:00Z"
      },
      {
        "event": "running",
        "created_at": "2026-01-15T14:30:01Z"
      },
      {
        "event": "completed",
        "created_at": "2026-01-15T14:30:15Z"
      }
    ],
    "created_at": "2026-01-15T14:30:00Z",
    "started_at": "2026-01-15T14:30:01Z",
    "completed_at": "2026-01-15T14:30:15Z"
  }
}
```

---

### Subscriptions & Billing

#### Current Subscription

```http
GET /api/v1/tenant/subscription/current
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "plan": {
      "id": "uuid",
      "name": "Pro Plan",
      "tier": "pro",
      "price_monthly": 49.99,
      "max_users": 25,
      "max_agents": 10,
      "included_executions": 10000
    },
    "status": "active",
    "billing_cycle": "monthly",
    "starts_at": "2026-01-10T10:15:00Z",
    "current_period_start": "2026-01-10T10:15:00Z",
    "current_period_end": "2026-02-10T10:15:00Z",
    "trial_ends_at": null,
    "next_billing_date": "2026-02-10",
    "next_billing_amount": 49.99,
    "usage": {
      "executions_used": 4500,
      "executions_included": 10000,
      "executions_remaining": 5500,
      "percentage_used": 45,
      "overage_executions": 0,
      "overage_amount": 0.00
    },
    "metadata": {
      "created_via": "email_verification",
      "plan_type": "organization",
      "plan_tier": "pro"
    }
  }
}
```

---

#### Subscribe to Plan

```http
POST /api/v1/tenant/subscription/subscribe
Content-Type: application/json

{
  "plan_id": "uuid",
  "billing_cycle": "annual",
  "payment_method_id": "uuid"
}
```

---

#### Change Plan

```http
PUT /api/v1/tenant/subscription/change-plan
Content-Type: application/json

{
  "plan_id": "uuid",
  "billing_cycle": "annual",
  "prorate": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "Subscription plan changed successfully",
  "data": {
    "subscription": {
      "id": "uuid",
      "plan": "Enterprise Plan",
      "status": "active"
    },
    "proration": {
      "credit": 25.00,
      "charge": 150.00,
      "net": 125.00
    },
    "next_billing_date": "2026-02-10",
    "next_billing_amount": 149.99
  }
}
```

---

#### Cancel Subscription

```http
POST /api/v1/tenant/subscription/cancel
Content-Type: application/json

{
  "reason": "Switching to competitor",
  "feedback": "Optional feedback",
  "cancel_immediately": false
}
```

---

#### Resume Subscription

```http
POST /api/v1/tenant/subscription/resume
```

---

#### Subscription History

```http
GET /api/v1/tenant/subscription/history
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "plan": "Pro Plan",
      "status": "active",
      "starts_at": "2026-01-10T10:15:00Z",
      "ends_at": null,
      "is_current": true
    },
    {
      "id": "uuid",
      "plan": "Free Plan",
      "status": "canceled",
      "starts_at": "2026-01-10T10:15:00Z",
      "ends_at": "2026-01-12T10:00:00Z",
      "is_current": false
    }
  ]
}
```

---

#### Plan Recommendations

```http
GET /api/v1/tenant/subscription/recommendations
```

**Response:**
```json
{
  "success": true,
  "data": {
    "current_plan": {
      "name": "Pro Plan",
      "tier": "pro",
      "price_monthly": 49.99
    },
    "usage_analysis": {
      "executions_used_percentage": 95,
      "users_count": 20,
      "users_limit": 25,
      "approaching_limit": true
    },
    "recommendations": [
      {
        "plan": {
          "id": "uuid",
          "name": "Enterprise Plan",
          "tier": "enterprise",
          "price_monthly": 149.99
        },
        "reason": "Your execution usage is at 95%. Upgrade for unlimited executions.",
        "savings": "Save 20% with annual billing",
        "priority": "high"
      }
    ]
  }
}
```

---

### Billing & Invoices

#### List Invoices

```http
GET /api/v1/tenant/billing/invoices
```

**Query Parameters:**
- `status` (optional): draft | pending | paid | failed | void
- `per_page` (optional): Default 15

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "invoice_number": "INV-20260115-ABC12",
      "subscription": {
        "id": "uuid",
        "plan": "Pro Plan"
      },
      "subtotal": 49.99,
      "tax": 0.00,
      "total": 49.99,
      "currency": "USD",
      "status": "paid",
      "due_date": "2026-02-10",
      "paid_at": "2026-01-10T10:15:00Z",
      "invoice_pdf_url": "https://storage.obsolio.com/invoices/...",
      "line_items": [
        {
          "description": "Pro Plan - Monthly Subscription",
          "quantity": 1,
          "unit_price": 49.99,
          "total": 49.99,
          "period_start": "2026-01-10",
          "period_end": "2026-02-10"
        }
      ],
      "created_at": "2026-01-10T10:15:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 10,
    "per_page": 15
  }
}
```

---

#### View Invoice

```http
GET /api/v1/tenant/billing/invoices/{id}
```

---

#### Payment Methods

```http
GET /api/v1/tenant/billing/payment-methods
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "type": "card",
      "brand": "Visa",
      "last4": "4242",
      "exp_month": 12,
      "exp_year": 2026,
      "country": "US",
      "is_default": true,
      "created_at": "2026-01-10T10:15:00Z"
    }
  ]
}
```

---

#### Add Payment Method

```http
POST /api/v1/tenant/billing/payment-methods
Content-Type: application/json

{
  "payment_token": "tok_visa",
  "set_as_default": true
}
```

---

#### Set Default Payment Method

```http
POST /api/v1/tenant/billing/payment-methods/{id}/set-default
```

---

#### Delete Payment Method

```http
DELETE /api/v1/tenant/billing/payment-methods/{id}
```

---

### Team Management (Memberships)

#### List Team Members

```http
GET /api/v1/tenant/memberships
```

**Query Parameters:**
- `status` (optional): active | invited | suspended | left
- `role` (optional): Filter by role

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "user_id": 1,
      "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@akwadna.com",
        "avatar_url": null
      },
      "role": {
        "id": 1,
        "name": "Owner"
      },
      "status": "active",
      "joined_at": "2026-01-10T10:00:00Z",
      "last_activity": "2026-01-15T14:30:00Z"
    },
    {
      "user_id": 2,
      "user": {
        "id": 2,
        "name": "Jane Smith",
        "email": "jane@akwadna.com",
        "avatar_url": null
      },
      "role": {
        "id": 2,
        "name": "Member"
      },
      "status": "invited",
      "invited_at": "2026-01-14T10:00:00Z",
      "joined_at": null
    }
  ]
}
```

---

#### Invite Member

```http
POST /api/v1/tenant/memberships/invite
Content-Type: application/json

{
  "email": "newmember@example.com",
  "role_id": 2,
  "message": "Welcome to the team!"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Invitation sent successfully",
  "data": {
    "user_id": 3,
    "email": "newmember@example.com",
    "status": "invited",
    "invited_at": "2026-01-15T14:30:00Z",
    "invitation_expires_at": "2026-01-22T14:30:00Z"
  }
}
```

---

#### Activate Member

```http
POST /api/v1/tenant/memberships/{userId}/activate
```

---

#### Suspend Member

```http
POST /api/v1/tenant/memberships/{userId}/suspend
Content-Type: application/json

{
  "reason": "Policy violation"
}
```

---

#### Reactivate Member

```http
POST /api/v1/tenant/memberships/{userId}/reactivate
```

---

#### Remove Member

```http
DELETE /api/v1/tenant/memberships/{userId}
```

---

### Activities & Sessions

#### List Activities

```http
GET /api/v1/tenant/activities
```

**Query Parameters:**
- `user_id` (optional): Filter by user
- `activity_type` (optional): Filter by type
- `date_from` (optional): YYYY-MM-DD
- `date_to` (optional): YYYY-MM-DD
- `per_page` (optional): Default 20

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "user": {
        "id": 1,
        "name": "John Doe"
      },
      "activity_type": "agent.executed",
      "action": "execute",
      "entity_type": "Agent",
      "entity_id": "uuid",
      "description": "Executed Email Assistant agent",
      "ip_address": "192.168.1.1",
      "created_at": "2026-01-15T14:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 1000,
    "per_page": 20
  }
}
```

---

#### View Activity

```http
GET /api/v1/tenant/activities/{id}
```

---

#### User Activities

```http
GET /api/v1/tenant/activities/user/{userId}
```

---

#### Active Sessions

```http
GET /api/v1/tenant/sessions
GET /api/v1/tenant/sessions/active
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "user": {
        "id": 1,
        "name": "John Doe"
      },
      "ip_address": "192.168.1.1",
      "user_agent": "Mozilla/5.0...",
      "device_type": "desktop",
      "browser": "Chrome",
      "platform": "Windows",
      "location": "Cairo, Egypt",
      "started_at": "2026-01-15T10:00:00Z",
      "last_activity_at": "2026-01-15T14:30:00Z",
      "is_active": true,
      "is_current": true
    }
  ]
}
```

---

#### Terminate Session

```http
POST /api/v1/tenant/sessions/{id}/terminate
```

---

### Roles & Permissions (Tenant-Scoped)

#### List Roles

```http
GET /api/v1/tenant/roles
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Owner",
      "permissions_count": 50,
      "members_count": 1,
      "is_default": false,
      "can_edit": false
    },
    {
      "id": 2,
      "name": "Member",
      "permissions_count": 15,
      "members_count": 8,
      "is_default": true,
      "can_edit": true
    }
  ]
}
```

---

#### Create Role

```http
POST /api/v1/tenant/roles
Content-Type: application/json

{
  "name": "Project Manager",
  "permission_ids": [1, 2, 3, 5, 8]
}
```

---

#### View Role

```http
GET /api/v1/tenant/roles/{id}
```

---

#### Update Role

```http
PUT /api/v1/tenant/roles/{id}
Content-Type: application/json

{
  "name": "Senior Project Manager",
  "permission_ids": [1, 2, 3, 4, 5, 8, 9]
}
```

---

#### Delete Role

```http
DELETE /api/v1/tenant/roles/{id}
```

---

#### List Permissions

```http
GET /api/v1/tenant/permissions
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "agents.view",
      "guard_name": "tenant",
      "category": "Agents",
      "description": "View agents list"
    },
    {
      "id": 2,
      "name": "agents.execute",
      "guard_name": "tenant",
      "category": "Agents",
      "description": "Execute agents"
    },
    {
      "id": 3,
      "name": "members.invite",
      "guard_name": "tenant",
      "category": "Team",
      "description": "Invite team members"
    }
  ]
}
```

---

## 🔄 Additional Endpoints

### Setup (Post-Registration)

#### Check Setup Status

```http
GET /api/v1/tenant/setup/status
```

---

#### Setup Organization

```http
POST /api/v1/tenant/setup/organization
Content-Type: application/json

{
  "organization_name": "Akwadna",
  "industry": "Technology",
  "company_size": "11-50",
  "country": "Egypt"
}
```

---

### Webhooks

#### Paymob Webhook

```http
POST /api/v1/webhooks/paymob
Content-Type: application/json

{
  "obj": {
    "id": 12345,
    "amount_cents": 4999,
    "success": true,
    "order": {
      "merchant_order_id": "ORD-123"
    }
  }
}
```

---

## 📝 Response Standards

### Success Response

```json
{
  "success": true,
  "data": { /* response data */ },
  "message": "Optional success message"
}
```

### Error Response

```json
{
  "success": false,
  "error": "Error type",
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Validation error message"]
  },
  "code": "ERROR_CODE"
}
```

### Pagination

```json
{
  "success": true,
  "data": [ /* items */ ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 15,
    "to": 15,
    "total": 150
  },
  "links": {
    "first": "https://api.obsolio.com/api/v1/resource?page=1",
    "last": "https://api.obsolio.com/api/v1/resource?page=10",
    "prev": null,
    "next": "https://api.obsolio.com/api/v1/resource?page=2"
  }
}
```

---

## 🔐 Authentication

### JWT Token

All authenticated requests require a JWT token in the Authorization header:

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

### Tenant Context

Tenant-specific requests require the tenant ID:

```
X-Tenant-Id: akwadna
```

Or use tenant subdomain:
```
Host: akwadna.obsolio.com
```

---

## ⚡ Rate Limiting

| Endpoint Type | Rate Limit |
|---------------|------------|
| Public | 60 requests/minute |
| Authenticated | 120 requests/minute |
| Admin Console | 300 requests/minute |

Rate limit headers:
```
X-RateLimit-Limit: 120
X-RateLimit-Remaining: 115
X-RateLimit-Reset: 1642253400
```

---

## 🌍 Supported Regions

- **Primary:** Egypt (Cairo)
- **CDN:** Global (CloudFlare/Fastly/BunnyCDN)

---

## 📧 Support

- **API Documentation:** https://docs.obsolio.com
- **Support Email:** api-support@obsolio.com
- **Status Page:** https://status.obsolio.com

---

**Last Updated:** January 15, 2026
**API Version:** 1.0
**Changelog:** https://docs.obsolio.com/changelog
