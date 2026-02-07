<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use Luxullus\LexBridge\Http\HttpClient;
use Luxullus\LexBridge\Http\HttpResponse;
use Luxullus\LexBridge\Logger;
use Luxullus\LexBridge\Models\Invoice;
use Luxullus\LexBridge\Repositories\InvoiceRepository;
use Luxullus\LexBridge\Http\HttpStatus;
use Exception;

/**
 * Service class to manage invoice operations
 */
final class InvoiceService {

    private HttpClient $client;
    private InvoiceRepository $invoiceRepository;
    
    public function __construct(HttpClient $client, InvoiceRepository $invoiceRepository)
    {
        $this->client = $client;
        $this->invoiceRepository = $invoiceRepository;
    }
    
    /**
     * Get all invoices with optional filters
     * @param array $filters Optional filters (customer_id, status, from_date, to_date)
     * @return Invoice[]
     */
    public function getInvoices(array $filters = []): array
    {
        return $this->invoiceRepository->findAll($filters);
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
    /**
     * Transfer a single invoice to Lexware by ID.
     * Fetches invoice from database, updates status, and sends to Lexware.
     *
     * @param string $invoiceId Invoice ID
     * @return array{
     *   response: HttpResponse,
     *   invoice: array|null
     * }
     */
    public function transferInvoiceById(string $invoiceId): array
    {
        // Fetch invoice from database with line items
        $invoice = $this->invoiceRepository->findById($invoiceId);

        if (!$invoice) {
            // Log not found
            Logger::log('InvoiceService', 'Invoice not found: %s', $invoiceId);
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
                Logger::log('InvoiceService', 'Lexware transfer failed for invoice %s: %s', $invoiceId, $errorMessage);
            }

            return [
                'response' => $response,
                'invoice' => $invoice->toArray()
            ];

        } catch (Exception $e) {
            $this->invoiceRepository->updateWithError($invoiceId, $e->getMessage());
            Logger::exception($e, 'InvoiceService - Lexware Transfer');

            return [
                'response' => new HttpResponse(HttpStatus::INTERNAL_SERVER_ERROR, null, $e->getMessage()),
                'invoice' => $invoice->toArray()
            ];
        }
    }

    /**
     * Create a new invoice with line items
     *
     * @param int $customerId
     * @param string|null $currency
     * @param array $lineItems (array of ["article_id" => int, "quantity" => float])
     * @return array ["invoice_id" => int, "error_code" => int, "error_message" => string]
     */
    public function createInvoiceWithItems(int $customerId, ?string $currency, array $lineItems): array
    {
        $normalizedCurrency = null;
        if ($currency !== null) {
            $trimmed = trim($currency);
            if ($trimmed !== '') {
                $normalizedCurrency = strtoupper(substr($trimmed, 0, 3));
            }
        }

        return $this->invoiceRepository->createInvoiceWithItems($customerId, $normalizedCurrency, $lineItems);
    }
}
