<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SubdomainRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $tenantUser;
    protected $tenant;
    protected $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed initial data if needed, or just create factories
        // Check if factories exist, otherwise manually create

        // Create Admin
        $this->adminUser = User::factory()->create([
            'email' => 'admin@obsolio.com',
            'is_system_admin' => true,
            'role' => 'system_admin'
        ]);

        // Create Tenant
        $this->tenant = Tenant::create([
            'id' => 'iti',
            'name' => 'ITI',
            'type' => 'organization'
        ]);
        $this->tenant->domains()->create(['domain' => 'iti.localhost']);

        // Create Tenant User
        $this->tenantUser = User::factory()->create([
            'email' => 'user@iti.com',
            'tenant_id' => 'iti',
        ]);
        TenantMembership::create([
            'tenant_id' => 'iti',
            'user_id' => $this->tenantUser->id,
            'role' => 'member'
        ]);

        // Other Tenant
        $this->otherTenant = Tenant::create([
            'id' => 'other',
            'name' => 'Other',
            'type' => 'organization'
        ]);
        $this->otherTenant->domains()->create(['domain' => 'other.localhost']);
    }

    #[Test]
    public function admin_can_login_on_admin_domain()
    {
        $response = $this->postJson('http://console.localhost/api/v1/auth/login', [
            'email' => 'admin@obsolio.com',
            'password' => 'password', // Assuming factory default password
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Login successful', $response->json('message'));
    }

    #[Test]
    public function admin_cannot_login_on_tenant_domain_as_admin()
    {
        // Actually, logic allows login IF they are member, but they are not.
        // And check.subdomain:tenant middleware doesn't block login endpoint generally, 
        // but AuthController login logic blocks if not member.

        $response = $this->postJson('http://iti.localhost/api/v1/auth/login', [
            'email' => 'admin@obsolio.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('You do not have access to this workspace', $response->json('message'));
    }

    #[Test]
    public function tenant_user_can_login_on_their_tenant_domain()
    {
        $response = $this->postJson('http://iti.localhost/api/v1/auth/login', [
            'email' => 'user@iti.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function tenant_user_cannot_login_on_other_tenant_domain()
    {
        $response = $this->postJson('http://other.localhost/api/v1/auth/login', [
            'email' => 'user@iti.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function system_admin_can_impersonate_tenant()
    {
        // 1. Login as Admin
        $loginResponse = $this->postJson('http://console.localhost/api/v1/auth/login', [
            'email' => 'admin@obsolio.com',
            'password' => 'password',
        ]);
        $token = $loginResponse->json('data.token');

        // 2. Impersonate
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('http://console.localhost/api/v1/admin/impersonate/iti');

        $response->assertStatus(200);
        $this->assertArrayHasKey('token', $response->json('data'));

        $impersonationToken = $response->json('data.token');

        // 3. Verify access with impersonation token on Tenant Domain
        // Currently we don't have a simple 'me' check or similar that requires tenant access?
        // /api/v1/auth/me is available on tenant domain

        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $impersonationToken)
            ->getJson('http://iti.localhost/api/v1/auth/me');

        $meResponse->assertStatus(200);
        $this->assertEquals('user@iti.com', $meResponse->json('data.email')); // Should return target user
    }
}
