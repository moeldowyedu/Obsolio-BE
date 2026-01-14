<?php

namespace Tests\Feature;

use App\Listeners\CreateTrialSubscription;
use App\Models\BillingInvoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceGenerationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that free plan generates $0.00 invoice after email verification
     */
    public function test_free_plan_generates_zero_dollar_invoice()
    {
        // Create a free plan
        $freePlan = SubscriptionPlan::factory()->create([
            'name' => 'Free Plan',
            'type' => 'organization',
            'tier' => 'free',
            'price_monthly' => 0.00,
            'trial_days' => 14,
            'is_active' => true,
            'is_published' => true,
            'is_default' => true,
        ]);

        // Create tenant and user
        $tenant = Tenant::factory()->create([
            'type' => 'organization',
            'status' => 'pending_verification',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => null,
        ]);

        // Trigger email verification event
        $event = new Verified($user);
        $listener = new CreateTrialSubscription();
        $listener->handle($event);

        // Assert subscription was created
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $freePlan->id,
            'status' => 'trialing',
        ]);

        $subscription = Subscription::where('tenant_id', $tenant->id)->first();

        // Assert invoice was created
        $this->assertDatabaseHas('billing_invoices', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'total' => 0.00,
            'status' => 'paid',
            'payment_method' => 'free_plan',
        ]);

        $invoice = BillingInvoice::where('tenant_id', $tenant->id)->first();

        // Assert invoice details
        $this->assertEquals('USD', $invoice->currency);
        $this->assertEquals(0.00, $invoice->subtotal);
        $this->assertEquals(0.00, $invoice->tax);
        $this->assertEquals(0.00, $invoice->total);
        $this->assertNotNull($invoice->paid_at);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
        $this->assertStringContainsString('Welcome to OBSOLIO', $invoice->notes);
    }

    /**
     * Test that paid plan generates draft invoice
     */
    public function test_paid_plan_generates_draft_invoice()
    {
        // Create a paid plan
        $proPlan = SubscriptionPlan::factory()->create([
            'name' => 'Pro Plan',
            'type' => 'organization',
            'tier' => 'pro',
            'price_monthly' => 29.99,
            'trial_days' => 30,
            'is_active' => true,
            'is_published' => true,
            'is_default' => true,
        ]);

        // Create tenant and user
        $tenant = Tenant::factory()->create([
            'type' => 'organization',
            'status' => 'pending_verification',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => null,
        ]);

        // Trigger email verification event
        $event = new Verified($user);
        $listener = new CreateTrialSubscription();
        $listener->handle($event);

        // Assert subscription was created
        $subscription = Subscription::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($subscription);

        // Assert invoice was created with draft status
        $this->assertDatabaseHas('billing_invoices', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'total' => 29.99,
            'status' => 'draft',
        ]);

        $invoice = BillingInvoice::where('tenant_id', $tenant->id)->first();

        // Assert invoice details
        $this->assertEquals(29.99, $invoice->subtotal);
        $this->assertEquals(29.99, $invoice->total);
        $this->assertNull($invoice->paid_at); // Not paid yet (trial period)
        $this->assertNull($invoice->payment_method); // No payment method yet
    }

    /**
     * Test invoice number uniqueness
     */
    public function test_invoice_numbers_are_unique()
    {
        $invoiceNumbers = [];

        for ($i = 0; $i < 5; $i++) {
            $freePlan = SubscriptionPlan::factory()->create([
                'tier' => 'free',
                'is_default' => true,
            ]);

            $tenant = Tenant::factory()->create();
            $user = User::factory()->create(['tenant_id' => $tenant->id]);

            $event = new Verified($user);
            $listener = new CreateTrialSubscription();
            $listener->handle($event);

            $invoice = BillingInvoice::where('tenant_id', $tenant->id)->first();
            $invoiceNumbers[] = $invoice->invoice_number;
        }

        // Assert all invoice numbers are unique
        $this->assertEquals(5, count(array_unique($invoiceNumbers)));
    }
}
