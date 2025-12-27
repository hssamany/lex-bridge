<?php

declare(strict_types=1);

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Set to '1' for development
ini_set('log_errors', '1');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load required classes
require_once __DIR__ . '/src/http/HttpResponse.php';
require_once __DIR__ . '/src/http/HttpClient.php';
require_once __DIR__ . '/src/database/Database.php';
require_once __DIR__ . '/src/models/Contact.php';
require_once __DIR__ . '/src/models/Invoice.php';
require_once __DIR__ . '/src/models/InvoiceLineItem.php';
require_once __DIR__ . '/src/services/ContactService.php';
require_once __DIR__ . '/src/services/InvoiceService.php';
require_once __DIR__ . '/src/controllers/ContactController.php';
require_once __DIR__ . '/src/controllers/InvoiceController.php';
require_once __DIR__ . '/src/repositories/ContactRepository.php';
require_once __DIR__ . '/src/repositories/InvoiceRepository.php';
require_once __DIR__ . '/src/Application.php';
require_once __DIR__ . '/config.php';

// Validate configuration
if (empty($apiKey) || empty($baseUrl)) {
    throw new Exception('API configuration missing. Please check config.php');
}
