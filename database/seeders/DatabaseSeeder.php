<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Starting database seeding...');

        // Seed in specific order to maintain referential integrity
        $this->call([
            PermissionSeeder::class,  // 1. Create all permissions first
            RoleSeeder::class,         // 2. Create roles and assign permissions
            BranchSeeder::class,       // 3. Create branches (requires tenant & organization)
            DepartmentSeeder::class,   // 4. Create departments (requires branches)
            UserSeeder::class,         // 5. Create users and assign roles
        ]);

        $this->command->info('Database seeding completed successfully!');
        $this->command->info('');
        $this->command->info('=== Demo Credentials ===');
        $this->command->info('Super Admin: superadmin@aasim.com / password');
        $this->command->info('Admin: admin@aasim.com / password');
        $this->command->info('Org Manager: sarah.johnson@aasim.com / password');
        $this->command->info('Project Manager: michael.chen@aasim.com / password');
        $this->command->info('Developer: emily.rodriguez@aasim.com / password');
        $this->command->info('Team Lead: lisa.anderson@aasim.com / password');
        $this->command->info('User: james.wilson@aasim.com / password');
        $this->command->info('Auditor: patricia.brown@aasim.com / password');
    }
}
