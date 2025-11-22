# Aasim AI - API Endpoints Reference

**Base URL:** `http://127.0.0.1:8000/api/v1/`

---

## 📊 ACTIVITIES
- `GET /activities` - List all activities
- `GET /activities/export` - Export activities
- `GET /activities/user/{userId}` - Get user activities
- `GET /activities/{id}` - Get activity details

## 🤖 AGENTS
- `GET /agents` - List all agents
- `POST /agents` - Create new agent
- `GET /agents/{agent}` - Get agent details
- `PUT|PATCH /agents/{agent}` - Update agent
- `DELETE /agents/{agent}` - Delete agent
- `POST /agents/{id}/clone` - Clone agent
- `POST /agents/{id}/execute` - Execute agent
- `GET /agents/{id}/executions` - Get agent executions
- `POST /agents/{id}/publish` - Publish agent to marketplace

## 📈 ANALYTICS
- `GET /analytics/agents` - Agent analytics
- `GET /analytics/executions` - Execution analytics

## 🔑 API KEYS
- `GET /api-keys` - List API keys
- `POST /api-keys` - Create API key
- `GET /api-keys/{api_key}` - Get API key
- `PUT|PATCH /api-keys/{api_key}` - Update API key
- `DELETE /api-keys/{api_key}` - Delete API key
- `POST /api-keys/{id}/regenerate` - Regenerate API key
- `POST /api-keys/{id}/toggle` - Enable/disable API key

## 🔐 AUTHENTICATION
- `POST /auth/register` - Register new user
- `POST /auth/login` - Login user
- `POST /auth/logout` - Logout user
- `GET /auth/me` - Get current user
- `PUT /auth/profile` - Update profile
- `POST /auth/refresh` - Refresh token
- `POST /auth/change-password` - Change password
- `POST /auth/forgot-password` - Request password reset
- `POST /auth/reset-password` - Reset password

## 🏢 BRANCHES
- `GET /branches` - List branches
- `POST /branches` - Create branch
- `GET /branches/{branch}` - Get branch details
- `PUT|PATCH /branches/{branch}` - Update branch
- `DELETE /branches/{branch}` - Delete branch
- `GET /branches/{branchId}/departments` - Get branch departments

## 🔌 CONNECTED APPS
- `GET /connected-apps` - List connected apps
- `POST /connected-apps` - Connect new app
- `GET /connected-apps/{connected_app}` - Get app details
- `PUT|PATCH /connected-apps/{connected_app}` - Update app
- `DELETE /connected-apps/{connected_app}` - Disconnect app
- `GET /connected-apps/{id}/logs` - Get app logs
- `POST /connected-apps/{id}/refresh-token` - Refresh app token
- `POST /connected-apps/{id}/revoke` - Revoke app access
- `POST /connected-apps/{id}/sync` - Sync app data
- `POST /connected-apps/{id}/test` - Test app connection

## 📊 DASHBOARD
- `GET /dashboard/stats` - Get dashboard statistics

## 🏛️ DEPARTMENTS
- `GET /departments` - List departments
- `POST /departments` - Create department
- `GET /departments/{department}` - Get department details
- `PUT|PATCH /departments/{department}` - Update department
- `DELETE /departments/{department}` - Delete department
- `GET /departments/{departmentId}/projects` - Get department projects

## ⚙️ ENGINES
- `GET /engines` - List AI engines
- `GET /engines/{id}` - Get engine details
- `GET /engines/{engine}/rubrics` - List engine rubrics
- `POST /engines/{engine}/rubrics` - Create rubric
- `POST /engines/{id}/rubrics` - Create rubric (alt)
- `GET /engines/{engine}/rubrics/{rubric}` - Get rubric
- `PUT|PATCH /engines/{engine}/rubrics/{rubric}` - Update rubric
- `DELETE /engines/{engine}/rubrics/{rubric}` - Delete rubric
- `PUT /engines/{engineId}/rubrics/{id}` - Update rubric (alt)
- `DELETE /engines/{engineId}/rubrics/{id}` - Delete rubric (alt)

## 🚀 EXECUTIONS
- `GET /executions` - List agent executions
- `GET /executions/{id}` - Get execution details
- `POST /executions/{id}/cancel` - Cancel execution
- `GET /executions/{id}/logs` - Get execution logs

## 👤 HITL APPROVALS (Human-in-the-Loop)
- `GET /hitl-approvals` - List approvals
- `GET /hitl-approvals/pending` - Get pending approvals
- `GET /hitl-approvals/{hitl_approval}` - Get approval details
- `POST /hitl-approvals/{id}/approve` - Approve request
- `POST /hitl-approvals/{id}/reject` - Reject request
- `POST /hitl-approvals/{id}/escalate` - Escalate request

## 📋 JOB FLOWS
- `GET /job-flows` - List job flows
- `POST /job-flows` - Create job flow
- `GET /job-flows/{job_flow}` - Get job flow details
- `PUT|PATCH /job-flows/{job_flow}` - Update job flow
- `DELETE /job-flows/{job_flow}` - Delete job flow
- `GET /job-flows/{id}/stats` - Get flow statistics
- `PUT /job-flows/{id}/status` - Update flow status
- `POST /job-flows/{id}/trigger` - Trigger job flow

## 🛒 MARKETPLACE
- `GET /marketplace` - Browse marketplace
- `POST /marketplace` - List agent in marketplace
- `GET /marketplace/my-listings` - Get my listings
- `GET /marketplace/my-purchases` - Get my purchases
- `GET /marketplace/{id}` - Get marketplace item
- `PUT /marketplace/{id}` - Update listing
- `DELETE /marketplace/{id}` - Remove listing
- `POST /marketplace/{id}/purchase` - Purchase agent

## 🏛️ ORGANIZATIONS
- `GET /organizations` - List organizations
- `POST /organizations` - Create organization
- `GET /organizations/{organization}` - Get organization details
- `PUT|PATCH /organizations/{organization}` - Update organization
- `DELETE /organizations/{organization}` - Delete organization
- `GET /organizations/{id}/dashboard` - Organization dashboard
- `GET /organizations/{organizationId}/branches` - Get organization branches
- `GET /organizations/{organizationId}/departments` - Get organization departments

## 🔒 PERMISSIONS
- `GET /permissions` - List all permissions
- `GET /permissions/list` - Get permission list
- `GET /permissions/{id}` - Get permission details

## 📁 PROJECTS
- `GET /projects` - List projects
- `POST /projects` - Create project
- `GET /projects/{project}` - Get project details
- `PUT|PATCH /projects/{project}` - Update project
- `DELETE /projects/{project}` - Delete project
- `PUT /projects/{id}/status` - Update project status

## 👥 ROLES
- `GET /roles` - List roles
- `POST /roles` - Create role
- `GET /roles/{role}` - Get role details
- `PUT|PATCH /roles/{role}` - Update role
- `DELETE /roles/{role}` - Delete role

## 🔄 SESSIONS
- `GET /sessions` - List active sessions
- `GET /sessions/active` - Get active sessions
- `POST /sessions/{id}/terminate` - Terminate session

## 💳 SUBSCRIPTIONS
- `GET /subscriptions` - List subscriptions
- `GET /subscriptions/current` - Get current subscription
- `POST /subscriptions/subscribe` - Subscribe to plan
- `POST /subscriptions/cancel` - Cancel subscription
- `GET /subscriptions/usage` - Get usage statistics
- `GET /subscriptions/usage/{date}` - Get usage for specific date

## 👥 TEAMS
- `GET /teams` - List teams
- `POST /teams` - Create team
- `GET /teams/{team}` - Get team details
- `PUT|PATCH /teams/{team}` - Update team
- `DELETE /teams/{team}` - Delete team
- `POST /teams/{id}/members` - Add team member
- `DELETE /teams/{id}/members/{userId}` - Remove team member

## 👤 USERS
- `GET /users` - List users
- `POST /users` - Create user
- `GET /users/{user}` - Get user details
- `PUT|PATCH /users/{user}` - Update user
- `DELETE /users/{user}` - Delete user
- `POST /users/{id}/assign` - Assign user to resource
- `GET /users/{id}/assignments` - Get user assignments
- `PUT /users/{id}/status` - Update user status

## 🔔 WEBHOOKS
- `GET /webhooks` - List webhooks
- `POST /webhooks` - Create webhook
- `GET /webhooks/{webhook}` - Get webhook details
- `PUT|PATCH /webhooks/{webhook}` - Update webhook
- `DELETE /webhooks/{webhook}` - Delete webhook
- `POST /webhooks/{id}/test` - Test webhook
- `POST /webhooks/{id}/toggle` - Enable/disable webhook

## 🔄 WORKFLOWS
- `GET /workflows` - List workflows
- `POST /workflows` - Create workflow
- `GET /workflows/{workflow}` - Get workflow details
- `PUT|PATCH /workflows/{workflow}` - Update workflow
- `DELETE /workflows/{workflow}` - Delete workflow
- `POST /workflows/{id}/execute` - Execute workflow
- `GET /workflows/{id}/executions` - Get workflow executions
- `GET /workflows/executions/{executionId}` - Get execution details

---

## Authentication

Most endpoints require authentication using Bearer tokens:

```bash
Authorization: Bearer YOUR_API_TOKEN
```

## Response Format

All responses follow this format:

```json
{
  "success": true,
  "data": {},
  "message": "Success message"
}
```

## Error Format

```json
{
  "success": false,
  "message": "Error message",
  "errors": {}
}
```
