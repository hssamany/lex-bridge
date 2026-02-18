<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Repositories;

use DateTime;
use Exception;
use JsonException;
use PDO;
use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Logger;
use Luxullus\LexBridge\Services\LineItemCalculator;
use Luxullus\LexBridge\Utils\UuidUtil;

/**
 * Repository for Invoice database operations
 */
final class InvoiceRepository
{
    private PDO $db;
    private string $invoiceTable;
    private string $customerTable;
    private string $lineItemTable;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->invoiceTable = \lexbridge_table('invoices');
        $this->customerTable = \lexbridge_table('customer');
        $this->lineItemTable = \lexbridge_table('invoice_line_items');
    }

    /**
     * Find invoice by ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        $sql = <<<SQL
            SELECT 
                i.*,
                c.lex_contact_id,
                c.Name AS company_name
            FROM {$this->invoiceTable} i
            LEFT JOIN {$this->customerTable} c ON i.contact_id = c.id
                WHERE i.id = :id
                LIMIT 1
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Find all invoices with optional filters.
     *
     * @param array<string, mixed> $filters
     * @param array{limit:int,offset:int} $pagination
     * @return array{items:array<int,array<string,mixed>>,total_count:int}
     */
    public function findAll(array $filters = [], array $pagination = ['limit' => 25, 'offset' => 0]): array
    {
        $selectSql = <<<SQL
            SELECT 
                i.id,
                i.voucher_date,
                i.title,
                i.status,
                i.total_gross_amount,
                i.currency,
                i.created_at,
                i.transmitted_at,
                i.contact_id,
                i.transmission_attempts,
                c.Name AS company_name,
                (SELECT COUNT(*) FROM {$this->lineItemTable} li WHERE li.invoice_id = i.id) as item_count
            SQL;

        $fromSql = <<<SQL
            FROM {$this->invoiceTable} i
            LEFT JOIN {$this->customerTable} c ON i.contact_id = c.id
        SQL;

        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "i.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['contact_id'])) {
            $where[] = "i.contact_id = :contact_id";
            $params[':contact_id'] = $filters['contact_id'];
        }

        if (!empty($filters['from_date'])) {
            $where[] = "i.voucher_date >= :from_date";
            $params[':from_date'] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $where[] = "i.voucher_date <= :to_date";
            $params[':to_date'] = $filters['to_date'];
        }

        $whereSql = '';
        if (!empty($where)) {
            $whereSql = " WHERE " . implode(" AND ", $where);
        }

        $countSql = <<<SQL
            SELECT COUNT(*) AS total
            {$fromSql}
            {$whereSql}
        SQL;

        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $totalCount = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = <<<SQL
            {$selectSql}
            {$fromSql}
            {$whereSql}
            ORDER BY i.created_at DESC
            LIMIT :limit OFFSET :offset
        SQL;

        $stmt = $this->db->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total_count' => $totalCount,
        ];
    }

    /**
     * Find line items for an invoice.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findLineItemsByInvoiceId(string $invoiceId): array
    {
        $sql = <<<SQL
            SELECT * 
            FROM {$this->lineItemTable}
            WHERE invoice_id = :invoice_id
            ORDER BY line_order ASC
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':invoice_id' => $invoiceId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    /**
     * Create invoice and line items using stored procedure.
     *
     * @param array<int, array<string, mixed>> $lineItems
     * @return array{invoice_id:int|null,error_code:int,error_message:string}
     */
    public function createInvoiceWithItems(array $lineItems): array
    {
        $lineItemIds = array_map(fn($item) => $item['line_item_id'] ?? null, $lineItems);
        $results = $this->invoiceCreator(['line_item_ids' => $lineItemIds]);
        $results['invoice_id'] = $results['createdInvoices'][0]['id'] ?? null;
        
        return $results;
    }

    public function createInvoicesForPendingLineItems(?string $filterFrom = null, ?string $filterTo = null): array{
        $deliveryDateFrom = $filterFrom ?? date('Y-m-d');
        $deliveryDateTo = $filterTo ?? date('Y-m-d'); // Default to today if not provided
        return $this->invoiceCreator(['from' => $deliveryDateFrom, 'to' => $deliveryDateTo]);
    }

    /**
     * Create invoices for all customers with pending line items (not yet assigned to an invoice).
     *
     * @param string|null $filterFrom
     * @param string|null $filterTo
     * @return array{createdInvoices:array<int,array<string,mixed>>,skippedLineItems:array<int,string>,error?:string}
     */
    private function invoiceCreator(array $filters): array
    {
        $deliveryDateTo = $filters['to'] ?? null;
        $deliveryDateFrom = $filters['from'] ?? null;
        $lineItemIds = $filters['line_item_ids'] ?? null;
        
        // Build WHERE clause and params
        $where = [];
        $params = [];

        if (!empty($lineItemIds)) {

            $placeholders = implode(',', array_fill(0, count($lineItemIds), '?'));
            
            // Use line item IDs filter
            $where[] = "li.id IN ($placeholders)";

            // Ensure all line item IDs are integers
            $params = array_values($lineItemIds??[]);

        } else {
            // Use date range filter
            $where[] = "li.invoice_id IS NULL";

            if ($deliveryDateFrom !== null) {
                $where[] = "li.order_delivery_date >= ?";
                $params[] = $deliveryDateFrom;
            }
            if ($deliveryDateTo !== null) {
                $where[] = "li.order_delivery_date <= ?";
                $params[] = $deliveryDateTo;
            }
        }

        $whereSql = implode(' AND ', $where);

        $sql = <<<SQL
            SELECT 
                li.id,
                li.invoice_id,
                li.order_id,
                li.article_id,
                li.quantity,
                li.unit_name,
                li.net_amount,
                li.gross_amount,
                li.line_total_net,
                li.line_total_gross,
                li.currency,
                li.order_delivery_date,
                c.id AS customer_id,
                c.Nummer AS customer_number

            FROM {$this->lineItemTable} li
            LEFT JOIN {$this->customerTable} c ON li.customer_id = c.id
            WHERE $whereSql
        SQL;        

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $lineItemRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group line items by customer ID
        $lineItemsByCustomer = [];
        foreach ($lineItemRows as $row) {

            $customerId = $row['customer_id'];
            if ($customerId === null) {
                Logger::info("Line item {$row['id']} has no matching customer for customer_number: {$row['customer_number']}", 'InvoiceRepository');
                continue;
            }

            $lineItemsByCustomer[$customerId][] = $row;
        }

        $createdInvoices = [];
        $skippedLineItems = [];

        foreach ($lineItemsByCustomer as $customerId => $lineItems) {
            
            if (empty($lineItems)) {
                continue;
            }

            try {

                $this->db->beginTransaction(); 
                {

                    // Calculate totals with fallback to quantity * unit price if line totals not set
                    $currency = $lineItems[0]['currency'] ?? null;
                    $totalNet = $this->sumLineItemTotals($lineItems, 'line_total_net', 'net_amount');
                    $totalGross = $this->sumLineItemTotals($lineItems, 'line_total_gross', 'gross_amount');
                    
                    // Find earliest shipping date from line items
                    $deliveryDates = array_column($lineItems, 'order_delivery_date');
                    $shippingDate = !empty($deliveryDates) ? min($deliveryDates) : null;
                    
                    // Insert invoice
                    $invoiceSql = <<<SQL
                        INSERT INTO {$this->invoiceTable} 
                        (id, contact_id, voucher_date, shipping_date, total_net_amount, total_gross_amount, currency, status, created_at) 
                        VALUES 
                        (:id, :contact_id, :voucher_date, :shipping_date, :total_net_amount, :total_gross_amount, :currency, :status, :created_at)
                    SQL;
                    
                    $newInvoiceId = UuidUtil::generateUuid();
                    $invoiceDate = date('Y-m-d');
                    
                    $invoiceStmt = $this->db->prepare($invoiceSql);
                    $invoiceStmt->bindValue(':status', 'draft', PDO::PARAM_STR);
                    $invoiceStmt->bindValue(':id', $newInvoiceId, PDO::PARAM_STR);
                    $invoiceStmt->bindValue(':contact_id', $customerId, PDO::PARAM_INT);
                    $invoiceStmt->bindValue(':voucher_date', $invoiceDate, PDO::PARAM_STR);
                    $invoiceStmt->bindValue(':total_net_amount', $totalNet, PDO::PARAM_STR);
                    $invoiceStmt->bindValue(':total_gross_amount', $totalGross, PDO::PARAM_STR);
                    $invoiceStmt->bindValue(':created_at', date('Y-m-d H:i:s'), PDO::PARAM_STR);
                    $invoiceStmt->bindValue(':shipping_date', $shippingDate, $shippingDate === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                    $invoiceStmt->bindValue(':currency', $currency, $currency === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                    $invoiceStmt->execute();
                    

                    if (!$newInvoiceId) {
                        
                        $failedLineItemIds = array_filter(array_map(fn($item) => (string)($item['id'] ?? ''), $lineItems));
                        $skippedLineItems = array_merge($skippedLineItems, $failedLineItemIds);
                        $this->db->rollBack();

                        continue;
                    }

                    // Batch update line items to reference this invoice
                    $lineItemIds = array_filter(array_column($lineItems, 'id'), fn($id) => !empty($id));
                    
                    if (!empty($lineItemIds)) {

                        // Build parameterized placeholders for UUID strings
                        $this->updateLineItemsInvoiceId($newInvoiceId, $lineItemIds);

                    } else {

                        $failedLineItemIds = array_filter(array_map(fn($item) => (string)($item['id'] ?? ''), $lineItems));
                        $skippedLineItems = array_merge($skippedLineItems, $failedLineItemIds);
                        $this->db->rollBack();

                        continue;
                    }

                    $createdInvoices[] = [
                        'id' => $newInvoiceId,
                        'invoice_id' => $newInvoiceId,
                        'contact_id' => $customerId,
                        'voucher_date' => $invoiceDate,
                        'total_net_amount' => $totalNet,
                        'total_gross_amount' => $totalGross,
                        'currency' => $currency,
                        'line_item_count' => count($lineItems),
                    ];

                } $this->db->commit();

            } catch (\Throwable $exception) {

                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }

                Logger::exception($exception, "InvoiceRepository - Create Invoice for Customer $customerId failed");

                $skippedLineItems = array_merge($skippedLineItems, $failedLineItemIds ?? []);
                continue;
            }
        }

        return [
            'createdInvoices' => $createdInvoices,  
            'skippedLineItems' => $skippedLineItems,
        ];
    }

    private function updateLineItemsInvoiceId(string $invoiceId, array $lineItemIds): void
    {
        $placeholders = [];
        $updateParams = [':invoice_id' => $invoiceId];

        foreach ($lineItemIds as $index => $lineItemId) {
            $paramName = ":line_item_id_{$index}";
            $placeholders[] = $paramName;
            $updateParams[$paramName] = (string)$lineItemId;
        }

        $inClause = implode(',', $placeholders);
        $updateSql = <<<SQL
            UPDATE {$this->lineItemTable} 
            SET invoice_id = :invoice_id 
            WHERE id IN ({$inClause})
        SQL;

        $updateStmt = $this->db->prepare($updateSql);

        foreach ($updateParams as $param => $value) {
            $updateStmt->bindValue($param, $value, PDO::PARAM_STR);
        }

        $updateStmt->execute();
    }

    /**
     * Update invoice after successful transmission.
     */
    public function updateAfterTransmission(string $invoiceId, array $lexwareResponse): bool
    {
        try {

            $this->db->beginTransaction();

            $lexCreatedDate = isset($lexwareResponse['createdDate'])
                ? (new DateTime($lexwareResponse['createdDate']))->format('Y-m-d H:i:s')
                : null;

            $lexUpdatedDate = isset($lexwareResponse['updatedDate'])
                ? (new DateTime($lexwareResponse['updatedDate']))->format('Y-m-d H:i:s')
                : null;

            $sql = <<<SQL
                UPDATE {$this->invoiceTable} 
                SET status = 'transmitted',
                    lex_id = :lex_id,
                    lex_resource_uri = :lex_resource_uri,
                    lex_version = :lex_version,
                    lex_created_date = :lex_created_date,
                    lex_updated_date = :lex_updated_date,
                    transmitted_at = :transmitted_at,
                    last_error_message = NULL,
                    last_error_code = NULL
                WHERE id = :id
            SQL;

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':id' => $invoiceId,
                ':lex_id' => $lexwareResponse['id'] ?? null,
                ':lex_resource_uri' => $lexwareResponse['resourceUri'] ?? null,
                ':lex_version' => $lexwareResponse['version'] ?? 0,
                ':lex_created_date' => $lexCreatedDate,
                ':lex_updated_date' => $lexUpdatedDate,
                ':transmitted_at' => date('Y-m-d H:i:s')
            ]);

            $this->db->commit();

            return true;

        } catch (Exception $e) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            Logger::exception($e, 'InvoiceRepository - Update After Transmission');
            return false;
        }
    }

    /**
     * Update invoice with transmission error.
     */
    public function updateWithError(string $invoiceId, string $errorMessage, ?string $errorCode = null): bool
    {
        try {
            $sql = <<<SQL
                UPDATE {$this->invoiceTable} 
                SET status = 'transmission_error',
                    last_error_message = :error_message,
                    last_error_code = :error_code,
                    transmission_attempts = transmission_attempts + 1,
                    last_transmission_attempt = :last_attempt
                WHERE id = :id
            SQL;

            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                ':id' => $invoiceId,
                ':error_message' => $errorMessage,
                ':error_code' => $errorCode,
                ':last_attempt' => date('Y-m-d H:i:s')
            ]);

        } catch (Exception $e) {
            Logger::exception($e, 'InvoiceRepository');
            return false;
        }
    }

    /**
     * Update invoice status.
     */
    public function updateStatus(string $invoiceId, string $status): bool
    {
        try {

            $sql = <<<SQL
                UPDATE {$this->invoiceTable} 
                SET status = :status
                WHERE id = :id
            SQL;

            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                ':id' => $invoiceId,
                ':status' => $status
            ]);

        } catch (Exception $e) {
            Logger::exception($e, 'InvoiceRepository - Update Status');
            return false;
        }
    }

    /**
     * Sum line item totals with fallback calculation using LineItemCalculator.
     *
     * @param array<int, array<string, mixed>> $lineItems
     * @param string $totalField Field containing pre-calculated total (e.g., 'line_total_gross')
     * @param string $unitField Field containing unit price (e.g., 'gross_amount')
     * @return float
     */
    private function sumLineItemTotals(array $lineItems, string $totalField, string $unitField): float
    {
        $calculator = new LineItemCalculator();
        
        return array_reduce($lineItems, function($carry, $item) use ($calculator, $totalField, $unitField) {
            // Use pre-calculated total if available
            $lineTotal = $item[$totalField] ?? null;
            
            // Fallback: calculate using quantity * unit price with precision handling
            if ($lineTotal === null && isset($item['quantity'], $item[$unitField])) {
                $lineTotal = $calculator->calculateLineTotal
                (
                    (float)$item['quantity'],
                    $item[$unitField]
                );
            }
            
            return $carry + ($lineTotal ?? 0.0);
        }, 0.0);
    }
}
