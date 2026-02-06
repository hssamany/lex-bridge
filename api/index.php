<?php

declare(strict_types=1);

error_log('API LOADED.2');
// Direct file write to bypass error_log
file_put_contents(__DIR__ . '/../logs/api-trace.txt', date('Y-m-d H:i:s') . " - API START\n", FILE_APPEND);

// Load bootstrap FIRST to initialize autoloader and error logging
require_once __DIR__ . '/../bootstrap.php';

file_put_contents(__DIR__ . '/../logs/api-trace.txt', date('Y-m-d H:i:s') . " - AFTER BOOTSTRAP\n", FILE_APPEND);
error_log('API LOADED.2');

use Luxullus\LexBridge\Api\ApiKernel;
use Luxullus\LexBridge\Constants\HttpHeader;
use Luxullus\LexBridge\Constants\ContentType;

// Log API request at entry point
error_log('[API Entry] ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . ' ' . ($_SERVER['REQUEST_URI'] ?? '/'));

// Set Content-Type header after autoloader is available
header(HttpHeader::CONTENT_TYPE . ': ' . ContentType::JSON);


try {

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

    // CORS handling: allowedOrigins entries may be literal strings or placeholders like
    // "regex:#^https://([a-z0-9-]+\.)*lukullus\.catering$#i" for wildcard domains.
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    $allowList = $allowedOrigins ?? [];
    $originAllowed = false;

    if ($origin !== null) 
    {
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
    
    // Log successful completion
    error_log(sprintf(
        '[API Success] %s %s completed',
        $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        $_SERVER['REQUEST_URI'] ?? '/'
    ));

} catch (\PDOException $e) {
    // Log detailed error for debugging
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
    
    // Return generic error to client
    http_response_code(500);
    echo json_encode([
        'statusCode' => 500,
        'isSuccess' => false,
        'error' => 'A database error occurred. Please try again later.'
    ]);
} catch (\Throwable $e) {
    // Log detailed error for debugging
    error_log(sprintf(
        '[API Error - Fatal] %s %s - Error: %s (File: %s:%d)\nStack trace:\n%s',
        $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        $_SERVER['REQUEST_URI'] ?? '/',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));
    
    // Return generic error to client
    http_response_code(500);
    echo json_encode([
        'statusCode' => 500,
        'isSuccess' => false,
        'error' => 'An error occurred. Please try again later.'
    ]);
}
