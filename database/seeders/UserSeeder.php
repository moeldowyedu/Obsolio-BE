<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first tenant
        $tenant = \App\Models\Tenant::first();

        if (!$tenant) {
            $this->command->warn('No tenant found. Please seed tenants first.');
            return;
        }

        // Create Super Admin
        $superAdmin = \App\Models\User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Super Admin',
            'email' => 'superadmin@obsolio.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'last_login_at' => now(),
        ]);
        $superAdmin->assignRole('Super Admin');

        // Create Admin
        $admin = \App\Models\User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@obsolio.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'last_login_at' => now(),
        ]);
        $admin->assignRole('Admin');

        // Create Organization Manager
        $orgManager = \App\Models\User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Sarah Johnson',
            'email' => 'sarah.johnson@obsolio.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'last_login_at' => now(),
        ]);
        $orgManager->assignRole('Organization Manager');

        // Create Project Manager
        $projectManager = \App\Models\User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Michael Chen',
            'email' => 'michael.chen@obsolio.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'last_login_at' => now(),
        ]);
        $projectManager->assignRole('Project Manager');

        // Create Developer 1
        $developer1 = \App\Models\User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Emily Rodriguez',
            'email' => 'emily.rodriguez@obsolio.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'last_login_at' => now(),
        ]);
        $developer1->assignRole('Developer');

        // Create Developer 2
        $developer2 = \App\Models\User::create([
            'tenant_id' => $tenant->id,
            'name' => 'David Kim',
            'email' => 'david.kim@obsolio.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'last_login_at' => now(),
        ]);
        $developer2->assignRole('Developer');

        // Create Team Lead
        $teamLead = \App\Models\User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Lisa Anderson',
            'email' => 'lisa.anderson@obsolio.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'last_login_at' => now(),
        ]);
        $teamLead->assignRole('Team Lead');

        // Create Regular Users
        $user1 = \App\Models\User::create([
            'tenant_id' => $tenant->id,
            'name' => 'James Wilson',
            'email' => 'james.wilson@obsolio.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'last_login_at' => now(),
        ]);
        $user1->assignRole('User');

        $user2 = \App\Models\User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Maria Garcia',
            'email' => 'maria.garcia@obsolio.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'last_login_at' => now(),
        ]);
        $user2->assignRole('User');

        $user3 = \App\Models\User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Robert Taylor',
            'email' => 'robert.taylor@obsolio.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'last_login_at' => now(),
        ]);
        $user3->assignRole('User');

        // Create Auditor
        $auditor = \App\Models\User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Patricia Brown',
            'email' => 'patricia.brown@obsolio.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'last_login_at' => now(),
        ]);
        $auditor->assignRole('Auditor');

        $this->command->info('Users with roles seeded successfully!');
        $this->command->info('Default password for all users: password');
    }
}
