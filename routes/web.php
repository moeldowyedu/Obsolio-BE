<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\Api\V1\AuthController;

// Landing page
Route::get('/', function () {
    return view('welcome');
});

// API Documentation JSON endpoint
Route::get('/api-docs-json', function () {
    $path = storage_path('api-docs/api-docs.json');
    if (!file_exists($path)) {
        abort(404, 'API Docs not found');
    }
    return response()->file($path, [
        'Content-Type' => 'application/json',
    ]);
});

// Web test endpoint
Route::get('/api/test-web', function () {
    return 'Web OK';
});

// ============================================
// EMAIL VERIFICATION ROUTES
// ============================================

// Email verification notice (if user tries to access without verifying)
Route::get('/email/verify', function () {
    return response()->json([
        'success' => false,
        'message' => 'Email verification required',
        'redirect_to' => config('app.frontend_url') . '/verify-email-sent'
    ]);
})->middleware('auth:api')->name('verification.notice');

// Email verification handler (THIS IS THE CRITICAL ONE!)
// Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
//     ->middleware(['signed', 'throttle:6,1'])
//     ->name('verification.verify');

// Resend verification email
Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
    ->middleware(['auth:api', 'throttle:6,1'])
    ->name('verification.send');