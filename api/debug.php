<?php
header(HttpHeader::CONTENT_TYPE . ': ' . ContentType::JSON);
echo json_encode([
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
    'PATH_INFO' => $_SERVER['PATH_INFO'] ?? null,
    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? null,
    'PHP_SELF' => $_SERVER['PHP_SELF'] ?? null,
    'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? null,
    'REDIRECT_URL' => $_SERVER['REDIRECT_URL'] ?? null,
    'GET' => $_GET,
], JSON_PRETTY_PRINT);
