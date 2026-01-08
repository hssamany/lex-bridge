<?php

declare(strict_types=1);

// Bootstrap application

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';

use Luxullus\LexBridge\Application;

$app = new Application();
$app->run();
