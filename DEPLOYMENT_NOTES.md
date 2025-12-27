# Deployment Notes for Agent Async Execution Feature

## ⚠️ Pre-Deployment Issues Found

### Swagger Generation Error (Pre-Existing)

When running `php artisan l5-swagger:generate` on production, the following error occurs:

```
ErrorException

@OA\Schema() is missing key-field: "schema" in \App\Http\Controllers\Api\V1\AuthController->verifyEmail() in /home/obsolio/htdocs/api.obsolio.com/app/Http/Controllers/Api/V1/AuthController.php on line 1085
```

**Status:** This is a **pre-existing issue** in production code, NOT related to the new Agent Execution feature.

**Impact:** Prevents Swagger documentation from being generated until fixed.

**Location:** `app/Http/Controllers/Api/V1/AuthController.php:1085`

**Required Fix:** The `@OA\Schema()` annotation in the `verifyEmail()` method is malformed. It needs the `schema` property.

**Example Fix:**
```php
// Before (incorrect):
@OA\Schema()

// After (correct):
@OA\Schema(schema="VerifyEmailResponse", ...)
// or remove if not needed
```

**Recommendation:** Fix this issue before deploying the new feature to ensure Swagger documentation can be generated.

---

## ✅ Agent Execution Feature - Deployment Checklist

### Prerequisites
- [ ] Fix AuthController Swagger error (see above)
- [ ] Ensure composer dependencies are up to date
- [ ] Backup database before running migrations
- [ ] Review MIGRATION_NOTES.md for breaking changes

### Deployment Steps

#### 1. Pull Code
```bash
cd /home/obsolio/htdocs/api.obsolio.com
git checkout main
git pull origin main
git merge feature/agent-async-execution
# or
git checkout feature/agent-async-execution
git pull origin feature/agent-async-execution
```

#### 2. Install Dependencies (if needed)
```bash
composer install --no-dev --optimize-autoloader
```

#### 3. Run Migrations (IMPORTANT: Follow Order)

**Migration Order is Critical:**

```bash
# Check which migrations are pending
php artisan migrate:status

# Run migrations in this order:
# 1. 2025_12_27_000002_create_agent_categories_table.php
# 2. 2025_12_27_000003_create_agent_category_map_table.php
# 3. 2025_12_27_120000_modify_agents_table_for_async_execution.php
# 4. 2025_12_27_125000_migrate_agent_categories_data.php (data migration)
# 5. 2025_12_27_130000_finalize_agents_table_changes.php
# 6. 2025_12_27_140000_create_agent_endpoints_table.php
# 7. 2025_12_27_150000_create_agent_runs_table.php

# Run all migrations
php artisan migrate --force
```

**Why This Order Matters:**
- Categories tables must exist first (steps 1-2)
- Agents table must have runtime_type column before data migration (step 3)
- Data migration populates runtime_type values (step 4)
- runtime_type constraint enforced after population (step 5)
- Finally create endpoint and run tracking tables (steps 6-7)

#### 4. Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

#### 5. Generate Swagger Documentation
```bash
# After fixing AuthController Swagger error:
php artisan l5-swagger:generate
```

#### 6. Verify Deployment
```bash
# Check routes are registered
php artisan route:list | grep agent

# Should show:
# POST   /api/v1/agents/{id}/run
# GET    /api/v1/agent-runs/{run_id}
# POST   /api/v1/webhooks/agents/callback
```

#### 7. Test API Endpoints

**A. Test Execute Agent (requires JWT):**
```bash
curl -X POST https://api.obsolio.com/api/v1/agents/{agent-uuid}/run \
  -H "Authorization: Bearer {jwt-token}" \
  -H "Content-Type: application/json" \
  -d '{"input": {"test": "data"}}'
```

**B. Test Get Run Status (requires JWT):**
```bash
curl -X GET https://api.obsolio.com/api/v1/agent-runs/{run-id} \
  -H "Authorization: Bearer {jwt-token}"
```

**C. Test Webhook Callback (no JWT):**
```bash
curl -X POST https://api.obsolio.com/api/v1/webhooks/agents/callback \
  -H "Content-Type: application/json" \
  -d '{
    "run_id": "{run-id}",
    "status": "completed",
    "output": {"result": "test"},
    "secret": "{callback-secret}"
  }'
```

#### 8. Verify Swagger Documentation
```bash
# Visit in browser:
https://api.obsolio.com/api/documentation

# Check for:
# - "Agent Execution" tag in sidebar
# - 3 endpoints under Agent Execution
# - All examples and schemas load correctly
```

---

## 🔧 Rollback Plan (If Needed)

### If Deployment Fails:

#### 1. Rollback Migrations
```bash
php artisan migrate:rollback --step=6
```

This will rollback the 6 new migrations in reverse order.

#### 2. Rollback Code
```bash
git checkout main
git reset --hard origin/main
```

#### 3. Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

---

## 📊 Database Changes Summary

### Tables Modified
- **agents** - Removed 5 columns, added 2 columns

### Tables Created
- **agent_categories** - Hierarchical categories
- **agent_category_map** - Many-to-many pivot
- **agent_endpoints** - Trigger and callback URLs
- **agent_runs** - Execution tracking

### Data Migration
- Existing agent categories preserved in new structure
- All existing agents get `runtime_type = 'custom'`
- All existing agents get `execution_timeout_ms = 30000`

---

## 🚨 Known Issues

### 1. AuthController Swagger Error
**File:** `app/Http/Controllers/Api/V1/AuthController.php:1085`
**Error:** `@OA\Schema() is missing key-field: "schema"`
**Status:** Pre-existing, not related to this feature
**Impact:** Prevents Swagger generation
**Priority:** HIGH - Must fix before generating docs

### 2. Deprecated Parameter Warnings
```
PHP Deprecated: Implicitly marking parameter as nullable is deprecated
```
**Files:**
- `app/Http/Controllers/Api/V1/TenantController.php:357`
- `app/Http/Controllers/Api/V1/TenantController.php:423`
- `app/Services/QueryOptimizer.php:302`
- `app/Services/QueryOptimizer.php:408`

**Status:** Pre-existing warnings
**Impact:** No functional impact, just warnings
**Priority:** LOW - Can be fixed later

---

## 📝 Post-Deployment Tasks

### 1. Update Frontend
- [ ] Update API client with new endpoints
- [ ] Implement polling for agent run status
- [ ] Handle all response codes (200, 202, 400, 404, 422, 500)
- [ ] Add UI for agent execution

### 2. Update Controllers (Breaking Changes)
- [ ] Fix `AdminController` - remove references to removed columns
- [ ] Fix `MarketplaceController` - update category queries
- [ ] See `MIGRATION_NOTES.md` for detailed list

### 3. Documentation
- [ ] Share `SWAGGER_DOCUMENTATION_GUIDE.md` with frontend team
- [ ] Update Postman/Insomnia collections
- [ ] Create example agent integration guide
- [ ] Document webhook security setup

### 4. Monitoring
- [ ] Add logging for agent executions
- [ ] Monitor webhook callback success rate
- [ ] Track average execution time
- [ ] Set up alerts for failed runs

---

## 🆘 Support

### If You Encounter Issues:

1. **Check Laravel Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Check Migration Status:**
   ```bash
   php artisan migrate:status
   ```

3. **Verify Database Schema:**
   ```bash
   php artisan db:show
   php artisan db:table agents
   ```

4. **Test Routes:**
   ```bash
   php artisan route:list | grep -i agent
   ```

5. **Clear All Caches:**
   ```bash
   php artisan optimize:clear
   ```

### Contact

For deployment issues or questions:
- Check `MIGRATION_NOTES.md` for implementation details
- Check `SWAGGER_DOCUMENTATION_GUIDE.md` for API documentation
- Review commit history on `feature/agent-async-execution` branch

---

## ✅ Success Criteria

Deployment is successful when:

- ✅ All migrations run without errors
- ✅ Swagger documentation generates successfully
- ✅ All 3 new endpoints appear in Swagger UI
- ✅ Test agent execution returns 202 Accepted
- ✅ Test webhook callback returns 200 OK
- ✅ No PHP errors in Laravel logs
- ✅ Existing functionality still works (no regression)

---

## 📅 Deployment History

| Date | Version | Status | Notes |
|------|---------|--------|-------|
| 2025-12-27 | v1.0 | Pending | Initial deployment of agent async execution |

---

**Document Version:** 1.0
**Last Updated:** 2025-12-27
**Author:** Development Team
