<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_check_if_on_trial(): void
    {
        $tenant = Tenant::create([
            'id' => 'test',
            'name' => 'Test Tenant',
            'trial_ends_at' => now()->addDays(7),
        ]);

        $this->assertTrue($tenant->isOnTrial());
    }

    public function test_tenant_trial_expiry_check(): void
    {
        $tenant = Tenant::create([
            'id' => 'test2',
            'name' => 'Test Tenant 2',
            'trial_ends_at' => now()->subDays(1),
        ]);

        $this->assertTrue($tenant->hasExpiredTrial());
        $this->assertFalse($tenant->isOnTrial());
    }

    public function test_tenant_without_trial_returns_false(): void
    {
        $tenant = Tenant::create([
            'id' => 'test3',
            'name' => 'Test Tenant 3',
            'trial_ends_at' => null,
        ]);

        $this->assertFalse($tenant->isOnTrial());
        $this->assertFalse($tenant->hasExpiredTrial());
    }
}
