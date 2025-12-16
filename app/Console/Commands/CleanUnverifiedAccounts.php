<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;

class CleanUnverifiedAccounts extends Command
{
    protected $signature = 'users:clean-unverified';
    protected $description = 'Delete unverified accounts older than 7 days';

    public function handle()
    {
        $this->info('🧹 Starting cleanup of unverified accounts...');

        $cutoffDate = Carbon::now()->subDays(7);

        // Find unverified users
        $unverifiedUsers = User::whereNull('email_verified_at')
            ->where('status', 'pending_verification')
            ->where('created_at', '<', $cutoffDate)
            ->with('tenant')
            ->get();

        $count = $unverifiedUsers->count();

        if ($count === 0) {
            $this->info('✅ No unverified accounts found. All clean!');
            return 0;
        }

        $this->info("📋 Found {$count} unverified accounts to delete.");

        $bar = $this->output->createProgressBar($count);

        foreach ($unverifiedUsers as $user) {
            try {
                $email = $user->email;
                $tenantId = $user->tenant_id;

                // Delete tenant (cascades to user via foreign key)
                if ($user->tenant) {
                    $user->tenant->delete();
                }

                $bar->advance();
            } catch (\Exception $e) {
                $this->error("\n✗ Failed to delete {$user->email}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Cleanup complete! Deleted {$count} unverified accounts.");

        return 0;
    }
}
