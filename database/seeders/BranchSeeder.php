<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first tenant and organization for demo purposes
        $tenant = \App\Models\Tenant::first();
        $organization = \App\Models\Organization::first();

        if (!$tenant || !$organization) {
            $this->command->warn('No tenant or organization found. Please seed tenants and organizations first.');
            return;
        }

        // Define branch data
        $branches = [
            [
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'name' => 'Headquarters',
                'location' => 'New York, NY, USA',
                'branch_code' => 'HQ-NYC-001',
                'branch_manager_id' => null, // Will be assigned later when users are created
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'name' => 'West Coast Office',
                'location' => 'San Francisco, CA, USA',
                'branch_code' => 'WC-SFO-001',
                'branch_manager_id' => null,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'name' => 'European Headquarters',
                'location' => 'London, UK',
                'branch_code' => 'EU-LON-001',
                'branch_manager_id' => null,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'name' => 'Asia Pacific Office',
                'location' => 'Singapore',
                'branch_code' => 'AP-SIN-001',
                'branch_manager_id' => null,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'name' => 'Middle East Office',
                'location' => 'Dubai, UAE',
                'branch_code' => 'ME-DXB-001',
                'branch_manager_id' => null,
                'status' => 'active',
            ],
        ];

        // Create branches
        foreach ($branches as $branchData) {
            \App\Models\Branch::create($branchData);
        }

        $this->command->info('Branches seeded successfully!');
    }
}
