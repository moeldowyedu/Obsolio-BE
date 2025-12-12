<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto-scaling Configuration
    |--------------------------------------------------------------------------
    |
    | Configure auto-scaling behavior for different deployment platforms
    |
    */

    /**
     * Scaling thresholds and metrics
     */
    'thresholds' => [
        'cpu' => [
            'target' => env('AUTOSCALE_CPU_TARGET', 70), // Target CPU utilization (%)
            'scale_up' => env('AUTOSCALE_CPU_SCALE_UP', 80), // Scale up threshold
            'scale_down' => env('AUTOSCALE_CPU_SCALE_DOWN', 30), // Scale down threshold
        ],

        'memory' => [
            'target' => env('AUTOSCALE_MEMORY_TARGET', 80), // Target memory utilization (%)
            'scale_up' => env('AUTOSCALE_MEMORY_SCALE_UP', 85),
            'scale_down' => env('AUTOSCALE_MEMORY_SCALE_DOWN', 40),
        ],

        'request_rate' => [
            'target' => env('AUTOSCALE_REQUEST_RATE', 1000), // Requests per second per instance
            'scale_up' => env('AUTOSCALE_REQUEST_RATE_UP', 1200),
            'scale_down' => env('AUTOSCALE_REQUEST_RATE_DOWN', 500),
        ],

        'response_time' => [
            'target' => env('AUTOSCALE_RESPONSE_TIME', 500), // Target response time in ms
            'max' => env('AUTOSCALE_RESPONSE_TIME_MAX', 1000), // Scale up if exceeded
        ],

        'queue_size' => [
            'default' => env('AUTOSCALE_QUEUE_DEFAULT', 100), // Jobs per worker
            'high' => env('AUTOSCALE_QUEUE_HIGH', 50),
            'webhooks' => env('AUTOSCALE_QUEUE_WEBHOOKS', 200),
        ],
    ],

    /**
     * Scaling limits
     */
    'limits' => [
        'api' => [
            'min' => env('AUTOSCALE_API_MIN', 3), // Minimum API instances
            'max' => env('AUTOSCALE_API_MAX', 20), // Maximum API instances
            'desired' => env('AUTOSCALE_API_DESIRED', 3),
        ],

        'queue_worker' => [
            'min' => env('AUTOSCALE_WORKER_MIN', 2),
            'max' => env('AUTOSCALE_WORKER_MAX', 15),
            'desired' => env('AUTOSCALE_WORKER_DESIRED', 2),
        ],

        'scheduler' => [
            'min' => env('AUTOSCALE_SCHEDULER_MIN', 2),
            'max' => env('AUTOSCALE_SCHEDULER_MAX', 4),
            'desired' => env('AUTOSCALE_SCHEDULER_DESIRED', 2),
        ],
    ],

    /**
     * Scaling behavior
     */
    'behavior' => [
        'scale_up' => [
            'stabilization_window' => env('AUTOSCALE_UP_STABILIZATION', 0), // Seconds before scaling up
            'percent' => env('AUTOSCALE_UP_PERCENT', 100), // Scale up by 100% (double)
            'pods' => env('AUTOSCALE_UP_PODS', 4), // Or add 4 pods
            'period' => env('AUTOSCALE_UP_PERIOD', 30), // Every 30 seconds
            'policy' => env('AUTOSCALE_UP_POLICY', 'max'), // Use most aggressive (max/min)
        ],

        'scale_down' => [
            'stabilization_window' => env('AUTOSCALE_DOWN_STABILIZATION', 300), // Wait 5 minutes
            'percent' => env('AUTOSCALE_DOWN_PERCENT', 50), // Scale down by max 50%
            'pods' => env('AUTOSCALE_DOWN_PODS', 2), // Or remove 2 pods
            'period' => env('AUTOSCALE_DOWN_PERIOD', 60), // Every 60 seconds
            'policy' => env('AUTOSCALE_DOWN_POLICY', 'min'), // Use most conservative
        ],
    ],

    /**
     * Cloud provider specific configurations
     */
    'providers' => [
        'aws_ecs' => [
            'enabled' => env('AUTOSCALE_AWS_ECS_ENABLED', false),
            'cluster' => env('AWS_ECS_CLUSTER', 'obsolio-production'),
            'service' => env('AWS_ECS_SERVICE', 'obsolio-api'),
            'target_tracking' => [
                'cpu' => 70,
                'memory' => 80,
                'alb_request_count' => 1000,
            ],
        ],

        'aws_lambda' => [
            'enabled' => env('AUTOSCALE_AWS_LAMBDA_ENABLED', false),
            'concurrent_executions' => env('AWS_LAMBDA_CONCURRENT', 1000),
            'provisioned_concurrency' => env('AWS_LAMBDA_PROVISIONED', 10),
        ],

        'gcp_cloud_run' => [
            'enabled' => env('AUTOSCALE_GCP_RUN_ENABLED', false),
            'min_instances' => 3,
            'max_instances' => 100,
            'max_concurrent_requests' => 80,
            'cpu_throttling' => false, // Always allocate CPU
        ],

        'azure_container_apps' => [
            'enabled' => env('AUTOSCALE_AZURE_APPS_ENABLED', false),
            'min_replicas' => 3,
            'max_replicas' => 30,
            'rules' => [
                ['type' => 'http', 'concurrent_requests' => 100],
                ['type' => 'cpu', 'utilization' => 70],
            ],
        ],

        'digitalocean_app_platform' => [
            'enabled' => env('AUTOSCALE_DO_APP_ENABLED', false),
            'instance_size' => 'professional-xs',
            'instance_count' => 3,
        ],
    ],

    /**
     * Custom metrics for auto-scaling
     */
    'custom_metrics' => [
        'enabled' => env('AUTOSCALE_CUSTOM_METRICS_ENABLED', true),

        'metrics' => [
            'http_requests_per_second' => [
                'type' => 'pods',
                'target' => 1000,
                'query' => 'rate(obsolio_http_requests_total[1m])',
            ],

            'http_request_duration_seconds' => [
                'type' => 'pods',
                'target' => 0.5,
                'query' => 'histogram_quantile(0.95, rate(obsolio_http_request_duration_seconds_bucket[1m]))',
            ],

            'queue_default_size' => [
                'type' => 'external',
                'target' => 100,
                'query' => 'obsolio_queue_default_size',
            ],

            'queue_high_size' => [
                'type' => 'external',
                'target' => 50,
                'query' => 'obsolio_queue_high_size',
            ],

            'database_connections' => [
                'type' => 'pods',
                'target' => 20,
                'query' => 'obsolio_database_connections_active',
            ],
        ],
    ],

    /**
     * Health check configuration for load balancers
     */
    'health_checks' => [
        'liveness' => [
            'path' => '/api/health/alive',
            'interval' => 10,
            'timeout' => 5,
            'failure_threshold' => 3,
            'success_threshold' => 1,
        ],

        'readiness' => [
            'path' => '/api/health/ready',
            'interval' => 5,
            'timeout' => 3,
            'failure_threshold' => 2,
            'success_threshold' => 1,
        ],

        'startup' => [
            'path' => '/api/health',
            'interval' => 10,
            'timeout' => 5,
            'failure_threshold' => 30, // Allow 5 minutes for startup (30 * 10s)
        ],
    ],

    /**
     * Load balancer configuration
     */
    'load_balancer' => [
        'algorithm' => env('LB_ALGORITHM', 'round_robin'), // round_robin, least_connections, ip_hash
        'health_check_interval' => env('LB_HEALTH_CHECK_INTERVAL', 10),
        'session_affinity' => env('LB_SESSION_AFFINITY', false),
        'connection_draining' => env('LB_CONNECTION_DRAINING', 300), // Seconds to drain connections
    ],

    /**
     * Monitoring and alerting
     */
    'monitoring' => [
        'enabled' => env('AUTOSCALE_MONITORING_ENABLED', true),

        'alerts' => [
            'scaling_events' => env('ALERT_SCALING_EVENTS', true),
            'threshold_breaches' => env('ALERT_THRESHOLD_BREACHES', true),
            'failed_scale_operations' => env('ALERT_FAILED_SCALING', true),
        ],

        'logging' => [
            'log_scale_up' => true,
            'log_scale_down' => true,
            'log_metrics' => env('APP_ENV') === 'local',
        ],
    ],

    /**
     * Time-based scaling (predictive scaling)
     */
    'scheduled_scaling' => [
        'enabled' => env('AUTOSCALE_SCHEDULED_ENABLED', false),

        'schedules' => [
            // Scale up for business hours
            'business_hours' => [
                'cron' => '0 8 * * 1-5', // 8 AM Mon-Fri
                'min_instances' => 10,
                'max_instances' => 30,
            ],

            // Scale down for off-hours
            'off_hours' => [
                'cron' => '0 20 * * *', // 8 PM daily
                'min_instances' => 3,
                'max_instances' => 15,
            ],

            // Scale up for expected high traffic
            'peak_hours' => [
                'cron' => '0 12 * * 1-5', // Noon Mon-Fri
                'min_instances' => 15,
                'max_instances' => 40,
            ],
        ],
    ],
];
