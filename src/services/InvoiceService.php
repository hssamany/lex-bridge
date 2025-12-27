<?php

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
    
    public function transferInvoiceToLexware(Invoice $invoice): HttpResponse
    {
        return $this->client->post('/invoices', $invoice->toArray());
    }
}
