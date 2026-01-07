<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';


// Set Content-Type header
header(HttpHeader::CONTENT_TYPE . ': ' . ContentType::JSON);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
} else {
    header('Access-Control-Allow-Origin: http://localhost'); // fallback or omit for stricter security
}

$kernel = new ApiKernel();
$kernel->handle();
