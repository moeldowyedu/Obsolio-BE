<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ensure column exists and is of correct type
        $result = DB::select("
            SELECT data_type 
            FROM information_schema.columns 
            WHERE table_name = 'tenants' 
            AND column_name = 'plan_id'
        ");

        $currentType = $result[0]->data_type ?? null;

        if (!$currentType) {
            // Column doesn't exist, create it
            DB::statement('ALTER TABLE tenants ADD COLUMN plan_id UUID NULL');
        } elseif ($currentType === 'character varying' || $currentType === 'varchar') {
            // Convert VARCHAR to UUID (drop and recreate to avoid casting issues if empty)
            DB::statement('ALTER TABLE tenants DROP COLUMN plan_id');
            DB::statement('ALTER TABLE tenants ADD COLUMN plan_id UUID NULL');
        }

        // 2. Add foreign key constraint if it doesn't exist
        $constraintExists = DB::select("
            SELECT constraint_name 
            FROM information_schema.table_constraints 
            WHERE table_name = 'tenants' 
            AND constraint_name = 'tenants_plan_id_foreign'
        ");

        if (empty($constraintExists)) {
            DB::statement('
                ALTER TABLE tenants 
                ADD CONSTRAINT tenants_plan_id_foreign 
                FOREIGN KEY (plan_id) 
                REFERENCES subscription_plans(id) 
                ON DELETE SET NULL
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_plan_id_foreign');
        DB::statement('ALTER TABLE tenants DROP COLUMN IF EXISTS plan_id');
        DB::statement('ALTER TABLE tenants ADD COLUMN plan_id VARCHAR(255)');
    }
};