<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use DateTime;
use Exception;
use Luxullus\LexBridge\Http\HttpClient;
use Luxullus\LexBridge\Http\HttpResponse;
use Luxullus\LexBridge\Http\HttpStatus;
use Luxullus\LexBridge\Logger;
use Luxullus\LexBridge\Models\Invoice;
use Luxullus\LexBridge\Models\InvoiceLineItem;
use Luxullus\LexBridge\Repositories\InvoiceRepository;
use Luxullus\LexBridge\Services\Pagination;

/**
 * Service class to manage invoice operations
 */
final class InvoiceService
{
    private HttpClient $client;
    private InvoiceRepository $repository;

    public function __construct(HttpClient $client, InvoiceRepository $repository)
    {
        $this->client = $client;
        $this->repository = $repository;
    }

    /**
     * Get all invoices with optional filters and enriched data.
     *
     * @param array<string, mixed> $filters Optional filters (contact_id, status, from_date, to_date)
     * @param array<string, mixed> $pagination
     * @return array{isSuccess:bool,invoices?:array<int,array<string,mixed>>,error?:string}
     */
    public function getInvoices(array $filters = [], array $pagination = []): array
    {
        $validationErrors = $this->validateFilters($filters);
        if (!empty($validationErrors)) {
            return [
                'isSuccess' => false,
                'error' => implode('; ', $validationErrors)
            ];
        }

        $validatedFilters = $this->validateAndNormalizeFilters($filters);
        $paginationState = Pagination::normalize($pagination);
        $result = $this->repository->findAll($validatedFilters, $paginationState);
        $rows = $result['items'] ?? [];
        $totalCount = (int) ($result['total_count'] ?? 0);

        return [
            'isSuccess' => true,
            'invoices' => $this->enrichInvoiceList($rows),
            'total_count' => $totalCount,
            'page' => $paginationState['page'],
            'page_size' => $paginationState['page_size'],
            'total_pages' => Pagination::totalPages($totalCount, $paginationState['page_size']),
        ];
    }

    /**
     * Get invoice by ID with full details.
     */
    public function getInvoiceById(string $invoiceId): ?Invoice
    {
        $row = $this->repository->findById($invoiceId);

        if ($row === null) {
            return null;
        }

        return $this->transformRowToInvoice($row);
    }

    /**
     * Transfer invoice to Lexware API.
     */
    public function transferInvoiceToLexware(Invoice $invoice): HttpResponse
    {
        $payload = $invoice->toLexwarePayload();
        return $this->client->post('/invoices', $payload);
    }

    /**
     * Transfer a single invoice to Lexware by ID.
     * Fetches invoice from database, updates status, and sends to Lexware.
     *
     * @param string $invoiceId Invoice ID
     * @return array{response:HttpResponse,invoice:array<string,mixed>|null}
     */
    public function transferInvoiceById(string $invoiceId): array
    {
        $row = $this->repository->findById($invoiceId);

        if ($row === null) {
            Logger::info('Invoice not found: ' . $invoiceId, 'InvoiceService');
            
            return [
                'response' => new HttpResponse(404, null, 'Invoice not found'),
                'invoice' => null
            ];
        }

        $invoice = $this->transformRowToInvoice($row);

        $this->repository->updateStatus($invoiceId, 'transmitting');

        try {
            $response = $this->transferInvoiceToLexware($invoice);

            if ($response->isSuccess()) {
                $this->handleSuccessfulTransmission($invoiceId, $response);
            } else {
                $this->handleTransmissionError($invoiceId, $response);
            }

            Logger::info(sprintf(
                'Invoice transmission completed - ID: %s, Success: %s, Status: %d',
                $invoiceId,
                $response->isSuccess() ? 'true' : 'false',
                $response->getStatusCode()
            ), 'InvoiceService');

            return [
                'response' => $response,
                'invoice' => $invoice->toArray()
            ];
        } catch (Exception $e) {
            $this->handleTransmissionException($invoiceId, $e);

            return [
                'response' => new HttpResponse(
                    HttpStatus::INTERNAL_SERVER_ERROR,
                    null,
                    $e->getMessage()
                ),
                'invoice' => $invoice->toArray()
            ];
        }
    }

    /**
     * Create a new invoice with line items.
     *
     * @param int $customerId
     * @param string|null $currency
     * @param array<int, array<string, mixed>> $lineItems
     * @return array{invoice_id:int|null,error_code:int,error_message:string}
     */
    public function createInvoiceWithItems(
        int $customerId,
        ?string $currency,
        array $lineItems
    ): array {
        $normalizedCurrency = $this->normalizeCurrency($currency);
        $validatedLineItems = $this->validateLineItems($lineItems);

        if (!empty($validatedLineItems['errors'])) {
            return [
                'invoice_id' => null,
                'error_code' => -1,
                'error_message' => 'Validation errors: ' . implode('; ', $validatedLineItems['errors'])
            ];
        }

        $result = $this->repository->createInvoiceWithItems(
            $customerId,
            $normalizedCurrency,
            $validatedLineItems['items']
        );

        if ($result['invoice_id'] !== null) {
            Logger::info(sprintf(
                'Invoice created - ID: %s, Customer: %d, Items: %d',
                $result['invoice_id'],
                $customerId,
                count($validatedLineItems['items'])
            ), 'InvoiceService');
        } else {
            Logger::info(sprintf(
                'Invoice creation failed - Customer: %d, Error: %s',
                $customerId,
                $result['error_message']
            ), 'InvoiceService');
        }

        return $result;
    }

    /**
     * Create invoices for pending line items using stored procedure.
     *
     * @param string|null $voucherDate
     * @return array{createdInvoices:array<int,array<string,mixed>>,skippedLineItems:array<int,string>,error:string|null,summary:array<string,int>}
     */
    public function createInvoicesForPendingLineItems(?string $voucherDate = null): array
    {
        $normalizedDate = $this->normalizeVoucherDate($voucherDate);

        $result = $this->repository->createInvoicesForPendingLineItemsViaStoredProc($normalizedDate);

        $summary = $this->buildInvoiceCreationSummary($result);

        Logger::info('Pending invoices creation completed: ' . json_encode($summary), 'InvoiceService');

        return array_merge($result, ['summary' => $summary]);
    }

    /**
     * Validate filters and return errors.
     *
     * @param array<string, mixed> $filters
     * @return array<int, string>
     */
    private function validateFilters(array $filters): array
    {
        $errors = [];

        if (isset($filters['start_date'])) {
            $date = $this->validateDate($filters['start_date']);
            if ($date === null) {
                $errors[] = 'Invalid start_date format (expected: YYYY-MM-DD)';
            }
        }

        if (isset($filters['end_date'])) {
            $date = $this->validateDate($filters['end_date']);
            if ($date === null) {
                $errors[] = 'Invalid end_date format (expected: YYYY-MM-DD)';
            }
        }

        if (isset($filters['status']) && is_string($filters['status'])) {
            $validStatuses = ['draft', 'transmitting', 'transmitted', 'transmission_error'];
            if (!in_array($filters['status'], $validStatuses, true)) {
                $errors[] = 'Invalid status (allowed: ' . implode(', ', $validStatuses) . ')';
            }
        }

        return $errors;
    }

    /**
     * Validate and normalize filter parameters.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function validateAndNormalizeFilters(array $filters): array
    {
        $normalized = [];

        if (isset($filters['contact_id']) && is_numeric($filters['contact_id'])) {
            $normalized['contact_id'] = (int) $filters['contact_id'];
        }

        if (isset($filters['status']) && is_string($filters['status'])) {
            $status = trim($filters['status']);
            if ($status !== '') {
                $normalized['status'] = $status;
            }
        }

        if (isset($filters['from_date'])) {
            $date = $this->validateDate($filters['from_date']);
            if ($date !== null) {
                $normalized['from_date'] = $date;
            }
        }

        if (isset($filters['to_date'])) {
            $date = $this->validateDate($filters['to_date']);
            if ($date !== null) {
                $normalized['to_date'] = $date;
            }
        }

        return $normalized;
    }

    /**
     * Validate date string.
     */
    private function validateDate(mixed $date): ?string
    {
        if (!is_string($date)) {
            return null;
        }

        $date = trim($date);
        if ($date === '') {
            return null;
        }

        try {
            $dt = new DateTime($date);
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Enrich invoice list with calculated fields.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function enrichInvoiceList(array $rows): array
    {
        return array_map(function (array $row): array {
            $status = $row['status'] ?? 'draft';
            $itemCount = isset($row['item_count']) ? (int) $row['item_count'] : 0;
            $totalGross = isset($row['total_gross_amount']) ? (float) $row['total_gross_amount'] : null;
            $currency = $row['currency'] ?? 'EUR';

            return [
                'id' => $row['id'] ?? null,
                'voucher_date' => $row['voucher_date'] ?? null,
                'title' => $row['title'] ?? 'Rechnung',
                'status' => $status,
                'total_gross_amount' => $totalGross,
                'currency' => $currency,
                'created_at' => $row['created_at'] ?? null,
                'transmitted_at' => $row['transmitted_at'] ?? null,
                'contact_id' => isset($row['contact_id']) ? (int) $row['contact_id'] : null,
                'transmission_attempts' => isset($row['transmission_attempts']) 
                    ? (int) $row['transmission_attempts'] 
                    : 0,
                'company_name' => $row['company_name'] ?? null,
                'item_count' => $itemCount,
                'line_item_count' => $itemCount, // Alias for consistency
                'display_status' => $this->formatStatusDisplay($status),
                'formatted_total' => $this->formatCurrency($totalGross, $currency),
            ];
        }, $rows);
    }

    /**
     * Format status for display.
     */
    private function formatStatusDisplay(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'transmitting' => 'Transmitting',
            'transmitted' => 'Transmitted',
            'transmission_error' => 'Error',
            default => ucfirst($status)
        };
    }

    /**
     * Format currency amount.
     */
    private function formatCurrency(?float $amount, string $currency): ?string
    {
        if ($amount === null) {
            return null;
        }

        $formatted = number_format($amount, 2, ',', '.');

        return match ($currency) {
            'EUR' => $formatted . ' €',
            'USD' => '$' . $formatted,
            'GBP' => '£' . $formatted,
            default => $formatted . ' ' . $currency
        };
    }

    /**
     * Transform database row to Invoice model with line items.
     *
     * @param array<string, mixed> $row
     */
    private function transformRowToInvoice(array $row): Invoice
    {
        $invoice = Invoice::fromDatabase($row);

        $lineItemRows = $this->repository->findLineItemsByInvoiceId($invoice->id);
        $invoice->lineItems = $this->transformLineItems($lineItemRows);

        return $invoice;
    }

    /**
     * Transform line item rows to models.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, InvoiceLineItem>
     */
    private function transformLineItems(array $rows): array
    {
        return array_map(
            fn(array $row) => InvoiceLineItem::fromDatabase($row),
            $rows
        );
    }

    /**
     * Handle successful invoice transmission.
     */
    private function handleSuccessfulTransmission(string $invoiceId, HttpResponse $response): void
    {
        $lexwareData = json_decode($response->getBody(), true) ?: [];

        $this->repository->updateAfterTransmission($invoiceId, $lexwareData);

        Logger::info(sprintf(
            'Invoice successfully transmitted - ID: %s, Lexware ID: %s',
            $invoiceId,
            $lexwareData['id'] ?? 'N/A'
        ), 'InvoiceService');
    }

    /**
     * Handle transmission error.
     */
    private function handleTransmissionError(string $invoiceId, HttpResponse $response): void
    {
        $errorMessage = $response->getError() ?? 'Unknown error';
        $errorCode = (string) $response->getStatusCode();

        $this->repository->updateWithError($invoiceId, $errorMessage, $errorCode);

        Logger::info(sprintf(
            'Invoice transmission failed - ID: %s, Status: %s, Error: %s',
            $invoiceId,
            $errorCode,
            $errorMessage
        ), 'InvoiceService');
    }

    /**
     * Handle transmission exception.
     */
    private function handleTransmissionException(string $invoiceId, Exception $e): void
    {
        $this->repository->updateWithError($invoiceId, $e->getMessage());

        Logger::exception($e, 'InvoiceService - Lexware Transfer [Invoice: ' . $invoiceId . ']');
    }

    /**
     * Normalize currency code.
     */
    private function normalizeCurrency(?string $currency): ?string
    {
        if ($currency === null) {
            return null;
        }

        $trimmed = trim($currency);
        if ($trimmed === '') {
            return null;
        }

        return strtoupper(substr($trimmed, 0, 3));
    }

    /**
     * Validate line items array.
     *
     * @param array<int, array<string, mixed>> $lineItems
     * @return array{items:array<int,array<string,mixed>>,errors:array<int,string>}
     */
    private function validateLineItems(array $lineItems): array
    {
        $validated = [];
        $errors = [];

        if (empty($lineItems)) {
            $errors[] = 'Invoice must have at least one line item (no line items provided)';
            return ['items' => [], 'errors' => $errors];
        }

        foreach ($lineItems as $index => $item) {
            if (!is_array($item)) {
                $errors[] = "Line item {$index} is not an array";
                continue;
            }

            if (!isset($item['article_id']) || !is_numeric($item['article_id'])) {
                $errors[] = "Line item {$index} missing valid article_id";
                continue;
            }

            if (!isset($item['quantity']) || !is_numeric($item['quantity'])) {
                $errors[] = "Line item {$index} missing valid quantity";
                continue;
            }

            $quantity = (float) $item['quantity'];
            if ($quantity <= 0) {
                $errors[] = "Line item {$index} must have positive quantity (got: {$quantity})";
                continue;
            }

            $lineItemId = $item['line_item_id'] ?? $item['id'] ?? null;
            $validatedItem = [
                'article_id' => (int) $item['article_id'],
                'quantity' => $quantity
            ];

            if ($lineItemId !== null && $lineItemId !== '') {
                $validatedItem['line_item_id'] = (string) $lineItemId;
            }

            $validated[] = $validatedItem;
        }

        return [
            'items' => $validated,
            'errors' => $errors
        ];
    }

    /**
     * Normalize voucher date.
     */
    private function normalizeVoucherDate(?string $voucherDate): ?string
    {
        if ($voucherDate === null) {
            return null;
        }

        $trimmed = trim($voucherDate);
        if ($trimmed === '') {
            return null;
        }

        try {
            $dt = new DateTime($trimmed);
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Build summary statistics for invoice creation.
     *
     * @param array{createdInvoices:array<int,array<string,mixed>>,skippedLineItems:array<int,string>,error?:string} $result
     * @return array<string, int>
     */
    private function buildInvoiceCreationSummary(array $result): array
    {
        return [
            'invoices_created' => count($result['createdInvoices']),
            'line_items_skipped' => count($result['skippedLineItems']),
            'has_error' => isset($result['error']) ? 1 : 0
        ];
    }
}
