# 📊 Pricing Setup Guide - OBSOLIO

Complete guide to reset and seed pricing data for the OBSOLIO platform.

---

## 📋 Overview

This guide will help you:
1. ✅ Safely delete existing pricing data
2. ✅ Seed fresh pricing data with 3 plans (Starter, Professional, Enterprise)
3. ✅ Set up billing cycles (Monthly, Annual with 17% discount)

---

## 🗂️ Tables Involved

### 1. `billing_cycles`
Stores billing cycle definitions (monthly, annual).

### 2. `subscription_plans`
Stores subscription plans with pricing, features, and limits.
- **Foreign Key:** `billing_cycle_id` → `billing_cycles.id`

---

## 🚀 Quick Start

### Option 1: Using Artisan Commands (Recommended)

```bash
# Navigate to project directory
cd /home/user/Obsolio-BE

# Delete existing data and seed fresh data
php artisan db:seed --class=PricingSeeder
```

**Note:** The seeder will **automatically truncate** the tables before seeding.

---

### Option 2: Manual SQL Deletion + Seeder

If you want more control:

#### Step 1: Delete existing data using SQL

```bash
# Using psql (PostgreSQL)
psql -U your_username -d your_database -f database/sql/delete_pricing_data.sql

# OR using Laravel Tinker
php artisan tinker
>>> DB::statement('DELETE FROM subscription_plans');
>>> DB::statement('DELETE FROM billing_cycles');
>>> DB::statement('ALTER SEQUENCE billing_cycles_id_seq RESTART WITH 1');
>>> exit
```

#### Step 2: Run the seeder

```bash
php artisan db:seed --class=PricingSeeder
```

---

## 📊 What Gets Seeded

### Billing Cycles (2 records)

| Code | Name | Months | Discount |
|------|------|--------|----------|
| monthly | Monthly | 1 | 0% |
| annual | Annual | 12 | 17% |

### Subscription Plans (6 records)

#### 1. **Starter Plan**
- **Monthly:** $29/month
- **Annual:** $288.84/year ($24.07/month, save 17%)
- **Trial:** 14 days
- **Executions:** 1,000/month
- **Agents:** 5 Basic agents
- **Users:** Up to 3
- **Storage:** 10GB

#### 2. **Professional Plan** (Most Popular)
- **Monthly:** $99/month
- **Annual:** $986.04/year ($82.17/month, save 17%)
- **Trial:** 14 days
- **Executions:** 5,000/month
- **Agents:** 20 agents (including 5 Professional tier)
- **Users:** Up to 10
- **Storage:** 50GB

#### 3. **Enterprise Plan**
- **Monthly:** $499/month
- **Annual:** $4,970.04/year ($414.17/month, save 17%)
- **Trial:** 30 days (longer trial for enterprise)
- **Executions:** 50,000/month
- **Agents:** Unlimited (all tiers)
- **Users:** Unlimited
- **Storage:** Unlimited

---

## 🎯 Key Features

### Trial Periods
- ✅ **Starter & Professional:** 14-day free trial
- ✅ **Enterprise:** 30-day free trial

### Pricing Strategy
- ✅ **Annual Discount:** 17% off (configurable)
- ✅ **Overage Pricing:**
  - Starter: $0.01 per additional execution
  - Professional: $0.008 per additional execution
  - Enterprise: $0.005 per additional execution

### Features Included

**Starter:**
- 1,000 executions/month
- 5 Basic AI agents
- 10GB storage
- Email support (48h response)
- Basic analytics
- API access

**Professional:**
- 5,000 executions/month
- 20 agents (5 Professional tier)
- 50GB storage
- Priority support (24h response)
- Advanced analytics
- Custom integrations
- Team collaboration
- Webhooks

**Enterprise:**
- 50,000+ executions/month
- Unlimited agents (all tiers)
- Unlimited storage
- 24/7 dedicated support
- Custom reports
- SSO (SAML, OAuth)
- SLA guarantee (99.9%)
- Dedicated account manager
- White-label options

---

## 🔍 Verification

After seeding, verify the data:

```bash
php artisan tinker

# Check billing cycles
>>> DB::table('billing_cycles')->get();

# Check subscription plans
>>> DB::table('subscription_plans')->select('name', 'billing_cycle_id', 'final_price', 'trial_days')->get();

# Check plans grouped by name (like the API endpoint does)
>>> \App\Models\SubscriptionPlan::with('billingCycle')
    ->where('is_active', true)
    ->where('is_published', true)
    ->get()
    ->groupBy('name');

>>> exit
```

---

## 🌐 Test the API

After seeding, test the pricing endpoint:

```bash
# Using curl
curl https://api.obsolio.com/api/v1/pricing/plans | jq

# Or visit in browser
open https://api.obsolio.com/api/documentation
```

You should see 3 plan groups (Starter, Professional, Enterprise), each with 2 variants (monthly, annual).

---

## 📝 Files Created

1. **`database/seeders/PricingSeeder.php`**
   - Complete seeder with all pricing data
   - Run with: `php artisan db:seed --class=PricingSeeder`

2. **`database/sql/delete_pricing_data.sql`**
   - Safe SQL deletion script
   - Handles foreign keys properly
   - Includes transaction safety

3. **`PRICING_SETUP_GUIDE.md`** (this file)
   - Complete documentation

---

## 🛠️ Customization

### Modify Pricing

Edit `database/seeders/PricingSeeder.php` and adjust:
- `final_price` - Price after discount
- `included_executions` - Monthly execution quota
- `max_agents` - Agent limits
- `trial_days` - Trial period
- `features` - Feature list (JSON array)

### Modify Discount Percentage

Change the `discount_percentage` in the billing cycles array:

```php
[
    'code' => 'annual',
    'name' => 'Annual',
    'months' => 12,
    'discount_percentage' => 20.00, // Change from 17% to 20%
]
```

Then recalculate `final_price` for annual plans:
```php
'final_price' => 348.00 * (1 - 0.20), // 20% discount
```

---

## ⚠️ Production Deployment

### On Production Server

```bash
# SSH to production
ssh user@api.obsolio.com

# Navigate to project
cd /home/obsolio/htdocs/api.obsolio.com

# Backup existing data (optional but recommended)
pg_dump -U your_user -d your_db -t subscription_plans -t billing_cycles > backup_pricing_$(date +%Y%m%d).sql

# Run the seeder
php artisan db:seed --class=PricingSeeder

# Clear cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Verify
php artisan tinker
>>> DB::table('subscription_plans')->count();
>>> exit
```

---

## 🔒 Safety Notes

1. **Foreign Key Handling:** The seeder deletes `subscription_plans` before `billing_cycles` to respect foreign key constraints.

2. **Transaction Safety:** The SQL deletion script uses transactions. If something goes wrong, run `ROLLBACK;`.

3. **Backup First:** Always backup production data before running destructive operations.

4. **Test Locally:** Test the seeder on your local/staging environment first.

---

## 📞 Support

If you encounter issues:
1. Check Laravel logs: `tail -f storage/logs/laravel.log`
2. Check database connection: `php artisan tinker >>> DB::connection()->getPdo();`
3. Verify migrations are up to date: `php artisan migrate:status`

---

## ✅ Checklist

Before running in production:

- [ ] Backup existing pricing data
- [ ] Test seeder on local/staging environment
- [ ] Verify all prices are correct
- [ ] Confirm trial periods (14 days for Starter/Pro, 30 for Enterprise)
- [ ] Check annual discount calculation (17%)
- [ ] Test API endpoint: `/api/v1/pricing/plans`
- [ ] Verify frontend pricing page displays correctly
- [ ] Clear all caches after seeding

---

## 🎉 Complete!

Your pricing data is now seeded and ready to use!

**Next Steps:**
1. Test the pricing page frontend
2. Verify checkout flow works with new plans
3. Test trial period activation
4. Monitor for any issues

---

**Last Updated:** January 16, 2026
**Version:** 1.0
