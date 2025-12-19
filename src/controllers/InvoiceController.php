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
     * Create an invoice and prepare data for view
     * 
     * @param Invoice $invoice Invoice object to create
     * @return array Formatted invoice response data
     */
    public function createInvoice(Invoice $invoice): array
    {
        $response = $this->invoiceService->createInvoice($invoice);
        
        return [
            'hasError' => $response->getError() !== null,
            'errorMessage' => $response->getError(),
            'statusCode' => $response->getStatusCode(),
            'isSuccess' => $response->isSuccess(),
            'requestData' => json_encode($invoice->toArray(), JSON_PRETTY_PRINT),
            'responseBody' => htmlspecialchars($response->getBody())
        ];
    }
}
