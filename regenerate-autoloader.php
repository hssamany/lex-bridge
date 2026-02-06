<?php
// Run this once via browser to regenerate autoloader on Strato
// Delete this file after running

require_once __DIR__ . '/vendor/autoload.php';

echo "Running composer dump-autoload...\n";

$output = shell_exec('cd ' . __DIR__ . ' && composer dump-autoload 2>&1');

if ($output) {
    echo "<pre>$output</pre>";
} else {
    echo "Command executed (no output)\n";
}

echo "\nNow delete this file and test /api/contacts";
