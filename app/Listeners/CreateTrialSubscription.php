<?php

namespace App\Listeners;

use App\Models\BillingInvoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CreateTrialSubscription implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(Verified $event): void
    {
        $user = $event->user;
        $tenant = $user->tenant;

        if (!$tenant) {
            Log::warning('User verified but has no tenant', ['user_id' => $user->id]);
            return;
        }

        // Check if tenant already has an active subscription
        $existingSubscription = Subscription::where('tenant_id', $tenant->id)
            ->whereIn('status', ['active', 'trialing'])
            ->first();

        if ($existingSubscription) {
            Log::info('Tenant already has active subscription', [
                'tenant_id' => $tenant->id,
                'subscription_id' => $existingSubscription->id
            ]);
            return;
        }

        try {
            DB::transaction(function () use ($tenant, $user) {
                // Determine default plan based on tenant type
                $defaultPlan = $this->getDefaultPlan($tenant->type);

                if (!$defaultPlan) {
                    Log::error('No default plan found for tenant type', ['type' => $tenant->type]);
                    return;
                }

                // Calculate trial end date based on plan's trial_days
                $trialDays = $defaultPlan->trial_days ?? 0;
                $trialEndsAt = $trialDays > 0 ? now()->addDays($trialDays) : null;

                // Determine subscription status
                $status = $trialDays > 0 ? 'trialing' : 'active';

                // Create subscription
                $subscription = Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $defaultPlan->id,
                    'status' => $status,
                    'billing_cycle' => 'monthly',
                    'starts_at' => now(),
                    'trial_ends_at' => $trialEndsAt,
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth(),
                    'metadata' => [
                        'created_via' => 'email_verification',
                        'user_id' => $user->id,
                        'plan_type' => $defaultPlan->type,
                        'plan_tier' => $defaultPlan->tier,
                    ],
                ]);

                Log::info('Trial subscription created', [
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'plan_name' => $defaultPlan->name,
                    'status' => $status,
                    'trial_days' => $trialDays,
                    'trial_ends_at' => $trialEndsAt,
                ]);

                // Generate invoice for the subscription (including free plans)
                $invoice = $this->generateInvoice($tenant, $subscription, $defaultPlan, $trialDays);

                // Link organization to tenant if applicable
                if ($tenant->type === 'organization') {
                    $organization = $tenant->organizations()->first();
                    if ($organization) {
                        $tenant->update(['organization_id' => $organization->id]);
                    }
                }
            });
        } catch (\Exception $e) {
            Log::error('Failed to create trial subscription', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get default plan for tenant type
     */
    protected function getDefaultPlan(string $tenantType): ?SubscriptionPlan
    {
        // Check if is_default column exists (backward compatibility)
        $hasIsDefaultColumn = Schema::hasColumn('subscription_plans', 'is_default');

        if ($hasIsDefaultColumn) {
            // Priority 1: Find plan marked as default for this type
            $defaultPlan = SubscriptionPlan::where('type', $tenantType)
                ->where('is_default', true)
                ->where('is_active', true)
                ->where('is_published', true)
                ->first();

            if ($defaultPlan) {
                Log::info('Using default plan for new tenant', [
                    'plan_id' => $defaultPlan->id,
                    'plan_name' => $defaultPlan->name,
                    'plan_tier' => $defaultPlan->tier,
                    'tenant_type' => $tenantType,
                    'trial_days' => $defaultPlan->trial_days
                ]);
                return $defaultPlan;
            }
        } else {
            // Fallback to old hardcoded logic if column doesn't exist
            Log::info('is_default column not found, using legacy plan selection', [
                'tenant_type' => $tenantType
            ]);

            $planMap = [
                'personal' => ['type' => 'personal', 'tier' => 'free'],
                'individual' => ['type' => 'personal', 'tier' => 'free'],
                'organization' => ['type' => 'organization', 'tier' => 'team'],
            ];

            $criteria = $planMap[$tenantType] ?? null;

            if ($criteria) {
                return SubscriptionPlan::where('type', $criteria['type'])
                    ->where('tier', $criteria['tier'])
                    ->where('is_active', true)
                    ->where('is_published', true)
                    ->first();
            }
        }

        // Final fallback to free tier
        Log::warning('No default plan found, falling back to free tier', [
            'tenant_type' => $tenantType
        ]);

        return SubscriptionPlan::where('type', $tenantType)
            ->where('tier', 'free')
            ->where('is_active', true)
            ->where('is_published', true)
            ->first();
    }

    /**
     * Generate invoice for the subscription (including $0.00 for free plans)
     */
    protected function generateInvoice($tenant, Subscription $subscription, SubscriptionPlan $plan, int $trialDays): ?BillingInvoice
    {
        try {
            // Determine invoice amount based on plan
            $isFree = $plan->isFree();
            $amount = $isFree ? 0.00 : ($plan->price_monthly ?? 0.00);

            // Generate unique invoice number
            $invoiceNumber = $this->generateInvoiceNumber($tenant->id);

            // Determine invoice description
            $description = $isFree
                ? "{$plan->name} - Free Plan (Trial Period)"
                : "{$plan->name} - {$trialDays} Day Trial";

            // Build line items
            $lineItems = [
                [
                    'description' => $description,
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'total' => $amount,
                    'period_start' => now()->format('Y-m-d'),
                    'period_end' => $subscription->trial_ends_at ? $subscription->trial_ends_at->format('Y-m-d') : now()->addMonth()->format('Y-m-d'),
                ]
            ];

            // Add trial information note for free plans
            $notes = $isFree
                ? "Welcome to OBSOLIO! Your {$plan->name} is now active. Enjoy {$trialDays} days of trial access with no payment required."
                : "Welcome to OBSOLIO! Your {$plan->name} trial is now active. You won't be charged until {$subscription->trial_ends_at->format('M d, Y')}.";

            // Create the invoice
            $invoice = BillingInvoice::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'invoice_number' => $invoiceNumber,
                'subtotal' => $amount,
                'tax' => 0.00, // Free plans have no tax
                'total' => $amount,
                'currency' => 'USD',
                'status' => $isFree ? 'paid' : 'draft', // Free plans are auto-paid, paid plans are draft until trial ends
                'due_date' => $subscription->trial_ends_at ?? now(),
                'paid_at' => $isFree ? now() : null, // Free plans are immediately "paid"
                'line_items' => $lineItems,
                'notes' => $notes,
                'payment_method' => $isFree ? 'free_plan' : null,
            ]);

            Log::info('Invoice generated for subscription', [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoiceNumber,
                'amount' => $amount,
                'status' => $invoice->status,
                'is_free_plan' => $isFree,
            ]);

            return $invoice;

        } catch (\Exception $e) {
            Log::error('Failed to generate invoice for subscription', [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Don't fail the entire subscription creation if invoice fails
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
