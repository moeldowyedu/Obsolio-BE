<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FIXES:
     * 1. Add missing foreign key for execution_id in hitl_approvals
     * 2. Add timestamps to team_members pivot table
     * 3. Add missing foreign keys for managers/leads
     * 4. Add missing indexes on foreign keys
     * 5. Add tenant_id index where missing
     */
    public function up(): void
    {
        // Fix 1: Add foreign key for execution_id in hitl_approvals
        if (Schema::hasTable('hitl_approvals') && Schema::hasColumn('hitl_approvals', 'execution_id')) {
            Schema::table('hitl_approvals', function (Blueprint $table) {
                if (!$this->foreignKeyExists('hitl_approvals', 'hitl_approvals_execution_id_foreign')) {
                    $table->foreign('execution_id')
                        ->references('id')
                        ->on('agent_executions')
                        ->cascadeOnDelete();
                }

                // Add index if missing
                if (!$this->indexExists('hitl_approvals', 'hitl_approvals_execution_id_index')) {
                    $table->index('execution_id');
                }
            });
        }

        // Fix 2: Add timestamps to team_members pivot table
        if (Schema::hasTable('team_members')) {
            Schema::table('team_members', function (Blueprint $table) {
                if (!Schema::hasColumn('team_members', 'created_at')) {
                    $table->timestamp('created_at')->nullable()->after('joined_at');
                }
                if (!Schema::hasColumn('team_members', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                }
            });
        }

        // Fix 3: Add foreign keys for manager/lead fields
        $managerFields = [
            'branches' => [
                ['column' => 'branch_manager_id', 'foreign_key' => 'branches_branch_manager_id_foreign'],
            ],
            'departments' => [
                ['column' => 'department_head_id', 'foreign_key' => 'departments_department_head_id_foreign'],
            ],
            'projects' => [
                ['column' => 'project_manager_id', 'foreign_key' => 'projects_project_manager_id_foreign'],
            ],
            'teams' => [
                ['column' => 'team_lead_id', 'foreign_key' => 'teams_team_lead_id_foreign'],
            ],
        ];

        foreach ($managerFields as $table => $fields) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table, $fields) {
                    foreach ($fields as $field) {
                        if (Schema::hasColumn($table, $field['column'])) {
                            if (!$this->foreignKeyExists($table, $field['foreign_key'])) {
                                $blueprint->foreign($field['column'])
                                    ->references('id')
                                    ->on('users')
                                    ->nullOnDelete();
                            }
                        }
                    }
                });
            }
        }

        // Fix 4: Add missing indexes on foreign keys for better query performance
        $indexesToAdd = [
            'agents' => ['created_by_user_id', 'marketplace_listing_id'],
            'workflows' => ['created_by_user_id', 'organization_id', 'department_id', 'project_id'],
            'workflow_executions' => ['workflow_id', 'triggered_by_user_id', 'tenant_id', 'status'],
            'webhooks' => ['tenant_id', 'user_id', 'is_active'],
            'api_keys' => ['tenant_id', 'user_id', 'is_active'],
            'organizations' => ['tenant_id'],
            'branches' => ['tenant_id', 'organization_id', 'branch_manager_id'],
            'departments' => ['tenant_id', 'organization_id', 'department_head_id'],
            'projects' => ['tenant_id', 'organization_id', 'department_id', 'project_manager_id'],
            'teams' => ['tenant_id', 'organization_id', 'team_lead_id'],
        ];

        foreach ($indexesToAdd as $table => $columns) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                    foreach ($columns as $column) {
                        if (Schema::hasColumn($table, $column)) {
                            $indexName = "{$table}_{$column}_index";
                            if (!$this->indexExists($table, $indexName)) {
                                $blueprint->index($column);
                            }
                        }
                    }
                });
            }
        }

        // Fix 5: Add composite indexes for common queries
        if (Schema::hasTable('agent_executions')) {
            Schema::table('agent_executions', function (Blueprint $table) {
                if (!$this->indexExists('agent_executions', 'agent_executions_tenant_agent_status_index')) {
                    $table->index(['tenant_id', 'agent_id', 'status'], 'agent_executions_tenant_agent_status_index');
                }
                if (!$this->indexExists('agent_executions', 'agent_executions_job_flow_status_index')) {
                    $table->index(['job_flow_id', 'status'], 'agent_executions_job_flow_status_index');
                }
            });
        }

        if (Schema::hasTable('hitl_approvals')) {
            Schema::table('hitl_approvals', function (Blueprint $table) {
                if (!$this->indexExists('hitl_approvals', 'hitl_approvals_tenant_status_assigned_index')) {
                    $table->index(['tenant_id', 'status', 'assigned_to_user_id'], 'hitl_approvals_tenant_status_assigned_index');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys
        if (Schema::hasTable('hitl_approvals')) {
            Schema::table('hitl_approvals', function (Blueprint $table) {
                $table->dropForeign(['execution_id']);
                $table->dropIndex(['execution_id']);
            });
        }

        // Drop timestamps from team_members
        if (Schema::hasTable('team_members')) {
            Schema::table('team_members', function (Blueprint $table) {
                $table->dropColumn(['created_at', 'updated_at']);
            });
        }

        // Drop manager/lead foreign keys
        $tables = ['branches', 'departments', 'projects', 'teams'];
        $columns = ['branch_manager_id', 'department_head_id', 'project_manager_id', 'team_lead_id'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                    foreach ($columns as $column) {
                        if (Schema::hasColumn($table, $column)) {
                            try {
                                $blueprint->dropForeign([$column]);
                            } catch (\Exception $e) {
                                // Foreign key might not exist
                            }
                        }
                    }
                });
            }
        }

        // Drop indexes (Laravel will auto-drop most indexes when needed)
    }

    /**
     * Check if foreign key exists
     */
    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $conn = Schema::getConnection();
        $schemaManager = $conn->getDoctrineSchemaManager();

        try {
            $foreignKeys = $schemaManager->listTableForeignKeys($table);
            foreach ($foreignKeys as $fk) {
                if ($fk->getName() === $foreignKey) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            // Table might not exist
        }

        return false;
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $conn = Schema::getConnection();
        $schemaManager = $conn->getDoctrineSchemaManager();

        try {
            $indexes = $schemaManager->listTableIndexes($table);
            return isset($indexes[$indexName]);
        } catch (\Exception $e) {
            // Table might not exist
        }

        return false;
    }
};
