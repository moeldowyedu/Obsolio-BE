<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Config tenancy.central_domains: ";
var_dump(config('tenancy.central_domains'));

echo "User Check: ";
try {
    $u = \App\Models\User::where('email', 'sayed2')->first();
    echo "User by email found? " . ($u ? 'Yes' : 'No') . "\n";
} catch (\Throwable $e) {
    echo "User Error: " . $e->getMessage() . "\n";
}

try {
    echo "Testing Lookup Logic for 'sayed2'...\n";
    $identifier = 'sayed2';
    if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $tenant = \App\Models\Tenant::where('id', $identifier)
            ->orWhere('subdomain_preference', $identifier)
            ->first();
        echo "Tenant found by ID? " . ($tenant ? 'Yes: ' . $tenant->id : 'No') . "\n";
    }
} catch (\Throwable $e) {
    echo "Logic Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
