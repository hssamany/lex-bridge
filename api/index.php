<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../api/ApiRouter.php';
require_once __DIR__ . '/ApiKernel.php';

header(HttpHeader::CONTENT_TYPE . ': ' . ContentType::JSON);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

$kernel = new ApiKernel();
$kernel->handle();
