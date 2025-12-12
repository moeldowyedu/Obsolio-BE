# Setup Guide

## PHP Requirements

This project requires PHP 8.2.26 or higher with the following extensions:

### Required PHP Extensions

#### 1. Sodium Extension (ext-sodium)
The Sodium extension is required for JWT authentication.

**To enable on Windows (WAMP/XAMPP):**

1. Open your `php.ini` file (located at `C:\wamp64\bin\php\php8.2.26\php.ini`)
2. Find the line `;extension=sodium` (it may have a semicolon at the start)
3. Remove the semicolon to uncomment it: `extension=sodium`
4. Save the file
5. Restart Apache/your web server

**To verify if sodium is enabled:**
```bash
php -m | grep sodium
```

If sodium appears in the list, it's enabled.

## Installation Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd OBSOLIO-BE
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   ```

   Update the following in your `.env`:
   - `DB_PORT=5050` (or your PostgreSQL port)
   - `DB_PASSWORD=your_password`

4. **Generate application key**
   ```bash
   php artisan key:generate
   ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

6. **Start the development server**
   ```bash
   php artisan serve
   ```

## Redis Configuration (Optional)

By default, this application uses:
- **Sessions**: Database
- **Cache**: File
- **Queue**: Sync

If you want to use Redis for better performance:

1. Install Redis on your system
2. Update your `.env`:
   ```env
   SESSION_DRIVER=redis
   QUEUE_CONNECTION=redis
   CACHE_STORE=redis
   ```

## Troubleshooting

### Composer Platform Check Error

If you see: "Your Composer dependencies require a PHP version >= 8.3.0"

This is already fixed in the composer.json with `"platform-check": false`. Just run:
```bash
composer install
```

### Missing Sodium Extension

If you see errors about `ext-sodium` missing, follow the instructions above to enable it in your `php.ini`.

### Redis Connection Error

If you see: "No connection could be made because the target machine actively refused it"

This means Redis is not running. Either:
1. Install and start Redis, or
2. Use the default file/database configuration (already set in `.env.example`)

### Cached Platform Check Error

If you see the platform check error even after pulling the latest changes:
```
Composer detected issues in your platform: Your Composer dependencies require a PHP version ">= 8.3.0"
```

This is caused by a cached platform check file. Fix it by running:
```bash
rm -rf vendor/composer/platform_check.php
composer install
```

Or on Windows:
```bash
del vendor\composer\platform_check.php
composer install
```
