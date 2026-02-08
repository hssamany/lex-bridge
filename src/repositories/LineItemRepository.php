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
     * @param array{limit:int,offset:int} $pagination
     * @return array{items:array<int,array<string,mixed>>,total_count:int}
     */
    public function findLineItems(array $filters = [], array $pagination = ['limit' => 25, 'offset' => 0]): array
    {
        $selectSql = "SELECT 
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
                i.voucher_date";

        $fromSql = "FROM {$this->lineItemTable} li
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

        $whereSql = '';
        if ($where) {
            $whereSql = ' WHERE ' . implode(' AND ', $where);
        }

        $countSql = "SELECT COUNT(*) AS total {$fromSql}{$whereSql}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $totalCount = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = "{$selectSql} {$fromSql}{$whereSql} ORDER BY li.created_at DESC, li.line_order ASC LIMIT :limit OFFSET :offset";

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

    /**
     * Update line item - pure data access only
     * Caller is responsible for calculating totals
     */
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
                    line_total_net = :line_total_net,
                    line_total_gross = :line_total_gross,
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
            ':tax_rate_percentage' => $data['tax_rate_percentage'] ?? null,
            ':line_total_net' => $data['line_total_net'] ?? null,
            ':line_total_gross' => $data['line_total_gross'] ?? null,
            ':article_valid_from' => $data['article_valid_from'] ?? null,
            ':article_valid_until' => $data['article_valid_until'] ?? null,
        ]);
    }
}
