<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Controllers;
use Luxullus\LexBridge\Services\InvoiceService;


/**
 * Controller class to handle invoice-related requests
 */
final class InvoiceController
{
    
    private InvoiceService $invoiceService;
    
    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }
    
    /**
     * Get all invoices with optional filters
     * 
     * @param array $filters Optional filters (customer_id, status, from_date, to_date)
     * @return array Formatted invoices list response data
     */
    public function getInvoices(array $filters = []): array
    {
        return $this->invoiceService->getInvoices($filters);
    }
    
    /**
     * Transfer a single invoice to Lexware by ID
     * 
     * @param string $invoiceId Invoice ID to transfer
     * @return array Transfer result with statusCode, isSuccess, error, and invoice data
     */
    public function transferInvoiceToLexware(string $invoiceId): array
    {
        $result = $this->invoiceService->transferInvoiceById($invoiceId);
        $response = $result['response'];
        $invoice = $result['invoice'];
        
        return [
            'statusCode' => $response->getStatusCode(),
            'isSuccess' => $response->isSuccess(),
            'error' => $response->getError(),
            'invoice' => $invoice
        ];
    }

    /**
     * Create a new invoice with line items
     *
     * @param int $customerId
     * @param string|null $currency
     * @param array $lineItems
     * @return array Result from InvoiceService
     */
    public function createInvoiceWithItems(int $customerId, ?string $currency, array $lineItems): array
    {
        return $this->invoiceService->createInvoiceWithItems($customerId, $currency, $lineItems);
    }
}
