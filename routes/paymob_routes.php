<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\PaymentController;

// Paymob Payment Routes (Tenant Domain)
Route::middleware(['jwt.auth', 'tenancy.domain'])->prefix('v1/payments')->group(function () {
    // Create payment for subscription
    Route::post('/subscription', [PaymentController::class, 'createSubscriptionPayment']);

    // Payment response (redirect after payment)
    Route::get('/response', [PaymentController::class, 'paymentResponse']);

    // Refund payment
    Route::post('/refund/{invoice_id}', [PaymentController::class, 'refundPayment']);
});

// Paymob Webhook (Public - No Auth)
Route::post('/v1/webhooks/paymob/callback', [PaymentController::class, 'paymobCallback'])
    ->name('paymob.callback');
