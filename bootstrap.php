<?php

declare(strict_types=1);

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', '1'); // Set to '1' for development
ini_set('log_errors', '1');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Composer autoload (for namespaced classes)
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

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
