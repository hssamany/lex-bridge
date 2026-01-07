<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use Luxullus\LexBridge\Api\HttpClient;
use Luxullus\LexBridge\Api\HttpResponse;
use Luxullus\LexBridge\Models\Invoice;
use Luxullus\LexBridge\Repositories\InvoiceRepository;
use Luxullus\LexBridge\Utils\HttpStatus;
use Exception;

/**
 * Service class to manage invoice operations
 */
final class InvoiceService
{
    private HttpClient $client;
    private InvoiceRepository $invoiceRepository;
    
    public function __construct(HttpClient $client, InvoiceRepository $invoiceRepository)
    {
        $this->client = $client;
        $this->invoiceRepository = $invoiceRepository;
    }
    
    /**
     * Get all invoices
     * @return Invoice[]
     */
    public function getInvoices(): array
    {
        return $this->invoiceRepository->findAll();
    }
    
    /**
     * Transfer invoice to Lexware API
     */
    public function transferInvoiceToLexware(Invoice $invoice): HttpResponse
    {
        $payload = $invoice->toLexwarePayload();        
        return $this->client->post('/invoices', $payload);
    }
    
    /**
     * Transfer a single invoice to Lexware by ID
     * Fetches invoice from database, updates status, and sends to Lexware
     * 
     * @param string $invoiceId Invoice ID
     * @return array Result with response and invoice data
     */
    public function transferInvoiceById(string $invoiceId): array
    {
        // Fetch invoice from database with line items
        $invoice = $this->invoiceRepository->findById($invoiceId);
        
        if (!$invoice) {
            // Return a mock error response for invoice not found
            return [
                'response' => new HttpResponse(404, null, 'Invoice not found'),
                'invoice' => null
            ];
        }
        
        // Update status to 'transmitting'
        $this->invoiceRepository->updateStatus($invoiceId, 'transmitting');
        
        try {
            // Transfer to Lexware
            $response = $this->transferInvoiceToLexware($invoice);
            
            if ($response->isSuccess()) {
                // Update invoice with Lexware response
                $lexwareData = json_decode($response->getBody(), true);
                $this->invoiceRepository->updateAfterTransmission($invoiceId, $lexwareData);
            } else {
                // Update with error
                $errorMessage = $response->getError() ?? 'Unknown error';
                $this->invoiceRepository->updateWithError($invoiceId, $errorMessage, (string)$response->getStatusCode());
            }
            
            return [
                'response' => $response,
                'invoice' => $invoice->toArray()
            ];
            
        } catch (Exception $e) {
            $this->invoiceRepository->updateWithError($invoiceId, $e->getMessage());
            
            // Return error response
            return [
                'response' => new HttpResponse(HttpStatus::INTERNAL_SERVER_ERROR, null, $e->getMessage()),
                'invoice' => $invoice->toArray()
            ];
        }
    }
}
