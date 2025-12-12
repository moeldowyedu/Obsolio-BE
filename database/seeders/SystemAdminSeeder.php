<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SystemAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = 'mofree81@gmail.com';

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Mohammed Salah',
                'password' => Hash::make('Akwadna9892@Aasim@2025'),
                'is_system_admin' => true,
                'status' => 'active',
                'phone' => '+1234567890',
                'country' => 'Egypt', // Assuming user's location based on name/context or default
                'email_verified_at' => now(),
                // 'tenant_id' => null, // Explicitly null for system admin
            ]
        );

        $this->command->info("System Admin user seeded: {$user->email}");
    }
}
