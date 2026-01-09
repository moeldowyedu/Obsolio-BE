<?php

namespace App\Listeners;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                $trialEndsAt = $defaultPlan->trial_days > 0
                    ? now()->addDays($defaultPlan->trial_days)
                    : null;

                // Determine subscription status
                $status = $trialEndsAt ? 'trialing' : 'active';

                // Calculate period dates
                $currentPeriodStart = now();
                $currentPeriodEnd = now()->addMonth(); // Default to monthly

                // Create subscription
                $subscription = Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $defaultPlan->id,
                    'status' => $status,
                    'billing_cycle' => 'monthly',
                    'starts_at' => now(),
                    'trial_ends_at' => $trialEndsAt,
                    'current_period_start' => $currentPeriodStart,
                    'current_period_end' => $currentPeriodEnd,
                    'metadata' => [
                        'created_via' => 'email_verification',
                        'user_id' => $user->id,
                        'plan_type' => $defaultPlan->type,
                        'plan_tier' => $defaultPlan->tier,
                    ],
                ]);

                // Update tenant's organization_id if it's an organization type
                if ($tenant->type === 'organization') {
                    $organization = $tenant->organizations()->first();
                    if ($organization) {
                        $tenant->update(['organization_id' => $organization->id]);
                    }
                }

                Log::info('Trial subscription created automatically', [
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'plan_id' => $defaultPlan->id,
                    'plan_name' => $defaultPlan->name,
                    'trial_ends_at' => $trialEndsAt,
                    'status' => $status,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create trial subscription', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get default plan for tenant type
     */
    protected function getDefaultPlan(string $tenantType): ?SubscriptionPlan
    {
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

        // Priority 2: Fallback to free tier (safety net)
        Log::warning('No default plan found, falling back to free tier', [
            'tenant_type' => $tenantType
        ]);

        return SubscriptionPlan::where('type', $tenantType)
            ->where('tier', 'free')
            ->where('is_active', true)
            ->where('is_published', true)
            ->first();
    }
}
