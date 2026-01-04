<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SystemAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if role exists, if not create it (safe fallback)
        // System-level roles must use 'console' guard (not 'web' or 'tenant')
        $roleName = 'Super Admin';
        if (!Role::where('name', $roleName)->where('guard_name', 'console')->exists()) {
            Role::create(['name' => $roleName, 'guard_name' => 'console']);
        }

        $user = User::updateOrCreate(
            ['email' => 'mofree81@gmail.com'],
            [
                'name' => 'Mohammed Salah',
                'password' => 'Akwadna9892@Aasim@2025',
                'email_verified_at' => now(),
                'is_system_admin' => true,
                'status' => 'active',
                'phone' => '+1234567890',
            ]
        );

        // Assign Super Admin role with correct guard
        $role = Role::where('name', $roleName)->where('guard_name', 'console')->first();
        if ($role && !$user->hasRole($role)) {
            $user->assignRole($role);
        }

        $this->command->info('System Admin seeded successfully: Mohammed Salah (mofree81@gmail.com)');
    }
}
