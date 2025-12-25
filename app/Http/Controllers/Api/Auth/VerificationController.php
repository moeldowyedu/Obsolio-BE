<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class VerificationController extends Controller
{
    /**
     * Verify email via signed URL
     */
    public function verify(Request $request, $id, $hash)
    {
        try {
            Log::info('Email verification attempt', [
                'user_id' => $id,
                'hash' => $hash,
                'url' => $request->fullUrl()
            ]);

            // Find user
            $user = User::findOrFail($id);

            // Check if hash matches
            if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
                Log::warning('Hash mismatch', ['user_id' => $id]);

                // Redirect to frontend with error
                return redirect(config('app.frontend_url') . '/verification-error?reason=invalid_link');
            }

            // Check if already verified
            if ($user->hasVerifiedEmail()) {
                Log::info('Already verified', ['user_id' => $id]);

                // Redirect to frontend with already verified status
                return redirect(config('app.frontend_url') . '/verification-success?already_verified=true');
            }

            // Check expiration
            $expires = $request->get('expires');
            if ($expires && now()->timestamp > $expires) {
                return redirect(config('app.frontend_url') . '/verification-error?reason=expired');
            }

            // Custom signature validation (handles domain variations)
            $signature = $request->get('signature');
            if (!$signature || !$this->isValidSignature($request, $user)) {
                Log::warning('Invalid signature', ['user_id' => $id]);

                return redirect(config('app.frontend_url') . '/verification-error?reason=invalid_signature');
            }

            // Mark as verified
            $user->markEmailAsVerified();

            // Update user status to active
            $user->update(['status' => 'active']);

            // Update tenant status to active
            $tenant = $user->tenant;
            if ($tenant && $tenant->status === 'pending_verification') {
                $tenant->update(['status' => 'active']);

                // Create the domain for the tenant NOW that it's verified
                $domain = config('tenancy.central_domains')[0] ?? 'obsolio.com';
                $subdomain = $tenant->subdomain_preference ?? $tenant->id;

                // Update tenant ID to use the preferred subdomain
                if ($tenant->subdomain_preference && $tenant->id !== $tenant->subdomain_preference) {
                    $tenant->update(['id' => $tenant->subdomain_preference]);
                }

                // Create domain
                $tenant->domains()->create([
                    'domain' => "{$subdomain}.{$domain}"
                ]);

                Log::info('Tenant activated and domain created', [
                    'tenant_id' => $tenant->id,
                    'domain' => "{$subdomain}.{$domain}"
                ]);
            }

            event(new Verified($user));

            Log::info('Email verified successfully', ['user_id' => $id]);

            // Redirect to frontend with success
            $workspaceUrl = $tenant ? "https://{$tenant->id}.{$domain}" : config('app.frontend_url');
            return redirect(config('app.frontend_url') . '/verification-success?workspace=' . urlencode($workspaceUrl));

        } catch (\Exception $e) {
            Log::error('Verification error', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return redirect(config('app.frontend_url') . '/verification-error?reason=server_error');
        }
    }

    /**
     * Validate signature with domain flexibility
     */
    protected function isValidSignature(Request $request, User $user)
    {
        $signature = $request->get('signature');

        // Build URLs to check (both domains)
        $urls = [
            $this->buildUrl($request, 'https://obsolio.com'),
            $this->buildUrl($request, 'https://api.obsolio.com'),
        ];

        // Check if signature matches either URL
        foreach ($urls as $url) {
            $expected = hash_hmac('sha256', $url, config('app.key'));

            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build URL for signature checking
     */
    protected function buildUrl(Request $request, $baseUrl)
    {
        $path = $request->path();
        $query = $request->query();
        unset($query['signature']);

        $url = $baseUrl . '/' . $path;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /**
     * Resend verification email
     */
    public function resend(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.'
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        Log::info('Verification email resent', ['user_id' => $user->id]);

        return response()->json([
            'message' => 'Verification email sent!'
        ], 200);
    }
}
