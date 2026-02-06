<?php

declare(strict_types=1);


namespace Luxullus\LexBridge\Repositories;

use PDO;
use Luxullus\LexBridge\Database\Database;

class LineItemRepository
{
    private \PDO $db;
    private string $lineItemTable;
    private string $invoiceTable;
    private string $customerTable;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->invoiceTable = \lexbridge_table('invoices');
        $this->customerTable = \lexbridge_table('customer');
        $this->lineItemTable = \lexbridge_table('invoice_line_items');
    }

    /**
     * Fetch line items with optional filters
     *
     * @param array $filters
     * @return array
     */
    public function findLineItems(array $filters = []): array
    {
        $sql = "SELECT 
                li.id,
                c.Nummer AS customer_number,
                    c.Name AS company_name,
                    c.id AS customer_id,
                    li.invoice_id,
                    li.order_id,
                    li.order_delivery_date,
                    li.line_order,
                    li.name,
                    li.description,
                    li.quantity,
                    li.currency,
                    li.net_amount,
                    li.gross_amount,
                    li.tax_rate_percentage,
                    li.line_total_net,
                    li.line_total_gross,
                    li.article_id,
                    li.article_number,
                    li.article_label,
                    li.article_valid_from,
                    li.article_valid_until,
                    li.created_at,
                    li.updated_at,
                    i.voucher_date
            FROM {$this->lineItemTable} li
            INNER JOIN {$this->invoiceTable} i ON li.invoice_id = i.id
            LEFT JOIN {$this->customerTable} c ON i.contact_id = c.id";

        $where = [];
        $params = [];

        if (!empty($filters['created_at_from'])) {
            $where[] = 'li.created_at >= :created_from';
            $params[':created_from'] = $filters['created_at_from'];
        }

        if (!empty($filters['created_at_to'])) {
            $where[] = 'li.created_at <= :created_to';
            $params[':created_to'] = $filters['created_at_to'];
        }

        if (!empty($filters['customer_id'])) {
            $where[] = 'i.contact_id = :customer_id';
            $params[':customer_id'] = (int)$filters['customer_id'];
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY li.created_at DESC, li.line_order ASC LIMIT 200';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findLineItemById(string $lineItemId): ?array
    {
        $sql = "SELECT 
                    li.id,
                    li.invoice_id,
                    li.order_id,
                    li.order_delivery_date,
                    li.line_order,
                    li.name,
                    li.description,
                    li.quantity,
                    li.currency,
                    li.net_amount,
                    li.gross_amount,
                    li.tax_rate_percentage,
                    li.line_total_net,
                    li.line_total_gross,
                    li.article_id,
                    li.article_number,
                    li.article_label,
                    li.article_valid_from,
                    li.article_valid_until,
                    li.created_at,
                    li.updated_at
            FROM {$this->lineItemTable} li 
            WHERE li.id = :line_item_id 
            LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':line_item_id' => $lineItemId]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateLineItem(string $lineItemId, array $data): bool
    {
        $sql = "UPDATE {$this->lineItemTable}
                SET
                    article_id = :article_id,
                    article_number = :article_number,
                    article_label = :article_label,
                    name = :article_name,
                    currency = :currency,
                    net_amount = :net_amount,
                    gross_amount = :gross_amount,
                    tax_rate_percentage = :tax_rate_percentage,
                    line_total_net = CASE
                        WHEN :net_amount_calc_check IS NULL OR quantity IS NULL THEN line_total_net
                        ELSE ROUND(:net_amount_calc_value * quantity, 2)
                    END,
                    line_total_gross = CASE
                        WHEN :gross_amount_calc_check IS NULL OR quantity IS NULL THEN line_total_gross
                        ELSE ROUND(:gross_amount_calc_value * quantity, 2)
                    END,
                    article_valid_from = :article_valid_from,
                    article_valid_until = :article_valid_until,
                    updated_at = NOW()
                WHERE id = :line_item_id";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':line_item_id' => $lineItemId,
            ':article_id' => $data['article_id'] ?? null,
            ':article_number' => $data['article_number'] ?? null,
            ':article_label' => $data['article_label'] ?? null,
            ':article_name' => $data['article_name'] ?? null,
            ':currency' => $data['currency'] ?? null,
            ':net_amount' => $data['net_amount'] ?? null,
            ':gross_amount' => $data['gross_amount'] ?? null,
            ':tax_rate_percentage' => $data['tax_rate_percentage'],
            ':article_valid_from' => $data['article_valid_from'] ?? null,
            ':article_valid_until' => $data['article_valid_until'] ?? null,
            ':net_amount_calc_check' => $data['net_amount'] ?? null,
            ':net_amount_calc_value' => $data['net_amount'] ?? null,
            ':gross_amount_calc_check' => $data['gross_amount'] ?? null,
            ':gross_amount_calc_value' => $data['gross_amount'] ?? null,
        ]);
    }
}
