# Swagger/OpenAPI Documentation Guide

## Overview

This document provides a complete mapping of all API endpoints that should appear in the Swagger documentation at `https://api.obsolio.com/api/documentation`.

---

## Current Documentation Status

### ✅ Already Documented (Existing in Production)

The following endpoints are already documented:

#### Authentication
- `POST /api/v1/auth/register` - Register new user with tenant
- `POST /api/v1/auth/login` - Login user
- `GET /api/v1/auth/me` - Get current user
- `POST /api/v1/auth/logout` - Logout user
- `POST /api/v1/auth/refresh` - Refresh token
- `PUT /api/v1/auth/profile` - Update user profile
- `POST /api/v1/auth/change-password` - Change user password

#### Dashboard
- `GET /api/v1/dashboard/stats` - Get dashboard statistics

#### Organizations
- `GET /api/v1/organizations` - List organizations
- `POST /api/v1/organizations` - Create organization
- `GET /api/v1/organizations/{organization}` - Get organization details
- `PUT /api/v1/organizations/{organization}` - Update organization
- `DELETE /api/v1/organizations/{organization}` - Delete organization
- `POST /api/v1/organizations/{organization}/switch` - Switch organization context

#### Permissions
- `GET /api/v1/permissions` - List permissions (grouped)
- `GET /api/v1/permissions/list` - List permissions (flat)
- `GET /api/v1/permissions/{id}` - Get permission details

#### Roles
- `GET /api/v1/roles` - List roles
- `POST /api/v1/roles` - Create role
- `GET /api/v1/roles/{id}` - Get role details
- `PUT /api/v1/roles/{id}` - Update role
- `DELETE /api/v1/roles/{id}` - Delete role

#### Tenant
- `GET /api/v1/tenants` - Get all user's tenants
- `POST /api/v1/tenants` - Create a new tenant
- `POST /api/v1/tenants/{id}/switch` - Switch active tenant
- `GET /api/v1/tenant` - Get current tenant
- `PUT /api/v1/tenant` - Update current tenant

#### Activities
- `GET /api/v1/activities` - List all activities
- `GET /api/v1/activities/{id}` - Get activity details
- `GET /api/v1/activities/user/{userId}` - Get user activities
- `GET /api/v1/activities/export` - Export activities

#### Sessions
- `GET /api/v1/sessions` - List active sessions
- `GET /api/v1/sessions/active` - Get active sessions
- `POST /api/v1/sessions/{id}/terminate` - Terminate session

---

## 🆕 NEW: Agent Execution Endpoints (To Be Added)

These are the new endpoints that have been implemented with complete Swagger annotations and should appear in the documentation after regeneration.

### Tag: **Agent Execution**

All agent execution endpoints use the `Agent Execution` tag for grouping in Swagger UI.

---

#### 1. Execute Agent Asynchronously

**Endpoint:** `POST /api/v1/agents/{id}/run`

**Summary:** Execute an agent asynchronously

**Description:** Initiates asynchronous execution of an agent. The agent will process the request in the background and send results to the callback webhook.

**Security:** Bearer Token (JWT) required

**Path Parameters:**
- `id` (string, uuid, required) - Agent UUID
  - Example: `550e8400-e29b-41d4-a716-446655440000`

**Request Body:**
```json
{
  "input": {
    "query": "What is the weather today?",
    "location": "Cairo"
  }
}
```

**Fields:**
- `input` (object, required) - Input parameters for the agent execution
  - Can contain any key-value pairs depending on the agent's requirements

**Responses:**

**202 Accepted** - Agent execution initiated successfully
```json
{
  "success": true,
  "message": "Agent execution initiated",
  "data": {
    "run_id": "660e8400-e29b-41d4-a716-446655440001",
    "status": "running",
    "agent": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Weather Agent",
      "runtime_type": "n8n"
    }
  }
}
```

**400 Bad Request** - Agent is not active or no trigger endpoint configured
```json
{
  "success": false,
  "message": "Agent is not active"
}
```

**404 Not Found** - Agent not found
```json
{
  "success": false,
  "message": "Agent not found"
}
```

**422 Unprocessable Entity** - Validation failed
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "input": ["The input field is required."]
  }
}
```

**500 Internal Server Error** - Agent failed to accept execution or internal error
```json
{
  "success": false,
  "message": "Failed to connect to agent"
}
```

---

#### 2. Get Agent Execution Status

**Endpoint:** `GET /api/v1/agent-runs/{run_id}`

**Summary:** Get agent execution status

**Description:** Retrieve the status and results of an agent execution run

**Security:** Bearer Token (JWT) required

**Path Parameters:**
- `run_id` (string, uuid, required) - Agent Run UUID
  - Example: `660e8400-e29b-41d4-a716-446655440001`

**Responses:**

**200 OK** - Run status retrieved successfully
```json
{
  "success": true,
  "data": {
    "run_id": "660e8400-e29b-41d4-a716-446655440001",
    "status": "completed",
    "input": {
      "query": "What is the weather today?",
      "location": "Cairo"
    },
    "output": {
      "result": "The weather in Cairo is sunny, 28°C"
    },
    "error": null,
    "created_at": "2025-12-27T10:00:00.000000Z",
    "updated_at": "2025-12-27T10:00:05.000000Z",
    "agent": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Weather Agent",
      "runtime_type": "n8n"
    }
  }
}
```

**Fields:**
- `run_id` (string, uuid) - Unique identifier for this run
- `status` (string, enum) - Execution status
  - Possible values: `pending`, `running`, `completed`, `failed`
- `input` (object) - Input parameters that were sent to the agent
- `output` (object, nullable) - Result returned by the agent (null until completed)
- `error` (string, nullable) - Error message if execution failed
- `created_at` (string, datetime) - When the run was created
- `updated_at` (string, datetime) - When the run was last updated
- `agent` (object) - Agent details
  - `id` (string, uuid) - Agent UUID
  - `name` (string) - Agent name
  - `runtime_type` (string) - Runtime environment (n8n or custom)

**404 Not Found** - Agent run not found
```json
{
  "success": false,
  "message": "Agent run not found"
}
```

**500 Internal Server Error** - Internal server error
```json
{
  "success": false,
  "message": "Internal server error"
}
```

---

#### 3. Agent Execution Callback Webhook

**Endpoint:** `POST /api/v1/webhooks/agents/callback`

**Summary:** Agent execution callback webhook

**Description:** Webhook endpoint for agents to send execution results. This endpoint does not require JWT authentication but validates a secret token instead.

**Security:** None (uses secret token validation instead of JWT)

**Request Body:**
```json
{
  "run_id": "660e8400-e29b-41d4-a716-446655440001",
  "status": "completed",
  "output": {
    "result": "The weather in Cairo is sunny, 28°C"
  },
  "error": null,
  "secret": "your-callback-secret-token"
}
```

**Fields:**
- `run_id` (string, uuid, required) - The run ID provided in the trigger request
- `status` (string, enum, required) - Execution result status
  - Possible values: `completed`, `failed`
- `output` (object, optional) - Result data (required if status is completed)
- `error` (string, optional) - Error message (required if status is failed)
- `secret` (string, required) - Secret token for authentication

**Responses:**

**200 OK** - Callback received and processed successfully
```json
{
  "success": true,
  "message": "Callback received and processed",
  "data": {
    "run_id": "660e8400-e29b-41d4-a716-446655440001",
    "status": "completed"
  }
}
```

**400 Bad Request** - No active callback endpoint configured
```json
{
  "success": false,
  "message": "No active callback endpoint configured for this agent"
}
```

**401 Unauthorized** - Invalid secret token
```json
{
  "success": false,
  "message": "Invalid secret"
}
```

**404 Not Found** - Agent run not found
```json
{
  "success": false,
  "message": "Agent run not found"
}
```

**422 Unprocessable Entity** - Validation failed
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "run_id": ["The run id field is required."]
  }
}
```

**500 Internal Server Error** - Internal server error
```json
{
  "success": false,
  "message": "Internal server error"
}
```

---

## Execution Flow Example

### Step-by-Step Agent Execution

1. **User initiates execution**
   ```bash
   POST /api/v1/agents/550e8400-e29b-41d4-a716-446655440000/run
   Authorization: Bearer <jwt-token>

   {
     "input": {
       "query": "What is the weather?",
       "location": "Cairo"
     }
   }
   ```

2. **System responds immediately**
   ```json
   {
     "success": true,
     "message": "Agent execution initiated",
     "data": {
       "run_id": "660e8400-e29b-41d4-a716-446655440001",
       "status": "running",
       "agent": {...}
     }
   }
   ```

3. **User polls for status**
   ```bash
   GET /api/v1/agent-runs/660e8400-e29b-41d4-a716-446655440001
   Authorization: Bearer <jwt-token>
   ```

4. **While processing, status shows running**
   ```json
   {
     "success": true,
     "data": {
       "run_id": "660e8400-e29b-41d4-a716-446655440001",
       "status": "running",
       "input": {...},
       "output": null,
       "error": null
     }
   }
   ```

5. **Agent sends callback (no JWT required)**
   ```bash
   POST /api/v1/webhooks/agents/callback

   {
     "run_id": "660e8400-e29b-41d4-a716-446655440001",
     "status": "completed",
     "output": {
       "result": "The weather in Cairo is sunny, 28°C"
     },
     "secret": "agent-callback-secret"
   }
   ```

6. **User polls again and gets result**
   ```json
   {
     "success": true,
     "data": {
       "run_id": "660e8400-e29b-41d4-a716-446655440001",
       "status": "completed",
       "output": {
         "result": "The weather in Cairo is sunny, 28°C"
       },
       "error": null
     }
   }
   ```

---

## How to Regenerate Documentation

To update the Swagger documentation and make these new endpoints appear:

1. **Ensure all dependencies are installed:**
   ```bash
   composer install
   ```

2. **Generate Swagger documentation:**
   ```bash
   php artisan l5-swagger:generate
   ```

3. **Clear cache (if needed):**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

4. **Verify the documentation:**
   - Visit `https://api.obsolio.com/api/documentation`
   - Look for the "Agent Execution" tag in the sidebar
   - The three new endpoints should appear under this tag

---

## Documentation File Locations

- **OpenAPI JSON:** `storage/api-docs/api-docs.json`
- **Controller with annotations:** `app/Http/Controllers/Api/V1/AgentExecutionController.php`
- **Base OpenAPI config:** `app/Http/Controllers/Controller.php`
- **L5-Swagger config:** `config/l5-swagger.php`

---

## Testing the Endpoints in Swagger UI

Once the documentation is regenerated:

1. **Authorize with JWT:**
   - Click the "Authorize" button in Swagger UI
   - Enter your JWT token in the format: `Bearer <your-token>`
   - Click "Authorize"

2. **Test Execute Agent:**
   - Expand `POST /api/v1/agents/{id}/run`
   - Click "Try it out"
   - Enter a valid agent UUID
   - Modify the request body as needed
   - Click "Execute"

3. **Test Get Run Status:**
   - Expand `GET /api/v1/agent-runs/{run_id}`
   - Click "Try it out"
   - Enter the run_id from step 2
   - Click "Execute"

4. **Test Callback (External Agents):**
   - This endpoint is called by external agents
   - Does not require JWT authorization
   - Requires valid secret token from agent_endpoints table

---

## Notes for Frontend Developers

### Agent Execution Best Practices

1. **Polling Strategy:**
   - Poll the status endpoint every 2-5 seconds
   - Use exponential backoff if run is still `running`
   - Stop polling when status is `completed` or `failed`

2. **Error Handling:**
   - Always check the `success` field
   - Display user-friendly messages for common errors (400, 404, 422)
   - Log 500 errors for debugging

3. **Status Display:**
   - `pending` - "Initializing..."
   - `running` - "Processing..." (show spinner)
   - `completed` - Display the output
   - `failed` - Display the error message

4. **Timeout Handling:**
   - Each agent has an `execution_timeout_ms` field
   - If polling exceeds this timeout, show a timeout message
   - User can still check status later

---

## Security Considerations

### JWT Authentication
- All user-facing endpoints require valid JWT token
- Token must be included in Authorization header: `Bearer <token>`

### Webhook Security
- Callback endpoint does NOT use JWT
- Instead, validates secret token stored in `agent_endpoints` table
- Secret token is sent in request body, validated using `hash_equals()`
- Invalid secret returns 401 Unauthorized

### Rate Limiting
- Consider implementing rate limiting on execution endpoint
- Prevent abuse of agent execution
- Protect against DOS attacks

---

## API Versioning

Current version: **v1**

All endpoints are prefixed with `/api/v1/`

Future versions should:
- Create new controllers under `Api\V2`
- Update OpenAPI `@OA\Server` annotation
- Maintain backward compatibility for v1

---

## Changelog

### 2025-12-27 - Agent Execution Feature
- ✅ Added `POST /api/v1/agents/{id}/run` - Execute agent asynchronously
- ✅ Added `GET /api/v1/agent-runs/{run_id}` - Get execution status
- ✅ Added `POST /api/v1/webhooks/agents/callback` - Agent callback webhook
- ✅ Complete Swagger annotations with examples
- ✅ Documented async execution flow
- ✅ Security requirements clearly defined

---

## Support

For issues with API documentation:
- Check that annotations are valid OpenAPI 3.0 syntax
- Verify `php artisan l5-swagger:generate` runs without errors
- Clear application cache if changes don't appear
- Check Laravel logs for generation errors

For API endpoint issues:
- See `MIGRATION_NOTES.md` for implementation details
- Check controller logic in `AgentExecutionController.php`
- Review model relationships in `Agent.php`, `AgentRun.php`, `AgentEndpoint.php`
