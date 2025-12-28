<?php

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
     * Get all invoices
     * 
     * @return array Formatted invoices list response data
     */
    public function getInvoices(): array
    {
        $invoices = $this->invoiceService->getInvoices();
        
        return [
            'success' => true,
            'invoices' => $invoices
        ];
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
}
