<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymobService;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentController extends Controller
{
    protected $paymobService;

    public function __construct(PaymobService $paymobService)
    {
        $this->paymobService = $paymobService;
    }

    /**
     * Create payment intent for subscription
     */
    public function createSubscriptionPayment(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
        ]);

        try {
            $tenant = $request->user()->currentTenant;
            $plan = SubscriptionPlan::with('billingCycle')->findOrFail($request->plan_id);

            // Get monthly equivalent price
            $amountUSD = $plan->getMonthlyEquivalentPrice();

            // Convert USD to EGP (TODO: use real exchange rate API)
            $exchangeRate = 30.5;
            $amountEGP = $amountUSD * $exchangeRate;

            // Define billing period
            $periodStart = now()->startOfMonth();
            $periodEnd = now()->endOfMonth();

            // Create invoice using new Invoice model
            $invoice = Invoice::createForTenant(
                $tenant,
                $periodStart,
                $periodEnd
            );

            // Add base plan line item
            InvoiceLineItem::createBasePlan($invoice, $plan, $amountUSD);

            // Recalculate total
            $invoice->recalculateTotal();

            // Prepare billing data
            $billingData = [
                'first_name' => $tenant->name ?? 'Customer',
                'last_name' => '',
                'email' => $request->user()->email,
                'phone_number' => $tenant->phone ?? '+201000000000',
                'apartment' => 'N/A',
                'floor' => 'N/A',
                'street' => 'N/A',
                'building' => 'N/A',
                'shipping_method' => 'N/A',
                'postal_code' => 'N/A',
                'city' => 'Cairo',
                'country' => 'EG',
                'state' => 'Cairo',
            ];

            // Prepare items for Paymob
            $items = [
                [
                    'name' => $plan->name,
                    'amount_cents' => (int) ($amountEGP * 100),
                    'description' => "Subscription - {$plan->billingCycle->name}",
                    'quantity' => 1,
                ]
            ];

            // Create Paymob payment
            $payment = $this->paymobService->createPayment(
                $invoice->invoice_number, // Use invoice_number as order ID
                $amountEGP,
                $billingData,
                $items
            );

            if (!$payment['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payment',
                    'error' => $payment['error'] ?? 'Unknown error',
                ], 500);
            }

            Log::info('Payment created', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'paymob_order_id' => $payment['order_id'],
                'amount_egp' => $amountEGP,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'iframe_url' => $payment['iframe_url'],
                    'amount_usd' => $amountUSD,
                    'amount_egp' => $amountEGP,
                    'currency' => 'EGP',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Payment creation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Paymob callback webhook
     */
    /**
     * Paymob callback webhook (DEPRECATED - use BillingController@paymobWebhook)
     * This method is kept for backward compatibility but should not be used
     */
    public function paymobCallback(Request $request): JsonResponse
    {
        Log::warning('Deprecated paymobCallback called - use BillingController@paymobWebhook instead');

        // Redirect to new webhook handler
        return app(\App\Http\Controllers\Api\BillingController::class)
            ->paymobWebhook($request);
    }

    /**
     * Payment response (after user completes payment)
     */
    public function paymentResponse(Request $request): JsonResponse
    {
        $success = $request->query('success') === 'true';
        $invoiceNumber = $request->query('invoice_number');

        if ($success && $invoiceNumber) {
            $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();

            if ($invoice && $invoice->isPaid()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment completed successfully',
                    'data' => [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'amount' => $invoice->total_amount,
                        'status' => $invoice->status,
                    ],
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment was not completed or failed',
        ]);
    }

    /**
     * Refund payment
     */
    public function refundPayment(Request $request, string $invoiceId): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $invoice = Invoice::findOrFail($invoiceId);
            $tenant = $request->user()->currentTenant;

            // Verify invoice belongs to tenant
            if ($invoice->tenant_id !== $tenant->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found',
                ], 404);
            }

            if (!$invoice->isPaid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only paid invoices can be refunded',
                ], 400);
            }

            if (!$invoice->payment_transaction_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No transaction ID found',
                ], 400);
            }

            // Process refund (convert to cents for Paymob)
            $amountCents = (int) ($invoice->total_amount * 100);
            $result = $this->paymobService->refund(
                $invoice->payment_transaction_id,
                $amountCents
            );

            if ($result['success']) {
                $invoice->refund($request->reason ?? 'Refunded via API');

                Log::info('Refund processed', [
                    'invoice_id' => $invoice->id,
                    'refund_id' => $result['refund_id'],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Refund processed successfully',
                    'data' => [
                        'refund_id' => $result['refund_id'],
                        'invoice_id' => $invoice->id,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Refund failed',
                'error' => $result['error'],
            ], 500);
        } catch (\Exception $e) {
            Log::error('Refund error', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Refund processing failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
