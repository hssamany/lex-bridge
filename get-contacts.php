<?php

// Load required classes
require_once 'src/http/HttpResponse.php';
require_once 'src/http/HttpClient.php';
require_once 'src/models/Contact.php';
require_once 'src/services/ContactService.php';
require_once 'src/controllers/ContactController.php';
require_once 'config.php';

// Initialize dependencies
$apiClient = new HttpClient($apiKey, $baseUrl);

$contactService = new ContactService($apiClient);
$contactController = new ContactController($contactService);

// Get page number from request
$page = isset($_GET['page']) ? (int)$_GET['page'] : 0;

// Get contacts data
$contactsData = $contactController->getContacts($page);

// Store in session and redirect
session_start();
$_SESSION['contactsData'] = $contactsData;
header('Location: index.php');
exit;
