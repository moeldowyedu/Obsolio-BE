# VPS Deployment Guide - Scheduled Billing Jobs

## 📋 Prerequisites
- Code pushed to origin
- SSH access to VPS server
- Root or sudo access

---

## 🚀 Deployment Steps

### 1. Pull Latest Code

```bash
# SSH into your VPS
ssh root@sayednaelhabeb

# Navigate to project directory
cd /home/obsolio/htdocs/api.obsolio.com

# Pull latest changes
git pull origin main
```

### 2. Install Dependencies (if needed)

```bash
# Update composer dependencies
composer install --no-dev --optimize-autoloader

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 3. Configure Queue

Update `.env`:
```bash
nano .env
```

Add/update:
```env
QUEUE_CONNECTION=database
```

Save and exit (Ctrl+X, Y, Enter)

### 4. Create Queue Tables

```bash
# Create queue migrations
php artisan queue:table
php artisan queue:failed-table

# Run migrations
php artisan migrate --force
```

### 5. Test Scheduler

```bash
# List all scheduled tasks
php artisan schedule:list

# You should see all 7 billing jobs listed

# Test scheduler manually
php artisan schedule:run
```

### 6. Setup Cron Job

```bash
# Edit crontab
crontab -e
```

Add this line:
```bash
* * * * * cd /home/obsolio/htdocs/api.obsolio.com && php artisan schedule:run >> /dev/null 2>&1
```

Save and exit.

Verify cron is added:
```bash
crontab -l
```

### 7. Configure Supervisor for Queue Workers

Create supervisor config:
```bash
sudo nano /etc/supervisor/conf.d/obsolio-worker.conf
```

Add this configuration:
```ini
[program:obsolio-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/obsolio/htdocs/api.obsolio.com/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/home/obsolio/htdocs/api.obsolio.com/storage/logs/worker.log
stopwaitsecs=3600
```

Save and exit.

### 8. Start Supervisor

```bash
# Reload supervisor configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start the workers
sudo supervisorctl start obsolio-worker:*

# Check status
sudo supervisorctl status
```

You should see:
```
obsolio-worker:obsolio-worker_00    RUNNING   pid 12345, uptime 0:00:05
obsolio-worker:obsolio-worker_01    RUNNING   pid 12346, uptime 0:00:05
```

### 9. Set Permissions

```bash
# Ensure storage and cache directories are writable
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 10. Test Jobs Manually

```bash
php artisan tinker
```

Then test each job:
```php
// Test monthly billing
App\Jobs\Billing\ProcessMonthlyBillingJob::dispatch();

// Test agent renewal
App\Jobs\Billing\RenewAgentSubscriptionsJob::dispatch();

// Test usage reset
App\Jobs\Billing\ResetUsageQuotasJob::dispatch();

exit
```

### 11. Monitor Logs

```bash
# Watch Laravel logs
tail -f storage/logs/laravel.log

# Watch worker logs
tail -f storage/logs/worker.log

# Watch queue in real-time
php artisan queue:monitor
```

---

## ✅ Verification Checklist

Run these commands to verify everything is working:

```bash
# 1. Check queue tables exist
php artisan tinker
>>> DB::table('jobs')->count()
>>> DB::table('failed_jobs')->count()
>>> exit

# 2. Check scheduler is configured
php artisan schedule:list

# 3. Check supervisor is running
sudo supervisorctl status

# 4. Check cron is configured
crontab -l

# 5. Check queue worker is processing
php artisan queue:work --once

# 6. View recent logs
tail -n 50 storage/logs/laravel.log
```

---

## 🔍 Troubleshooting

### Queue Worker Not Starting

```bash
# Check supervisor logs
sudo tail -f /var/log/supervisor/supervisord.log

# Restart supervisor
sudo supervisorctl restart obsolio-worker:*
```

### Jobs Not Running

```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear queue
php artisan queue:flush
```

### Scheduler Not Running

```bash
# Verify cron is running
sudo service cron status

# Check cron logs
grep CRON /var/log/syslog

# Test scheduler manually
php artisan schedule:run
```

### Permission Issues

```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage

# Fix bootstrap/cache permissions
sudo chown -R www-data:www-data bootstrap/cache
sudo chmod -R 775 bootstrap/cache
```

---

## 📊 Monitoring Commands

```bash
# View queue status
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# View scheduled tasks
php artisan schedule:list

# Check supervisor status
sudo supervisorctl status

# Restart queue workers
sudo supervisorctl restart obsolio-worker:*

# Stop queue workers
sudo supervisorctl stop obsolio-worker:*

# Start queue workers
sudo supervisorctl start obsolio-worker:*
```

---

## 🎯 Expected Behavior

After deployment, you should see:

1. **Cron running every minute** - Check with `grep CRON /var/log/syslog`
2. **2 queue workers running** - Check with `sudo supervisorctl status`
3. **7 scheduled jobs listed** - Check with `php artisan schedule:list`
4. **Jobs in logs** - Check with `tail -f storage/logs/laravel.log`

### Job Schedule (Africa/Cairo Time):

- **00:00** - Monthly Billing (1st of month)
- **01:00** - Agent Renewal (daily)
- **02:00** - Usage Reset (1st of month)
- **03:00** - Expired Subscriptions (daily)
- **04:00** - Cleanup (1st of month)
- **10:00** - Overdue Reminders (daily)
- **14:00** - Failed Payments (daily)

---

## 🔄 Maintenance Commands

```bash
# Restart everything
sudo supervisorctl restart obsolio-worker:*
php artisan config:clear
php artisan cache:clear

# Update code
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
sudo supervisorctl restart obsolio-worker:*

# View logs
tail -f storage/logs/laravel.log
tail -f storage/logs/worker.log
```

---

## 📝 Quick Reference

| Command | Purpose |
|---------|---------|
| `php artisan schedule:list` | List all scheduled jobs |
| `php artisan schedule:run` | Run scheduler manually |
| `php artisan queue:work` | Start queue worker |
| `php artisan queue:monitor` | Monitor queue |
| `php artisan queue:failed` | View failed jobs |
| `sudo supervisorctl status` | Check supervisor status |
| `sudo supervisorctl restart obsolio-worker:*` | Restart workers |
| `tail -f storage/logs/laravel.log` | Watch logs |

---

## ✅ Deployment Complete!

Once all steps are done, your billing automation is live! 🎉

The system will now:
- Generate invoices automatically on the 1st of each month
- Renew agent subscriptions daily
- Reset usage quotas monthly
- Send payment reminders
- Retry failed payments
- Handle expired subscriptions
- Clean up old invoices

Monitor the logs for the first few days to ensure everything runs smoothly.
