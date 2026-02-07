<?php

declare(strict_types=1);

// Bootstrap application
require_once __DIR__ . '/bootstrap.php';

use Luxullus\LexBridge\Api\ApiKernel;
use Luxullus\LexBridge\Application;
use Luxullus\LexBridge\Constants\HttpHeader;
use Luxullus\LexBridge\Constants\ContentType;
use Luxullus\LexBridge\Logger;

// Determine if this is an API request
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$isApiRequest = preg_match('#/api(?:/|$)#', $requestPath) === 1;

if ($isApiRequest) {
    // Handle API requests
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
        Logger::exception($e, 'API Database Error');
        
        http_response_code(500);
        echo json_encode([
            'statusCode' => 500,
            'isSuccess' => false,
            'error' => 'A database error occurred. Please try again later.'
        ]);
    } catch (\Throwable $e) {
        Logger::exception($e, 'API Fatal Error');
        
        http_response_code(500);
        echo json_encode([
            'statusCode' => 500,
            'isSuccess' => false,
            'error' => 'An error occurred. Please try again later.'
        ]);
    }
} else {
    // Handle regular application requests
    $app = new Application();
    $app->run();
}
