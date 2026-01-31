<?php

declare(strict_types=1);


use Luxullus\LexBridge\Api\ApiKernel;
use Luxullus\LexBridge\Constants\HttpHeader;
use Luxullus\LexBridge\Constants\ContentType;

require_once __DIR__ . '/../bootstrap.php';

// Set Content-Type header
header(HttpHeader::CONTENT_TYPE . ': ' . ContentType::JSON);
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
