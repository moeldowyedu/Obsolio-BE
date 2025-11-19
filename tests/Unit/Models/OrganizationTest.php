<?php

namespace Tests\Unit\Models;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('tenancy:install');

        $this->tenant = Tenant::create(['id' => 'test', 'name' => 'Test Tenant']);
        $this->tenant->domains()->create(['domain' => 'test.localhost']);

        tenancy()->initialize($this->tenant);
    }

    public function test_organization_has_branches_relationship(): void
    {
        $organization = Organization::factory()->create(['tenant_id' => $this->tenant->id]);
        Branch::factory()->count(3)->create([
            'organization_id' => $organization->id,
        ]);

        $this->assertCount(3, $organization->branches);
    }

    public function test_organization_has_departments_relationship(): void
    {
        $organization = Organization::factory()->create(['tenant_id' => $this->tenant->id]);
        Department::factory()->count(2)->create([
            'organization_id' => $organization->id,
        ]);

        $this->assertCount(2, $organization->departments);
    }

    public function test_organization_has_users_relationship(): void
    {
        $organization = Organization::factory()->create(['tenant_id' => $this->tenant->id]);
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $organization->users()->attach($user->id, [
            'assignment_type' => 'permanent',
            'role' => 'employee',
        ]);

        $this->assertCount(1, $organization->users);
    }

    public function test_organization_settings_are_cast_to_array(): void
    {
        $organization = Organization::factory()->create([
            'tenant_id' => $this->tenant->id,
            'settings' => ['theme' => 'dark', 'notifications' => true],
        ]);

        $this->assertIsArray($organization->settings);
        $this->assertEquals('dark', $organization->settings['theme']);
    }
}
