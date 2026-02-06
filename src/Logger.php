<?php

declare(strict_types=1);

namespace Luxullus\LexBridge;

use Throwable;

/**
 * Centralized logging utility
 */
final class Logger
{
    /**
     * Log an exception with full context
     */
    public static function exception(Throwable $e, string $context = ''): void
    {
        $prefix = $context ? "[$context]" : '';
        
        error_log(sprintf(
            '%s Exception: %s (Code: %s, File: %s:%d)%sStack trace:%s%s',
            $prefix,
            $e->getMessage(),
            $e->getCode(),
            $e->getFile(),
            $e->getLine(),
            PHP_EOL,
            PHP_EOL,
            $e->getTraceAsString()
        ));
    }

    /**
     * Log an informational message
     */
    public static function info(string $message, string $context = ''): void
    {
        $prefix = $context ? "[$context]" : '';
        error_log("$prefix $message");
    }

    /**
     * Log with custom formatting
     */
    public static function log(string $context, string $format, mixed ...$args): void
    {
        error_log(sprintf("[$context] $format", ...$args));
    }

    /**
     * Log API request details
     */
    public static function apiRequest(string $method, string $uri, ?string $query = null): void
    {
        self::log(
            'API Request',
            '%s %s%s',
            $method,
            $uri,
            $query ? "?$query" : ''
        );
    }

    private function __construct()
    {
        // Prevent instantiation
    }
}
