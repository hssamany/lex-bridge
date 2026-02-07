<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Repositories;

use DateTime;
use Exception;
use JsonException;
use PDO;
use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Logger;

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

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Find all invoices with optional filters.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
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

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Find line items for an invoice.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findLineItemsByInvoiceId(string $invoiceId): array
    {
        $sql = "SELECT * 
                FROM {$this->lineItemTable}
                WHERE invoice_id = :invoice_id
                ORDER BY line_order ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':invoice_id' => $invoiceId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    /**
     * Create invoice and line items using stored procedure.
     *
     * @param int $customerId
     * @param string|null $currency
     * @param array<int, array<string, mixed>> $lineItems
     * @return array{invoice_id:int|null,error_code:int,error_message:string}
     */
    public function createInvoiceWithItems(int $customerId, ?string $currency, array $lineItems): array
    {
        try {
            $stmt = $this->db->prepare(
                'CALL create_invoice_from_selection(:customer_id, :currency, :line_items, @invoice_id, @error_code, @error_message)'
            );

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
            $stmt->bindValue(':currency', $currency, $currency === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':line_items', $jsonLineItems, PDO::PARAM_STR);

            $stmt->execute();

            $result = $this->db
                ->query('SELECT @invoice_id AS invoice_id, @error_code AS error_code, @error_message AS error_message')
                ->fetch(PDO::FETCH_ASSOC);

            return $result ?: [
                'invoice_id' => null,
                'error_code' => -1,
                'error_message' => 'Failed to retrieve result'
            ];
        } catch (\PDOException $e) {
            Logger::exception($e, 'InvoiceRepository - Create Invoice With Items');
            
            return [
                'invoice_id' => null,
                'error_code' => -1,
                'error_message' => 'Database error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Call stored procedure to create invoices for pending line items.
     *
     * @param string|null $voucherDate
     * @return array{createdInvoices:array<int,array<string,mixed>>,skippedLineItems:array<int,string>,error?:string}
     */
    public function createInvoicesForPendingLineItemsViaStoredProc(?string $voucherDate = null): array
    {
        try {
            $stmt = $this->db->prepare('CALL sp_create_invoices_for_pending_line_items(:voucher_date)');
            $stmt->bindValue(':voucher_date', $voucherDate, $voucherDate === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
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
                // Consume remaining result sets
            }

            return [
                'createdInvoices' => $createdInvoices,
                'skippedLineItems' => $skippedLineItems,
            ];
        } catch (\PDOException $exception) {
            Logger::exception($exception, 'InvoiceRepository - Create Invoices Via Stored Proc');
            
            return [
                'createdInvoices' => [],
                'skippedLineItems' => [],
                'error' => 'Database error: ' . $exception->getMessage(),
            ];
        }
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
     * Update invoice status.
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
