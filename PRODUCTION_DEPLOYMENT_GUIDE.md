# Production Deployment Guide - Billing Flow Implementation

## Overview
This deployment implements a complete subscription billing flow with plan selection during registration, trial period handling, and Paymob payment integration.

## Critical Changes

### 1. Plan Selection During Registration
- Users must now select a plan and billing cycle during registration
- Frontend MUST send `plan_id` and `billing_cycle` parameters

### 2. Trial Invoice Logic
- All trial invoices now show $0.00 regardless of plan type
- No breaking changes to existing subscriptions

### 3. Post-Trial Payment Flow
- New scheduled job runs daily to handle trial expirations
- Automatically generates invoices and payment links

### 4. Paymob Integration
- Monthly billing now automatically generates Paymob payment links
- Payment URLs stored in invoice metadata

## Pre-Deployment Checklist

### 1. Verify Paymob Configuration
```bash
# Check if Paymob credentials are configured
sudo -u obsolio php artisan tinker
>>> config('services.paymob.api_key')
>>> config('services.paymob.integration_id')
>>> config('services.paymob.iframe_id')
>>> exit
```

If not configured, add to `.env`:
```env
PAYMOB_API_KEY=your_api_key
PAYMOB_INTEGRATION_ID=your_integration_id
PAYMOB_IFRAME_ID=your_iframe_id
PAYMOB_HMAC_SECRET=your_hmac_secret
PAYMOB_CURRENCY=EGP
```

### 2. Database Backup
```bash
# Backup database before deployment
cd /home/obsolio/htdocs/api.obsolio.com
sudo -u postgres pg_dump obsolio_production > backup_$(date +%Y%m%d_%H%M%S).sql
```

### 3. Verify Subscription Plans Exist
```bash
sudo -u obsolio php artisan tinker
>>> \App\Models\SubscriptionPlan::count()
>>> \App\Models\SubscriptionPlan::where('is_active', true)->get(['id', 'name', 'price_monthly', 'price_annual', 'trial_days'])
>>> exit
```

**IMPORTANT**: If no plans exist, run the pricing seeder:
```bash
sudo -u obsolio php artisan db:seed --class=PricingSeeder
```

## Deployment Steps

### Step 1: Pull Latest Changes
```bash
cd /home/obsolio/htdocs/api.obsolio.com
sudo -u obsolio git fetch origin
sudo -u obsolio git checkout claude/review-backend-repo-7eaaY
sudo -u obsolio git pull origin claude/review-backend-repo-7eaaY
```

### Step 2: Install Dependencies (if needed)
```bash
sudo -u obsolio composer install --no-dev --optimize-autoloader
```

### Step 3: Clear All Caches
```bash
sudo -u obsolio php artisan cache:clear
sudo -u obsolio php artisan config:clear
sudo -u obsolio php artisan route:clear
sudo -u obsolio php artisan view:clear
sudo -u obsolio php artisan optimize:clear
```

### Step 4: Optimize for Production
```bash
sudo -u obsolio php artisan config:cache
sudo -u obsolio php artisan route:cache
sudo -u obsolio php artisan view:cache
sudo -u obsolio php artisan event:cache
```

### Step 5: Verify Scheduler is Running
```bash
# Check if Laravel scheduler cron job exists
sudo crontab -u obsolio -l | grep schedule:run

# If not present, add it:
sudo crontab -u obsolio -e
# Add this line:
# * * * * * cd /home/obsolio/htdocs/api.obsolio.com && php artisan schedule:run >> /dev/null 2>&1
```

### Step 6: Test Scheduler
```bash
# View scheduled tasks
sudo -u obsolio php artisan schedule:list

# Should show:
# - users:clean-unverified (daily at 02:00)
# - HandleTrialExpirationJob (daily at 03:00)
```

### Step 7: Restart PHP-FPM
```bash
sudo systemctl restart php8.2-fpm
```

### Step 8: Monitor Logs
```bash
# Watch application logs for any errors
sudo tail -f /home/obsolio/htdocs/api.obsolio.com/storage/logs/laravel.log
```

## Post-Deployment Verification

### 1. Test Registration Endpoint
```bash
curl -X POST https://api.obsolio.com/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "fullName": "Test User",
    "email": "test@example.com",
    "password": "testpassword123",
    "password_confirmation": "testpassword123",
    "country": "Egypt",
    "phone": "+201234567890",
    "subdomain": "testcompany",
    "organizationFullName": "Test Company",
    "plan_id": "PLAN_UUID_HERE",
    "billing_cycle": "monthly"
  }'
```

**Expected Response**: Success with verification required message

### 2. Verify Existing Subscriptions Not Affected
```bash
sudo -u obsolio php artisan tinker
>>> $sub = \App\Models\Subscription::where('status', 'trialing')->first()
>>> $sub->status
>>> $sub->trial_ends_at
>>> exit
```

### 3. Test Trial Expiration Job (Manually)
```bash
# Run the job manually to test (won't affect production data if no expired trials)
sudo -u obsolio php artisan tinker
>>> \App\Jobs\Billing\HandleTrialExpirationJob::dispatch()
>>> exit

# Check logs
sudo tail -50 /home/obsolio/htdocs/api.obsolio.com/storage/logs/laravel.log | grep "trial expiration"
```

### 4. Verify Paymob Integration
```bash
sudo -u obsolio php artisan tinker
>>> $invoice = \App\Models\BillingInvoice::where('status', 'pending')->first()
>>> $invoice->metadata['paymob_payment_url'] ?? 'Not generated yet'
>>> exit
```

## Frontend Integration Requirements

### Registration Form Changes
The frontend registration form MUST be updated to include:

```javascript
// Add plan selection dropdown
const registrationData = {
  fullName: "...",
  email: "...",
  password: "...",
  password_confirmation: "...",
  country: "...",
  phone: "...",
  subdomain: "...",
  organizationFullName: "...",
  // NEW REQUIRED FIELDS
  plan_id: "uuid-of-selected-plan", // Get from /api/v1/pricing/plans
  billing_cycle: "monthly" // or "annual"
}
```

### Get Available Plans
```javascript
// Fetch available plans before showing registration form
GET /api/v1/pricing/plans?billing_cycle=monthly
GET /api/v1/pricing/plans?billing_cycle=annual
```

### Display Trial Information
- Show trial period length (14 or 30 days) based on selected plan
- Display "$0 during trial" messaging
- Show post-trial pricing clearly

## Rollback Plan

If issues occur, rollback with:

```bash
cd /home/obsolio/htdocs/api.obsolio.com

# 1. Checkout previous commit
sudo -u obsolio git log --oneline -5
sudo -u obsolio git checkout <previous-commit-hash>

# 2. Clear caches
sudo -u obsolio php artisan cache:clear
sudo -u obsolio php artisan config:clear
sudo -u obsolio php artisan route:clear
sudo -u obsolio php artisan view:clear

# 3. Recache
sudo -u obsolio php artisan config:cache
sudo -u obsolio php artisan route:cache
sudo -u obsolio php artisan view:cache

# 4. Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

## Monitoring

### Daily Checks
```bash
# Check trial expiration job logs
grep "trial expiration" /home/obsolio/htdocs/api.obsolio.com/storage/logs/laravel.log

# Check for payment link generation
grep "Payment link generated" /home/obsolio/htdocs/api.obsolio.com/storage/logs/laravel.log

# Check for errors
grep "ERROR" /home/obsolio/htdocs/api.obsolio.com/storage/logs/laravel.log | tail -20
```

### Weekly Checks
```bash
# Verify subscription statuses
sudo -u obsolio php artisan tinker
>>> \App\Models\Subscription::groupBy('status')->selectRaw('status, count(*) as count')->get()
>>> exit

# Check invoice generation
sudo -u obsolio php artisan tinker
>>> \App\Models\BillingInvoice::where('created_at', '>=', now()->subDays(7))->count()
>>> exit
```

## Troubleshooting

### Issue: Scheduler not running
**Solution**: Verify cron job exists for `obsolio` user:
```bash
sudo crontab -u obsolio -l
# Should see: * * * * * cd /home/obsolio/htdocs/api.obsolio.com && php artisan schedule:run
```

### Issue: Payment links not generating
**Check**:
1. Paymob credentials configured correctly
2. Invoice total > 0
3. Invoice status is 'pending' or 'draft'
4. Check logs for Paymob API errors

### Issue: Registration fails with validation error
**Check**:
1. Frontend sending `plan_id` and `billing_cycle`
2. Plan ID exists and is active
3. Billing cycle is 'monthly' or 'annual'

### Issue: Trial invoices showing wrong amount
**Check**:
1. Subscription status is 'trialing'
2. trial_ends_at is in the future
3. Check CreateTrialSubscription logs

## Security Notes

1. **Paymob Credentials**: Never commit `.env` file
2. **HMAC Verification**: Always verify Paymob webhooks with HMAC
3. **Database Backups**: Automated daily backups recommended
4. **Logging**: Monitor logs for suspicious activity

## Support Contacts

- **Backend Issues**: Check Laravel logs
- **Payment Issues**: Check Paymob dashboard
- **Database Issues**: PostgreSQL logs

## Success Criteria

✅ Registration accepts plan selection
✅ Trial invoices show $0.00
✅ Scheduler runs daily jobs
✅ Payment links generated automatically
✅ Existing subscriptions unaffected
✅ No errors in logs for 24 hours

## Notes

- **Zero Downtime**: These changes don't require database migrations
- **Backward Compatible**: Existing subscriptions continue working
- **Frontend Update Required**: Registration form MUST be updated
- **Testing**: Test in staging before production if available
