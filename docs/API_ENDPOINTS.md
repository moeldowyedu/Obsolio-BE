# OBSOLIO AI API Endpoints Reference

Complete reference for all API endpoints in the OBSOLIO AI Platform.

## Table of Contents

- [Authentication](#authentication)
- [Users](#users)
- [Roles & Permissions](#roles--permissions)
- [Organizations](#organizations)
- [Branches](#branches)
- [Departments](#departments)
- [Projects](#projects)
- [Teams](#teams)
- [AI Agents](#ai-agents)
- [Workflows](#workflows)
- [Job Flows](#job-flows)
- [HITL Approvals](#hitl-approvals)
- [API Keys](#api-keys)
- [Webhooks](#webhooks)
- [Connected Apps](#connected-apps)
- [User Activities](#user-activities)
- [User Sessions](#user-sessions)
- [Marketplace](#marketplace)
- [Subscriptions](#subscriptions)

## Base Information

**Base URL**: `http://localhost:8000/api/v1`
**Authentication**: Bearer Token (Laravel Sanctum)
**Content-Type**: `application/json`

## Authentication

### Register
```http
POST /auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "avatar_url": "https://example.com/avatar.jpg" (optional),
  "phone": "+1234567890" (optional)
}
```

**Response (201)**:
```json
{
  "success": true,
  "data": {
    "user": {
      "id": "uuid",
      "name": "John Doe",
      "email": "john@example.com",
      "status": "active"
    },
    "token": "your_sanctum_token_here"
  }
}
```

### Login
```http
POST /auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "SecurePass123!"
}
```

### Logout
```http
POST /auth/logout
Authorization: Bearer {token}
```

### Get Authenticated User
```http
GET /auth/me
Authorization: Bearer {token}
```

### Update Profile
```http
PUT /auth/profile
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Updated Name",
  "email": "newemail@example.com",
  "avatar_url": "https://example.com/new-avatar.jpg",
  "phone": "+1234567890"
}
```

### Change Password
```http
POST /auth/change-password
Authorization: Bearer {token}
Content-Type: application/json

{
  "current_password": "OldPassword123!",
  "password": "NewPassword123!",
  "password_confirmation": "NewPassword123!"
}
```

## Users

### List Users
```http
GET /users?status=active&role=admin&search=john&sort=-created_at&per_page=20
Authorization: Bearer {token}
```

**Query Parameters**:
- `status`: Filter by status (active, inactive, suspended)
- `role`: Filter by role name
- `organization_id`: Filter by organization UUID
- `search`: Search by name or email
- `sort`: Sort field (prefix with `-` for ascending)
- `per_page`: Items per page (default: 15)
- `page`: Page number

### Create User
```http
POST /users
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "SecurePass123!",
  "status": "active",
  "roles": ["admin", "editor"]
}
```

### Get User
```http
GET /users/{id}
Authorization: Bearer {token}
```

### Update User
```http
PUT /users/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Updated Name",
  "email": "updated@example.com",
  "status": "inactive",
  "roles": ["editor"]
}
```

### Delete User
```http
DELETE /users/{id}
Authorization: Bearer {token}
```

### Update User Status
```http
PUT /users/{id}/status
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "suspended"
}
```

### Assign User to Entity
```http
POST /users/{id}/assign
Authorization: Bearer {token}
Content-Type: application/json

{
  "entity_type": "Project",
  "entity_id": "project-uuid",
  "role": "Developer"
}
```

### Get User Assignments
```http
GET /users/{id}/assignments?per_page=15
Authorization: Bearer {token}
```

## Roles & Permissions

### List Roles
```http
GET /roles?per_page=20
Authorization: Bearer {token}
```

### Create Role
```http
POST /roles
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "custom-role",
  "display_name": "Custom Role",
  "description": "A custom role",
  "permissions": ["view-users", "create-users"]
}
```

### List Permissions
```http
GET /permissions?per_page=50
Authorization: Bearer {token}
```

### List All Permissions (Unpaginated)
```http
GET /permissions/list
Authorization: Bearer {token}
```

## Organizations

### List Organizations
```http
GET /organizations?search=acme&per_page=15
Authorization: Bearer {token}
```

### Create Organization
```http
POST /organizations
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "ACME Corporation",
  "type": "enterprise",
  "industry": "Technology",
  "size": "medium",
  "description": "A technology company"
}
```

### Organization Dashboard
```http
GET /organizations/{id}/dashboard
Authorization: Bearer {token}
```

## API Keys

### List API Keys
```http
GET /api-keys?per_page=15
Authorization: Bearer {token}
```

**Note**: API keys are masked in list/show responses. Full key is returned only once on creation.

### Create API Key
```http
POST /api-keys
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Production API Key",
  "scopes": ["read", "write"],
  "expires_at": "2026-01-01"
}
```

**Response (201)**:
```json
{
  "success": true,
  "data": {
    "api_key": {
      "id": "uuid",
      "name": "Production API Key",
      "key_prefix": "OBSOLIO_12345678",
      "scopes": ["read", "write"],
      "expires_at": "2026-01-01",
      "is_active": true
    },
    "plain_key": "OBSOLIO_full_key_shown_only_once_here"
  }
}
```

### Regenerate API Key
```http
POST /api-keys/{id}/regenerate
Authorization: Bearer {token}
```

### Toggle API Key Status
```http
POST /api-keys/{id}/toggle
Authorization: Bearer {token}
```

## Webhooks

### List Webhooks
```http
GET /webhooks?per_page=15
Authorization: Bearer {token}
```

### Create Webhook
```http
POST /webhooks
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Production Webhook",
  "url": "https://your-server.com/webhook",
  "description": "Webhook for production events",
  "events": [
    "agent.deployed",
    "agent.executed",
    "job.completed",
    "job.failed"
  ],
  "headers": [
    {
      "key": "X-Custom-Header",
      "value": "custom-value"
    }
  ],
  "secret": "optional-custom-secret",
  "is_active": true
}
```

**Available Events**:
- `agent.deployed`, `agent.updated`, `agent.executed`
- `job.started`, `job.completed`, `job.failed`
- `approval.requested`, `approval.approved`, `approval.rejected`
- `workflow.started`, `workflow.completed`, `workflow.failed`
- `error.occurred`
- `user.created`, `user.updated`
- `project.created`, `project.updated`

### Test Webhook
```http
POST /webhooks/{id}/test
Authorization: Bearer {token}
```

### Toggle Webhook Status
```http
POST /webhooks/{id}/toggle
Authorization: Bearer {token}
```

## Connected Apps

### List Connected Apps
```http
GET /connected-apps?per_page=15
Authorization: Bearer {token}
```

### Connect App
```http
POST /connected-apps
Authorization: Bearer {token}
Content-Type: application/json

{
  "app_name": "Slack Integration",
  "app_type": "slack",
  "credentials": {
    "client_id": "slack-client-id",
    "client_secret": "slack-client-secret"
  },
  "access_token": "slack-access-token",
  "refresh_token": "slack-refresh-token",
  "token_expires_at": "2026-01-01",
  "scopes": ["chat:write", "channels:read"]
}
```

### Sync App Data
```http
POST /connected-apps/{id}/sync
Authorization: Bearer {token}
```

### Test Connection
```http
POST /connected-apps/{id}/test
Authorization: Bearer {token}
```

### Refresh Token
```http
POST /connected-apps/{id}/refresh-token
Authorization: Bearer {token}
```

### Revoke Access
```http
POST /connected-apps/{id}/revoke
Authorization: Bearer {token}
```

### Get App Logs
```http
GET /connected-apps/{id}/logs?per_page=20
Authorization: Bearer {token}
```

## User Activities

### List Activities
```http
GET /activities?user_id=uuid&activity_type=create&status=success&date_from=2025-01-01&date_to=2025-12-31&per_page=20
Authorization: Bearer {token}
```

**Query Parameters**:
- `user_id`: Filter by user UUID
- `organization_id`: Filter by organization UUID
- `activity_type`: Filter by type (create, update, delete, api_call, etc.)
- `action`: Filter by action (create, read, update, delete, execute)
- `entity_type`: Filter by entity type (User, Project, Agent, etc.)
- `status`: Filter by status (success, failure)
- `is_sensitive`: Filter sensitive activities (true/false)
- `requires_audit`: Filter audit-required activities (true/false)
- `date_from`: Filter from date (YYYY-MM-DD)
- `date_to`: Filter to date (YYYY-MM-DD)

### Get Activity Details
```http
GET /activities/{id}
Authorization: Bearer {token}
```

### Get User's Activities
```http
GET /activities/user/{userId}?activity_type=create&per_page=20
Authorization: Bearer {token}
```

### Export Activities to CSV
```http
GET /activities/export?user_id=uuid&date_from=2025-01-01&date_to=2025-12-31
Authorization: Bearer {token}
```

**Response**:
```json
{
  "success": true,
  "message": "Activities exported successfully",
  "data": {
    "filename": "activities_export_2025-11-20_143022.csv",
    "csv_data": "ID,User,Organization,Activity Type,...",
    "total_records": 1523
  }
}
```

## User Sessions

### List Sessions
```http
GET /sessions?user_id=uuid&is_active=true&device_type=web&per_page=20
Authorization: Bearer {token}
```

### List Active Sessions
```http
GET /sessions/active?per_page=20
Authorization: Bearer {token}
```

### Terminate Session
```http
POST /sessions/{id}/terminate
Authorization: Bearer {token}
```

## AI Agents

### List Agents
```http
GET /agents?status=active&per_page=15
Authorization: Bearer {token}
```

### Create Agent
```http
POST /agents
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Data Processing Agent",
  "description": "Processes and analyzes data",
  "engine_id": "engine-uuid",
  "config": {
    "temperature": 0.7,
    "max_tokens": 2000
  },
  "is_public": false
}
```

### Execute Agent
```http
POST /agents/{id}/execute
Authorization: Bearer {token}
Content-Type: application/json

{
  "input": {
    "data": "Input data for agent"
  },
  "parameters": {
    "temperature": 0.8
  }
}
```

### Clone Agent
```http
POST /agents/{id}/clone
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Cloned Agent Name"
}
```

### Publish to Marketplace
```http
POST /agents/{id}/publish
Authorization: Bearer {token}
Content-Type: application/json

{
  "price": 29.99,
  "category": "data-processing"
}
```

## Workflows

### List Workflows
```http
GET /workflows?per_page=15
Authorization: Bearer {token}
```

### Create Workflow
```http
POST /workflows
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Data Processing Workflow",
  "description": "Multi-step data processing",
  "steps": [
    {
      "name": "Extract",
      "type": "agent",
      "agent_id": "agent-uuid",
      "config": {}
    },
    {
      "name": "Transform",
      "type": "agent",
      "agent_id": "agent-uuid-2",
      "config": {}
    }
  ]
}
```

### Execute Workflow
```http
POST /workflows/{id}/execute
Authorization: Bearer {token}
Content-Type: application/json

{
  "input": {
    "data": "Workflow input data"
  }
}
```

### Get Workflow Executions
```http
GET /workflows/{id}/executions?per_page=20
Authorization: Bearer {token}
```

## HITL Approvals

### List Pending Approvals
```http
GET /hitl-approvals/pending?per_page=15
Authorization: Bearer {token}
```

### Approve Request
```http
POST /hitl-approvals/{id}/approve
Authorization: Bearer {token}
Content-Type: application/json

{
  "comment": "Approved after review"
}
```

### Reject Request
```http
POST /hitl-approvals/{id}/reject
Authorization: Bearer {token}
Content-Type: application/json

{
  "reason": "Does not meet requirements",
  "comment": "Please revise and resubmit"
}
```

### Escalate Request
```http
POST /hitl-approvals/{id}/escalate
Authorization: Bearer {token}
Content-Type: application/json

{
  "escalate_to_user_id": "supervisor-uuid",
  "reason": "Requires senior approval"
}
```

## Common Response Codes

- `200 OK` - Successful GET, PUT, PATCH, or POST
- `201 Created` - Successful POST that creates a resource
- `204 No Content` - Successful DELETE
- `400 Bad Request` - Invalid request data
- `401 Unauthorized` - Missing or invalid authentication token
- `403 Forbidden` - Authenticated but not authorized
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation errors
- `500 Internal Server Error` - Server error

## Error Response Format

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": [
      "The email field is required.",
      "The email must be a valid email address."
    ],
    "password": [
      "The password must be at least 8 characters."
    ]
  }
}
```

## Pagination Response Format

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [ /* array of items */ ],
    "first_page_url": "http://api.example.com/users?page=1",
    "from": 1,
    "last_page": 5,
    "last_page_url": "http://api.example.com/users?page=5",
    "links": [ /* pagination links */ ],
    "next_page_url": "http://api.example.com/users?page=2",
    "path": "http://api.example.com/users",
    "per_page": 15,
    "prev_page_url": null,
    "to": 15,
    "total": 73
  }
}
```

## Rate Limiting

- **Authentication endpoints**: 5 requests per minute
- **General API endpoints**: 60 requests per minute
- **Bulk operations**: 10 requests per minute

Rate limit headers are included in all responses:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1637745600
```

---

For more information, see the main [README.md](../README.md).
