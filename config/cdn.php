<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CDN Configuration
    |--------------------------------------------------------------------------
    |
    | Configure CDN providers for serving static assets globally
    |
    */

    /**
     * Enable/Disable CDN
     */
    'enabled' => env('CDN_ENABLED', env('APP_ENV') === 'production'),

    /**
     * Active CDN Provider
     *
     * Supported: cloudfront, cloudflare, fastly, bunny, local
     */
    'provider' => env('CDN_PROVIDER', 'cloudflare'),

    /**
     * CDN Providers Configuration
     */
    'providers' => [
        /**
         * AWS CloudFront
         */
        'cloudfront' => [
            'url' => env('CDN_CLOUDFRONT_URL', 'https://d123456789.cloudfront.net'),
            'distribution_id' => env('CDN_CLOUDFRONT_DISTRIBUTION_ID'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),

            // For signed URLs
            'key_pair_id' => env('CDN_CLOUDFRONT_KEY_PAIR_ID'),
            'private_key_path' => env('CDN_CLOUDFRONT_PRIVATE_KEY_PATH', storage_path('keys/cloudfront-private-key.pem')),

            // Cache behavior
            'default_ttl' => 86400, // 24 hours
            'max_ttl' => 31536000, // 1 year
            'min_ttl' => 0,

            // Compression
            'compress' => true,
            'compression_formats' => ['gzip', 'br'],

            // Origin settings
            'origin_protocol_policy' => 'https-only',
            'origin_ssl_protocols' => ['TLSv1.2'],
        ],

        /**
         * Cloudflare CDN
         */
        'cloudflare' => [
            'url' => env('CDN_CLOUDFLARE_URL', 'https://cdn.obsolio.com'),
            'zone_id' => env('CDN_CLOUDFLARE_ZONE_ID'),
            'api_token' => env('CDN_CLOUDFLARE_API_TOKEN'),

            // For signed URLs
            'token_secret' => env('CDN_CLOUDFLARE_TOKEN_SECRET'),

            // Cache settings
            'cache_level' => 'aggressive', // aggressive, standard, simplified
            'browser_cache_ttl' => 14400, // 4 hours
            'edge_cache_ttl' => 7200, // 2 hours

            // Optimization
            'auto_minify' => [
                'html' => true,
                'css' => true,
                'js' => true,
            ],
            'brotli' => true,
            'rocket_loader' => false, // Can break some JS
            'mirage' => true, // Image optimization
            'polish' => 'lossless', // lossless, lossy, off

            // Security
            'ssl' => 'full', // off, flexible, full, strict
            'always_use_https' => true,
            'opportunistic_encryption' => true,
        ],

        /**
         * Fastly CDN
         */
        'fastly' => [
            'url' => env('CDN_FASTLY_URL', 'https://cdn.obsolio.com'),
            'service_id' => env('CDN_FASTLY_SERVICE_ID'),
            'api_key' => env('CDN_FASTLY_API_KEY'),

            // Cache settings
            'default_ttl' => 3600,
            'stale_ttl' => 86400, // Serve stale while revalidating

            // Compression
            'gzip' => true,
            'brotli' => true,
        ],

        /**
         * Bunny CDN
         */
        'bunny' => [
            'url' => env('CDN_BUNNY_URL', 'https://cdn.obsolio.b-cdn.net'),
            'pull_zone_id' => env('CDN_BUNNY_PULL_ZONE_ID'),
            'api_key' => env('CDN_BUNNY_API_KEY'),
            'storage_zone' => env('CDN_BUNNY_STORAGE_ZONE'),

            // For signed URLs
            'token_secret' => env('CDN_BUNNY_TOKEN_SECRET'),

            // Cache settings
            'cache_expiration' => 86400,
            'browser_cache_ttl' => 14400,

            // Optimization
            'optimizer' => true,
            'webp' => true,
            'avif' => false,
        ],

        /**
         * Local (no CDN)
         */
        'local' => [
            'url' => env('APP_URL', 'http://localhost'),
        ],
    ],

    /**
     * Asset Types and Their Cache Settings
     */
    'asset_types' => [
        'images' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico'],
            'cache_control' => 'public, max-age=31536000, immutable', // 1 year
        ],

        'styles' => [
            'extensions' => ['css'],
            'cache_control' => 'public, max-age=31536000, immutable',
        ],

        'scripts' => [
            'extensions' => ['js', 'mjs'],
            'cache_control' => 'public, max-age=31536000, immutable',
        ],

        'fonts' => [
            'extensions' => ['woff', 'woff2', 'ttf', 'otf', 'eot'],
            'cache_control' => 'public, max-age=31536000, immutable',
        ],

        'videos' => [
            'extensions' => ['mp4', 'webm', 'ogg'],
            'cache_control' => 'public, max-age=604800', // 1 week
        ],

        'documents' => [
            'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
            'cache_control' => 'public, max-age=3600', // 1 hour
        ],
    ],

    /**
     * Asset Versioning Strategy
     *
     * Options: git_hash, manifest, timestamp, custom
     */
    'versioning' => [
        'strategy' => env('CDN_VERSIONING_STRATEGY', 'git_hash'),

        // Manifest file path (for webpack/vite builds)
        'manifest_path' => public_path('build/manifest.json'),

        // Custom version string
        'custom_version' => env('CDN_ASSET_VERSION', '1.0.0'),
    ],

    /**
     * Cache Purging
     */
    'purge' => [
        // Auto-purge on deployment
        'on_deploy' => env('CDN_PURGE_ON_DEPLOY', true),

        // Paths to purge on deployment
        'deploy_paths' => [
            '/css/*',
            '/js/*',
            '/images/*',
        ],

        // Retry configuration
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
    ],

    /**
     * Preloading and Prefetching
     */
    'preload' => [
        'enabled' => env('CDN_PRELOAD_ENABLED', true),

        // Critical assets to preload (HTTP/2 push)
        'assets' => [
            ['path' => 'css/app.css', 'type' => 'style'],
            ['path' => 'js/app.js', 'type' => 'script'],
            ['path' => 'fonts/inter-var.woff2', 'type' => 'font'],
        ],
    ],

    /**
     * Image Optimization
     */
    'images' => [
        'optimization' => env('CDN_IMAGE_OPTIMIZATION', true),

        // Responsive images
        'responsive' => true,
        'breakpoints' => [320, 640, 768, 1024, 1280, 1536],

        // Formats
        'formats' => ['webp', 'avif', 'jpg', 'png'],

        // Quality
        'quality' => env('CDN_IMAGE_QUALITY', 85),
    ],

    /**
     * Security Headers for CDN Assets
     */
    'security_headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'interest-cohort=()',
    ],

    /**
     * CORS Configuration for CDN
     */
    'cors' => [
        'allowed_origins' => env('CDN_CORS_ORIGINS', '*'),
        'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],
        'allowed_headers' => ['Origin', 'Content-Type', 'Accept', 'Authorization'],
        'max_age' => 86400,
    ],

    /**
     * Monitoring
     */
    'monitoring' => [
        'log_purges' => true,
        'log_signed_urls' => env('APP_ENV') !== 'production',
        'track_performance' => true,
    ],
];
