<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymobService;
use App\Models\BillingInvoice;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            'billing_cycle' => 'required|in:monthly,annual',
        ]);

        try {
            $tenant = Tenant::findOrFail(tenancy()->tenant->id);
            $plan = \App\Models\SubscriptionPlan::findOrFail($request->plan_id);

            // Calculate amount based on billing cycle
            $amount = $request->billing_cycle === 'monthly'
                ? $plan->price_monthly
                : $plan->price_annual;

            // Convert USD to EGP (you should use real exchange rate API)
            $exchangeRate = 30.5; // Example rate
            $amountEGP = $amount * $exchangeRate;

            // Create invoice
            $invoice = BillingInvoice::create([
                'tenant_id' => $tenant->id,
                'invoice_number' => 'INV-' . time(),
                'subtotal' => $amount,
                'tax' => 0,
                'total' => $amount,
                'currency' => 'USD',
                'status' => 'pending',
                'line_items' => [
                    [
                        'description' => "{$plan->name} Subscription ({$request->billing_cycle})",
                        'quantity' => 1,
                        'unit_price' => $amount,
                        'amount' => $amount,
                    ]
                ],
            ]);

            // Prepare billing data
            $billingData = [
                'first_name' => $tenant->name,
                'last_name' => '',
                'email' => $request->user()->email,
                'phone_number' => $request->phone ?? '+201000000000',
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

            // Prepare items
            $items = [
                [
                    'name' => $plan->name,
                    'amount_cents' => (int) ($amountEGP * 100),
                    'description' => "Subscription - {$request->billing_cycle}",
                    'quantity' => 1,
                ]
            ];

            // Create Paymob payment
            $payment = $this->paymobService->createPayment(
                $invoice->id,
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

            // Update invoice with payment key
            $invoice->update([
                'paymob_order_id' => $payment['order_id'],
                'paymob_payment_key' => $payment['payment_key'],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice_id' => $invoice->id,
                    'iframe_url' => $payment['iframe_url'],
                    'amount' => $amount,
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
    public function paymobCallback(Request $request): JsonResponse
    {
        try {
            $data = $request->all();

            // Process callback
            $result = $this->paymobService->processCallback($data);

            if (!$result['success']) {
                Log::warning('Paymob payment failed', ['data' => $data]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed',
                ]);
            }

            // Find invoice
            $invoice = BillingInvoice::where('paymob_order_id', $result['order_id'])->first();

            if (!$invoice) {
                Log::error('Invoice not found for Paymob order', ['order_id' => $result['order_id']]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found',
                ]);
            }

            // Update invoice status
            DB::transaction(function () use ($invoice, $result) {
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'paymob_transaction_id' => $result['transaction_id'],
                ]);

                // Activate or update subscription
                $subscription = Subscription::where('tenant_id', $invoice->tenant_id)
                    ->latest()
                    ->first();

                if ($subscription) {
                    $subscription->update([
                        'status' => 'active',
                        'starts_at' => now(),
                        'current_period_start' => now(),
                        'current_period_end' => $subscription->billing_cycle === 'monthly'
                            ? now()->addMonth()
                            : now()->addYear(),
                    ]);
                }
            });

            Log::info('Payment processed successfully', [
                'invoice_id' => $invoice->id,
                'transaction_id' => $result['transaction_id'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Paymob callback error', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Callback processing failed',
            ], 500);
        }
    }

    /**
     * Payment response (after user completes payment)
     */
    public function paymentResponse(Request $request): JsonResponse
    {
        $success = $request->query('success') === 'true';
        $invoiceId = $request->query('invoice_id');

        if ($success && $invoiceId) {
            $invoice = BillingInvoice::find($invoiceId);

            if ($invoice && $invoice->status === 'paid') {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment completed successfully',
                    'data' => [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'amount' => $invoice->total,
                        'currency' => $invoice->currency,
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
            $invoice = BillingInvoice::findOrFail($invoiceId);

            if ($invoice->status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only paid invoices can be refunded',
                ], 400);
            }

            if (!$invoice->paymob_transaction_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No transaction ID found',
                ], 400);
            }

            // Process refund
            $amountCents = (int) ($invoice->total * 100);
            $result = $this->paymobService->refund(
                $invoice->paymob_transaction_id,
                $amountCents
            );

            if ($result['success']) {
                $invoice->update([
                    'status' => 'refunded',
                    'notes' => $request->reason ?? 'Refunded',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Refund processed successfully',
                    'data' => [
                        'refund_id' => $result['refund_id'],
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
