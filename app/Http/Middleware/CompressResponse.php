<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Response Compression Middleware
 *
 * Compresses responses using Gzip or Brotli based on client support
 * to reduce bandwidth and improve load times
 */
class CompressResponse
{
    /**
     * Minimum size for compression (in bytes)
     */
    private const MIN_SIZE = 1024; // 1KB

    /**
     * Content types that should be compressed
     */
    private const COMPRESSIBLE_TYPES = [
        'text/html',
        'text/css',
        'text/javascript',
        'text/plain',
        'text/xml',
        'application/json',
        'application/javascript',
        'application/xml',
        'application/xhtml+xml',
        'application/rss+xml',
        'application/atom+xml',
        'application/ld+json',
        'image/svg+xml',
        'font/ttf',
        'font/otf',
        'font/eot',
    ];

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip compression if disabled or if it's an OPTIONS request
        if (!config('compression.enabled', true) || $request->isMethod('OPTIONS')) {
            return $response;
        }

        // Skip compression for certain response types
        if ($this->shouldSkipCompression($response)) {
            return $response;
        }

        // Check if content is compressible
        if (!$this->isCompressible($response)) {
            return $response;
        }

        // Determine best compression method based on client support
        $encoding = $this->negotiateEncoding($request);

        if (!$encoding) {
            return $response;
        }

        // Compress the response
        return $this->compressResponse($response, $encoding);
    }

    /**
     * Check if compression should be skipped
     */
    private function shouldSkipCompression($response): bool
    {
        // Skip if response is not successful
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return true;
        }

        // Skip if already encoded
        if ($response->headers->has('Content-Encoding')) {
            return true;
        }

        // Skip for binary file responses (usually already compressed)
        if ($response instanceof BinaryFileResponse) {
            return true;
        }

        // Skip for streamed responses
        if ($response instanceof StreamedResponse) {
            return true;
        }

        return false;
    }

    /**
     * Check if response content is compressible
     */
    private function isCompressible($response): bool
    {
        // Check content type
        $contentType = $response->headers->get('Content-Type', '');
        $isCompressibleType = false;

        foreach (self::COMPRESSIBLE_TYPES as $type) {
            if (str_starts_with($contentType, $type)) {
                $isCompressibleType = true;
                break;
            }
        }

        if (!$isCompressibleType) {
            return false;
        }

        // Check content size
        $content = $response->getContent();
        if (strlen($content) < self::MIN_SIZE) {
            return false;
        }

        return true;
    }

    /**
     * Negotiate compression encoding with client
     */
    private function negotiateEncoding(Request $request): ?string
    {
        $acceptEncoding = strtolower($request->header('Accept-Encoding', ''));

        // Parse Accept-Encoding header with quality values
        $encodings = $this->parseAcceptEncoding($acceptEncoding);

        // Prefer Brotli (better compression ratio)
        if (isset($encodings['br']) && extension_loaded('brotli') && config('compression.brotli.enabled', true)) {
            return 'br';
        }

        // Fallback to Gzip
        if (isset($encodings['gzip']) && function_exists('gzencode') && config('compression.gzip.enabled', true)) {
            return 'gzip';
        }

        // Fallback to Deflate
        if (isset($encodings['deflate']) && function_exists('gzdeflate') && config('compression.gzip.enabled', true)) {
            return 'deflate';
        }

        return null;
    }

    /**
     * Parse Accept-Encoding header
     */
    private function parseAcceptEncoding(string $header): array
    {
        $encodings = [];
        $parts = explode(',', $header);

        foreach ($parts as $part) {
            $part = trim($part);
            $encoding = strtok($part, ';');
            $quality = 1.0;

            // Parse quality value (q=0.8)
            if (str_contains($part, 'q=')) {
                preg_match('/q=([0-9.]+)/', $part, $matches);
                $quality = isset($matches[1]) ? (float) $matches[1] : 1.0;
            }

            if ($quality > 0) {
                $encodings[$encoding] = $quality;
            }
        }

        // Sort by quality (highest first)
        arsort($encodings);

        return $encodings;
    }

    /**
     * Compress response content
     */
    private function compressResponse($response, string $encoding)
    {
        $content = $response->getContent();
        $compressed = null;
        $startTime = microtime(true);

        try {
            switch ($encoding) {
                case 'br':
                    $compressed = $this->brotliCompress($content);
                    break;

                case 'gzip':
                    $compressed = $this->gzipCompress($content);
                    break;

                case 'deflate':
                    $compressed = $this->deflateCompress($content);
                    break;
            }

            if ($compressed === null) {
                return $response;
            }

            // Only use compressed version if it's actually smaller
            if (strlen($compressed) >= strlen($content)) {
                return $response;
            }

            // Update response
            $response->setContent($compressed);
            $response->headers->set('Content-Encoding', $encoding);
            $response->headers->set('Content-Length', strlen($compressed));
            $response->headers->set('Vary', 'Accept-Encoding');

            // Add compression stats in debug mode
            if (config('app.debug')) {
                $duration = (microtime(true) - $startTime) * 1000;
                $ratio = round((1 - strlen($compressed) / strlen($content)) * 100, 2);

                $response->headers->set('X-Compression-Method', $encoding);
                $response->headers->set('X-Compression-Ratio', "{$ratio}%");
                $response->headers->set('X-Compression-Time', round($duration, 2) . 'ms');
                $response->headers->set('X-Original-Size', strlen($content));
                $response->headers->set('X-Compressed-Size', strlen($compressed));
            }

            // Log compression stats
            if (config('compression.logging.enabled', false)) {
                \App\Services\StructuredLogger::debug('Response compressed', [
                    'encoding' => $encoding,
                    'original_size' => strlen($content),
                    'compressed_size' => strlen($compressed),
                    'ratio' => round((1 - strlen($compressed) / strlen($content)) * 100, 2) . '%',
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ], \App\Services\StructuredLogger::CATEGORY_PERFORMANCE);
            }

            return $response;
        } catch (\Exception $e) {
            // Return uncompressed response on error
            \App\Services\StructuredLogger::error('Compression failed', [
                'encoding' => $encoding,
                'error' => $e->getMessage(),
            ], \App\Services\StructuredLogger::CATEGORY_PERFORMANCE);

            return $response;
        }
    }

    /**
     * Compress using Brotli
     */
    private function brotliCompress(string $content): ?string
    {
        if (!extension_loaded('brotli')) {
            return null;
        }

        $level = config('compression.brotli.level', 4); // 0-11, 4 is balanced
        $defaultMode = defined('BROTLI_TEXT') ? BROTLI_TEXT : 1;
        $mode = config('compression.brotli.mode', $defaultMode);

        if (function_exists('brotli_compress')) {
            return brotli_compress($content, $level, $mode);
        }

        return null;
    }

    /**
     * Compress using Gzip
     */
    private function gzipCompress(string $content): ?string
    {
        if (!function_exists('gzencode')) {
            return null;
        }

        $level = config('compression.gzip.level', 6); // 1-9, 6 is balanced

        return gzencode($content, $level);
    }

    /**
     * Compress using Deflate
     */
    private function deflateCompress(string $content): ?string
    {
        if (!function_exists('gzdeflate')) {
            return null;
        }

        $level = config('compression.gzip.level', 6);

        return gzdeflate($content, $level);
    }
}
