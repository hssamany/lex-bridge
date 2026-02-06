<?php

declare(strict_types=1);

// Bootstrap application
require_once __DIR__ . '/bootstrap.php';

use Luxullus\LexBridge\Api\ApiKernel;
use Luxullus\LexBridge\Application;
use Luxullus\LexBridge\Constants\HttpHeader;
use Luxullus\LexBridge\Constants\ContentType;

// Determine if this is an API request
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$isApiRequest = (strpos($requestUri, '/api/') === 0 || strpos($requestUri, '/api') === 0);

if ($isApiRequest) {
    // Handle API requests
    error_log('API LOADED.2');
    file_put_contents(__DIR__ . '/logs/api-trace.txt', date('Y-m-d H:i:s') . " - API START\n", FILE_APPEND);
    
    error_log('[API Entry] ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . ' ' . $requestUri);
    
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
        
        error_log(sprintf('[API Success] %s %s completed', $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN', $requestUri));
        
    } catch (\PDOException $e) {
        error_log(sprintf(
            '[API Error - Database] %s %s - Error: %s (Code: %s, File: %s:%d)\nStack trace:\n%s',
            $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            $requestUri,
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
            $requestUri,
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
} else {
    // Handle regular application requests
    error_log(sprintf(
        '[Main Entry] %s %s - IP: %s',
        $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        $requestUri,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ));
    
    $app = new Application();
    $app->run();
}
