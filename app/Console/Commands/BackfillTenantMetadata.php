<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\Organization;
use App\Models\User;

class BackfillTenantMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:backfill-metadata';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing name and short_name on tenants from organizations or owners';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting tenant metadata backfill...');

        $tenants = Tenant::all();
        $count = 0;

        foreach ($tenants as $tenant) {
            $updated = false;

            // Strategy 1: Organization Sync
            if ($tenant->type === 'organization' || $tenant->type === null) {
                // Try to find organization
                $org = Organization::where('tenant_id', $tenant->id)->first();

                if ($org) {
                    $tenant->name = $org->name;
                    $tenant->short_name = $org->short_name ?? $org->slug ?? $tenant->id;
                    $tenant->type = 'organization'; // Ensure type is set
                    $updated = true;
                    $this->line("Synced Organization: {$tenant->id} -> {$tenant->name}");
                }
            }

            // Strategy 2: Personal Sync (if not updated by Org)
            if (!$updated && ($tenant->type === 'personal' || $tenant->type === null)) {
                // Try to find owner via membership
                $owner = User::whereHas('tenantMemberships', function ($q) use ($tenant) {
                    $q->where('tenant_id', $tenant->id)
                        ->where('role', 'owner');
                })->first();

                // Fallback: Check if a user exists with this tenant_id (legacy structure)
                if (!$owner) {
                    $owner = User::where('tenant_id', $tenant->id)->first();
                }

                if ($owner) {
                    $tenant->name = $owner->name . "'s Workspace";
                    $tenant->short_name = $tenant->id; // Personal workspaces use ID as short name
                    $tenant->type = 'personal';
                    $updated = true;
                    $this->line("Synced Personal: {$tenant->id} -> {$tenant->name}");
                }
            }

            // Save if changed
            if ($updated) {
                $tenant->save();
                $count++;
            }
        }

        $this->info("Backfill completed. Updated {$count} tenants.");
    }
}
