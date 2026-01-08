<?php

declare(strict_types=1);


namespace Luxullus\LexBridge\Repositories;

use PDO;
use DateTime;
use Exception;
use Luxullus\LexBridge\Models\Invoice;
use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Models\InvoiceLineItem;

/**
 * Repository for Invoice database operations
 */
class InvoiceRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Find invoice by ID with line items
     */
    public function findById(string $id): ?Invoice
    {
        $sql = "SELECT 
                    i.*,
                    c.lex_contact_id,
                    c.company_name
                FROM invoices i
                LEFT JOIN customer c ON i.contact_id = c.id
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
                    c.company_name,
                    (SELECT COUNT(*) FROM invoice_line_items li WHERE li.invoice_id = i.id) as item_count
                FROM invoices i
                LEFT JOIN customer c ON i.contact_id = c.id";
        
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
                FROM invoice_line_items
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
    public function createInvoiceWithItems($customerId, array $lineItems)
    {
        try {

            $stmt = $this->db->prepare('CALL create_invoice_from_selection(:customer_id, :line_items, @invoice_id, @error_code, @error_message)');
            $jsonLineItems = json_encode($lineItems);

            $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
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
            
            $sql = "UPDATE invoices 
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
            error_log("Error updating invoice after transmission: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update invoice with transmission error
     */
    public function updateWithError(string $invoiceId, string $errorMessage, ?string $errorCode = null): bool
    {
        try {
            $sql = "UPDATE invoices 
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
            error_log("Error updating invoice with error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update invoice status
     */
    public function updateStatus(string $invoiceId, string $status): bool
    {
        try {
            $sql = "UPDATE invoices SET status = :status WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':id' => $invoiceId,
                ':status' => $status
            ]);
            
        } catch (Exception $e) {
            error_log("Error updating invoice status: " . $e->getMessage());
            return false;
        }
    }
}
