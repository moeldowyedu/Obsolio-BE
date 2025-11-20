<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Get all permissions
        $allPermissions = \Spatie\Permission\Models\Permission::all();

        // Super Admin - Full access to everything
        $superAdmin = \Spatie\Permission\Models\Role::create([
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ]);
        $superAdmin->givePermissionTo($allPermissions);

        // Admin - Full access except system-critical operations
        $admin = \Spatie\Permission\Models\Role::create([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);
        $adminPermissions = $allPermissions->filter(function ($permission) {
            return !str_contains($permission->name, 'delete-permissions') &&
                   !str_contains($permission->name, 'delete-roles');
        });
        $admin->givePermissionTo($adminPermissions);

        // Organization Manager - Manage organization, branches, departments
        $orgManager = \Spatie\Permission\Models\Role::create([
            'name' => 'Organization Manager',
            'guard_name' => 'web',
        ]);
        $orgManagerPermissions = \Spatie\Permission\Models\Permission::whereIn('name', [
            // Organizations
            'view-organizations', 'update-organizations', 'manage-settings-organizations',
            // Branches
            'view-branches', 'create-branches', 'update-branches', 'delete-branches',
            // Departments
            'view-departments', 'create-departments', 'update-departments', 'delete-departments', 'assign-head-departments',
            // Users (limited)
            'view-users', 'create-users', 'update-users',
            // Teams
            'view-teams', 'create-teams', 'update-teams', 'delete-teams', 'manage-members-teams',
            // Projects
            'view-projects', 'create-projects', 'update-projects', 'archive-projects',
            // Reports & Analytics
            'view-reports', 'create-reports', 'export-reports',
            'view-analytics', 'export-analytics',
        ])->get();
        $orgManager->givePermissionTo($orgManagerPermissions);

        // Project Manager - Manage projects, tasks, workflows
        $projectManager = \Spatie\Permission\Models\Role::create([
            'name' => 'Project Manager',
            'guard_name' => 'web',
        ]);
        $projectManagerPermissions = \Spatie\Permission\Models\Permission::whereIn('name', [
            // Projects
            'view-projects', 'create-projects', 'update-projects',
            // Tasks
            'view-tasks', 'create-tasks', 'update-tasks', 'delete-tasks', 'assign-tasks',
            // Teams (view only)
            'view-teams', 'view-users',
            // Workflows
            'view-workflows', 'create-workflows', 'update-workflows', 'execute-workflows',
            // Job Flows
            'view-job-flows', 'create-job-flows', 'update-job-flows', 'execute-job-flows', 'monitor-job-flows',
            // HITL
            'view-hitl-approvals', 'create-hitl-approvals', 'assign-hitl-approvals',
            // Reports
            'view-reports', 'create-reports', 'export-reports',
        ])->get();
        $projectManager->givePermissionTo($projectManagerPermissions);

        // Developer - Technical access to agents, workflows, integrations
        $developer = \Spatie\Permission\Models\Role::create([
            'name' => 'Developer',
            'guard_name' => 'web',
        ]);
        $developerPermissions = \Spatie\Permission\Models\Permission::whereIn('name', [
            // Agents
            'view-agents', 'create-agents', 'update-agents', 'execute-agents', 'configure-agents',
            // Workflows
            'view-workflows', 'create-workflows', 'update-workflows', 'execute-workflows', 'publish-workflows',
            // Job Flows
            'view-job-flows', 'create-job-flows', 'update-job-flows', 'execute-job-flows', 'monitor-job-flows',
            // Integrations
            'view-integrations', 'create-integrations', 'update-integrations', 'configure-integrations',
            // API Keys
            'view-api-keys', 'create-api-keys', 'update-api-keys', 'regenerate-api-keys',
            // Webhooks
            'view-webhooks', 'create-webhooks', 'update-webhooks', 'delete-webhooks', 'test-webhooks',
            // Connected Apps
            'view-connected-apps', 'create-connected-apps', 'update-connected-apps', 'authorize-connected-apps',
            // Logs
            'view-logs', 'export-logs',
            // Projects & Tasks (view only)
            'view-projects', 'view-tasks',
        ])->get();
        $developer->givePermissionTo($developerPermissions);

        // Team Lead - Manage team members and tasks
        $teamLead = \Spatie\Permission\Models\Role::create([
            'name' => 'Team Lead',
            'guard_name' => 'web',
        ]);
        $teamLeadPermissions = \Spatie\Permission\Models\Permission::whereIn('name', [
            // Teams
            'view-teams', 'update-teams', 'manage-members-teams',
            // Tasks
            'view-tasks', 'create-tasks', 'update-tasks', 'assign-tasks',
            // Projects
            'view-projects', 'update-projects',
            // Users (limited)
            'view-users',
            // HITL
            'view-hitl-approvals', 'approve-hitl-approvals', 'reject-hitl-approvals',
            // Reports
            'view-reports', 'create-reports',
        ])->get();
        $teamLead->givePermissionTo($teamLeadPermissions);

        // User - Basic user access
        $user = \Spatie\Permission\Models\Role::create([
            'name' => 'User',
            'guard_name' => 'web',
        ]);
        $userPermissions = \Spatie\Permission\Models\Permission::whereIn('name', [
            // Projects & Tasks (own)
            'view-projects', 'view-tasks',
            // Agents & Workflows (execute only)
            'view-agents', 'execute-agents',
            'view-workflows', 'execute-workflows',
            // Own profile
            'view-users', 'update-users',
            // Activities
            'view-activities',
            // Sessions
            'view-sessions',
        ])->get();
        $user->givePermissionTo($userPermissions);

        // Auditor - Read-only access to logs and activities
        $auditor = \Spatie\Permission\Models\Role::create([
            'name' => 'Auditor',
            'guard_name' => 'web',
        ]);
        $auditorPermissions = \Spatie\Permission\Models\Permission::whereIn('name', [
            'view-users', 'view-roles', 'view-permissions',
            'view-organizations', 'view-branches', 'view-departments',
            'view-projects', 'view-tasks',
            'view-activities', 'export-activities', 'audit-activities',
            'view-sessions',
            'view-logs', 'export-logs',
            'view-reports', 'export-reports',
            'view-analytics', 'export-analytics',
        ])->get();
        $auditor->givePermissionTo($auditorPermissions);

        $this->command->info('Roles and permissions seeded successfully!');
    }
}
