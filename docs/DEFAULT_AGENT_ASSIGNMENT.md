# Default Agent Assignment

## Overview

New tenants automatically receive default agents when they verify their email address. This feature ensures that users have immediate access to AI agents upon account activation, improving the onboarding experience.

---

## How It Works

### Registration Flow

```
1. User Registers
   ↓
2. Email Verification
   ↓
3. CreateTrialSubscription Listener Triggered
   ├─ Creates Subscription
   ├─ Generates Invoice
   ├─ Assigns Default Agents ⭐ NEW
   └─ Links Organization
```

### Assignment Logic

1. **Check Plan Limits**
   - Reads `max_agents` from subscription plan
   - Skips assignment if `max_agents` is 0 or less

2. **Find Suitable Agents**
   - **Priority 1:** Featured + Free agents (`is_featured=true`, `price_model='free'`)
   - **Priority 2:** Featured + Basic tier agents (`is_featured=true`, `tier_id=1`)
   - **Fallback:** Any free or basic tier agents

3. **Create Assignments**
   - Creates `TenantAgent` records
   - Sets status to `active`
   - Includes metadata for tracking
   - Respects plan's `max_agents` limit

4. **Idempotency**
   - Checks for existing assignments
   - Skips if agent already assigned
   - Safe to run multiple times

---

## Configuration

### Subscription Plan Settings

Control agent assignment via subscription plan configuration:

```php
SubscriptionPlan::create([
    'name' => 'Free Plan',
    'tier' => 'free',
    'max_agents' => 3,  // ⭐ Allows 3 agents
    // ...
]);
```

| Plan Tier | Typical max_agents |
|-----------|-------------------|
| Free      | 2-3 agents        |
| Starter   | 5 agents          |
| Pro       | 10 agents         |
| Team      | 25 agents         |
| Enterprise| Unlimited (999)   |

### Agent Configuration

Mark agents for auto-assignment:

```php
Agent::create([
    'name' => 'Email Assistant',
    'price_model' => 'free',
    'is_active' => true,
    'is_featured' => true,  // ⭐ Will be auto-assigned
    'tier_id' => 1,         // Basic tier
    // ...
]);
```

---

## Database Schema

### TenantAgent Record

```sql
CREATE TABLE tenant_agents (
    id UUID PRIMARY KEY,
    tenant_id VARCHAR(255) NOT NULL,
    agent_id UUID NOT NULL,
    status VARCHAR(255) DEFAULT 'active',
    purchased_at TIMESTAMP,
    activated_at TIMESTAMP,
    configuration JSONB DEFAULT '{}',
    metadata JSONB DEFAULT '{}',
    -- ...
);
```

### Auto-Assignment Metadata

```json
{
  "configuration": {
    "assigned_via": "auto_assignment",
    "plan_name": "Free Plan",
    "assigned_at": "2026-01-14T10:30:00.000Z"
  },
  "metadata": {
    "is_default_agent": true,
    "agent_name": "Email Assistant",
    "agent_tier": 1
  }
}
```

---

## Examples

### Example 1: Free Plan (2 Agents)

**Plan Configuration:**
```php
max_agents = 2
```

**Available Agents:**
- ✅ Email Assistant (featured, free)
- ✅ Task Manager (featured, free)
- ⏭️ Calendar Bot (featured, free) - skipped, limit reached

**Result:** 2 agents assigned

---

### Example 2: Pro Plan (10 Agents)

**Plan Configuration:**
```php
max_agents = 10
```

**Available Agents:** 5 featured free agents

**Result:** All 5 agents assigned (under limit)

---

### Example 3: Viewer Plan (0 Agents)

**Plan Configuration:**
```php
max_agents = 0
```

**Result:** No agents assigned (skipped)

---

## Testing

### Run Tests

```bash
# Test agent assignment
php artisan test --filter AgentAssignmentTest

# Expected output:
# ✓ free plan assigns default agents
# ✓ plan with zero agents skips assignment
# ✓ featured agents are prioritized
# ✓ agent assignment is idempotent
# ✓ assigned agents have correct metadata
```

### Manual Testing

```bash
# 1. Create test agents
php artisan tinker
>>> Agent::factory()->count(3)->create(['price_model' => 'free', 'is_featured' => true]);

# 2. Register a new user via API
POST /api/v1/auth/register

# 3. Verify email
# Click verification link in email

# 4. Check assignments
>>> $tenant = Tenant::latest()->first();
>>> $tenant->agents()->count();
=> 2

>>> $tenant->agents()->pluck('name');
=> ["Email Assistant", "Task Manager"]
```

---

## Logging

### Success Log

```
[INFO] Default agents assigned to tenant
{
  "tenant_id": "acme-corp",
  "plan_name": "Free Plan",
  "max_agents_allowed": 2,
  "agents_assigned": 2,
  "agent_names": ["Email Assistant", "Task Manager"]
}
```

### No Agents Available

```
[WARNING] No suitable agents found for assignment
{
  "tenant_id": "acme-corp",
  "plan_name": "Free Plan",
  "max_agents": 2
}
```

### Plan Doesn't Allow Agents

```
[INFO] Plan does not allow agents, skipping assignment
{
  "tenant_id": "acme-corp",
  "plan_name": "Viewer Plan",
  "max_agents": 0
}
```

---

## Troubleshooting

### No Agents Assigned

**Symptoms:** New tenant has 0 agents after verification

**Possible Causes:**

1. **No free agents in database**
   ```bash
   # Check for free agents
   Agent::where('is_active', true)
       ->where('is_featured', true)
       ->where('price_model', 'free')
       ->count();
   ```

2. **Plan max_agents is 0**
   ```bash
   # Check plan settings
   $plan = SubscriptionPlan::where('tier', 'free')->first();
   $plan->max_agents; // Should be > 0
   ```

3. **Agents not marked as featured**
   ```bash
   # Mark agents as featured
   Agent::where('price_model', 'free')
       ->update(['is_featured' => true]);
   ```

### Too Many Agents Assigned

**Symptoms:** Tenant has more agents than allowed

**Solution:** This shouldn't happen due to `limit()` in query, but verify:

```bash
# Check tenant agent count vs plan limit
$tenant = Tenant::find('acme-corp');
$subscription = $tenant->activeSubscription()->first();
$plan = $subscription->plan;

echo "Allowed: {$plan->max_agents}\n";
echo "Assigned: " . $tenant->agents()->count() . "\n";
```

### Duplicate Assignments

**Symptoms:** Same agent assigned multiple times

**Solution:** Idempotency check prevents this, but verify:

```bash
# Check for duplicates
TenantAgent::select('tenant_id', 'agent_id')
    ->groupBy('tenant_id', 'agent_id')
    ->havingRaw('COUNT(*) > 1')
    ->get();
```

---

## API Endpoints

### Get Tenant's Agents

```http
GET /api/v1/tenant/agents
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Email Assistant",
      "status": "active",
      "purchased_at": "2026-01-14T10:30:00Z",
      "metadata": {
        "is_default_agent": true
      }
    }
  ]
}
```

---

## Future Enhancements

### Planned Features

1. **Custom Agent Packs**
   - Allow admins to define agent packs per plan
   - Example: "Marketing Pack", "Sales Pack", "Support Pack"

2. **Smart Assignment**
   - AI-based agent recommendations
   - Based on industry, company size, use case

3. **Onboarding Wizard**
   - Let users choose their own starter agents
   - Show agent capabilities and demos

4. **Usage-Based Recommendations**
   - Suggest additional agents based on usage patterns
   - "Users like you also use..."

---

## Related Documentation

- [Subscription Plans](./SUBSCRIPTION_PLANS.md)
- [Agent Management](./AGENT_MANAGEMENT.md)
- [Free Trial Flow](./FREE_TRIAL_FLOW.md)
- [Invoice Generation](./INVOICE_GENERATION.md)

---

## Support

For questions or issues:
- Check logs: `storage/logs/laravel.log`
- Run tests: `php artisan test --filter AgentAssignmentTest`
- Contact: dev-team@obsolio.com
