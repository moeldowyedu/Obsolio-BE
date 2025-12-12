# OBSOLIO - Laravel Backend API

Enterprise-grade REST API for the OBSOLIO Platform built with Laravel 12 and PostgreSQL, featuring multi-tenancy, comprehensive role-based access control, and extensive activity logging.

## 🚀 Features

- **JWT Authentication** - Secure token-based authentication with Laravel Sanctum
- **Multi-Tenancy** - Full tenant isolation using `stancl/tenancy`
- **Role-Based Access Control (RBAC)** - Granular permissions using Spatie Laravel Permission
- **Hierarchical Organization** - Organizations, branches, departments, teams, and projects
- **Integration Management** - API keys, webhooks, and connected apps
- **Comprehensive Activity Logging** - Complete audit trail for all operations
- **User Session Management** - Track and manage active user sessions
- **RESTful API** - Standard HTTP methods and consistent JSON responses
- **Pagination & Filtering** - Efficient data retrieval with search and sort capabilities
- **Soft Deletes** - Recoverable data deletion
- **AI Agent Management** - Create, execute, and monitor AI agents
- **Workflow Orchestration** - Build and execute complex workflows
- **Human-in-the-Loop (HITL)** - Approval workflows for critical operations
- **Marketplace** - Share and purchase AI agents
- **Health Checks** - Kubernetes-ready readiness and liveness probes
- **Prometheus Metrics** - Built-in metrics endpoint for monitoring

## 📋 Requirements

- **PHP**: 8.2+
- **Database**: PostgreSQL 15+ or SQLite (development)
- **Composer**: 2.0+
- **Laravel**: 12.38.1
- **Extensions**: PDO, OpenSSL, Mbstring, Tokenizer, XML, Ctype, JSON

## 🔧 Installation

### 1. Clone Repository
```bash
git clone <repository-url>
cd OBSOLIO-BE
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Environment Configuration
```bash
cp .env.example .env
```

Update `.env` with your configuration:

```env
APP_NAME="OBSOLIO"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database Configuration
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=obsolio
DB_USERNAME=postgres
DB_PASSWORD=your_password

# For development, you can use SQLite:
# DB_CONNECTION=sqlite
# DB_DATABASE=/absolute/path/to/database.sqlite

# Frontend URL for CORS
FRONTEND_URL=http://localhost:5173

# JWT Configuration (will be generated)
JWT_SECRET=

# Tenancy Configuration
CENTRAL_DOMAINS=localhost,127.0.0.1
```

### 4. Generate Application Key
```bash
php artisan key:generate
```

### 5. Run Migrations
```bash
# For PostgreSQL:
php artisan migrate

# For development with SQLite:
touch database/database.sqlite
php artisan migrate
```

### 6. (Optional) Seed Database
```bash
php artisan db:seed
```

### 7. Start Development Server
```bash
php artisan serve
```

API will be available at: **http://localhost:8000**

## 🔐 Authentication

All endpoints except registration, login, and public marketplace require authentication via Laravel Sanctum tokens.

### Authentication Header
```bash
Authorization: Bearer <your_token>
```

### Obtaining a Token
```bash
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "your_password"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": { "id": "uuid", "name": "User Name", "email": "user@example.com" },
    "token": "your_sanctum_token_here"
  }
}
```

## 📚 API Documentation

### Base URL
```
http://localhost:8000/api/v1
```

### Response Format

#### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

#### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": { "field": ["Validation error message"] }
}
```

### HTTP Status Codes
- `200` - OK (Success)
- `201` - Created
- `204` - No Content (Successful deletion)
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Unprocessable Entity (Validation Error)
- `500` - Internal Server Error

## 🛣️ API Endpoints

### Health & Monitoring (4 endpoints)
```bash
GET  /api/health               # Lightweight health check
GET  /api/health/detailed      # Detailed health check with database status
GET  /api/health/ready         # Kubernetes readiness probe
GET  /api/health/alive         # Kubernetes liveness probe
GET  /api/metrics              # Prometheus metrics
```

### Authentication (9 endpoints)
```bash
POST /api/v1/auth/register          # Register new user
POST /api/v1/auth/login             # Login user
POST /api/v1/auth/logout            # Logout user
POST /api/v1/auth/refresh           # Refresh JWT token
GET  /api/v1/auth/me                # Get authenticated user
PUT  /api/v1/auth/profile           # Update profile
POST /api/v1/auth/change-password   # Change password
POST /api/v1/auth/forgot-password   # Request password reset
POST /api/v1/auth/reset-password    # Reset password with token
GET  /api/v1/dashboard/stats        # Get dashboard statistics
```

### Users (8 endpoints)
```bash
GET    /api/v1/users                 # List users (paginated, filterable)
POST   /api/v1/users                 # Create user
GET    /api/v1/users/{id}            # Get user details
PUT    /api/v1/users/{id}            # Update user
DELETE /api/v1/users/{id}            # Soft delete user
PUT    /api/v1/users/{id}/status     # Update user status
POST   /api/v1/users/{id}/assign     # Assign user to entity
GET    /api/v1/users/{id}/assignments # Get user assignments
```

### Roles & Permissions (8 endpoints)
```bash
GET    /api/v1/roles              # List all roles
POST   /api/v1/roles              # Create custom role
GET    /api/v1/roles/{id}         # Get role details
PUT    /api/v1/roles/{id}         # Update custom role
DELETE /api/v1/roles/{id}         # Delete custom role
GET    /api/v1/permissions        # List all permissions (paginated)
GET    /api/v1/permissions/list   # List all permissions (unpaginated)
GET    /api/v1/permissions/{id}   # Get permission details
```

### Organizations (7 endpoints)
```bash
GET    /api/v1/organizations                       # List organizations
POST   /api/v1/organizations                       # Create organization
GET    /api/v1/organizations/{id}                  # Get organization
PUT    /api/v1/organizations/{id}                  # Update organization
DELETE /api/v1/organizations/{id}                  # Delete organization
GET    /api/v1/organizations/{id}/dashboard        # Organization dashboard
GET    /api/v1/organizations/{id}/branches         # List organization branches
GET    /api/v1/organizations/{id}/departments      # List organization departments
```

### Branches (6 endpoints)
```bash
GET    /api/v1/branches                  # List branches
POST   /api/v1/branches                  # Create branch
GET    /api/v1/branches/{id}             # Get branch
PUT    /api/v1/branches/{id}             # Update branch
DELETE /api/v1/branches/{id}             # Delete branch
GET    /api/v1/branches/{id}/departments # List branch departments
```

### Departments (6 endpoints)
```bash
GET    /api/v1/departments              # List departments (hierarchical)
POST   /api/v1/departments              # Create department
GET    /api/v1/departments/{id}         # Get department
PUT    /api/v1/departments/{id}         # Update department
DELETE /api/v1/departments/{id}         # Delete department
GET    /api/v1/departments/{id}/projects # List department projects
```

### Projects (6 endpoints)
```bash
GET    /api/v1/projects              # List projects (filterable)
POST   /api/v1/projects              # Create project
GET    /api/v1/projects/{id}         # Get project
PUT    /api/v1/projects/{id}         # Update project
DELETE /api/v1/projects/{id}         # Delete project
PUT    /api/v1/projects/{id}/status  # Update project status
```

### Teams (7 endpoints)
```bash
GET    /api/v1/teams                     # List teams
POST   /api/v1/teams                     # Create team
GET    /api/v1/teams/{id}                # Get team
PUT    /api/v1/teams/{id}                # Update team
DELETE /api/v1/teams/{id}                # Delete team
POST   /api/v1/teams/{id}/members        # Add team member
DELETE /api/v1/teams/{id}/members/{userId} # Remove team member
```

### AI Agents (11 endpoints)
```bash
GET    /api/v1/agents                  # List agents
POST   /api/v1/agents                  # Create agent
GET    /api/v1/agents/{id}             # Get agent
PUT    /api/v1/agents/{id}             # Update agent
DELETE /api/v1/agents/{id}             # Delete agent
POST   /api/v1/agents/{id}/publish     # Publish agent to marketplace
POST   /api/v1/agents/{id}/clone       # Clone agent
POST   /api/v1/agents/{id}/execute     # Execute agent
GET    /api/v1/agents/{id}/executions  # Get agent executions
GET    /api/v1/analytics/agents        # Agent analytics
```

### Agent Executions (5 endpoints)
```bash
GET    /api/v1/executions                 # List executions
GET    /api/v1/executions/{id}            # Get execution details
GET    /api/v1/executions/{id}/logs       # Get execution logs
POST   /api/v1/executions/{id}/cancel     # Cancel execution
GET    /api/v1/analytics/executions       # Execution analytics
```

### Workflows (8 endpoints)
```bash
GET    /api/v1/workflows                       # List workflows
POST   /api/v1/workflows                       # Create workflow
GET    /api/v1/workflows/{id}                  # Get workflow
PUT    /api/v1/workflows/{id}                  # Update workflow
DELETE /api/v1/workflows/{id}                  # Delete workflow
POST   /api/v1/workflows/{id}/execute          # Execute workflow
GET    /api/v1/workflows/{id}/executions       # Get workflow executions
GET    /api/v1/workflows/executions/{executionId} # Execution details
```

### Job Flows (8 endpoints)
```bash
GET    /api/v1/job-flows               # List job flows
POST   /api/v1/job-flows               # Create job flow
GET    /api/v1/job-flows/{id}          # Get job flow
PUT    /api/v1/job-flows/{id}          # Update job flow
DELETE /api/v1/job-flows/{id}          # Delete job flow
PUT    /api/v1/job-flows/{id}/status   # Update job flow status
POST   /api/v1/job-flows/{id}/trigger  # Trigger job flow
GET    /api/v1/job-flows/{id}/stats    # Get job flow statistics
```

### HITL Approvals (7 endpoints)
```bash
GET    /api/v1/hitl-approvals             # List approvals
GET    /api/v1/hitl-approvals/{id}        # Get approval
GET    /api/v1/hitl-approvals/pending     # List pending approvals
POST   /api/v1/hitl-approvals/{id}/approve   # Approve request
POST   /api/v1/hitl-approvals/{id}/reject    # Reject request
POST   /api/v1/hitl-approvals/{id}/escalate  # Escalate request
```

### API Keys (7 endpoints)
```bash
GET    /api/v1/api-keys                 # List API keys (masked)
POST   /api/v1/api-keys                 # Create API key (returns full key once)
GET    /api/v1/api-keys/{id}            # Get API key (masked)
PUT    /api/v1/api-keys/{id}            # Update API key
DELETE /api/v1/api-keys/{id}            # Revoke API key
POST   /api/v1/api-keys/{id}/regenerate # Regenerate API key
POST   /api/v1/api-keys/{id}/toggle     # Toggle API key status
```

### Webhooks (7 endpoints)
```bash
GET    /api/v1/webhooks               # List webhooks
POST   /api/v1/webhooks               # Create webhook
GET    /api/v1/webhooks/{id}          # Get webhook
PUT    /api/v1/webhooks/{id}          # Update webhook
DELETE /api/v1/webhooks/{id}          # Delete webhook
POST   /api/v1/webhooks/{id}/test     # Send test webhook
POST   /api/v1/webhooks/{id}/toggle   # Toggle webhook status
```

### Connected Apps (10 endpoints)
```bash
GET    /api/v1/connected-apps                    # List connected apps
POST   /api/v1/connected-apps                    # Connect app
GET    /api/v1/connected-apps/{id}               # Get app
PUT    /api/v1/connected-apps/{id}               # Update app settings
DELETE /api/v1/connected-apps/{id}               # Disconnect app
POST   /api/v1/connected-apps/{id}/sync          # Sync app data
POST   /api/v1/connected-apps/{id}/test          # Test connection
POST   /api/v1/connected-apps/{id}/refresh-token # Refresh OAuth token
POST   /api/v1/connected-apps/{id}/revoke        # Revoke app access
GET    /api/v1/connected-apps/{id}/logs          # Get app activity logs
```

### User Activities (4 endpoints)
```bash
GET    /api/v1/activities                 # List all activities (filtered by permission)
GET    /api/v1/activities/{id}            # Get activity details
GET    /api/v1/activities/user/{userId}   # Get user's activities
GET    /api/v1/activities/export          # Export activities to CSV
```

### User Sessions (3 endpoints)
```bash
GET    /api/v1/sessions                # List user sessions
GET    /api/v1/sessions/active         # List active sessions only
POST   /api/v1/sessions/{id}/terminate # Terminate specific session
```

### Marketplace (8 endpoints)
```bash
GET    /api/v1/marketplace                  # List marketplace items (public)
GET    /api/v1/marketplace/{id}             # Get marketplace item (public)
POST   /api/v1/marketplace                  # Create marketplace listing
PUT    /api/v1/marketplace/{id}             # Update listing
DELETE /api/v1/marketplace/{id}             # Delete listing
POST   /api/v1/marketplace/{id}/purchase    # Purchase agent
GET    /api/v1/marketplace/my-listings      # Get my listings
GET    /api/v1/marketplace/my-purchases     # Get my purchases
```

### Engines & Rubrics (12 endpoints)
```bash
GET    /api/v1/engines                          # List engines (public)
GET    /api/v1/engines/{id}                     # Get engine details
GET    /api/v1/engines/{id}/rubrics             # List engine rubrics
POST   /api/v1/engines/{id}/rubrics             # Create rubric
GET    /api/v1/engines/{id}/rubrics/{rubricId}  # Get rubric
PUT    /api/v1/engines/{id}/rubrics/{rubricId}  # Update rubric
DELETE /api/v1/engines/{id}/rubrics/{rubricId}  # Delete rubric
```

### Subscriptions & Billing (6 endpoints)
```bash
GET    /api/v1/subscriptions            # List subscriptions
GET    /api/v1/subscriptions/current    # Get current subscription
POST   /api/v1/subscriptions/subscribe  # Subscribe to plan
POST   /api/v1/subscriptions/cancel     # Cancel subscription
GET    /api/v1/subscriptions/usage      # Get usage statistics
GET    /api/v1/subscriptions/usage/{date} # Get usage by date
```

**Total: 150+ API Endpoints**

## 🔍 Filtering, Pagination & Sorting

### Pagination
All list endpoints support pagination:
```bash
GET /api/v1/users?page=2&per_page=20
```

**Default:** `per_page=15`

### Filtering
Filter by specific fields:
```bash
GET /api/v1/users?status=active&organization_id=uuid&role=admin
GET /api/v1/activities?activity_type=create&status=success&date_from=2025-01-01
```

### Searching
Search by name or email:
```bash
GET /api/v1/users?search=john
GET /api/v1/organizations?search=acme
```

### Sorting
Sort results:
```bash
GET /api/v1/projects?sort=name           # Ascending by name
GET /api/v1/projects?sort=-created_at    # Descending by created_at (- prefix)
```

## 🧪 Testing Examples

### Register New User
```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!"
  }'
```

### Login
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "SecurePass123!"
  }'
```

### List Users (with token)
```bash
curl -X GET "http://localhost:8000/api/v1/users?status=active&per_page=20" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Create Organization
```bash
curl -X POST http://localhost:8000/api/v1/organizations \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "ACME Corporation",
    "type": "enterprise",
    "industry": "Technology",
    "size": "medium"
  }'
```

### Create and Test Webhook
```bash
# Create webhook
curl -X POST http://localhost:8000/api/v1/webhooks \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Production Webhook",
    "url": "https://your-server.com/webhook",
    "events": ["agent.deployed", "job.completed"],
    "is_active": true
  }'

# Test webhook
curl -X POST http://localhost:8000/api/v1/webhooks/{webhook_id}/test \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## 🏗️ Database Schema

### Core Tables
- **tenants** - Multi-tenant isolation
- **users** - User accounts
- **roles** - System and custom roles
- **permissions** - Granular permissions
- **role_has_permissions** - Role-permission mapping
- **model_has_roles** - User-role mapping

### Organization Structure
- **organizations** - Top-level organizations
- **branches** - Organization branches
- **departments** - Hierarchical departments
- **teams** - Teams with members
- **projects** - Project management
- **user_assignments** - User-entity assignments

### Integration & Activity
- **api_keys** - API key management with hashing
- **webhooks** - Webhook configurations
- **connected_apps** - OAuth app connections
- **connected_app_logs** - App activity logs
- **user_activities** - Complete audit trail
- **user_sessions** - Session management

### AI & Automation
- **agents** - AI agent definitions
- **agent_executions** - Execution history
- **workflows** - Workflow definitions
- **workflow_executions** - Workflow runs
- **job_flows** - Job orchestration
- **hitl_approvals** - Human-in-the-loop approvals
- **marketplace_listings** - Marketplace items

## 🔒 Security Features

- ✅ **Password Hashing** - Secure bcrypt hashing
- ✅ **JWT Authentication** - Token-based auth with Sanctum
- ✅ **CORS Protection** - Configured for frontend origins
- ✅ **SQL Injection Prevention** - Eloquent ORM with parameterized queries
- ✅ **XSS Protection** - Input sanitization
- ✅ **CSRF Protection** - Laravel CSRF middleware
- ✅ **Rate Limiting** - Throttling on auth endpoints
- ✅ **API Key Hashing** - SHA-256 hashing, prefix-only display
- ✅ **Soft Deletes** - Recoverable data deletion
- ✅ **Multi-Tenancy** - Complete tenant isolation
- ✅ **Role-Based Access Control** - Granular permissions
- ✅ **Activity Logging** - Complete audit trail with IP tracking
- ✅ **Session Management** - Track and terminate sessions

## 🌐 CORS Configuration

Frontend allowed origins configured in `config/cors.php`:

```php
'allowed_origins' => [
    env('FRONTEND_URL', 'http://localhost:5173'),
],
```

Update `FRONTEND_URL` in `.env` to match your frontend application.

## 📦 Key Dependencies

```json
{
  "laravel/framework": "^12.0",
  "laravel/sanctum": "^4.0",
  "spatie/laravel-permission": "^6.0",
  "stancl/tenancy": "^3.0",
  "fruitcake/laravel-cors": "^3.0"
}
```

### Development Dependencies
```json
{
  "phpunit/phpunit": "^11.0",
  "laravel/pint": "^1.0",
  "fakerphp/faker": "^1.23"
}
```

## 🐛 Troubleshooting

### Database Connection Failed
Ensure PostgreSQL is running and credentials in `.env` are correct:
```bash
# Check PostgreSQL status
sudo systemctl status postgresql

# Test connection
psql -U postgres -h 127.0.0.1
```

### CORS Errors
Verify `FRONTEND_URL` in `.env` matches your frontend URL exactly:
```env
FRONTEND_URL=http://localhost:5173
```

### Migration Fails
Ensure foreign key order is correct. Reset and re-run:
```bash
php artisan migrate:fresh
```

### Permission Denied Errors
Check file permissions:
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Tenant Not Found
Ensure you're accessing the application through the correct domain configured in tenancy.

## 📊 Monitoring & Observability

### Health Checks
```bash
# Basic health check
curl http://localhost:8000/api/health

# Detailed health check (includes database)
curl http://localhost:8000/api/health/detailed

# Kubernetes readiness probe
curl http://localhost:8000/api/health/ready

# Kubernetes liveness probe
curl http://localhost:8000/api/health/alive
```

### Prometheus Metrics
```bash
curl http://localhost:8000/api/metrics
```

## 🚀 Deployment

### Production Checklist
- [ ] Set `APP_ENV=production` in `.env`
- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Generate new `APP_KEY` and `JWT_SECRET`
- [ ] Configure production database credentials
- [ ] Set correct `APP_URL` and `FRONTEND_URL`
- [ ] Run `composer install --optimize-autoloader --no-dev`
- [ ] Run `php artisan config:cache`
- [ ] Run `php artisan route:cache`
- [ ] Run `php artisan view:cache`
- [ ] Set up SSL/TLS certificates
- [ ] Configure queue workers
- [ ] Set up log rotation
- [ ] Configure backups

### Environment Variables
```env
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
# ... production database credentials

QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

## 📄 License

Proprietary - OBSOLIO Platform

## 👥 Support

For issues, questions, or feature requests:
- **Documentation**: [API Docs](http://localhost:8000/api/documentation)
- **Email**: support@obsolio.com
- **Issues**: GitHub Issues

---

**Built with ❤️ using Laravel & PostgreSQL**
