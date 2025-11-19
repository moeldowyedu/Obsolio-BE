<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Read/Write Splitting Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration enables read/write splitting for database queries.
    | Read queries go to replicas, write queries go to the master.
    |
    | To enable, update your .env:
    | DB_READ_HOST_1=replica1.example.com
    | DB_READ_HOST_2=replica2.example.com
    |
    | Then update config/database.php to use this configuration.
    |
    */

    'pgsql_with_replicas' => [
        'driver' => 'pgsql',
        'read' => [
            [
                'host' => env('DB_READ_HOST_1', env('DB_HOST', '127.0.0.1')),
                'port' => env('DB_READ_PORT_1', env('DB_PORT', '5432')),
            ],
            [
                'host' => env('DB_READ_HOST_2', env('DB_HOST', '127.0.0.1')),
                'port' => env('DB_READ_PORT_2', env('DB_PORT', '5432')),
            ],
        ],
        'write' => [
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
        ],
        'sticky' => true, // Keep same connection for session after write
        'database' => env('DB_DATABASE', 'forge'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8'),
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Pool Configuration
    |--------------------------------------------------------------------------
    |
    | Configure connection pooling for better performance with high concurrency
    |
    */

    'pool' => [
        'min' => env('DB_POOL_MIN', 2),
        'max' => env('DB_POOL_MAX', 20),
        'idle_timeout' => env('DB_POOL_IDLE_TIMEOUT', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Hints for Read Replica Routing
    |--------------------------------------------------------------------------
    |
    | These hints help the application decide query routing
    |
    */

    'routing' => [
        // Always use master for these operations
        'force_master' => [
            'transactions',
            'locks',
            'writes',
        ],

        // Can use replicas
        'allow_replica' => [
            'select',
            'count',
            'aggregate',
        ],

        // Replica lag tolerance (seconds)
        'max_replica_lag' => env('DB_MAX_REPLICA_LAG', 5),
    ],
];
