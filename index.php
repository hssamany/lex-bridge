<?php

declare(strict_types=1);

// Bootstrap application
require_once __DIR__ . '/bootstrap.php';

// Create and run the application
$app = new Application($apiKey, $baseUrl);
$app -> run();