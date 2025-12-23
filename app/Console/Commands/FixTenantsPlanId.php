<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixTenantsPlanId extends Command
{
    protected $signature = 'tenants:fix-plan-id';
    protected $description = 'Fix plan_id column and add foreign key to tenants table';

    public function handle()
    {
        try {
            $this->info('🔍 Checking current setup...');

            // Get current database user
            $currentUser = DB::select("SELECT current_user")[0]->current_user;
            $this->info("Current DB User: {$currentUser}");

            // Get table owner
            $owner = DB::select("
                SELECT tableowner 
                FROM pg_tables 
                WHERE tablename = 'tenants'
            ")[0]->tableowner ?? 'unknown';
            $this->info("Table Owner: {$owner}");

            // Check if we already have the foreign key
            $fkExists = DB::select("
                SELECT constraint_name 
                FROM information_schema.table_constraints 
                WHERE table_name = 'tenants' 
                AND constraint_name = 'tenants_plan_id_foreign'
            ");

            if (!empty($fkExists)) {
                $this->info('✅ Foreign key already exists!');
                $this->markMigrationAsRan();
                return 0;
            }

            // Check current column type
            $result = DB::select("
                SELECT data_type 
                FROM information_schema.columns 
                WHERE table_name = 'tenants' 
                AND column_name = 'plan_id'
            ");

            $currentType = $result[0]->data_type ?? null;
            $this->info("Current plan_id type: {$currentType}");

            if ($currentType && $currentType !== 'uuid') {
                $this->warn('⚠️  plan_id is not UUID type. Attempting conversion...');

                // Try to convert using USING clause (safer)
                try {
                    DB::statement('
                        ALTER TABLE tenants 
                        ALTER COLUMN plan_id TYPE uuid 
                        USING plan_id::uuid
                    ');
                    $this->info('✅ Converted plan_id to UUID');
                } catch (\Exception $e) {
                    $this->error('❌ Failed to convert: ' . $e->getMessage());
                    $this->warn('You need to run this SQL manually as superuser:');
                    $this->warn('ALTER TABLE tenants ALTER COLUMN plan_id TYPE uuid USING plan_id::uuid;');
                    return 1;
                }
            }

            // Add foreign key
            $this->info('📌 Adding foreign key constraint...');
            DB::statement('
                ALTER TABLE tenants 
                ADD CONSTRAINT tenants_plan_id_foreign 
                FOREIGN KEY (plan_id) 
                REFERENCES subscription_plans(id) 
                ON DELETE SET NULL
            ');

            $this->info('✅ Foreign key added successfully!');

            // Mark migration as completed
            $this->markMigrationAsRan();

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->newLine();
            $this->warn('🔧 Manual Fix Required:');
            $this->warn('Run these SQL commands as database owner:');
            $this->newLine();
            $this->line('ALTER TABLE tenants ALTER COLUMN plan_id TYPE uuid USING plan_id::uuid;');
            $this->line('ALTER TABLE tenants ADD CONSTRAINT tenants_plan_id_foreign FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL;');

            return 1;
        }
    }

    private function markMigrationAsRan()
    {
        $exists = DB::table('migrations')
            ->where('migration', '2025_12_22_221835_add_plan_id_foreign_to_tenants')
            ->exists();

        if (!$exists) {
            DB::table('migrations')->insert([
                'migration' => '2025_12_22_221835_add_plan_id_foreign_to_tenants',
                'batch' => DB::table('migrations')->max('batch') + 1,
            ]);
            $this->info('✅ Migration marked as completed!');
        }
    }
}