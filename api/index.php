<?php

declare(strict_types=1);
error_log('[API] request received: ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . ' ' . ($_SERVER['REQUEST_URI'] ?? '/'));
// Load bootstrap FIRST to initialize autoloader and error logging
require_once __DIR__ . '/../bootstrap.php';

use Luxullus\LexBridge\Api\ApiKernel;
use Luxullus\LexBridge\Constants\HttpHeader;
use Luxullus\LexBridge\Constants\ContentType;

// Set Content-Type header
header(HttpHeader::CONTENT_TYPE . ': ' . ContentType::JSON);

try {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
    
    // CORS handling
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    $allowList = $allowedOrigins ?? [];
    $originAllowed = false;
    
    if ($origin !== null) {
        if (in_array($origin, $allowList, true)) {
            $originAllowed = true;
        } elseif (preg_match('#^https://([a-z0-9-]+\.)*lukullus\.catering$#i', $origin)) {
            $originAllowed = true;
        }
    }
    
    $cors = 'Access-Control-Allow-Origin: ';
    
    if ($originAllowed) {
        header($cors . $origin);
    } elseif (!empty($allowList)) {
        header($cors . reset($allowList));
    }
    
    $kernel = new ApiKernel();
    $kernel->handle();
    
} catch (\PDOException $e) {
    error_log(sprintf(
        '[API Error - Database] %s %s - Error: %s (Code: %s, File: %s:%d)\nStack trace:\n%s',
        $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        $_SERVER['REQUEST_URI'] ?? '/',
        $e->getMessage(),
        $e->getCode(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));
    
    http_response_code(500);
    echo json_encode([
        'statusCode' => 500,
        'isSuccess' => false,
        'error' => 'A database error occurred. Please try again later.'
    ]);
} catch (\Throwable $e) {
    error_log(sprintf(
        '[API Error - Fatal] %s %s - Error: %s (File: %s:%d)\nStack trace:\n%s',
        $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        $_SERVER['REQUEST_URI'] ?? '/',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));
    
    http_response_code(500);
    echo json_encode([
        'statusCode' => 500,
        'isSuccess' => false,
        'error' => 'An error occurred. Please try again later.'
    ]);
}
