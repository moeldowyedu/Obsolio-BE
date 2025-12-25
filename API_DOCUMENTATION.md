# OBSOLIO AI - Complete API Documentation

## Base URL
```
http://your-domain.com/api/v1
```

## Authentication
All protected endpoints require Bearer token authentication using Laravel Sanctum.

### Headers
```
Authorization: Bearer {your-token}
Content-Type: application/json
Accept: application/json
```

---

## Authentication Endpoints

### POST /auth/register
Register a new user and organization.

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "organization_name": "Acme Corp",
  "industry": "Technology",
  "company_size": "10-50"
}
```

**Response (201):**
```json
{
  "user": {...},
  "tenant": {...},
  "organization": {...},
  "token": "1|xxxx..."
}
```

### POST /auth/login
Login existing user.

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response (200):**
```json
{
  "user": {...},
  "token": "2|xxxx..."
}
```

### POST /auth/logout
Logout current user. *Requires authentication*

**Response (200):**
```json
{
  "message": "Logged out successfully"
}
```

### GET /auth/me
Get authenticated user details. *Requires authentication*

**Response (200):**
```json
{
  "user": {
    "id": "uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "tenant": {...},
    "assignments": [...],
    "teams": [...]
  }
}
```

### POST /auth/forgot-password
Request password reset link.

**Request Body:**
```json
{
  "email": "john@example.com"
}
```

### POST /auth/reset-password
Reset password with token.

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "newpassword123",
  "password_confirmation": "newpassword123",
  "token": "reset-token"
}
```

---

## Organization Endpoints

### GET /organizations
List all organizations for the current tenant. *Requires authentication*

**Query Parameters:**
- `page` (int): Page number
- `per_page` (int): Items per page (default: 15, max: 100)
- `search` (string): Search by name or industry

**Response (200):**
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "total": 50
  }
}
```

### POST /organizations
Create a new organization. *Requires authentication*

**Request Body:**
```json
{
  "name": "Acme Corp",
  "industry": "Technology",
  "company_size": "50-200",
  "country": "USA",
  "timezone": "America/New_York",
  "description": "Leading tech company",
  "settings": {}
}
```

### GET /organizations/{id}
Get organization details. *Requires authentication*

### PUT /organizations/{id}
Update organization. *Requires authentication*

### DELETE /organizations/{id}
Delete organization. *Requires authentication*

### GET /organizations/{id}/dashboard
Get organization dashboard with stats. *Requires authentication*

---

## Branch Endpoints

### GET /branches
List all branches. *Requires authentication*

### GET /organizations/{organizationId}/branches
List branches for a specific organization. *Requires authentication*

### POST /branches
Create a new branch. *Requires authentication*

**Request Body:**
```json
{
  "organization_id": "uuid",
  "name": "West Coast Office",
  "location": "San Francisco, CA",
  "branch_code": "WC001",
  "branch_manager_id": "uuid",
  "status": "active"
}
```

### GET /branches/{id}
Get branch details. *Requires authentication*

### PUT /branches/{id}
Update branch. *Requires authentication*

### DELETE /branches/{id}
Delete branch. *Requires authentication*

---

## Department Endpoints

### GET /departments
List all departments. *Requires authentication*

### GET /organizations/{organizationId}/departments
List departments by organization. *Requires authentication*

### GET /branches/{branchId}/departments
List departments by branch. *Requires authentication*

### POST /departments
Create a new department. *Requires authentication*

**Request Body:**
```json
{
  "organization_id": "uuid",
  "branch_id": "uuid",
  "parent_department_id": "uuid",
  "name": "Engineering",
  "department_head_id": "uuid",
  "description": "Software Engineering Department",
  "budget": 500000.00,
  "status": "active"
}
```

### GET /departments/{id}
Get department details with subdepartments. *Requires authentication*

### PUT /departments/{id}
Update department. *Requires authentication*

### DELETE /departments/{id}
Delete department. *Requires authentication*

---

## Project Endpoints

### GET /projects
List all projects. *Requires authentication*

**Query Parameters:**
- `status` (string): Filter by status (planning, active, completed, cancelled)
- `department_id` (uuid): Filter by department

### GET /departments/{departmentId}/projects
List projects by department. *Requires authentication*

### POST /projects
Create a new project. *Requires authentication*

**Request Body:**
```json
{
  "organization_id": "uuid",
  "department_id": "uuid",
  "name": "Product Launch 2024",
  "project_manager_id": "uuid",
  "description": "Launch new product line",
  "start_date": "2024-01-01",
  "end_date": "2024-12-31",
  "budget": 1000000.00,
  "status": "planning"
}
```

### GET /projects/{id}
Get project details. *Requires authentication*

### PUT /projects/{id}
Update project. *Requires authentication*

### PUT /projects/{id}/status
Update project status only. *Requires authentication*

**Request Body:**
```json
{
  "status": "active"
}
```

### DELETE /projects/{id}
Delete project. *Requires authentication*

---

## Team Endpoints

### GET /teams
List all teams. *Requires authentication*

### POST /teams
Create a new team. *Requires authentication*

**Request Body:**
```json
{
  "organization_id": "uuid",
  "name": "Frontend Team",
  "team_lead_id": "uuid",
  "description": "Frontend development team"
}
```

### GET /teams/{id}
Get team details with members. *Requires authentication*

### PUT /teams/{id}
Update team. *Requires authentication*

### DELETE /teams/{id}
Delete team. *Requires authentication*

### POST /teams/{id}/members
Add member to team. *Requires authentication*

**Request Body:**
```json
{
  "user_id": "uuid"
}
```

### DELETE /teams/{id}/members/{userId}
Remove member from team. *Requires authentication*

---

## User Endpoints

### GET /users
List all users in the tenant. *Requires authentication*

**Query Parameters:**
- `status` (string): Filter by status (active, inactive, invited)
- `search` (string): Search by name or email

### POST /users
Create/invite a new user. *Requires authentication*

**Request Body:**
```json
{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "status": "invited",
  "avatar_url": "https://..."
}
```

### GET /users/{id}
Get user details. *Requires authentication*

### PUT /users/{id}
Update user. *Requires authentication*

### DELETE /users/{id}
Delete user. *Requires authentication*

### PUT /users/{id}/status
Update user status. *Requires authentication*

**Request Body:**
```json
{
  "status": "active"
}
```

### POST /users/{id}/assign
Assign user to organization/department. *Requires authentication*

**Request Body:**
```json
{
  "organization_id": "uuid",
  "branch_id": "uuid",
  "department_id": "uuid",
  "project_id": "uuid",
  "access_scope": {
    "role": "member",
    "permissions": []
  }
}
```

### GET /users/{id}/assignments
Get user assignments. *Requires authentication*

---

## Engine Endpoints

### GET /engines
List all available AI engines. *Public*

**Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "slug": "document-analyzer",
      "name": "Document Analyzer",
      "description": "Analyzes and extracts information from documents",
      "category": "document-processing",
      "capabilities": ["extract", "classify", "summarize"],
      "input_types": ["pdf", "docx", "txt"],
      "color": "#4CAF50",
      "icon": "document-text",
      "is_active": true
    }
  ]
}
```

### GET /engines/{id}
Get engine details. *Requires authentication*

### POST /engines/{id}/rubrics
Create custom rubric for engine. *Requires authentication*

**Request Body:**
```json
{
  "name": "Quality Assessment Rubric",
  "criteria": {
    "accuracy": "Information accuracy",
    "completeness": "Information completeness",
    "clarity": "Output clarity"
  },
  "weights": {
    "accuracy": 0.5,
    "completeness": 0.3,
    "clarity": 0.2
  },
  "threshold": 80.0,
  "is_default": false
}
```

### PUT /engines/{engineId}/rubrics/{id}
Update custom rubric. *Requires authentication*

### DELETE /engines/{engineId}/rubrics/{id}
Delete custom rubric. *Requires authentication*

---

## Agent Endpoints

### GET /agents
List all agents for the tenant. *Requires authentication*

**Query Parameters:**
- `status` (string): Filter by status (draft, active, inactive, archived)
- `type` (string): Filter by type (custom, marketplace, template)

**Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Invoice Processor",
      "description": "Automatically processes invoices",
      "type": "custom",
      "engines_used": ["uuid1", "uuid2"],
      "status": "active",
      "is_published": false,
      "version": 1,
      "created_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

### POST /agents
Create a new agent. *Requires authentication*

**Request Body:**
```json
{
  "name": "Invoice Processor",
  "description": "Processes incoming invoices",
  "type": "custom",
  "engines_used": ["engine-uuid-1", "engine-uuid-2"],
  "config": {
    "model": "gpt-4",
    "temperature": 0.7,
    "max_tokens": 2000
  },
  "input_schema": {
    "type": "object",
    "properties": {
      "invoice_file": { "type": "string" }
    }
  },
  "output_schema": {
    "type": "object",
    "properties": {
      "vendor": { "type": "string" },
      "amount": { "type": "number" },
      "date": { "type": "string" }
    }
  },
  "rubric_config": {
    "rubric_id": "uuid",
    "auto_approve_threshold": 90
  },
  "status": "draft"
}
```

### GET /agents/{id}
Get agent details. *Requires authentication*

### PUT /agents/{id}
Update agent. *Requires authentication*

### DELETE /agents/{id}
Delete agent. *Requires authentication*

### POST /agents/{id}/publish
Publish agent to marketplace. *Requires authentication*

**Request Body:**
```json
{
  "title": "Invoice Processing Agent",
  "description": "Professional invoice processor",
  "category": "finance",
  "industry": "accounting",
  "price_type": "subscription",
  "price": 99.99,
  "currency": "USD"
}
```

### POST /agents/{id}/clone
Clone an existing agent. *Requires authentication*

**Request Body:**
```json
{
  "name": "Invoice Processor v2"
}
```

### GET /agents/{id}/executions
Get execution history for agent. *Requires authentication*

### POST /agents/{id}/execute
Execute agent manually. *Requires authentication*

**Request Body:**
```json
{
  "input_data": {
    "invoice_file": "path/to/invoice.pdf"
  }
}
```

---

## Job Flow Endpoints

### GET /job-flows
List all job flows. *Requires authentication*

**Query Parameters:**
- `status` (string): Filter by status (active, inactive, paused)
- `agent_id` (uuid): Filter by agent

**Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "agent_id": "uuid",
      "job_title": "Daily Invoice Processing",
      "employment_type": "full-time",
      "schedule_type": "daily",
      "schedule_config": {
        "time": "09:00",
        "timezone": "America/New_York"
      },
      "hitl_mode": "hybrid",
      "status": "active",
      "total_runs": 150,
      "successful_runs": 145,
      "failed_runs": 5,
      "next_run_at": "2024-01-02T09:00:00Z"
    }
  ]
}
```

### POST /job-flows
Create a new job flow (employ an agent). *Requires authentication*

**Request Body:**
```json
{
  "agent_id": "uuid",
  "job_title": "Daily Invoice Processing",
  "job_description": "Process all incoming invoices daily",
  "organization_id": "uuid",
  "department_id": "uuid",
  "reporting_manager_id": "uuid",
  "employment_type": "full-time",
  "schedule_type": "daily",
  "schedule_config": {
    "time": "09:00",
    "timezone": "America/New_York",
    "days_of_week": [1, 2, 3, 4, 5]
  },
  "input_source": {
    "type": "email",
    "config": {
      "email": "invoices@company.com",
      "folder": "inbox"
    }
  },
  "output_destination": {
    "type": "database",
    "config": {
      "table": "processed_invoices"
    }
  },
  "hitl_mode": "hybrid",
  "hitl_supervisor_id": "uuid",
  "hitl_rules": {
    "require_approval_above": 10000,
    "require_approval_below_confidence": 80
  },
  "status": "active"
}
```

### GET /job-flows/{id}
Get job flow details. *Requires authentication*

### PUT /job-flows/{id}
Update job flow. *Requires authentication*

### DELETE /job-flows/{id}
Delete job flow. *Requires authentication*

### PUT /job-flows/{id}/status
Update job flow status. *Requires authentication*

**Request Body:**
```json
{
  "status": "paused"
}
```

### POST /job-flows/{id}/trigger
Manually trigger job flow execution. *Requires authentication*

**Request Body:**
```json
{
  "input_data": {...}
}
```

### GET /job-flows/{id}/stats
Get job flow statistics. *Requires authentication*

**Response (200):**
```json
{
  "total_runs": 150,
  "successful_runs": 145,
  "failed_runs": 5,
  "success_rate": 96.67,
  "avg_duration_seconds": 45.5,
  "last_run_at": "2024-01-01T09:00:00Z",
  "next_run_at": "2024-01-02T09:00:00Z"
}
```

---

## Workflow Endpoints

### GET /workflows
List all workflows. *Requires authentication*

### POST /workflows
Create a new workflow. *Requires authentication*

**Request Body:**
```json
{
  "name": "Document Processing Pipeline",
  "description": "Multi-step document processing",
  "organization_id": "uuid",
  "nodes": [
    {
      "id": "node1",
      "type": "agent",
      "agent_id": "uuid",
      "position": { "x": 100, "y": 100 }
    },
    {
      "id": "node2",
      "type": "agent",
      "agent_id": "uuid",
      "position": { "x": 300, "y": 100 }
    }
  ],
  "edges": [
    {
      "id": "edge1",
      "source": "node1",
      "target": "node2"
    }
  ],
  "config": {
    "error_handling": "continue",
    "timeout": 3600
  },
  "status": "draft"
}
```

### GET /workflows/{id}
Get workflow details. *Requires authentication*

### PUT /workflows/{id}
Update workflow. *Requires authentication*

### DELETE /workflows/{id}
Delete workflow. *Requires authentication*

### POST /workflows/{id}/execute
Execute workflow. *Requires authentication*

**Request Body:**
```json
{
  "input_data": {...}
}
```

### GET /workflows/{id}/executions
Get workflow execution history. *Requires authentication*

### GET /workflows/executions/{executionId}
Get detailed execution information. *Requires authentication*

---

## HITL Approval Endpoints

### GET /hitl-approvals
List HITL approvals. *Requires authentication*

**Query Parameters:**
- `status` (string): Filter by status (pending, approved, rejected, escalated)
- `priority` (string): Filter by priority (low, normal, high, urgent)

**Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "job_flow_id": "uuid",
      "agent_id": "uuid",
      "task_data": {...},
      "ai_decision": {...},
      "ai_confidence": 75.5,
      "rubric_score": 78.0,
      "status": "pending",
      "priority": "normal",
      "assigned_to_user_id": "uuid",
      "expires_at": "2024-01-02T00:00:00Z",
      "created_at": "2024-01-01T12:00:00Z"
    }
  ]
}
```

### GET /hitl-approvals/pending
List pending approvals for current user. *Requires authentication*

### GET /hitl-approvals/{id}
Get approval details. *Requires authentication*

### POST /hitl-approvals/{id}/approve
Approve decision. *Requires authentication*

**Request Body:**
```json
{
  "comments": "Looks good, approved"
}
```

### POST /hitl-approvals/{id}/reject
Reject decision. *Requires authentication*

**Request Body:**
```json
{
  "comments": "Amount seems incorrect",
  "corrections": {
    "amount": 1500.00
  }
}
```

### POST /hitl-approvals/{id}/escalate
Escalate to higher authority. *Requires authentication*

**Request Body:**
```json
{
  "escalate_to_user_id": "uuid",
  "reason": "Requires senior approval"
}
```

---

## Agent Execution Endpoints

### GET /executions
List all agent executions. *Requires authentication*

**Query Parameters:**
- `status` (string): Filter by status (pending, running, completed, failed, cancelled)
- `agent_id` (uuid): Filter by agent
- `from_date` (date): Filter from date
- `to_date` (date): Filter to date

**Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "agent_id": "uuid",
      "job_flow_id": "uuid",
      "triggered_by": "schedule",
      "input_data": {...},
      "output_data": {...},
      "status": "completed",
      "started_at": "2024-01-01T09:00:00Z",
      "completed_at": "2024-01-01T09:00:45Z",
      "duration_seconds": 45,
      "rubric_scores": {
        "accuracy": 95,
        "completeness": 90,
        "overall": 92.5
      }
    }
  ]
}
```

### GET /executions/{id}
Get execution details. *Requires authentication*

### GET /executions/{id}/logs
Get execution logs. *Requires authentication*

**Response (200):**
```json
{
  "logs": "Execution started...\nProcessing input...\nCompleted successfully"
}
```

### POST /executions/{id}/cancel
Cancel running execution. *Requires authentication*

---

## Marketplace Endpoints

### GET /marketplace
List marketplace listings. *Public*

**Query Parameters:**
- `category` (string): Filter by category
- `industry` (string): Filter by industry
- `price_type` (string): Filter by price type (free, one-time, subscription)
- `search` (string): Search by title or description

**Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "agent_id": "uuid",
      "title": "Invoice Processing Agent",
      "description": "Automated invoice processing",
      "category": "finance",
      "industry": "accounting",
      "price_type": "subscription",
      "price": 99.99,
      "currency": "USD",
      "rating_average": 4.8,
      "reviews_count": 25,
      "purchases_count": 150,
      "is_featured": false
    }
  ]
}
```

### GET /marketplace/{id}
Get listing details. *Public*

### POST /marketplace
Create marketplace listing. *Requires authentication*

**Request Body:**
```json
{
  "agent_id": "uuid",
  "title": "Invoice Processing Agent",
  "description": "Professional invoice processor with 95% accuracy",
  "category": "finance",
  "industry": "accounting",
  "price_type": "subscription",
  "price": 99.99,
  "currency": "USD",
  "thumbnail_url": "https://...",
  "screenshots": ["https://..."],
  "tags": ["invoice", "automation", "finance"]
}
```

### PUT /marketplace/{id}
Update listing. *Requires authentication*

### DELETE /marketplace/{id}
Delete listing. *Requires authentication*

### POST /marketplace/{id}/purchase
Purchase agent from marketplace. *Requires authentication*

**Response (200):**
```json
{
  "purchase": {...},
  "agent": {...},
  "message": "Agent purchased successfully"
}
```

### GET /marketplace/my-listings
Get current user's listings. *Requires authentication*

### GET /marketplace/my-purchases
Get current user's purchases. *Requires authentication*

---

## Webhook Endpoints

### GET /webhooks
List all webhooks. *Requires authentication*

### POST /webhooks
Create webhook. *Requires authentication*

**Request Body:**
```json
{
  "name": "Slack Notifications",
  "url": "https://hooks.slack.com/services/xxx",
  "events": ["agent.execution.completed", "hitl.approval.required"],
  "secret": "webhook-secret",
  "is_active": true
}
```

### GET /webhooks/{id}
Get webhook details. *Requires authentication*

### PUT /webhooks/{id}
Update webhook. *Requires authentication*

### DELETE /webhooks/{id}
Delete webhook. *Requires authentication*

### POST /webhooks/{id}/test
Test webhook delivery. *Requires authentication*

**Response (200):**
```json
{
  "success": true,
  "response_code": 200,
  "response_time_ms": 150
}
```

---

## Subscription & Billing Endpoints

### GET /subscriptions
List subscription plans. *Requires authentication*

### GET /subscriptions/current
Get current subscription. *Requires authentication*

**Response (200):**
```json
{
  "id": "uuid",
  "plan_slug": "professional",
  "status": "active",
  "current_period_start": "2024-01-01T00:00:00Z",
  "current_period_end": "2024-02-01T00:00:00Z"
}
```

### POST /subscriptions/subscribe
Subscribe to a plan. *Requires authentication*

**Request Body:**
```json
{
  "plan_slug": "professional",
  "payment_method_id": "pm_xxx"
}
```

### POST /subscriptions/cancel
Cancel subscription. *Requires authentication*

### GET /subscriptions/usage
Get current usage statistics. *Requires authentication*

**Response (200):**
```json
{
  "agent_executions": 1500,
  "api_calls": 5000,
  "storage_bytes": 1073741824,
  "webhook_deliveries": 250,
  "period_start": "2024-01-01",
  "period_end": "2024-01-31"
}
```

### GET /subscriptions/usage/{date}
Get usage for specific date. *Requires authentication*

---

## Dashboard Endpoint

### GET /dashboard/stats
Get dashboard statistics. *Requires authentication*

**Response (200):**
```json
{
  "total_agents": 25,
  "active_job_flows": 18,
  "pending_approvals": 5,
  "executions_today": 150,
  "total_users": 12,
  "subscription_status": "active",
  "trial_ends_at": null
}
```

---

## Error Responses

All endpoints may return the following error responses:

### 400 Bad Request
```json
{
  "message": "Invalid request data"
}
```

### 401 Unauthorized
```json
{
  "message": "Unauthenticated"
}
```

### 403 Forbidden
```json
{
  "message": "This action is unauthorized"
}
```

### 404 Not Found
```json
{
  "message": "Resource not found"
}
```

### 422 Validation Error
```json
{
  "message": "The given data was invalid",
  "errors": {
    "email": ["The email field is required"],
    "password": ["The password must be at least 8 characters"]
  }
}
```

### 429 Too Many Requests
```json
{
  "message": "Too many attempts. Please try again later."
}
```

### 500 Internal Server Error
```json
{
  "message": "Server error. Please try again later."
}
```

---

## Rate Limiting

API requests are rate-limited based on your subscription plan:
- **Free/Trial**: 100 requests per minute
- **Professional**: 1000 requests per minute
- **Enterprise**: 10000 requests per minute

Rate limit headers are included in all responses:
```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1609459200
```

---

## Pagination

All list endpoints support pagination with the following query parameters:
- `page` (int): Page number (default: 1)
- `per_page` (int): Items per page (default: 15, max: 100)

Response format:
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 15,
    "to": 15,
    "total": 150
  },
  "links": {
    "first": "https://api.OBSOLIO.ai/v1/agents?page=1",
    "last": "https://api.OBSOLIO.ai/v1/agents?page=10",
    "prev": null,
    "next": "https://api.OBSOLIO.ai/v1/agents?page=2"
  }
}
```

---

## Webhooks

When enabled, webhooks will send POST requests to your configured URL for the following events:

### Event Types
- `agent.execution.started`
- `agent.execution.completed`
- `agent.execution.failed`
- `hitl.approval.required`
- `hitl.approval.approved`
- `hitl.approval.rejected`
- `workflow.execution.completed`
- `subscription.updated`
- `subscription.cancelled`

### Webhook Payload
```json
{
  "event": "agent.execution.completed",
  "timestamp": "2024-01-01T12:00:00Z",
  "data": {
    "execution_id": "uuid",
    "agent_id": "uuid",
    "status": "completed",
    "duration_seconds": 45
  }
}
```

### Webhook Signature
All webhooks include a signature header for verification:
```
X-Webhook-Signature: sha256=xxx
```

---

## SDK & Client Libraries

Official SDKs available for:
- JavaScript/TypeScript (npm: `@OBSOLIO/js-sdk`)
- Python (pip: `OBSOLIO-sdk`)
- PHP (composer: `OBSOLIO/php-sdk`)

---

---

## Admin Endpoints (System Admin Only)

**Authentication Required**: System Admin role + JWT token
**Base Path**: `/api/v1/admin`

### Tenant Management

#### GET /admin/tenants
List all tenants with advanced filtering.

**Query Parameters:**
- `search` (string): Search by name, email, or subdomain
- `type` (string): Filter by type (personal, organization)
- `status` (string): Filter by status (pending_verification, active, inactive, suspended)
- `plan_id` (uuid): Filter by subscription plan
- `has_subscription` (boolean): Filter by subscription existence
- `sort_by` (string): Sort field (created_at, name, email, type, status)
- `sort_order` (string): Sort direction (asc, desc)
- `per_page` (int): Results per page (default: 20)

**Example Request:**
```bash
GET /api/v1/admin/tenants?type=organization&status=active&search=acme&per_page=50
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "name": "Acme Corporation",
        "email": "admin@acme.com",
        "phone": "+1234567890",
        "type": "organization",
        "status": "active",
        "subdomain_preference": "acme",
        "subdomain_activated_at": "2025-01-15T11:00:00Z",
        "is_on_trial": true,
        "trial_days_remaining": 7,
        "billing_cycle": "monthly",
        "has_active_subscription": true,
        "active_subscription": {
          "id": "650e8400-e29b-41d4-a716-446655440001",
          "plan_id": "750e8400-e29b-41d4-a716-446655440002",
          "status": "trialing",
          "billing_cycle": "monthly",
          "plan": {
            "id": "750e8400-e29b-41d4-a716-446655440002",
            "name": "Business",
            "tier": "business",
            "type": "organization",
            "price_monthly": 299.00,
            "price_annual": 2990.00
          }
        },
        "organization": {
          "id": "850e8400-e29b-41d4-a716-446655440003",
          "name": "Acme Corporation",
          "industry": "Technology",
          "company_size": "50-200"
        },
        "memberships_count": 15,
        "created_at": "2025-01-15T10:30:00Z",
        "updated_at": "2025-02-10T14:20:00Z"
      }
    ],
    "total": 150,
    "per_page": 50,
    "last_page": 3
  }
}
```

#### GET /admin/tenants/statistics
Get comprehensive tenant statistics.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "total_tenants": 1250,
    "by_type": {
      "personal": 800,
      "organization": 450
    },
    "by_status": {
      "active": 1100,
      "pending_verification": 50,
      "inactive": 75,
      "suspended": 25
    },
    "with_active_subscription": 1050,
    "on_trial": 120,
    "subscription_by_plan": [
      {
        "id": "750e8400-e29b-41d4-a716-446655440002",
        "name": "Free Personal",
        "type": "personal",
        "tier": "free",
        "active_subscriptions": 500
      },
      {
        "id": "750e8400-e29b-41d4-a716-446655440003",
        "name": "Pro Personal",
        "type": "personal",
        "tier": "pro",
        "active_subscriptions": 200
      },
      {
        "id": "750e8400-e29b-41d4-a716-446655440004",
        "name": "Business",
        "type": "organization",
        "tier": "business",
        "active_subscriptions": 250
      }
    ],
    "recent_signups": 45
  }
}
```

#### GET /admin/tenants/{id}
Get detailed information about a specific tenant.

**Example Request:**
```bash
GET /api/v1/admin/tenants/550e8400-e29b-41d4-a716-446655440000
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Acme Corporation",
    "email": "admin@acme.com",
    "phone": "+1234567890",
    "type": "organization",
    "status": "active",
    "subdomain_preference": "acme",
    "subdomain_activated_at": "2025-01-15T11:00:00Z",
    "is_on_trial": true,
    "trial_days_remaining": 7,
    "billing_cycle": "monthly",
    "has_active_subscription": true,
    "active_subscription": {
      "id": "650e8400-e29b-41d4-a716-446655440001",
      "plan_id": "750e8400-e29b-41d4-a716-446655440002",
      "status": "trialing",
      "billing_cycle": "monthly",
      "trial_ends_at": "2025-03-01T00:00:00Z",
      "current_period_start": "2025-02-15T00:00:00Z",
      "current_period_end": "2025-03-15T00:00:00Z",
      "plan": {
        "name": "Business",
        "tier": "business",
        "price_monthly": 299.00
      }
    },
    "subscriptions": [
      {
        "id": "650e8400-e29b-41d4-a716-446655440001",
        "status": "trialing",
        "created_at": "2025-02-15T00:00:00Z"
      }
    ],
    "organization": {
      "id": "850e8400-e29b-41d4-a716-446655440003",
      "name": "Acme Corporation",
      "industry": "Technology"
    },
    "memberships": [
      {
        "id": "950e8400-e29b-41d4-a716-446655440004",
        "user": {
          "id": "a50e8400-e29b-41d4-a716-446655440005",
          "name": "John Doe",
          "email": "john@acme.com"
        },
        "role": "admin"
      }
    ],
    "invoices": [
      {
        "id": "b50e8400-e29b-41d4-a716-446655440006",
        "amount": 299.00,
        "status": "paid",
        "paid_at": "2025-02-15T00:00:00Z"
      }
    ],
    "payment_methods": [
      {
        "id": "c50e8400-e29b-41d4-a716-446655440007",
        "type": "card",
        "last4": "4242",
        "brand": "visa"
      }
    ],
    "memberships_count": 15,
    "invoices_count": 3,
    "subscriptions_count": 2,
    "created_at": "2025-01-15T10:30:00Z",
    "updated_at": "2025-02-10T14:20:00Z"
  }
}
```

#### PUT /admin/tenants/{id}/status
Update tenant status.

**Request Body:**
```json
{
  "status": "active",
  "reason": "Payment verified and account approved"
}
```

**Status Options:**
- `pending_verification` - Waiting for verification
- `active` - Active tenant
- `inactive` - Inactive tenant
- `suspended` - Suspended due to violation or non-payment

**Response (200):**
```json
{
  "success": true,
  "message": "Tenant status updated successfully",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "active",
    "updated_at": "2025-02-22T15:30:00Z"
  }
}
```

#### PUT /admin/tenants/{id}/subscription
Change tenant's subscription plan.

**Request Body:**
```json
{
  "plan_id": "750e8400-e29b-41d4-a716-446655440008",
  "billing_cycle": "annual",
  "starts_immediately": true,
  "prorate": false
}
```

**Fields:**
- `plan_id` (required, uuid): UUID of the new subscription plan
- `billing_cycle` (required, enum): "monthly" or "annual"
- `starts_immediately` (optional, boolean): Start change now or at end of current period (default: false)
- `prorate` (optional, boolean): Calculate prorated amount for plan change (default: false)

**Response (200):**
```json
{
  "success": true,
  "message": "Subscription changed successfully",
  "data": {
    "tenant": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Acme Corporation"
    },
    "subscription": {
      "id": "d50e8400-e29b-41d4-a716-446655440009",
      "plan_id": "750e8400-e29b-41d4-a716-446655440008",
      "status": "active",
      "billing_cycle": "annual",
      "starts_at": "2025-02-22T00:00:00Z",
      "current_period_start": "2025-02-22T00:00:00Z",
      "current_period_end": "2026-02-22T00:00:00Z",
      "plan": {
        "id": "750e8400-e29b-41d4-a716-446655440008",
        "name": "Enterprise",
        "tier": "enterprise",
        "type": "organization",
        "price_annual": 9990.00
      }
    }
  }
}
```

#### GET /admin/tenants/{id}/subscriptions
View complete subscription history for a tenant.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "tenant": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Acme Corporation",
      "email": "admin@acme.com"
    },
    "subscriptions": [
      {
        "id": "d50e8400-e29b-41d4-a716-446655440009",
        "plan": {
          "name": "Enterprise",
          "tier": "enterprise",
          "price_annual": 9990.00
        },
        "status": "active",
        "billing_cycle": "annual",
        "starts_at": "2025-02-22T00:00:00Z",
        "trial_ends_at": null,
        "created_at": "2025-02-22T15:30:00Z"
      },
      {
        "id": "650e8400-e29b-41d4-a716-446655440001",
        "plan": {
          "name": "Business",
          "tier": "business",
          "price_monthly": 299.00
        },
        "status": "canceled",
        "billing_cycle": "monthly",
        "starts_at": "2025-02-15T00:00:00Z",
        "canceled_at": "2025-02-22T15:30:00Z",
        "created_at": "2025-02-15T00:00:00Z"
      }
    ]
  }
}
```

#### POST /admin/tenants/{id}/extend-trial
Extend tenant's trial period.

**Request Body:**
```json
{
  "days": 30,
  "reason": "Customer requested demo extension for evaluation"
}
```

**Fields:**
- `days` (required, integer): Number of days to extend (1-365)
- `reason` (optional, string): Reason for extension (max 500 chars)

**Response (200):**
```json
{
  "success": true,
  "message": "Trial extended by 30 days",
  "data": {
    "id": "650e8400-e29b-41d4-a716-446655440001",
    "status": "trialing",
    "trial_ends_at": "2025-03-31T00:00:00Z",
    "updated_at": "2025-02-22T16:00:00Z"
  }
}
```

#### DELETE /admin/tenants/{id}
Soft delete a tenant (cancels active subscriptions).

**Response (200):**
```json
{
  "success": true,
  "message": "Tenant deleted successfully"
}
```

### Subscription Plan Management

#### GET /admin/subscription-plans
List all subscription plans.

**Query Parameters:**
- `type` (string): Filter by type (personal, organization)
- `active` (boolean): Filter by active status

**Example Request:**
```bash
GET /api/v1/admin/subscription-plans?type=organization&active=true
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "750e8400-e29b-41d4-a716-446655440002",
      "name": "Business",
      "type": "organization",
      "tier": "business",
      "price_monthly": 299.00,
      "price_annual": 2990.00,
      "max_users": 50,
      "max_agents": 100,
      "storage_gb": 200,
      "trial_days": 14,
      "is_active": true,
      "is_published": true,
      "is_archived": false,
      "plan_version": "1.0.0",
      "display_order": 3,
      "features": [
        "All Team features",
        "Up to 50 team members",
        "Advanced Permissions",
        "Custom Integrations",
        "200GB Storage",
        "Priority Phone Support",
        "Dedicated Account Manager",
        "SLA Guarantee"
      ],
      "highlight_features": [
        "Priority Support",
        "50 Users",
        "100 Agents"
      ],
      "limits": {
        "agents_per_month": 20000,
        "api_calls_per_day": 10000
      },
      "description": "For growing businesses",
      "created_at": "2025-01-01T00:00:00Z"
    }
  ]
}
```

#### POST /admin/subscription-plans
Create a new subscription plan.

**Request Body:**
```json
{
  "name": "Startup",
  "type": "organization",
  "tier": "team",
  "price_monthly": 149.00,
  "price_annual": 1490.00,
  "max_users": 20,
  "max_agents": 50,
  "storage_gb": 100,
  "trial_days": 14,
  "features": [
    "All Pro features",
    "Up to 20 team members",
    "Team Collaboration",
    "Shared Agent Library",
    "100GB Storage",
    "Phone Support"
  ],
  "highlight_features": [
    "20 Users",
    "50 Agents",
    "Phone Support"
  ],
  "limits": {
    "agents_per_month": 10000,
    "api_calls_per_day": 5000
  },
  "description": "Perfect for growing startups",
  "is_published": true,
  "display_order": 2
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Subscription plan created successfully",
  "data": {
    "id": "e50e8400-e29b-41d4-a716-446655440010",
    "name": "Startup",
    "type": "organization",
    "tier": "team",
    "price_monthly": 149.00,
    "price_annual": 1490.00,
    "is_published": true,
    "created_at": "2025-02-22T17:00:00Z"
  }
}
```

#### PUT /admin/subscription-plans/{id}
Update an existing subscription plan.

**Request Body (all fields optional):**
```json
{
  "name": "Startup Plus",
  "price_monthly": 199.00,
  "price_annual": 1990.00,
  "max_users": 25,
  "is_published": true,
  "is_archived": false,
  "display_order": 2
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Plan updated successfully",
  "data": {
    "id": "e50e8400-e29b-41d4-a716-446655440010",
    "name": "Startup Plus",
    "price_monthly": 199.00,
    "updated_at": "2025-02-22T17:30:00Z"
  }
}
```

#### DELETE /admin/subscription-plans/{id}
Delete a subscription plan (only if no active subscriptions exist).

**Response (200):**
```json
{
  "success": true,
  "message": "Plan deleted successfully"
}
```

**Error Response (400):**
```json
{
  "success": false,
  "message": "Cannot delete plan with 15 active subscriptions"
}
```

### Analytics

#### GET /admin/analytics/overview
Get comprehensive system analytics.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "total_tenants": 1250,
    "active_tenants": 1100,
    "total_users": 5430,
    "total_subscriptions": 1050,
    "total_revenue_monthly": 125000.00,
    "total_agents": 250,
    "active_agents": 235,
    "featured_agents": 15
  }
}
```

#### GET /admin/analytics/revenue
Get revenue analytics.

**Query Parameters:**
- `period` (string): Time period (day, week, month, year)

**Example Request:**
```bash
GET /api/v1/admin/analytics/revenue?period=month
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "summary": {
      "total_revenue": 125000.00,
      "total_invoices": 1050,
      "average_invoice": 119.05,
      "period": "month"
    },
    "daily_breakdown": [
      {
        "date": "2025-02-01",
        "total": 4250.00,
        "count": 35
      },
      {
        "date": "2025-02-02",
        "total": 3890.00,
        "count": 32
      }
    ]
  }
}
```

### Activity Logs

#### GET /admin/activity-logs
Get system activity logs.

**Query Parameters:**
- `user_id` (uuid): Filter by user
- `action` (string): Filter by action type

**Response (200):**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "f50e8400-e29b-41d4-a716-446655440011",
        "user_id": "a50e8400-e29b-41d4-a716-446655440005",
        "action": "tenant_status_changed",
        "properties": {
          "old_status": "pending_verification",
          "new_status": "active",
          "reason": "Payment verified"
        },
        "user": {
          "name": "Admin User",
          "email": "admin@obsolio.com"
        },
        "created_at": "2025-02-22T15:30:00Z"
      }
    ],
    "total": 500
  }
}
```

---

## Payment Endpoints (Paymob Integration)

### Create Subscription Payment

Create a payment intent for subscription using Paymob gateway.

**Endpoint:** `POST /api/v1/payments/subscription`

**Authentication:** Required (JWT)

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "plan_id": "9d4e5f6g-7h8i-9j0k-1l2m-3n4o5p6q7r8s",
  "billing_cycle": "monthly",
  "phone": "+201234567890"
}
```

**Request Fields:**
- `plan_id` (required, uuid): ID of the subscription plan
- `billing_cycle` (required, enum): Either "monthly" or "annual"
- `phone` (optional, string): Customer phone number (defaults to +201000000000)

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "invoice_id": "9d4e5f6g-7h8i-9j0k-1l2m-3n4o5p6q7r8s",
    "iframe_url": "https://accept.paymob.com/api/acceptance/iframes/799158?payment_token=ZXlKaGJHY2lPaUpJ...",
    "amount": 49.00,
    "amount_egp": 1494.50,
    "currency": "EGP"
  }
}
```

**Error Response (500):**
```json
{
  "success": false,
  "message": "Failed to create payment",
  "error": "Paymob API error details"
}
```

**Usage Example:**
```javascript
// Create subscription payment
const response = await fetch('/api/v1/payments/subscription', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    plan_id: '9d4e5f6g-7h8i-9j0k-1l2m-3n4o5p6q7r8s',
    billing_cycle: 'monthly',
    phone: '+201234567890'
  })
});

const data = await response.json();

if (data.success) {
  // Redirect user to Paymob iframe
  window.location.href = data.data.iframe_url;
}
```

---

### Paymob Callback (Webhook)

Handles Paymob payment callbacks after payment completion.

**Endpoint:** `POST /api/v1/webhooks/paymob/callback`

**Authentication:** Not required (HMAC verification)

**Request Body (from Paymob):**
```json
{
  "obj": {
    "id": 123456789,
    "pending": false,
    "amount_cents": 149450,
    "success": true,
    "is_auth": false,
    "is_capture": false,
    "is_standalone_payment": true,
    "is_voided": false,
    "is_refunded": false,
    "is_3d_secure": false,
    "integration_id": 123456,
    "profile_id": 654321,
    "has_parent_transaction": false,
    "order": {
      "id": 987654321,
      "created_at": "2025-12-25T10:30:00.000000Z",
      "delivery_needed": false,
      "merchant": {
        "id": 111111,
        "created_at": "2025-01-01T00:00:00.000000Z",
        "phones": ["+201000000000"],
        "company_emails": ["admin@obsolio.com"],
        "company_name": "OBSOLIO",
        "state": "",
        "country": "EG",
        "city": "Cairo",
        "postal_code": "",
        "street": ""
      },
      "collector": null,
      "amount_cents": 149450,
      "shipping_data": {
        "id": 222222,
        "first_name": "Acme Corp",
        "last_name": "",
        "street": "N/A",
        "building": "N/A",
        "floor": "N/A",
        "apartment": "N/A",
        "city": "Cairo",
        "state": "Cairo",
        "country": "EG",
        "email": "admin@acme.com",
        "phone_number": "+201234567890",
        "postal_code": "N/A",
        "extra_description": "",
        "shipping_method": "N/A",
        "order_id": 987654321,
        "order": 987654321
      },
      "currency": "EGP",
      "is_payment_locked": false,
      "is_return": false,
      "is_cancel": false,
      "is_returned": false,
      "is_canceled": false,
      "merchant_order_id": "INV-1735124400",
      "wallet_notification": null,
      "paid_amount_cents": 149450,
      "notify_user_with_email": false,
      "items": [
        {
          "name": "Professional Plan",
          "description": "Subscription - monthly",
          "amount_cents": 149450,
          "quantity": 1
        }
      ],
      "order_url": "https://accept.paymob.com/standalone/?ref=i_...",
      "commission_fees": 0,
      "delivery_fees_cents": 0,
      "delivery_vat_cents": 0,
      "payment_method": "tbc",
      "merchant_staff_tag": null,
      "api_source": "OTHER",
      "data": {}
    },
    "created_at": "2025-12-25T10:30:15.000000Z",
    "transaction_processed_callback_responses": [],
    "currency": "EGP",
    "source_data": {
      "type": "card",
      "pan": "2346",
      "sub_type": "MasterCard"
    },
    "api_source": "OTHER",
    "terminal_id": null,
    "merchant_commission": 0,
    "installment": null,
    "discount_details": [],
    "is_void": false,
    "is_refund": false,
    "data": {
      "klass": "VPCPayment",
      "gateway_integration_pk": 123456,
      "batch_number": "20251225",
      "card_num": "512345xxxxxx2346",
      "receipt_no": "334455667788",
      "acq_response_code": "00",
      "avs_acq_response_code": "Unsupported",
      "authorize_id": "334455",
      "transaction_no": "9988776655",
      "merchant": "Test Merchant",
      "message": "Approved",
      "txn_response_code": "0"
    },
    "is_hidden": false,
    "payment_key_claims": {
      "user_id": 333333,
      "amount_cents": 149450,
      "currency": "EGP",
      "integration_id": 123456,
      "pmk_ip": "192.168.1.100",
      "billing_data": {
        "apartment": "N/A",
        "first_name": "Acme Corp",
        "last_name": "",
        "street": "N/A",
        "building": "N/A",
        "phone_number": "+201234567890",
        "country": "EG",
        "email": "admin@acme.com",
        "floor": "N/A",
        "state": "Cairo"
      },
      "lock_order_when_paid": false,
      "exp": 1735210815
    },
    "error_occured": false,
    "is_live": false,
    "other_endpoint_reference": null,
    "refunded_amount_cents": 0,
    "source_id": -1,
    "is_captured": false,
    "captured_amount": 0,
    "merchant_staff_tag": null,
    "updated_at": "2025-12-25T10:30:30.000000Z",
    "is_settled": false,
    "bill_balanced": false,
    "is_bill": false,
    "owner": 333333,
    "parent_transaction": null
  },
  "type": "TRANSACTION",
  "hmac": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Payment processed successfully"
}
```

**Error Response (500):**
```json
{
  "success": false,
  "message": "Payment verification failed"
}
```

**Notes:**
- This endpoint is called by Paymob servers, not by your frontend
- HMAC signature is verified to ensure authenticity
- Updates invoice status to "paid" and activates subscription
- Creates payment transaction record

---

### Payment Response (User Redirect)

Handles user redirect after completing payment on Paymob iframe.

**Endpoint:** `GET /api/v1/payments/response`

**Authentication:** Required (JWT)

**Query Parameters:**
- `success` (required, string): "true" or "false"
- `invoice_id` (required, uuid): ID of the invoice

**Success Response (200):**
```json
{
  "success": true,
  "message": "Payment completed successfully",
  "data": {
    "invoice_id": "9d4e5f6g-7h8i-9j0k-1l2m-3n4o5p6q7r8s",
    "invoice_number": "INV-1735124400",
    "amount": 49.00,
    "currency": "USD"
  }
}
```

**Error Response (200):**
```json
{
  "success": false,
  "message": "Payment was not completed or failed"
}
```

**Usage Example:**
```javascript
// After user completes payment on Paymob iframe, they're redirected to:
// https://yourapp.com/payment-success?success=true&invoice_id=9d4e5f6g-7h8i-9j0k-1l2m-3n4o5p6q7r8s

// Your frontend should call this endpoint to verify payment status
const params = new URLSearchParams(window.location.search);
const response = await fetch(`/api/v1/payments/response?${params}`, {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

const data = await response.json();
if (data.success) {
  // Show success message and redirect to dashboard
  window.location.href = '/dashboard';
}
```

---

### Refund Payment

Process a refund for a paid invoice.

**Endpoint:** `POST /api/v1/payments/refund/{invoice_id}`

**Authentication:** Required (JWT + Admin)

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**URL Parameters:**
- `invoice_id` (required, uuid): ID of the invoice to refund

**Request Body:**
```json
{
  "reason": "Customer requested refund due to service not meeting expectations"
}
```

**Request Fields:**
- `reason` (optional, string, max 500): Reason for refund

**Success Response (200):**
```json
{
  "success": true,
  "message": "Refund processed successfully",
  "data": {
    "refund_id": "123456789"
  }
}
```

**Error Response (400):**
```json
{
  "success": false,
  "message": "Only paid invoices can be refunded"
}
```

**Error Response (500):**
```json
{
  "success": false,
  "message": "Refund failed",
  "error": "Paymob API error details"
}
```

**Usage Example:**
```bash
curl -X POST https://api.obsolio.com/api/v1/payments/refund/9d4e5f6g-7h8i-9j0k-1l2m-3n4o5p6q7r8s \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "reason": "Customer requested refund"
  }'
```

---

## Paymob Payment Flow

### Complete Payment Flow Diagram

```
1. User selects plan
   ↓
2. Frontend calls POST /api/v1/payments/subscription
   ↓
3. Backend creates:
   - BillingInvoice (status: pending)
   - Paymob Order
   - Payment Key
   ↓
4. Backend returns iframe_url
   ↓
5. Frontend redirects user to Paymob iframe
   ↓
6. User enters card details and completes payment
   ↓
7. Paymob calls POST /api/v1/webhooks/paymob/callback
   ↓
8. Backend verifies HMAC and updates:
   - Invoice status → "paid"
   - Subscription status → "active"
   - Creates PaymentTransaction record
   ↓
9. Paymob redirects user back to your app
   ↓
10. Frontend calls GET /api/v1/payments/response
    ↓
11. Backend confirms payment status
    ↓
12. User sees success message
```

### Payment Configuration

The following environment variables must be set:

```env
PAYMOB_API_KEY=your_api_key_here
PAYMOB_INTEGRATION_ID=your_integration_id_here
PAYMOB_HMAC_SECRET=your_hmac_secret_here
PAYMOB_IFRAME_ID=799158
PAYMOB_CURRENCY=EGP
```

### Exchange Rate

Currently using hardcoded exchange rate (1 USD = 30.5 EGP). In production, integrate with a real-time exchange rate API.

### Security

- **HMAC Verification**: All webhook callbacks are verified using HMAC signature
- **JWT Authentication**: All payment endpoints (except webhook) require authentication
- **Tenant Isolation**: Payments are tenant-scoped
- **Invoice Validation**: Only paid invoices can be refunded

---

## Support

For API support, contact:
- Email: api@OBSOLIO.ai
- Documentation: https://docs.OBSOLIO.ai
- Status Page: https://status.OBSOLIO.ai
