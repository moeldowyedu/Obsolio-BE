<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permission groups with CRUD operations
        $permissionGroups = [
            'users' => ['view', 'create', 'update', 'delete', 'assign-roles'],
            'roles' => ['view', 'create', 'update', 'delete', 'assign-permissions'],
            'permissions' => ['view', 'create', 'update', 'delete'],
            'organizations' => ['view', 'create', 'update', 'delete', 'manage-settings'],
            'branches' => ['view', 'create', 'update', 'delete'],
            'departments' => ['view', 'create', 'update', 'delete', 'assign-head'],
            'teams' => ['view', 'create', 'update', 'delete', 'manage-members'],
            'projects' => ['view', 'create', 'update', 'delete', 'archive'],
            'agents' => ['view', 'create', 'update', 'delete', 'execute', 'configure'],
            'workflows' => ['view', 'create', 'update', 'delete', 'execute', 'publish'],
            'job-flows' => ['view', 'create', 'update', 'delete', 'execute', 'monitor'],
            'tasks' => ['view', 'create', 'update', 'delete', 'assign'],
            'hitl-approvals' => ['view', 'create', 'approve', 'reject', 'assign'],
            'integrations' => ['view', 'create', 'update', 'delete', 'configure'],
            'api-keys' => ['view', 'create', 'update', 'delete', 'regenerate'],
            'webhooks' => ['view', 'create', 'update', 'delete', 'test'],
            'connected-apps' => ['view', 'create', 'update', 'delete', 'authorize', 'revoke'],
            'activities' => ['view', 'export', 'audit'],
            'sessions' => ['view', 'terminate'],
            'settings' => ['view', 'update'],
            'logs' => ['view', 'export', 'delete'],
            'reports' => ['view', 'create', 'export'],
            'analytics' => ['view', 'export'],
        ];

        // Create all permissions
        foreach ($permissionGroups as $group => $actions) {
            foreach ($actions as $action) {
                \Spatie\Permission\Models\Permission::create([
                    'name' => "{$action}-{$group}",
                    'guard_name' => 'web',
                ]);
            }
        }

        $this->command->info('Permissions seeded successfully!');
    }
}
