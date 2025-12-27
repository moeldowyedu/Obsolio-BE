<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * This migration MUST run BEFORE the schema changes.
     * It migrates existing category data from the agents.category column
     * to the new normalized agent_categories table.
     */
    public function up(): void
    {
        // Step 1: Get all distinct categories from existing agents
        $categories = DB::table('agents')
            ->select('category')
            ->distinct()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->pluck('category');

        if ($categories->isEmpty()) {
            // No categories to migrate, skip
            return;
        }

        // Step 2: Create category records in agent_categories table
        // Note: This assumes agent_categories table exists, so this migration
        // should actually run AFTER creating agent_categories but BEFORE modifying agents

        $categoryMap = [];
        foreach ($categories as $category) {
            $id = (string) Str::uuid();
            $slug = Str::slug($category);

            // Check if category already exists
            $existing = DB::table('agent_categories')
                ->where('slug', $slug)
                ->first();

            if ($existing) {
                $categoryMap[$category] = $existing->id;
            } else {
                DB::table('agent_categories')->insert([
                    'id' => $id,
                    'name' => ucfirst($category),
                    'slug' => $slug,
                    'parent_id' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $categoryMap[$category] = $id;
            }
        }

        // Step 3: Create mappings in agent_category_map
        $agents = DB::table('agents')
            ->select('id', 'category')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->get();

        foreach ($agents as $agent) {
            if (isset($categoryMap[$agent->category])) {
                // Check if mapping already exists
                $exists = DB::table('agent_category_map')
                    ->where('agent_id', $agent->id)
                    ->where('category_id', $categoryMap[$agent->category])
                    ->exists();

                if (!$exists) {
                    DB::table('agent_category_map')->insert([
                        'agent_id' => $agent->id,
                        'category_id' => $categoryMap[$agent->category],
                    ]);
                }
            }
        }

        // Step 4: Set default runtime_type for ALL existing agents
        DB::table('agents')
            ->whereNull('runtime_type')
            ->orWhere('runtime_type', '')
            ->update(['runtime_type' => 'custom']);

        // Step 5: Set default execution_timeout_ms for agents that don't have it
        DB::table('agents')
            ->whereNull('execution_timeout_ms')
            ->orWhere('execution_timeout_ms', 0)
            ->update(['execution_timeout_ms' => 30000]);
    }

    /**
     * Reverse the migrations.
     *
     * Note: This will attempt to restore category data from agent_category_map
     */
    public function down(): void
    {
        // Restore category column data from agent_category_map
        $mappings = DB::table('agent_category_map')
            ->join('agent_categories', 'agent_category_map.category_id', '=', 'agent_categories.id')
            ->select('agent_category_map.agent_id', 'agent_categories.name')
            ->get();

        foreach ($mappings as $mapping) {
            DB::table('agents')
                ->where('id', $mapping->agent_id)
                ->update(['category' => strtolower($mapping->name)]);
        }

        // Note: We don't delete the agent_categories or agent_category_map records
        // as those are handled by their respective down() migrations
    }
};
