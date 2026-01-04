# SystemAdminSeeder - Final Fix

## Issue
The `roles` table has a check constraint that only allows:
- `guard_name = 'console'` with `tenant_id IS NULL`
- `guard_name = 'tenant'` with `tenant_id IS NOT NULL`

But the User model uses guards `web` and `api`, causing a guard mismatch when assigning roles.

## Solution
Created migration `2026_01_04_150000_relax_roles_guard_constraint.php` to update the constraint:

```sql
CHECK (
    (guard_name = 'console' AND tenant_id IS NULL) OR
    (guard_name = 'tenant' AND tenant_id IS NOT NULL) OR
    (guard_name = 'web' AND tenant_id IS NULL) OR      -- Added
    (guard_name = 'api' AND tenant_id IS NULL)          -- Added
)
```

## Steps to Fix

1. **Run the new migration:**
   ```bash
   php artisan migrate --force
   ```

2. **Run the seeder:**
   ```bash
   php artisan db:seed --class=SystemAdminSeeder --force
   ```

3. **Or run all seeders:**
   ```bash
   php artisan db:seed --force
   ```

## What Changed
- SystemAdminSeeder now creates role with `guard_name = 'web'` (compatible with User model)
- Role has `tenant_id = NULL` (system-level, not tenant-specific)
- Constraint now allows `web` and `api` guards for system-level roles

✅ This fixes the guard mismatch while maintaining proper tenant isolation!
