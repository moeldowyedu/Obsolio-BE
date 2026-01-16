<?php

namespace App\Jobs\Billing;

use App\Models\BillingInvoice;
use App\Models\Subscription;
use App\Services\PaymobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HandleTrialExpirationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info("Starting trial expiration handling");

        // Get subscriptions with expired trials
        $expiredTrials = Subscription::where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->whereDate('trial_ends_at', '<=', now())
            ->with(['tenant', 'plan'])
            ->get();

        Log::info("Found {$expiredTrials->count()} expired trials");

        $processedCount = 0;
        $failedCount = 0;

        foreach ($expiredTrials as $subscription) {
            try {
                DB::beginTransaction();

                $this->processExpiredTrial($subscription);

                DB::commit();
                $processedCount++;

            } catch (\Exception $e) {
                DB::rollBack();
                $failedCount++;

                Log::error("Failed to process expired trial", [
                    'subscription_id' => $subscription->id,
                    'tenant_id' => $subscription->tenant_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::info("Trial expiration handling completed", [
            'processed' => $processedCount,
            'failed' => $failedCount,
        ]);
    }

    /**
     * Process a single expired trial subscription
     */
    protected function processExpiredTrial(Subscription $subscription): void
    {
        $tenant = $subscription->tenant;
        $plan = $subscription->plan;

        Log::info("Processing expired trial", [
            'subscription_id' => $subscription->id,
            'tenant' => $tenant->name ?? $tenant->id,
            'plan' => $plan->name,
        ]);

        // Check if plan is free - if so, just activate without payment
        if ($plan->isFree()) {
            $subscription->update([
                'status' => 'active',
            ]);

            Log::info("Trial expired for free plan, activated without payment", [
                'subscription_id' => $subscription->id,
            ]);

            // TODO: Send notification email about trial end
            return;
        }

        // For paid plans, generate first paid invoice
        $invoice = $this->generateFirstPaidInvoice($subscription);

        if (!$invoice) {
            throw new \Exception("Failed to generate invoice for expired trial");
        }

        // Update subscription status to active (payment pending)
        $subscription->update([
            'status' => 'active',
        ]);

        // Generate Paymob payment link
        try {
            $paymentLink = $this->generatePaymentLink($invoice, $tenant);

            if ($paymentLink) {
                // Store payment link in invoice metadata
                $invoice->update([
                    'metadata' => array_merge($invoice->metadata ?? [], [
                        'paymob_payment_url' => $paymentLink,
                        'payment_link_generated_at' => now()->toISOString(),
                    ]),
                ]);

                Log::info("Payment link generated for post-trial invoice", [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'payment_url' => $paymentLink,
                ]);

                // TODO: Send invoice email with payment link
                // Mail::to($tenant->email)->send(new PostTrialInvoiceEmail($invoice, $paymentLink));
            }

        } catch (\Exception $e) {
            Log::error("Failed to generate payment link for invoice", [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            // Don't fail the entire process if payment link generation fails
            // The invoice is still created and can be paid manually
        }

        Log::info("Expired trial processed successfully", [
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ]);
    }

    /**
     * Generate first paid invoice after trial ends
     */
    protected function generateFirstPaidInvoice(Subscription $subscription): ?BillingInvoice
    {
        $tenant = $subscription->tenant;
        $plan = $subscription->plan;
        $billingCycle = $subscription->billing_cycle;

        // Calculate invoice amount based on billing cycle
        $amount = $billingCycle === 'annual' ? $plan->price_annual : $plan->price_monthly;

        // Calculate period dates
        $periodStart = now();
        $periodEnd = $billingCycle === 'annual' ? now()->addYear() : now()->addMonth();

        // Generate unique invoice number
        $invoiceNumber = $this->generateInvoiceNumber($tenant->id);

        // Build line items
        $lineItems = [
            [
                'description' => "{$plan->name} - " . ucfirst($billingCycle) . " Subscription",
                'quantity' => 1,
                'unit_price' => $amount,
                'total' => $amount,
                'period_start' => $periodStart->format('Y-m-d'),
                'period_end' => $periodEnd->format('Y-m-d'),
            ]
        ];

        $notes = "Your trial period has ended. This is your first paid invoice for {$plan->name}. " .
                 "Please complete payment to continue using the service without interruption.";

        // Create the invoice
        $invoice = BillingInvoice::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'invoice_number' => $invoiceNumber,
            'subtotal' => $amount,
            'tax' => 0.00,
            'total' => $amount,
            'currency' => 'USD',
            'status' => 'pending', // Awaiting payment
            'due_date' => now()->addDays(7), // 7 days to pay
            'paid_at' => null,
            'line_items' => $lineItems,
            'notes' => $notes,
            'payment_method' => null,
            'metadata' => [
                'is_post_trial' => true,
                'billing_cycle' => $billingCycle,
                'period_start' => $periodStart->toISOString(),
                'period_end' => $periodEnd->toISOString(),
            ],
        ]);

        // Update subscription billing dates
        $subscription->update([
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'next_billing_date' => $periodEnd,
        ]);

        Log::info("First paid invoice generated after trial", [
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoiceNumber,
            'amount' => $amount,
            'billing_cycle' => $billingCycle,
        ]);

        return $invoice;
    }

    /**
     * Generate Paymob payment link for invoice
     */
    protected function generatePaymentLink(BillingInvoice $invoice, $tenant): ?string
    {
        try {
            $paymobService = app(PaymobService::class);

            // Create payment order with Paymob
            $orderData = [
                'amount_cents' => $invoice->total * 100, // Convert to cents
                'currency' => $invoice->currency,
                'billing_data' => [
                    'first_name' => $tenant->name ?? 'Customer',
                    'last_name' => '',
                    'email' => $tenant->email ?? 'noreply@obsolio.com',
                    'phone_number' => $tenant->phone ?? '+201000000000',
                ],
                'merchant_order_id' => $invoice->invoice_number,
                'items' => array_map(function ($item) {
                    return [
                        'name' => $item['description'],
                        'amount_cents' => $item['total'] * 100,
                        'quantity' => $item['quantity'] ?? 1,
                    ];
                }, $invoice->line_items),
            ];

            $result = $paymobService->createPayment(
                $invoice->invoice_number,
                $invoice->total,
                $orderData['billing_data']
            );

            return $result['iframe_url'] ?? null;

        } catch (\Exception $e) {
            Log::error("Failed to create Paymob payment link", [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generate a unique invoice number
     */
    protected function generateInvoiceNumber(string $tenantId): string
    {
        // Format: INV-YYYYMMDD-XXXXX
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(5));
        $invoiceNumber = "INV-{$date}-{$random}";

        // Ensure uniqueness (rare collision check)
        while (BillingInvoice::where('invoice_number', $invoiceNumber)->exists()) {
            $random = strtoupper(Str::random(5));
            $invoiceNumber = "INV-{$date}-{$random}";
        }

        return $invoiceNumber;
    }
}
