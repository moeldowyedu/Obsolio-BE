<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first tenant, organization, and branch
        $tenant = \App\Models\Tenant::first();
        $organization = \App\Models\Organization::first();
        $branch = \App\Models\Branch::first();

        if (!$tenant || !$organization || !$branch) {
            $this->command->warn('No tenant, organization, or branch found. Please seed them first.');
            return;
        }

        // Create parent departments first
        $engineering = \App\Models\Department::create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'parent_department_id' => null,
            'name' => 'Engineering',
            'department_head_id' => null,
            'description' => 'Engineering and technology department',
            'budget' => 500000.00,
            'status' => 'active',
        ]);

        $operations = \App\Models\Department::create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'parent_department_id' => null,
            'name' => 'Operations',
            'department_head_id' => null,
            'description' => 'Business operations department',
            'budget' => 300000.00,
            'status' => 'active',
        ]);

        $sales = \App\Models\Department::create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'parent_department_id' => null,
            'name' => 'Sales',
            'department_head_id' => null,
            'description' => 'Sales and business development',
            'budget' => 400000.00,
            'status' => 'active',
        ]);

        $hr = \App\Models\Department::create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'parent_department_id' => null,
            'name' => 'Human Resources',
            'department_head_id' => null,
            'description' => 'HR and talent management',
            'budget' => 200000.00,
            'status' => 'active',
        ]);

        // Create sub-departments (children)
        \App\Models\Department::create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'parent_department_id' => $engineering->id, // Child of Engineering
            'name' => 'Software Development',
            'department_head_id' => null,
            'description' => 'Software engineering team',
            'budget' => 250000.00,
            'status' => 'active',
        ]);

        \App\Models\Department::create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'parent_department_id' => $engineering->id, // Child of Engineering
            'name' => 'DevOps',
            'department_head_id' => null,
            'description' => 'Infrastructure and DevOps team',
            'budget' => 150000.00,
            'status' => 'active',
        ]);

        \App\Models\Department::create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'parent_department_id' => $engineering->id, // Child of Engineering
            'name' => 'QA & Testing',
            'department_head_id' => null,
            'description' => 'Quality assurance and testing',
            'budget' => 100000.00,
            'status' => 'active',
        ]);

        \App\Models\Department::create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'parent_department_id' => $sales->id, // Child of Sales
            'name' => 'Enterprise Sales',
            'department_head_id' => null,
            'description' => 'Enterprise and B2B sales',
            'budget' => 200000.00,
            'status' => 'active',
        ]);

        \App\Models\Department::create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'parent_department_id' => $sales->id, // Child of Sales
            'name' => 'Customer Success',
            'department_head_id' => null,
            'description' => 'Customer success and support',
            'budget' => 150000.00,
            'status' => 'active',
        ]);

        \App\Models\Department::create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'parent_department_id' => $operations->id, // Child of Operations
            'name' => 'Finance',
            'department_head_id' => null,
            'description' => 'Finance and accounting',
            'budget' => 100000.00,
            'status' => 'active',
        ]);

        \App\Models\Department::create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'parent_department_id' => $hr->id, // Child of HR
            'name' => 'Recruitment',
            'department_head_id' => null,
            'description' => 'Talent acquisition team',
            'budget' => 80000.00,
            'status' => 'active',
        ]);

        $this->command->info('Departments with hierarchy seeded successfully!');
    }
}
