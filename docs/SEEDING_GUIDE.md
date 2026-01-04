# Server Seeding Guide - OBSOLIO Pricing Infrastructure

## ✅ Ready to Seed

All migrations are complete and the pricing infrastructure is ready. Here's what to run:

## 1. Run All Seeders

```bash
# Run all seeders (recommended)
php artisan db:seed

# Or run specific seeders in order:
php artisan db:seed --class=BillingCyclesSeeder
php artisan db:seed --class=AgentTiersSeeder
php artisan db:seed --class=AgentsSeeder
php artisan db:seed --class=AgentPricingSeeder
php artisan db:seed --class=SubscriptionPlansSeeder
php artisan db:seed --class=SystemAdminSeeder
```

## 2. What Gets Seeded

### Phase 1 - Core Pricing Data
- **BillingCyclesSeeder**: 3 billing cycles (Monthly, Semi-Annual, Annual)
- **AgentTiersSeeder**: 4 tiers (Basic, Professional, Specialized, Enterprise)
- **AgentsSeeder**: 41 agents with tier assignments
- **AgentPricingSeeder**: Pricing for each agent by tier

### Phase 2 - Subscription Plans
- **SubscriptionPlansSeeder**: 10 subscription plans
  - Starter (Monthly, Semi-Annual, Annual)
  - Professional (Monthly, Semi-Annual, Annual)
  - Business (Monthly, Semi-Annual, Annual)
  - Enterprise (Monthly)

### System Admin
- **SystemAdminSeeder**: Creates Super Admin user and role

## 3. Verify Seeding

```bash
# Check seeded data
php artisan tinker

# Then run:
DB::table('billing_cycles')->count();        // Should be 3
DB::table('agent_tiers')->count();           // Should be 4
DB::table('agents')->count();                // Should be 41
DB::table('agent_pricing')->count();         // Should be ~164 (41 agents × 4 tiers)
DB::table('subscription_plans')->count();    // Should be 10
```

## 4. Production Considerations

### For Production Server:

```bash
# Run migrations first
php artisan migrate --force

# Then seed
php artisan db:seed --force

# Or seed specific classes
php artisan db:seed --class=BillingCyclesSeeder --force
php artisan db:seed --class=AgentTiersSeeder --force
php artisan db:seed --class=AgentsSeeder --force
php artisan db:seed --class=AgentPricingSeeder --force
php artisan db:seed --class=SubscriptionPlansSeeder --force
```

### Important Notes:

1. **Idempotent Seeders**: Most seeders use `updateOrCreate()` so they're safe to run multiple times
2. **SystemAdminSeeder**: Fixed to use `guard_name = 'console'` for system roles
3. **No Sample Data**: These seeders create production-ready pricing data, not test data

## 5. Post-Seeding Steps

After seeding, you can:

1. **Test API Endpoints**: Visit `http://your-domain.com/api/documentation`
2. **Create Test Subscription**: Use the API to create a test subscription
3. **Verify Pricing**: Check that all plans and agents are correctly priced

## 6. Troubleshooting

If seeding fails:

```bash
# Clear cache
php artisan cache:clear
php artisan config:clear

# Check database connection
php artisan tinker
DB::connection()->getPdo();

# Re-run specific seeder
php artisan db:seed --class=SubscriptionPlansSeeder
```

---

## ✅ You're Ready!

All migrations are complete, seeders are fixed, and the pricing infrastructure is production-ready.

Run: `php artisan db:seed` and you're good to go! 🚀
