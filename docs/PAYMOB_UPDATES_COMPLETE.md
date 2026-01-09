# Paymob Integration Updates - Complete!

## ✅ What Was Updated

### 1. BillingController Webhook (PRIORITY 1) ✅
**File:** `app/Http/Controllers/Api/BillingController.php`

**Changes:**
- ✅ Added PaymobService HMAC verification
- ✅ Uses `PaymobService::verifyHmac()` for security
- ✅ Uses `PaymobService::processCallback()` for payment processing
- ✅ Works with new `Invoice` model from Phase 4
- ✅ Automatically activates subscriptions on successful payment
- ✅ Comprehensive logging for debugging

**Key Features:**
```php
// HMAC verification
$paymobService = app(\App\Services\PaymobService::class);
if (!$paymobService->verifyHmac($data)) {
    return response()->json(['message' => 'Invalid signature'], 400);
}

// Process payment
$result = $paymobService->processCallback($data);

// Update invoice
$invoice->markAsPaid($result['transaction_id'], 'paymob');

// Activate subscription
if ($invoice->subscription_id) {
    $subscription->update(['status' => 'active']);
}
```

---

### 2. Removed Duplicate Webhook Route ✅
**File:** `routes/api.php`

**Changes:**
- ✅ Commented out `require __DIR__ . '/paymob_routes.php';`
- ✅ Prevents duplicate webhook handlers
- ✅ Single source of truth: `BillingController@paymobWebhook`

**Active Webhook:**
```
POST /api/v1/webhooks/paymob → BillingController@paymobWebhook
```

---

### 3. Updated PaymentController ✅
**File:** `app/Http/Controllers/Api/V1/PaymentController.php`

**Changes:**
- ✅ Replaced `BillingInvoice` with new `Invoice` model
- ✅ Uses `InvoiceLineItem::createBasePlan()` for line items
- ✅ Uses `Invoice::createForTenant()` factory method
- ✅ Uses `Invoice::markAsPaid()` and `Invoice::refund()` methods
- ✅ Removed billing_cycle parameter (uses plan's billing cycle)
- ✅ Uses `invoice_number` as Paymob order ID
- ✅ Deprecated old `paymobCallback()` method

**New Payment Flow:**
```php
// Create invoice with new model
$invoice = Invoice::createForTenant($tenant, $periodStart, $periodEnd);

// Add line items
InvoiceLineItem::createBasePlan($invoice, $plan, $amountUSD);

// Recalculate total
$invoice->recalculateTotal();

// Create Paymob payment
$payment = $paymobService->createPayment(
    $invoice->invoice_number,  // Use invoice_number as order ID
    $amountEGP,
    $billingData,
    $items
);
```

---

## 🔄 Integration Status

### ✅ Completed (Priority 1)
- [x] HMAC verification in webhook
- [x] New Invoice model integration
- [x] Removed duplicate routes
- [x] Updated payment creation flow
- [x] Subscription activation on payment
- [x] Comprehensive logging

### ⏳ Pending (Priority 2)
- [ ] Integration with `ProcessMonthlyBillingJob`
- [ ] Integration with `RetryFailedPaymentsJob`
- [ ] Agent add-on payment support
- [ ] Payment retry logic in `BillingController`

---

## 🧪 Testing Guide

### 1. Test Webhook Locally

```bash
# Use ngrok or similar to expose local server
ngrok http 8000

# Configure Paymob webhook URL:
# https://your-ngrok-url.ngrok.io/api/v1/webhooks/paymob
```

### 2. Test Payment Creation

```bash
curl -X POST https://api.obsolio.com/api/v1/payments/subscription \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "plan_id": "PLAN_UUID_HERE"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "invoice_id": "...",
    "invoice_number": "INV-2026-01-0001",
    "iframe_url": "https://accept.paymob.com/api/acceptance/iframes/799158?payment_token=...",
    "amount_usd": 29.99,
    "amount_egp": 914.695,
    "currency": "EGP"
  }
}
```

### 3. Test Webhook

**Paymob will send:**
```json
{
  "id": "123456",
  "success": true,
  "order": "INV-2026-01-0001",
  "amount_cents": 91470,
  "currency": "EGP",
  "hmac": "...",
  // ... other fields
}
```

**Check logs:**
```bash
tail -f storage/logs/laravel.log | grep "Paymob"
```

**Expected logs:**
```
Paymob webhook received
Payment processed successfully
Subscription activated
```

### 4. Verify Database

```bash
php artisan tinker
```

```php
// Check invoice
$invoice = Invoice::where('invoice_number', 'INV-2026-01-0001')->first();
$invoice->status; // Should be 'paid'
$invoice->payment_transaction_id; // Should have Paymob transaction ID

// Check subscription
$subscription = $invoice->subscription;
$subscription->status; // Should be 'active'
```

---

## 🚀 Deployment Steps

### On VPS Server:

```bash
# 1. Pull latest code
cd /home/obsolio/htdocs/api.obsolio.com
git pull origin main

# 2. Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# 3. Verify routes
php artisan route:list | grep paymob

# Should show:
# POST  api/v1/webhooks/paymob  → BillingController@paymobWebhook
```

### Configure Paymob Dashboard:

1. Login to Paymob dashboard
2. Go to Settings → Webhooks
3. Set webhook URL: `https://api.obsolio.com/api/v1/webhooks/paymob`
4. Enable webhook events:
   - Transaction Successful
   - Transaction Failed
   - Transaction Pending

---

## 📊 Monitoring

### Check Webhook Logs

```bash
# Watch webhook activity
tail -f storage/logs/laravel.log | grep "Paymob webhook"

# Check for HMAC failures
grep "HMAC verification failed" storage/logs/laravel.log

# Check successful payments
grep "Payment processed successfully" storage/logs/laravel.log
```

### Check Failed Payments

```bash
php artisan tinker
```

```php
// Get failed invoices
Invoice::where('status', 'failed')->get();

// Get pending invoices
Invoice::where('status', 'pending')->get();
```

---

## 🔐 Security Notes

### HMAC Verification
- ✅ All webhooks are verified using HMAC signature
- ✅ Invalid signatures are rejected with 400 error
- ✅ Logged for security monitoring

### Webhook Security
- ✅ No authentication required (verified by HMAC)
- ✅ Public endpoint (as required by Paymob)
- ✅ All actions logged

---

## 🐛 Troubleshooting

### Webhook Not Receiving Data

**Check:**
1. Paymob webhook URL is correct
2. Server is accessible from internet
3. No firewall blocking Paymob IPs

**Debug:**
```bash
# Check if webhook endpoint is accessible
curl -X POST https://api.obsolio.com/api/v1/webhooks/paymob \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'
```

### HMAC Verification Failing

**Check:**
1. `PAYMOB_HMAC_SECRET` in `.env` is correct
2. Webhook data structure matches Paymob format

**Debug:**
```bash
# Check logs for HMAC details
grep "HMAC verification" storage/logs/laravel.log
```

### Invoice Not Found

**Check:**
1. Invoice number matches Paymob order ID
2. Invoice was created before webhook

**Debug:**
```php
// Check if invoice exists
Invoice::where('invoice_number', 'INV-2026-01-0001')->first();
```

---

## 📝 Next Steps (Priority 2)

1. **Integrate with Scheduled Jobs**
   - Update `ProcessMonthlyBillingJob` to create Paymob payments
   - Update `RetryFailedPaymentsJob` to retry with Paymob

2. **Add Agent Add-on Payments**
   - Create endpoint for agent subscription payments
   - Handle agent line items in invoices

3. **Add Payment Retry Logic**
   - Implement `BillingController@retryPayment()`
   - Add retry button in frontend

4. **Add PDF Invoice Generation**
   - Implement `BillingController@downloadInvoice()`
   - Generate PDF with invoice details

---

## ✅ Summary

**Priority 1 Updates: COMPLETE** ✅

- Paymob integration now works with new pricing system
- HMAC verification implemented
- Duplicate webhooks removed
- New Invoice model integrated
- Subscriptions auto-activate on payment

**Status:** Ready for testing and deployment! 🚀

**Next:** Test webhook integration, then proceed with Priority 2 (scheduled jobs integration).
