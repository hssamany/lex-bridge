<?php

/**
 * Service class to manage invoice operations
 */
final class InvoiceService
{
    private HttpClient $client;
    
    public function __construct(HttpClient $client)
    {
        $this->client = $client;
    }
    
    public function createInvoice(Invoice $invoice): HttpResponse
    {
        return $this->client->post('/invoices', $invoice->toArray());
    }
}
