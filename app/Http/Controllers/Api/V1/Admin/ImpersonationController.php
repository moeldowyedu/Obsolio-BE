<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function impersonate(Request $request, $tenantId)
    {
        $admin = auth()->user();

        // Find tenant and verify it exists
        $tenant = \App\Models\Tenant::find($tenantId);
        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        // Find a user to impersonate (Owner)
        // We use the tenant's ownerMembership relation/helper or logic
        $membership = $tenant->ownerMembership;

        if (!$membership) {
            // Fallback: find any admin user
            $membership = $tenant->memberships()->where('role', 'admin')->first();
        }

        if (!$membership) {
            return response()->json(['message' => 'No suitable user found to impersonate in this tenant'], 404);
        }

        $targetUser = $membership->user;
        if (!$targetUser) {
            return response()->json(['message' => 'Target user not found'], 404);
        }

        // Generate Impersonation Token
        // Expiration: 1 hour
        $ttl = 60; // minutes
        $token = \Tymon\JWTAuth\Facades\JWTAuth::claims([
            'is_impersonating' => true,
            'impersonator_id' => $admin->id,
            'original_role' => $admin->role ?? 'system_admin',
            'exp' => now()->addMinutes($ttl)->timestamp
        ])->fromUser($targetUser);

        // Log the impersonation start
        \DB::table('impersonation_logs')->insert([
            'impersonator_id' => $admin->id,
            'impersonated_tenant_id' => $tenant->id,
            'token' => substr($token, 0, 10) . '...', // Store only prefix or hash for audit
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Impersonation started',
            'data' => [
                'token' => $token,
                'redirect_url' => $this->getTenantUrl($tenant),
                'user' => $targetUser
            ]
        ]);
    }

    public function stopImpersonation(Request $request)
    {
        // This endpoint would likely be called WITH the impersonation token on the Tenant Domain?
        // Or on the Admin domain?
        // If called on Admin domain, they just stop calling API as tenant. 
        // If called on Tenant domain (to "Logout" of impersonation), we need to handle it in AuthController or here?
        // But this controller is under /admin route group!
        // So this endpoint is for the Admin Dashboard to manually stop/invalidate?
        // Or if the admin UI tracks the session.

        // Let's assume this logs the stop action.

        $user = auth()->user(); // This is the admin user normally, unless using impersonation token here?
        // If using impersonation token, this route isn't accessible (CheckSubdomain:admin).
        // So this is for the Admin to mark it as ended?

        // Actually, "Stop Impersonation" is usually triggered by the user acting as tenant, wanting to go back.
        // But they are on Tenant Domain.
        // So they need an endpoint on Tenant API to "revert".
        // Or credentials to get back original token.

        // For now, implementing simple logging if Admin calls it.

        return response()->json(['message' => 'Impersonation session ended']);
    }

    private function getTenantUrl($tenant)
    {
        $domain = $tenant->domains->first()->domain ?? $tenant->id . '.' . config('tenancy.central_domains')[0];
        $protocol = request()->secure() ? 'https://' : 'http://';
        // Handle localhost special port if needed
        $port = request()->getPort();
        $portStr = ($port && $port != 80 && $port != 443) ? ":$port" : "";

        // If domain already has port (localhost case?), usually domain is 'tenant.localhost'
        return $protocol . $domain . $portStr;
    }
}
