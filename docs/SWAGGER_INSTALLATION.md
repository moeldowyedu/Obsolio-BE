# Swagger Installation & Configuration Guide

## Issue: AWS SDK Security Advisory

The installation is blocked due to a security advisory in the AWS SDK package. Here are the solutions:

## Solution 1: Update AWS SDK (Recommended)

Update the AWS SDK to a secure version:

```bash
# Update AWS SDK to latest secure version
composer update aws/aws-sdk-php

# Then install l5-swagger
composer require darkaonline/l5-swagger
```

## Solution 2: Ignore Security Advisory (Temporary)

If you need to proceed immediately, you can ignore the advisory:

```bash
# Add to composer.json under "config"
"audit": {
    "ignore": ["PKSA-dxyf-6n16-t87m"]
}

# Then run
composer require darkaonline/l5-swagger
```

## Solution 3: Manual Installation

Alternatively, manually add to `composer.json`:

```json
{
    "require": {
        "darkaonline/l5-swagger": "^8.0"
    }
}
```

Then run:
```bash
composer update darkaonline/l5-swagger --with-all-dependencies
```

## After Installation

Once l5-swagger is installed:

### 1. Publish Configuration
```bash
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

### 2. Configure l5-swagger

Edit `config/l5-swagger.php`:

```php
'defaults' => [
    'routes' => [
        'api' => 'api/documentation',
    ],
    'paths' => [
        'docs' => storage_path('api-docs'),
        'docs_json' => 'api-docs.json',
        'annotations' => [
            base_path('app/Http/Controllers/Api'),
        ],
    ],
],
```

### 3. Add OpenAPI Info

Create `app/Http/Controllers/Api/Controller.php` or update existing:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;

/**
 * @OA\Info(
 *     title="OBSOLIO Pricing API",
 *     version="1.0.0",
 *     description="Complete pricing and billing API for OBSOLIO platform",
 *     @OA\Contact(
 *         email="support@obsolio.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local Development Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class Controller extends BaseController
{
    //
}
```

### 4. Generate Documentation

```bash
php artisan l5-swagger:generate
```

### 5. Access Swagger UI

Visit: `http://localhost:8000/api/documentation`

## Current Status

✅ **All 27 endpoints have Swagger annotations**
- SubscriptionController: 7 endpoints
- AgentMarketplaceController: 8 endpoints
- BillingController: 7 endpoints
- UsageController: 5 endpoints

Once l5-swagger is installed and configured, the documentation will be automatically generated from these annotations.

## Alternative: Use Existing Swagger/OpenAPI Tools

If installation continues to fail, you can:

1. **Use Postman** - Import the API documentation manually
2. **Use Stoplight** - Generate docs from annotations
3. **Use Redoc** - Alternative to Swagger UI
4. **Manual Documentation** - Use the comprehensive API_DOCUMENTATION.md already created

## Troubleshooting

If issues persist:

```bash
# Clear composer cache
composer clear-cache

# Update all dependencies
composer update

# Try installing with specific version
composer require "darkaonline/l5-swagger:^8.5"
```
