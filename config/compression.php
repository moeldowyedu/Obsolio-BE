<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Response Compression Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Gzip and Brotli compression for HTTP responses
    |
    */

    /**
     * Enable/Disable compression globally
     */
    'enabled' => env('COMPRESSION_ENABLED', true),

    /**
     * Gzip Compression
     */
    'gzip' => [
        'enabled' => env('COMPRESSION_GZIP_ENABLED', true),

        /**
         * Compression level (1-9)
         * 1 = fastest, lowest compression
         * 6 = balanced (recommended)
         * 9 = slowest, best compression
         */
        'level' => env('COMPRESSION_GZIP_LEVEL', 6),
    ],

    /**
     * Brotli Compression
     *
     * Brotli provides 15-25% better compression than Gzip
     * but requires the PHP brotli extension
     */
    'brotli' => [
        'enabled' => env('COMPRESSION_BROTLI_ENABLED', true),

        /**
         * Compression level (0-11)
         * 0 = no compression
         * 4 = balanced (recommended for dynamic content)
         * 11 = maximum compression (for static assets)
         */
        'level' => env('COMPRESSION_BROTLI_LEVEL', 4),

        /**
         * Compression mode
         * BROTLI_TEXT = optimized for text (default)
         * BROTLI_GENERIC = general purpose
         * BROTLI_FONT = optimized for fonts
         */
        'mode' => defined('BROTLI_TEXT') ? BROTLI_TEXT : 0,
    ],

    /**
     * Minimum size for compression (bytes)
     * Responses smaller than this won't be compressed
     */
    'min_size' => env('COMPRESSION_MIN_SIZE', 1024), // 1KB

    /**
     * Content types that should be compressed
     *
     * Add or remove content types as needed
     */
    'compressible_types' => [
        // Text
        'text/html',
        'text/css',
        'text/javascript',
        'text/plain',
        'text/xml',
        'text/csv',

        // Application
        'application/json',
        'application/javascript',
        'application/xml',
        'application/xhtml+xml',
        'application/rss+xml',
        'application/atom+xml',
        'application/ld+json',
        'application/vnd.api+json',

        // Images (vector only, not raster)
        'image/svg+xml',
        'image/x-icon',

        // Fonts
        'font/ttf',
        'font/otf',
        'font/eot',
        'application/font-woff',
        'application/font-woff2',
        'application/x-font-ttf',
        'application/x-font-otf',
    ],

    /**
     * Paths to exclude from compression
     *
     * Use patterns like:
     * - 'api/webhooks/*' - exclude all webhook endpoints
     * - '*.jpg' - exclude all JPEG images
     */
    'exclude_paths' => [
        // Already compressed file types
        '*.jpg',
        '*.jpeg',
        '*.png',
        '*.gif',
        '*.webp',
        '*.mp4',
        '*.mp3',
        '*.zip',
        '*.gz',
        '*.pdf',

        // Streaming endpoints
        'api/stream/*',
        'websocket/*',
    ],

    /**
     * Compression for different environments
     */
    'environments' => [
        'production' => [
            'gzip_level' => 6,
            'brotli_level' => 4,
        ],

        'staging' => [
            'gzip_level' => 5,
            'brotli_level' => 3,
        ],

        'local' => [
            'gzip_level' => 3, // Faster compression for development
            'brotli_level' => 2,
        ],
    ],

    /**
     * Static asset compression
     *
     * For build tools like Vite/Webpack, you should pre-compress
     * static assets at build time for better performance
     */
    'static_assets' => [
        'precompress' => env('COMPRESSION_PRECOMPRESS', true),

        // Generate .gz and .br files for static assets
        'generate_compressed_files' => env('COMPRESSION_GENERATE_FILES', env('APP_ENV') === 'production'),

        // Directories to precompress
        'directories' => [
            'public/build',
            'public/css',
            'public/js',
        ],
    ],

    /**
     * Performance settings
     */
    'performance' => [
        // Use faster compression for responses larger than this size
        'large_response_threshold' => 1048576, // 1MB

        // Compression level for large responses
        'large_response_gzip_level' => 3,
        'large_response_brotli_level' => 2,

        // Cache compressed responses (requires cache driver)
        'cache_compressed' => env('COMPRESSION_CACHE_ENABLED', false),
        'cache_ttl' => 3600, // 1 hour
    ],

    /**
     * Logging
     */
    'logging' => [
        'enabled' => env('COMPRESSION_LOGGING_ENABLED', false),

        // Log compression stats to performance monitoring
        'log_stats' => env('APP_ENV') !== 'production',

        // Log compression failures
        'log_errors' => true,
    ],

    /**
     * HTTP/2 Server Push
     *
     * When using HTTP/2, the server can push compressed assets
     */
    'http2_push' => [
        'enabled' => env('COMPRESSION_HTTP2_PUSH', false),

        // Assets to push
        'assets' => [
            '/build/assets/app.css',
            '/build/assets/app.js',
        ],
    ],

    /**
     * Vary header configuration
     *
     * The Vary header tells caches to serve different versions
     * based on Accept-Encoding
     */
    'vary_header' => [
        'enabled' => true,
        'value' => 'Accept-Encoding',
    ],
];
