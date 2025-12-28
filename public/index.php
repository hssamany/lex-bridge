<?php

declare(strict_types=1);

// Bootstrap application
require_once __DIR__ . '/../bootstrap.php';

// Create and run the application (simplified - no dependencies needed)
$app = new Application();
$app->run();
