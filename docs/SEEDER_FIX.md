# SystemAdminSeeder Fix

## Issue
The `SystemAdminSeeder` was failing with a check constraint violation because it was trying to create a role with `guard_name = 'web'` and no `tenant_id`.

## Root Cause
The migration `2025_12_28_120916_add_tenant_id_to_roles_table.php` added a check constraint:
```sql
CHECK (
    (guard_name = 'console' AND tenant_id IS NULL) OR
    (guard_name = 'tenant' AND tenant_id IS NOT NULL)
)
```

This means:
- `guard_name = 'console'` → System-level roles (no tenant_id)
- `guard_name = 'tenant'` → Tenant-scoped roles (requires tenant_id)
- `guard_name = 'web'` → **NOT ALLOWED** by the constraint

## Solution
Changed the SystemAdminSeeder to use `guard_name = 'console'` for system admin roles:

```php
Role::create(['name' => 'Super Admin', 'guard_name' => 'console']);
```

## Fixed
✅ SystemAdminSeeder now creates roles with correct guard_name
✅ Seeder will run successfully without constraint violations
