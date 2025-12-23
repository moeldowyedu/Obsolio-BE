<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymobService
{
    protected $apiKey;
    protected $integrationId;
    protected $hmacSecret;
    protected $iframeId;
    protected $currency;
    protected $baseUrl = 'https://accept.paymob.com/api';

    public function __construct()
    {
        $this->apiKey = config('services.paymob.api_key');
        $this->integrationId = config('services.paymob.integration_id');
        $this->hmacSecret = config('services.paymob.hmac_secret');
        $this->iframeId = config('services.paymob.iframe_id');
        $this->currency = config('services.paymob.currency', 'EGP');
    }

    /**
     * Step 1: Authentication
     * Get authentication token
     */
    public function authenticate()
    {
        try {
            $response = Http::post("{$this->baseUrl}/auth/tokens", [
                'api_key' => $this->apiKey,
            ]);

            if ($response->successful()) {
                return $response->json('token');
            }

            Log::error('Paymob authentication failed', ['response' => $response->json()]);
            throw new \Exception('Failed to authenticate with Paymob');
        } catch (\Exception $e) {
            Log::error('Paymob authentication error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Step 2: Order Registration
     * Register an order with Paymob
     */
    public function registerOrder($authToken, $amountCents, $orderId, $items = [])
    {
        try {
            $response = Http::post("{$this->baseUrl}/ecommerce/orders", [
                'auth_token' => $authToken,
                'delivery_needed' => false,
                'amount_cents' => $amountCents, // Amount in cents (e.g., 100 EGP = 10000 cents)
                'currency' => $this->currency,
                'merchant_order_id' => $orderId,
                'items' => $items,
            ]);

            if ($response->successful()) {
                return $response->json('id');
            }

            Log::error('Paymob order registration failed', ['response' => $response->json()]);
            throw new \Exception('Failed to register order with Paymob');
        } catch (\Exception $e) {
            Log::error('Paymob order registration error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Step 3: Payment Key
     * Generate payment key for transaction
     */
    public function getPaymentKey($authToken, $orderPaymobId, $amountCents, $billingData)
    {
        try {
            $response = Http::post("{$this->baseUrl}/acceptance/payment_keys", [
                'auth_token' => $authToken,
                'amount_cents' => $amountCents,
                'expiration' => 3600, // 1 hour
                'order_id' => $orderPaymobId,
                'billing_data' => $billingData,
                'currency' => $this->currency,
                'integration_id' => $this->integrationId,
            ]);

            if ($response->successful()) {
                return $response->json('token');
            }

            Log::error('Paymob payment key generation failed', ['response' => $response->json()]);
            throw new \Exception('Failed to generate payment key');
        } catch (\Exception $e) {
            Log::error('Paymob payment key error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create complete payment process
     * Returns iframe URL for payment
     */
    public function createPayment($orderId, $amount, $billingData, $items = [])
    {
        try {
            // Convert amount to cents
            $amountCents = $amount * 100;

            // Step 1: Authenticate
            $authToken = $this->authenticate();

            // Step 2: Register Order
            $orderPaymobId = $this->registerOrder($authToken, $amountCents, $orderId, $items);

            // Step 3: Get Payment Key
            $paymentKey = $this->getPaymentKey($authToken, $orderPaymobId, $amountCents, $billingData);

            // Return iframe URL
            $iframeUrl = "https://accept.paymob.com/api/acceptance/iframes/{$this->iframeId}?payment_token={$paymentKey}";

            return [
                'success' => true,
                'iframe_url' => $iframeUrl,
                'payment_key' => $paymentKey,
                'order_id' => $orderPaymobId,
            ];
        } catch (\Exception $e) {
            Log::error('Paymob payment creation error', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify callback/webhook HMAC signature
     */
    public function verifyHmac($data)
    {
        $concatenatedString =
            $data['amount_cents'] .
            $data['created_at'] .
            $data['currency'] .
            $data['error_occured'] .
            $data['has_parent_transaction'] .
            $data['id'] .
            $data['integration_id'] .
            $data['is_3d_secure'] .
            $data['is_auth'] .
            $data['is_capture'] .
            $data['is_refunded'] .
            $data['is_standalone_payment'] .
            $data['is_voided'] .
            $data['order'] .
            $data['owner'] .
            $data['pending'] .
            $data['source_data_pan'] .
            $data['source_data_sub_type'] .
            $data['source_data_type'] .
            $data['success'];

        $hash = hash_hmac('sha512', $concatenatedString, $this->hmacSecret);

        return $hash === $data['hmac'];
    }

    /**
     * Process callback from Paymob
     */
    public function processCallback($data)
    {
        // Verify HMAC
        if (!$this->verifyHmac($data)) {
            Log::error('Paymob HMAC verification failed', ['data' => $data]);
            return [
                'success' => false,
                'error' => 'Invalid HMAC signature',
            ];
        }

        // Check if payment was successful
        $isSuccess = $data['success'] === 'true' || $data['success'] === true;

        return [
            'success' => $isSuccess,
            'transaction_id' => $data['id'],
            'order_id' => $data['order'],
            'amount_cents' => $data['amount_cents'],
            'currency' => $data['currency'],
            'payment_method' => $data['source_data_type'] ?? null,
            'raw_data' => $data,
        ];
    }

    /**
     * Refund transaction
     */
    public function refund($transactionId, $amountCents)
    {
        try {
            $authToken = $this->authenticate();

            $response = Http::post("{$this->baseUrl}/acceptance/void_refund/refund", [
                'auth_token' => $authToken,
                'transaction_id' => $transactionId,
                'amount_cents' => $amountCents,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'refund_id' => $response->json('id'),
                ];
            }

            throw new \Exception('Refund failed');
        } catch (\Exception $e) {
            Log::error('Paymob refund error', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
