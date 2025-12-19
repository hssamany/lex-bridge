<?php

// Load required classes
require_once 'src/http/HttpResponse.php';
require_once 'src/http/HttpClient.php';
require_once 'src/models/Invoice.php';
require_once 'src/services/InvoiceService.php';
require_once 'src/controllers/InvoiceController.php';
require_once 'config.php';

// Initialize dependencies
$apiClient = new HttpClient($apiKey, $baseUrl);
$invoiceService = new InvoiceService($apiClient);
$invoiceController = new InvoiceController($invoiceService);

// Create invoice object
$testData = [
    "archived" => false,
    "voucherDate" => "2027-02-22T00:00:00.000+01:00",
    "address" => [
        "contactId" => "3971800d-b4d7-489c-a47c-1cb08bb38061"
    ],
    "lineItems" => [
        [
            "type" => "custom",
            "name" => "Energieriegel Testpaket",
            "quantity" => 1,
            "unitName" => "Stück",
            "unitPrice" => [
                "currency" => "EUR",
                "netAmount" => 5,
                "taxRatePercentage" => 0
            ],
            "discountPercentage" => 0
        ],
        [
            "type" => "text",
            "name" => "Strukturieren Sie Ihre Belege durch Text-Elemente.",
            "description" => "Das hilft beim Verständnis"
        ]
    ],
    "totalPrice" => [
        "currency" => "EUR"
    ],
    "taxConditions" => [
        "taxType" => "net"
    ],
    "paymentConditions" => [
        "paymentTermLabel" => "10 Tage - 3 %, 30 Tage netto",
        "paymentTermDuration" => 30,
        "paymentDiscountConditions" => [
            "discountPercentage" => 3,
            "discountRange" => 10
        ]
    ],
    "shippingConditions" => [
        "shippingDate" => "2027-04-22T00:00:00.000+02:00",
        "shippingType" => "delivery"
    ],
    "title" => "Rechnung",
    "introduction" => "Ihre bestellten Positionen stellen wir Ihnen hiermit in Rechnung",
    "remark" => "Vielen Dank für Ihren Einkauf"
];

$invoice = new Invoice($testData);

// Create invoice using controller
$invoiceData = $invoiceController->createInvoice($invoice);

// Extract data for view
extract($invoiceData);

// Load the view
require_once 'views/invoice-view.php';
