<?php

declare(strict_types=1);

// Error handling - .user.ini handles configuration on production FastCGI
// These are fallbacks for environments without .user.ini support
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL);

// Create logs directory if it doesn't exist
$logsDir = __DIR__ . '/Logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}

// Try to set error_log path (for XAMPP/environments without .user.ini)
$errorLogPath = $logsDir . '/php-error.log';
@ini_set('error_log', $errorLogPath);

// Composer autoload and configuration - load early
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';


// Start session ONLY for main web app, not API
// API should be stateless
$isApiRequest = isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') === 0;

if (!$isApiRequest && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate configuration
if (empty($apiKey) || empty($baseUrl)) {
    throw new Exception('API configuration missing. Please check config.php');
}

// Define constants for global access
define('API_KEY', $apiKey);
define('API_BASE_URL', $baseUrl);
define('DB_HOST', $dbHost);
define('DB_PORT', $dbPort);
define('DB_NAME', $dbName);
define('DB_USERNAME', $dbUsername);
define('DB_PASSWORD', $dbPassword);

if (!function_exists('lexbridge_base_path')) {
    function lexbridge_base_path(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

        if ($scriptName === '') {
            return '/';
        }

        $directory = str_replace('\\', '/', dirname($scriptName));

        if ($directory === '/' || $directory === '\\' || $directory === '.' || $directory === '') {
            return '/';
        }

        return rtrim($directory, '/') . '/';
    }
}

if (!function_exists('lexbridge_base_uri')) {
    function lexbridge_base_uri(): string
    {
        $basePath = lexbridge_base_path();

        return $basePath === '/' ? '/' : rtrim($basePath, '/');
    }
}
