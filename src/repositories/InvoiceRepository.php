<?php

declare(strict_types=1);


namespace Luxullus\LexBridge\Repositories;

use PDO;
use DateTime;
use Exception;
use JsonException;
use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Logger;
use Luxullus\LexBridge\Models\Invoice;
use Luxullus\LexBridge\Models\InvoiceLineItem;

/**
 * Repository for Invoice database operations
 */
class InvoiceRepository
{
    private \PDO $db;
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
     * Find invoice by ID with line items
     */
    public function findById(string $id): ?Invoice
    {
        $sql = "SELECT 
                    i.*,
                    c.lex_contact_id,
                    c.Name AS company_name
                FROM {$this->invoiceTable} i
                LEFT JOIN {$this->customerTable} c ON i.contact_id = c.id
                WHERE i.id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }
        
        $invoice = Invoice::fromDatabase($row);
        
        // Load line items
        $invoice->lineItems = $this->findLineItemsByInvoiceId($id);
        
        return $invoice;
    }
    
    /**
     * Find all invoices with optional filters
     */
    public function findAll(array $filters = []): array
    {
        $sql = "SELECT 
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
                FROM {$this->invoiceTable} i
                LEFT JOIN {$this->customerTable} c ON i.contact_id = c.id";
        
        $where = [];
        $params = [];
        
        // Apply filters
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
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY i.voucher_date DESC, i.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        // Return raw arrays to preserve calculated fields like item_count and company_name
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find invoices by contact ID
     */
    public function findByContactId(int $contactId): array
    {
        return $this->findAll(['contact_id' => $contactId]);
    }
    
    /**
     * Find invoices by status
     */
    public function findByStatus(string $status): array
    {
        return $this->findAll(['status' => $status]);
    }
    
    /**
     * Find line items for an invoice
     */
    public function findLineItemsByInvoiceId(string $invoiceId): array
    {
        $sql = "SELECT * 
            FROM {$this->lineItemTable}
                WHERE invoice_id = :invoice_id
                ORDER BY line_order ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':invoice_id' => $invoiceId]);
        
        $lineItems = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lineItems[] = InvoiceLineItem::fromDatabase($row);
        }
        
        return $lineItems;
    }    

    /**
     * Creates invoice and its line items using the stored procedure.
     *
     * @param int $customerId
     * @param string|null $currency
     * @param array $lineItems (array of ["article_id" => int, "quantity" => float])
     * @return array ["invoice_id" => int, "error_code" => int, "error_message" => string]
     */
    public function createInvoiceWithItems(int $customerId, ?string $currency, array $lineItems)
    {
        try {

            $stmt = $this->db->prepare('CALL create_invoice_from_selection(:customer_id, :currency, :line_items, @invoice_id, @error_code, @error_message)');

            try {
                $jsonLineItems = json_encode($lineItems, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                return [
                    'invoice_id' => null,
                    'error_code' => -1,
                    'error_message' => 'Line item encoding error: ' . $exception->getMessage(),
                ];
            }

            $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
            if ($currency === null) {
                $stmt->bindValue(':currency', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':currency', $currency, PDO::PARAM_STR);
            }
            $stmt->bindValue(':line_items', $jsonLineItems, PDO::PARAM_STR);

            $stmt->execute();

            $result = $this->db 
            -> query('SELECT @invoice_id AS invoice_id, @error_code AS error_code, @error_message AS error_message')
            -> fetch(PDO::FETCH_ASSOC);
            
            return $result;

        } catch (\PDOException $e) {
            // Log the exception as needed
            return [
                'invoice_id' => null,
                'error_code' => -1,
                'error_message' => 'Database error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create draft invoices for every customer with line items lacking an invoice reference.
     *
     * @param string|null $voucherDate Defaults to today when omitted.
     * @return array{
     *     createdInvoices: array<int, array{invoice_id: string, customer_id: int, customer_number: ?string, line_item_count: int}>,
     *     skippedLineItems: array<int, string>,
     *     error?: string
     * }
     */
    public function createInvoicesForPendingLineItems(?string $voucherDate = null): array
    {
        $result = [
            'createdInvoices' => [],
            'skippedLineItems' => [],
        ];

        $voucherDate = $voucherDate ?: date('Y-m-d');

        $pendingSql = "SELECT
                li.id,
                li.customer_number,
                li.line_order,
                li.quantity,
                li.net_amount,
                li.gross_amount,
                li.line_total_net,
                li.line_total_gross,
                li.currency,
                c.id AS customer_id
            FROM {$this->lineItemTable} li
            LEFT JOIN {$this->customerTable} c ON c.Nummer = li.customer_number
            WHERE (li.invoice_id IS NULL OR li.invoice_id = '')
            ORDER BY li.customer_number ASC, li.created_at ASC, li.id ASC";

        try {
            $stmt = $this->db->prepare($pendingSql);
            $stmt->execute();
            $pendingItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$pendingItems) {
                return $result;
            }

            $grouped = [];

            foreach ($pendingItems as $row) {
                $customerId = isset($row['customer_id']) ? (int)$row['customer_id'] : null;

                if (!$customerId) {
                    if (!empty($row['id'])) {
                        $result['skippedLineItems'][] = (string)$row['id'];
                    }
                    continue;
                }

                $customerKey = $row['customer_number'] ?? (string)$customerId;

                if (!isset($grouped[$customerKey])) {
                    $grouped[$customerKey] = [
                        'customer_number' => $row['customer_number'] ?? null,
                        'customer_id' => $customerId,
                        'items' => [],
                    ];
                }

                $grouped[$customerKey]['items'][] = $row;
            }

            if (!$grouped) {
                return $result;
            }

            $this->db->beginTransaction();

            $insertInvoiceSql = "INSERT INTO {$this->invoiceTable} (
                    id,
                    contact_id,
                    voucher_date,
                    currency,
                    total_net_amount,
                    total_gross_amount,
                    tax_type,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    :id,
                    :contact_id,
                    :voucher_date,
                    :currency,
                    :total_net_amount,
                    :total_gross_amount,
                    'net',
                    'draft',
                    NOW(),
                    NOW()
                )";

            $insertInvoiceStmt = $this->db->prepare($insertInvoiceSql);

            $updateLineItemSql = "UPDATE {$this->lineItemTable}
                SET
                    invoice_id = :invoice_id,
                    line_order = :line_order,
                    line_total_net = :line_total_net,
                    line_total_gross = :line_total_gross,
                    updated_at = NOW()
                WHERE id = :id
                  AND (invoice_id IS NULL OR invoice_id = '')";

            $updateLineItemStmt = $this->db->prepare($updateLineItemSql);

            foreach ($grouped as $group) {
                $invoiceId = Invoice::generateUuid();
                $currency = null;
                $totalNet = 0.0;
                $totalGross = 0.0;
                $linkedLineItems = [];

                foreach ($group['items'] as $index => $item) {
                    $itemCurrency = $item['currency'] ?? null;
                    if ($currency === null && $itemCurrency !== null && $itemCurrency !== '') {
                        $currency = (string)$itemCurrency;
                    }

                    $quantity = $item['quantity'] ?? null;
                    $netAmount = $item['net_amount'] ?? null;
                    $grossAmount = $item['gross_amount'] ?? null;

                    $lineTotalNet = $item['line_total_net'] ?? null;
                    if ($lineTotalNet === null && $quantity !== null && $netAmount !== null) {
                        $lineTotalNet = round((float)$quantity * (float)$netAmount, 2);
                    }

                    $lineTotalGross = $item['line_total_gross'] ?? null;
                    if ($lineTotalGross === null && $quantity !== null && $grossAmount !== null) {
                        $lineTotalGross = round((float)$quantity * (float)$grossAmount, 2);
                    }

                    $existingLineOrder = isset($item['line_order']) ? (int)$item['line_order'] : 0;
                    $lineOrder = $existingLineOrder > 0
                        ? $existingLineOrder
                        : $index + 1;

                    $updateLineItemStmt->execute([
                        ':invoice_id' => $invoiceId,
                        ':line_order' => $lineOrder,
                        ':line_total_net' => $lineTotalNet,
                        ':line_total_gross' => $lineTotalGross,
                        ':id' => $item['id'],
                    ]);

                    if ($updateLineItemStmt->rowCount() === 0) {
                        $result['skippedLineItems'][] = (string)$item['id'];
                        continue;
                    }

                    if ($lineTotalNet !== null) {
                        $totalNet += (float)$lineTotalNet;
                    }

                    if ($lineTotalGross !== null) {
                        $totalGross += (float)$lineTotalGross;
                    }

                    $linkedLineItems[] = (string)$item['id'];
                }

                if (!$linkedLineItems) {
                    continue;
                }

                $currency = $currency ?? 'EUR';

                $insertInvoiceStmt->execute([
                    ':id' => $invoiceId,
                    ':contact_id' => $group['customer_id'],
                    ':voucher_date' => $voucherDate,
                    ':currency' => $currency,
                    ':total_net_amount' => round($totalNet, 2),
                    ':total_gross_amount' => round($totalGross, 2),
                ]);

                $result['createdInvoices'][] = [
                    'invoice_id' => $invoiceId,
                    'customer_id' => $group['customer_id'],
                    'customer_number' => $group['customer_number'],
                    'line_item_count' => count($linkedLineItems),
                ];
            }

            $this->db->commit();

            return $result;
        } catch (Exception $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $result['error'] = $exception->getMessage();
            return $result;
        }
    }

    /**
     * Call stored procedure to create invoices for unassigned line items.
     *
     * @param string|null $voucherDate
     * @return array{
     *     createdInvoices: array<int, array<string, mixed>>,
     *     skippedLineItems: array<int, string>,
     *     error?: string
     * }
     */
    public function createInvoicesForPendingLineItemsViaStoredProc(?string $voucherDate = null): array
    {
        try {
            $stmt = $this->db->prepare('CALL sp_create_invoices_for_pending_line_items(:voucher_date)');

            if ($voucherDate === null || trim((string)$voucherDate) === '') {
                $stmt->bindValue(':voucher_date', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':voucher_date', $voucherDate, PDO::PARAM_STR);
            }

            $stmt->execute();

            $createdInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $skippedLineItems = [];
            if ($stmt->nextRowset()) {
                $skippedRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($skippedRows as $row) {
                    if (isset($row['line_item_id'])) {
                        $skippedLineItems[] = (string) $row['line_item_id'];
                    }
                }
            }

            while ($stmt->nextRowset()) {
                // consume any additional result sets emitted by the stored procedure
            }

            return [
                'createdInvoices' => $createdInvoices,
                'skippedLineItems' => $skippedLineItems,
            ];
        } catch (\PDOException $exception) {
            return [
                'createdInvoices' => [],
                'skippedLineItems' => [],
                'error' => 'Database error: ' . $exception->getMessage(),
            ];
        }
    }
    
    /**
     * Update invoice after successful Lexware transmission
     */
    public function updateAfterTransmission(string $invoiceId, array $lexwareResponse): bool
    {
        try {
            $this->db->beginTransaction();
            
            // Parse dates from Lexware response
            $lexCreatedDate = isset($lexwareResponse['createdDate']) 
                ? (new DateTime($lexwareResponse['createdDate']))->format('Y-m-d H:i:s') 
                : null;
            
            $lexUpdatedDate = isset($lexwareResponse['updatedDate']) 
                ? (new DateTime($lexwareResponse['updatedDate']))->format('Y-m-d H:i:s') 
                : null;
            
            $sql = "UPDATE {$this->invoiceTable} 
                    SET status = 'transmitted',
                        lex_id = :lex_id,
                        lex_resource_uri = :lex_resource_uri,
                        lex_version = :lex_version,
                        lex_created_date = :lex_created_date,
                        lex_updated_date = :lex_updated_date,
                        transmitted_at = :transmitted_at,
                        last_error_message = NULL,
                        last_error_code = NULL
                    WHERE id = :id";
            
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
            $this->db->rollBack();
            Logger::exception($e, 'InvoiceRepository - Update After Transmission');
            return false;
        }
    }
    
    /**
     * Update invoice with transmission error
     */
    public function updateWithError(string $invoiceId, string $errorMessage, ?string $errorCode = null): bool
    {
        try {
            $sql = "UPDATE {$this->invoiceTable} 
                    SET status = 'transmission_error',
                        last_error_message = :error_message,
                        last_error_code = :error_code,
                        transmission_attempts = transmission_attempts + 1,
                        last_transmission_attempt = :last_attempt
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':id' => $invoiceId,
                ':error_message' => $errorMessage,
                ':error_code' => $errorCode,
                ':last_attempt' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            Logger::exception($e, 'InvoiceRepository - Update With Error');
            return false;
        }
    }
    
    /**
     * Update invoice status
     */
    public function updateStatus(string $invoiceId, string $status): bool
    {
        try {
            $sql = "UPDATE {$this->invoiceTable} SET status = :status WHERE id = :id";
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
}
