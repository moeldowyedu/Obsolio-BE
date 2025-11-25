<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = App\Models\User::first();

if ($user) {
    echo "User ID: {$user->id}\n";
    echo "User Email: {$user->email}\n";

    $memberships = App\Models\TenantMembership::where('user_id', $user->id)->get();
    echo "User Memberships: {$memberships->count()}\n";

    foreach ($memberships as $m) {
        $tenant = App\Models\Tenant::find($m->tenant_id);
        echo "  - Tenant: {$m->tenant_id} (Name: {$tenant->name}, Role: {$m->role})\n";
    }
} else {
    echo "No users found\n";
}
