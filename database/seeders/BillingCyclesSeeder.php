<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BillingCyclesSeeder extends Seeder
{
    public function run()
    {
        DB::table('billing_cycles')->insert([
            [
                'code' => 'monthly',
                'name' => 'Monthly',
                'months' => 1,
                'discount_percentage' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'semi_annual',
                'name' => 'Semi-Annual',
                'months' => 6,
                'discount_percentage' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'annual',
                'name' => 'Annual',
                'months' => 12,
                'discount_percentage' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->command->info('✅ Billing cycles seeded successfully!');
    }
}
