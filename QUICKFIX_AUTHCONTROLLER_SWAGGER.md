# Quick Fix: AuthController Swagger Error

## Problem

Swagger documentation generation fails with:
```
@OA\Schema() is missing key-field: "schema" in \App\Http\Controllers\Api\V1\AuthController->verifyEmail()
in /home/obsolio/htdocs/api.obsolio.com/app/Http/Controllers/Api/V1/AuthController.php on line 1085
```

## Solution

The `@OA\Schema()` annotation on line 1085 is malformed and needs to be fixed or removed.

### Option 1: Remove the Malformed Annotation (Quickest)

If the schema isn't being used, simply remove it:

**Connect to production server:**
```bash
ssh root@sayednaelhabeb
cd /home/obsolio/htdocs/api.obsolio.com
```

**Edit the file:**
```bash
nano app/Http/Controllers/Api/V1/AuthController.php
```

**Navigate to line 1085** (use Ctrl+_ then type 1085 and press Enter)

**Look for something like:**
```php
* @OA\Schema()
```

**And remove the entire line** or comment it out:
```php
// * @OA\Schema()
```

**Save and exit:** Ctrl+X, then Y, then Enter

### Option 2: Fix the Annotation Properly

If the schema is needed, fix it properly:

**The annotation should look like:**
```php
* @OA\Schema(
*     schema="VerifyEmailResponse",
*     type="object",
*     @OA\Property(property="success", type="boolean", example=true),
*     @OA\Property(property="message", type="string", example="Email verified successfully")
* )
```

**Not:**
```php
* @OA\Schema()
```

### Option 3: Use sed Command (Automated)

**Backup first:**
```bash
cd /home/obsolio/htdocs/api.obsolio.com
cp app/Http/Controllers/Api/V1/AuthController.php app/Http/Controllers/Api/V1/AuthController.php.backup
```

**Remove the malformed annotation:**
```bash
sed -i '1085d' app/Http/Controllers/Api/V1/AuthController.php
```

**Or comment it out:**
```bash
sed -i '1085s/^/\/\/ /' app/Http/Controllers/Api/V1/AuthController.php
```

## After Fixing

**Test Swagger generation:**
```bash
cd /home/obsolio/htdocs/api.obsolio.com
php artisan l5-swagger:generate
```

**Expected output:**
```
Regenerating docs default
[Deprecation warnings are OK - they're just warnings]
[No error about @OA\Schema()]
```

**Verify the docs:**
```bash
# Check if the JSON was generated
ls -lh storage/api-docs/api-docs.json

# Should show a file with recent timestamp
```

**Visit in browser:**
```
https://api.obsolio.com/api/documentation
```

**You should now see:**
- All existing endpoints (Authentication, Dashboard, etc.)
- **NEW:** "Agent Execution" tag with 3 endpoints:
  - POST /v1/agents/{id}/run
  - GET /v1/agent-runs/{run_id}
  - POST /v1/webhooks/agents/callback

## If You Need to See Line 1085 Context

**View lines around 1085:**
```bash
sed -n '1080,1090p' app/Http/Controllers/Api/V1/AuthController.php
```

This will show you the context around the error to decide whether to remove or fix it.

## Rollback if Needed

**If something goes wrong:**
```bash
cd /home/obsolio/htdocs/api.obsolio.com
cp app/Http/Controllers/Api/V1/AuthController.php.backup app/Http/Controllers/Api/V1/AuthController.php
```

## Complete Fix Script

Here's a complete script that:
1. Backs up the file
2. Comments out the problematic line
3. Regenerates Swagger docs
4. Shows the result

```bash
#!/bin/bash
cd /home/obsolio/htdocs/api.obsolio.com

# Backup
echo "Creating backup..."
cp app/Http/Controllers/Api/V1/AuthController.php app/Http/Controllers/Api/V1/AuthController.php.backup

# Show the problematic line
echo "Problematic line 1085:"
sed -n '1085p' app/Http/Controllers/Api/V1/AuthController.php

# Comment it out
echo "Commenting out line 1085..."
sed -i '1085s/^/\/\/ /' app/Http/Controllers/Api/V1/AuthController.php

# Verify the change
echo "Line 1085 after fix:"
sed -n '1085p' app/Http/Controllers/Api/V1/AuthController.php

# Regenerate Swagger
echo "Regenerating Swagger documentation..."
php artisan l5-swagger:generate

# Check result
echo "Checking generated file..."
ls -lh storage/api-docs/api-docs.json

echo "Done! Visit https://api.obsolio.com/api/documentation to see the new Agent Execution endpoints"
```

**Save this as `fix_swagger.sh` and run:**
```bash
chmod +x fix_swagger.sh
./fix_swagger.sh
```

## Alternative: Skip the Problematic File

If you want to temporarily exclude AuthController from Swagger generation:

**Edit `config/l5-swagger.php`:**
```bash
nano config/l5-swagger.php
```

**Find the `annotations` section and modify it:**
```php
'annotations' => [
    base_path('app/Http/Controllers/Api/V1'),
    // Exclude AuthController temporarily
    '!' . base_path('app/Http/Controllers/Api/V1/AuthController.php'),
],
```

**Then regenerate:**
```bash
php artisan l5-swagger:generate
```

This will generate docs without AuthController annotations, but include all other endpoints including the new Agent Execution ones.

## Summary

**Recommended Quick Fix:**
```bash
cd /home/obsolio/htdocs/api.obsolio.com
cp app/Http/Controllers/Api/V1/AuthController.php app/Http/Controllers/Api/V1/AuthController.php.backup
sed -i '1085s/^/\/\/ /' app/Http/Controllers/Api/V1/AuthController.php
php artisan l5-swagger:generate
```

This will:
- ✅ Backup the original file
- ✅ Comment out the problematic line
- ✅ Generate Swagger docs successfully
- ✅ Include the new Agent Execution endpoints

**Result:** Your API documentation at `https://api.obsolio.com/api/documentation` will show the new "Agent Execution" tag with all three endpoints fully documented!
