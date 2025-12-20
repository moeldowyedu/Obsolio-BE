<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\Organization;

$tenantId = 'loogentic10';
$tenant = Tenant::find($tenantId);

echo "--- Tenant Lookup ---\n";
if ($tenant) {
    echo "Tenant Found: {$tenant->id}\n";
    echo "Slug: {$tenant->slug}\n";
    echo "Short Name: {$tenant->short_name}\n";

    echo "\n--- Organization Lookup (via Relation) ---\n";
    foreach ($tenant->organizations as $org) {
        echo "Org ID: {$org->id}\n";
        echo "Org Name: {$org->name}\n";
        echo "Org Short Name: {$org->short_name}\n";
        echo "Org Tenant ID: {$org->tenant_id}\n";
    }
} else {
    echo "Tenant '{$tenantId}' NOT FOUND.\n";
}

echo "\n--- Organization Lookup (Direct 'loogentic10') ---\n";
$orgByShort = Organization::where('short_name', $tenantId)->first();
if ($orgByShort) {
    echo "Found by short_name: YES ({$orgByShort->id})\n";
} else {
    echo "Found by short_name: NO\n";
}

$orgById = Organization::find($tenantId);
if ($orgById) {
    echo "Found by ID: YES\n";
} else {
    echo "Found by ID: NO\n";
}
