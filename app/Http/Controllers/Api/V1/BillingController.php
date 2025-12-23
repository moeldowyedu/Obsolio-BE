<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    /**
     * Get tenant invoices.
     */
    public function invoices(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $invoices = BillingInvoice::where('tenant_id', $tenant->id)
            ->with('subscription.plan')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    /**
     * Get specific invoice.
     */
    public function showInvoice(string $id): JsonResponse
    {
        $invoice = BillingInvoice::with('subscription.plan')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    /**
     * Get payment methods.
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $methods = PaymentMethod::where('tenant_id', $tenant->id)
            ->orderBy('is_default', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $methods,
        ]);
    }

    /**
     * Add payment method.
     */
    public function addPaymentMethod(Request $request): JsonResponse
    {
        $request->validate([
            'stripe_payment_method_id' => 'required|string',
            'type' => 'required|in:card,bank_account,paypal',
            'is_default' => 'boolean',
        ]);

        $tenant = $request->user()->tenant;

        try {
            $paymentMethod = PaymentMethod::create([
                'tenant_id' => $tenant->id,
                'type' => $request->type,
                'stripe_payment_method_id' => $request->stripe_payment_method_id,
                'is_default' => $request->is_default ?? false,
            ]);

            if ($request->is_default) {
                $paymentMethod->setAsDefault();
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment method added successfully',
                'data' => $paymentMethod,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add payment method',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set default payment method.
     */
    public function setDefaultPaymentMethod(string $id): JsonResponse
    {
        $paymentMethod = PaymentMethod::findOrFail($id);
        $paymentMethod->setAsDefault();

        return response()->json([
            'success' => true,
            'message' => 'Default payment method updated',
        ]);
    }

    /**
     * Delete payment method.
     */
    public function deletePaymentMethod(string $id): JsonResponse
    {
        $paymentMethod = PaymentMethod::findOrFail($id);

        if ($paymentMethod->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete default payment method',
            ], 400);
        }

        $paymentMethod->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment method deleted successfully',
        ]);
    }
}