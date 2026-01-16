<?php

namespace App\Listeners;

use App\Models\Agent;
use App\Models\BillingInvoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TenantAgent;
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
                // Check if tenant has a plan selected during registration
                $plan = null;
                if ($tenant->plan_id) {
                    $plan = SubscriptionPlan::find($tenant->plan_id);
                    Log::info('Using plan selected during registration', [
                        'tenant_id' => $tenant->id,
                        'plan_id' => $tenant->plan_id,
                        'plan_name' => $plan->name ?? 'Unknown'
                    ]);
                }

                // Fallback to default plan if no plan was selected
                if (!$plan) {
                    $plan = $this->getDefaultPlan($tenant->type);
                    Log::info('No plan selected during registration, using default', [
                        'tenant_id' => $tenant->id,
                        'plan_id' => $plan->id ?? null,
                        'plan_name' => $plan->name ?? 'Unknown'
                    ]);
                }

                if (!$plan) {
                    Log::error('No plan found for tenant', ['tenant_id' => $tenant->id]);
                    return;
                }

                // Get billing cycle preference from tenant data
                $billingCycle = $tenant->data['billing_cycle'] ?? 'monthly';

                // Calculate trial end date based on plan's trial_days
                $trialDays = $plan->trial_days ?? 0;
                $trialEndsAt = $trialDays > 0 ? now()->addDays($trialDays) : null;

                // Determine subscription status
                $status = $trialDays > 0 ? 'trialing' : 'active';

                // Calculate period end based on billing cycle
                $currentPeriodEnd = $billingCycle === 'annual' ? now()->addYear() : now()->addMonth();

                // Create subscription
                $subscription = Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'status' => $status,
                    'billing_cycle' => $billingCycle,
                    'starts_at' => now(),
                    'trial_ends_at' => $trialEndsAt,
                    'current_period_start' => now(),
                    'current_period_end' => $currentPeriodEnd,
                    'metadata' => [
                        'created_via' => 'email_verification',
                        'user_id' => $user->id,
                        'plan_type' => $plan->type,
                        'plan_tier' => $plan->tier,
                        'selected_during_registration' => !empty($tenant->plan_id),
                    ],
                ]);

                Log::info('Trial subscription created', [
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'plan_name' => $plan->name,
                    'billing_cycle' => $billingCycle,
                    'status' => $status,
                    'trial_days' => $trialDays,
                    'trial_ends_at' => $trialEndsAt,
                ]);

                // Generate invoice for the subscription (including free plans)
                $invoice = $this->generateInvoice($tenant, $subscription, $plan, $trialDays);

                // Assign default agents to the new tenant
                $this->assignDefaultAgents($tenant, $plan);

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
     * Generate invoice for the subscription (including $0.00 for trial periods)
     */
    protected function generateInvoice($tenant, Subscription $subscription, SubscriptionPlan $plan, int $trialDays): ?BillingInvoice
    {
        try {
            // During trial period, invoice should ALWAYS be $0.00 regardless of plan
            $isTrialing = $trialDays > 0;
            $isFree = $plan->isFree();

            // Trial invoices are always $0.00, non-trial free plans are also $0.00
            $amount = ($isTrialing || $isFree) ? 0.00 : ($plan->price_monthly ?? 0.00);

            // Generate unique invoice number
            $invoiceNumber = $this->generateInvoiceNumber($tenant->id);

            // Determine invoice description based on trial status
            if ($isTrialing) {
                $description = "{$plan->name} - {$trialDays} Day Trial Period (No charge)";
            } elseif ($isFree) {
                $description = "{$plan->name} - Free Plan";
            } else {
                $description = "{$plan->name} - Monthly Subscription";
            }

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

            // Determine notes based on trial and plan status
            if ($isTrialing && !$isFree) {
                // Paid plan with trial
                $notes = "Welcome to OBSOLIO! Your {$plan->name} trial is now active. " .
                         "You won't be charged until {$subscription->trial_ends_at->format('M d, Y')} when your trial ends. " .
                         "This invoice shows $0.00 for the trial period.";
            } elseif ($isTrialing && $isFree) {
                // Free plan with trial (rare case)
                $notes = "Welcome to OBSOLIO! Your {$plan->name} is now active. " .
                         "Enjoy {$trialDays} days of trial access with no payment required.";
            } elseif ($isFree) {
                // Free plan without trial
                $notes = "Welcome to OBSOLIO! Your {$plan->name} is now active. " .
                         "This plan is free and requires no payment.";
            } else {
                // Paid plan without trial (immediate charge)
                $notes = "Welcome to OBSOLIO! Your {$plan->name} subscription is now active. " .
                         "Please complete payment to continue using the service.";
            }

            // Create the invoice
            $invoice = BillingInvoice::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'invoice_number' => $invoiceNumber,
                'subtotal' => $amount,
                'tax' => 0.00, // Trial and free plans have no tax
                'total' => $amount,
                'currency' => 'USD',
                // Trial invoices are marked as 'paid' since they're $0.00
                // Free plan invoices are marked as 'paid'
                // Paid plan invoices without trial are marked as 'draft'
                'status' => ($isTrialing || $isFree) ? 'paid' : 'draft',
                'due_date' => $subscription->trial_ends_at ?? now(),
                'paid_at' => ($isTrialing || $isFree) ? now() : null, // $0.00 invoices are immediately "paid"
                'line_items' => $lineItems,
                'notes' => $notes,
                'payment_method' => ($isTrialing || $isFree) ? 'trial_period' : null,
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

    /**
     * Assign default agents to new tenant based on their plan
     */
    protected function assignDefaultAgents($tenant, SubscriptionPlan $plan): void
    {
        try {
            // Get the maximum number of agents allowed on this plan
            $maxAgents = $plan->max_agents ?? 0;

            // If plan doesn't allow agents, skip assignment
            if ($maxAgents <= 0) {
                Log::info('Plan does not allow agents, skipping assignment', [
                    'tenant_id' => $tenant->id,
                    'plan_name' => $plan->name,
                    'max_agents' => $maxAgents,
                ]);
                return;
            }

            // Find suitable agents to assign
            // Priority 1: Free agents
            // Priority 2: Basic tier agents (tier_id = 1)
            $agents = $this->findDefaultAgents($plan, $maxAgents);

            if ($agents->isEmpty()) {
                Log::warning('No suitable agents found for assignment', [
                    'tenant_id' => $tenant->id,
                    'plan_name' => $plan->name,
                    'max_agents' => $maxAgents,
                ]);
                return;
            }

            $assignedCount = 0;

            foreach ($agents as $agent) {
                // Check if tenant already has this agent (rare, but check for idempotency)
                $exists = TenantAgent::where('tenant_id', $tenant->id)
                    ->where('agent_id', $agent->id)
                    ->exists();

                if ($exists) {
                    Log::debug('Agent already assigned, skipping', [
                        'tenant_id' => $tenant->id,
                        'agent_id' => $agent->id,
                    ]);
                    continue;
                }

                // Create tenant-agent assignment
                TenantAgent::create([
                    'tenant_id' => $tenant->id,
                    'agent_id' => $agent->id,
                    'status' => 'active',
                    'purchased_at' => now(),
                    'activated_at' => now(),
                    'configuration' => [
                        'assigned_via' => 'auto_assignment',
                        'plan_name' => $plan->name,
                        'assigned_at' => now()->toISOString(),
                    ],
                    'metadata' => [
                        'is_default_agent' => true,
                        'agent_name' => $agent->name,
                        'agent_tier' => $agent->tier_id ?? null,
                    ],
                ]);

                $assignedCount++;

                Log::debug('Agent assigned to tenant', [
                    'tenant_id' => $tenant->id,
                    'agent_id' => $agent->id,
                    'agent_name' => $agent->name,
                ]);
            }

            Log::info('Default agents assigned to tenant', [
                'tenant_id' => $tenant->id,
                'plan_name' => $plan->name,
                'max_agents_allowed' => $maxAgents,
                'agents_assigned' => $assignedCount,
                'agent_names' => $agents->pluck('name')->toArray(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to assign default agents', [
                'tenant_id' => $tenant->id,
                'plan_name' => $plan->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Don't fail the entire subscription creation if agent assignment fails
        }
    }

    /**
     * Find suitable default agents based on plan tier
     */
    protected function findDefaultAgents(SubscriptionPlan $plan, int $limit)
    {
        // Check if agent_tiers table exists (for backward compatibility)
        $hasAgentTiersTable = Schema::hasTable('agent_tiers');

        $query = Agent::where('is_active', true)
            ->where('is_featured', true); // Only assign featured agents by default

        if ($hasAgentTiersTable && Schema::hasColumn('agents', 'tier_id')) {
            // New schema with agent tiers
            // Priority: Free agents from Basic tier (tier_id = 1)
            $query->where(function ($q) {
                $q->where('price_model', 'free')
                    ->orWhere('tier_id', 1); // Basic tier
            });
        } else {
            // Legacy schema: Just use price_model
            $query->where('price_model', 'free');
        }

        // Order by creation date (oldest first, assuming they are most stable)
        $agents = $query->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        // Fallback: If no featured free agents, get any free agents
        if ($agents->isEmpty()) {
            Log::info('No featured free agents found, trying non-featured', [
                'plan_name' => $plan->name,
            ]);

            $query = Agent::where('is_active', true);

            if ($hasAgentTiersTable && Schema::hasColumn('agents', 'tier_id')) {
                $query->where(function ($q) {
                    $q->where('price_model', 'free')
                        ->orWhere('tier_id', 1);
                });
            } else {
                $query->where('price_model', 'free');
            }

            $agents = $query->orderBy('created_at', 'asc')
                ->limit($limit)
                ->get();
        }

        return $agents;
    }
}
