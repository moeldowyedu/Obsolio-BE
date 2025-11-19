<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('tenancy:install');
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'tenant_name' => 'Test Company',
            'organization_name' => 'Test Org',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'tenant' => ['id', 'name'],
                    'organization' => ['id', 'name'],
                    'token',
                ]
            ]);

        $this->assertDatabaseHas('tenants', ['name' => 'Test Company']);
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_user_can_login(): void
    {
        $tenant = Tenant::create(['id' => 'test', 'name' => 'Test Tenant']);
        $tenant->domains()->create(['domain' => 'test.localhost']);

        tenancy()->initialize($tenant);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                ]
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'invalid@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    public function test_user_can_logout(): void
    {
        $tenant = Tenant::create(['id' => 'test2', 'name' => 'Test Tenant 2']);
        $tenant->domains()->create(['domain' => 'test2.localhost']);

        tenancy()->initialize($tenant);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
        $this->assertCount(0, $user->tokens);
    }

    public function test_user_can_get_profile(): void
    {
        $tenant = Tenant::create(['id' => 'test3', 'name' => 'Test Tenant 3']);
        $tenant->domains()->create(['domain' => 'test3.localhost']);

        tenancy()->initialize($tenant);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email']
            ]);
    }
}
