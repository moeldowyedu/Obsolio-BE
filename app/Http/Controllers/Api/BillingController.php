<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Billing",
 *     description="Invoice and payment management endpoints"
 * )
 */
class BillingController extends Controller
{
    /**
     * List all invoices for tenant
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/billing/invoices",
     *     summary="List all invoices",
     *     description="Returns paginated list of invoices for the tenant",
     *     tags={"Billing"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function invoices(Request $request)
    {
        $tenant = $request->user()->currentTenant;

        $invoices = Invoice::forTenant($tenant->id)
            ->with('lineItems')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $invoices
        ]);
    }

    /**
     * Get single invoice with line items
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/billing/invoices/{invoice}",
     *     summary="Get invoice details",
     *     description="Returns detailed invoice information with line items",
     *     tags={"Billing"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="invoice",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Invoice not found")
     * )
     */
    public function invoice(Request $request, Invoice $invoice)
    {
        $tenant = $request->user()->currentTenant;

        // Verify invoice belongs to tenant
        if ($invoice->tenant_id !== $tenant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        $invoice->load(['lineItems.agent', 'subscription.plan']);

        return response()->json([
            'success' => true,
            'data' => $invoice
        ]);
    }

    /**
     * Get upcoming invoice preview
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/billing/upcoming",
     *     summary="Preview upcoming invoice",
     *     description="Returns estimated charges for the next billing period",
     *     tags={"Billing"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="base_subscription", type="number"),
     *                 @OA\Property(property="agent_addons", type="number"),
     *                 @OA\Property(property="usage_overage", type="number"),
     *                 @OA\Property(property="total", type="number")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="No active subscription")
     * )
     */
    public function upcomingInvoice(Request $request)
    {
        $tenant = $request->user()->currentTenant;
        $subscription = $tenant->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription'
            ], 400);
        }

        $plan = $subscription->plan;

        // Base plan cost
        $baseAmount = $plan->getMonthlyEquivalentPrice();

        // Agent add-ons
        $addonAmount = $tenant->getMonthlyAgentCost();

        // Estimated overage
        $overageExecutions = $subscription->getOverageExecutions();
        $overageAmount = $overageExecutions > 0 && $plan->overage_price_per_execution
            ? $overageExecutions * $plan->overage_price_per_execution
            : 0;

        $total = $baseAmount + $addonAmount + $overageAmount;

        return response()->json([
            'success' => true,
            'data' => [
                'base_subscription' => $baseAmount,
                'agent_addons' => $addonAmount,
                'usage_overage' => $overageAmount,
                'overage_executions' => $overageExecutions,
                'total' => $total,
                'next_billing_date' => $subscription->next_billing_date,
            ]
        ]);
    }

    /**
     * Update payment method
     * 
     * @OA\Post(
     *     path="/api/v1/pricing/billing/payment-method",
     *     summary="Update payment method",
     *     description="Updates the default payment method for the tenant",
     *     tags={"Billing"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payment_method"},
     *             @OA\Property(property="payment_method", type="string", enum={"paymob", "bank_transfer"}),
     *             @OA\Property(property="payment_details", type="object")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payment method updated")
     * )
     */
    public function updatePaymentMethod(Request $request)
    {
        $validated = $request->validate([
            'payment_method' => 'required|string|in:paymob,bank_transfer',
            'payment_details' => 'nullable|array',
        ]);

        $tenant = $request->user()->currentTenant;

        // TODO: Store payment method preference

        return response()->json([
            'success' => true,
            'message' => 'Payment method updated'
        ]);
    }

    /**
     * Paymob webhook handler
     * 
     * @OA\Post(
     *     path="/api/v1/webhooks/paymob",
     *     summary="Paymob payment webhook",
     *     description="Handles payment notifications from Paymob (no authentication required - verified by signature)",
     *     tags={"Billing"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="string"),
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="merchant_order_id", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Webhook processed"),
     *     @OA\Response(response=400, description="Invalid webhook data"),
     *     @OA\Response(response=404, description="Invoice not found")
     * )
     */
    public function paymobWebhook(Request $request)
    {
        $data = $request->all();

        Log::info('Paymob webhook received', ['data' => $data]);

        // Verify HMAC signature using PaymobService
        $paymobService = app(\App\Services\PaymobService::class);

        if (!$paymobService->verifyHmac($data)) {
            Log::error('Paymob HMAC verification failed', ['data' => $data]);
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        // Process callback
        $result = $paymobService->processCallback($data);

        if (!$result['success']) {
            Log::warning('Paymob payment failed', ['data' => $data]);
            return response()->json(['message' => 'Payment verification failed'], 400);
        }

        // Extract invoice number from order ID
        $invoiceId = $result['order_id'];

        // Find invoice by ID or invoice_number
        $invoice = Invoice::where('id', $invoiceId)
            ->orWhere('invoice_number', $invoiceId)
            ->first();

        if (!$invoice) {
            Log::error('Invoice not found for Paymob order', ['order_id' => $invoiceId]);
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        // Update invoice based on payment status
        if ($result['success']) {
            $invoice->markAsPaid($result['transaction_id'], 'paymob');

            Log::info('Payment processed successfully', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'transaction_id' => $result['transaction_id'],
                'amount' => $result['amount_cents'] / 100,
            ]);

            // If invoice has a subscription, activate it
            if ($invoice->subscription_id) {
                $subscription = $invoice->subscription;
                if ($subscription && $subscription->status !== 'active') {
                    $subscription->update([
                        'status' => 'active',
                        'current_period_start' => now(),
                        'current_period_end' => $subscription->plan->billingCycle
                            ? now()->addMonths($subscription->plan->billingCycle->months)
                            : now()->addMonth(),
                    ]);

                    Log::info('Subscription activated', [
                        'subscription_id' => $subscription->id,
                        'tenant_id' => $subscription->tenant_id,
                    ]);
                }
            }
        } else {
            $invoice->markAsFailed('Payment failed via Paymob webhook');

            Log::warning('Payment marked as failed', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook processed successfully'
        ]);
    }

    /**
     * Download invoice PDF
     * 
     * @OA\Get(
     *     path="/api/v1/pricing/billing/invoices/{invoice}/download",
     *     summary="Download invoice PDF",
     *     description="Downloads the invoice as a PDF file (not yet implemented)",
     *     tags={"Billing"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="invoice",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=404, description="Invoice not found"),
     *     @OA\Response(response=501, description="Not implemented")
     * )
     */
    public function downloadInvoice(Request $request, Invoice $invoice)
    {
        $tenant = $request->user()->currentTenant;

        if ($invoice->tenant_id !== $tenant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        // TODO: Generate PDF

        return response()->json([
            'success' => false,
            'message' => 'PDF generation not implemented yet'
        ], 501);
    }

    /**
     * Retry failed payment
     * 
     * @OA\Post(
     *     path="/api/v1/pricing/billing/invoices/{invoice}/retry",
     *     summary="Retry failed payment",
     *     description="Retries payment for a failed invoice (not yet implemented)",
     *     tags={"Billing"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="invoice",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Payment retry initiated"),
     *     @OA\Response(response=400, description="Invoice is not in failed status"),
     *     @OA\Response(response=404, description="Invoice not found"),
     *     @OA\Response(response=501, description="Not implemented")
     * )
     */
    public function retryPayment(Request $request, Invoice $invoice)
    {
        $tenant = $request->user()->currentTenant;

        if ($invoice->tenant_id !== $tenant->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        if ($invoice->status !== 'failed') {
            return response()->json([
                'success' => false,
                'message' => 'Invoice is not in failed status'
            ], 400);
        }

        // TODO: Initiate payment with Paymob

        return response()->json([
            'success' => false,
            'message' => 'Payment retry not implemented yet'
        ], 501);
    }
}
