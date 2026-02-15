<?php

declare(strict_types=1);


namespace Luxullus\LexBridge\Repositories;

use PDO;
use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Logger;
use Luxullus\LexBridge\Utils\UuidUtil;

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
        Logger::info('<<<Fetching line items with filters: ' . json_encode($filters), 'LineItemRepository');
        
        $selectSql = <<<SQL
            SELECT 
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
                li.order_delivery_date
        SQL;

        $fromSql = <<<SQL
            FROM {$this->lineItemTable} li
            LEFT JOIN {$this->customerTable} c ON li.customer_id = c.id
        SQL;

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
            $where[] = 'li.customer_id = :customer_id';
            $params[':customer_id'] = (int)$filters['customer_id'];
        }

        $whereSql = '';
        if ($where) {
            $whereSql = ' WHERE ' . implode(' AND ', $where);
        }

        $countSql = <<<SQL
            SELECT COUNT(*) AS total
            {$fromSql}
            {$whereSql}
        SQL;

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


        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Logger::info('>>>Fetched line items: ' . json_encode($results), 'LineItemRepository');
        return [
            'items' => $results,
            'total_count' => $totalCount,
        ];
    }

    public function findLineItemById(string $lineItemId): ?array
    {
        $sql = <<<SQL
            SELECT 
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
            LIMIT 1
        SQL;
        
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
        $sql = <<<SQL
            UPDATE {$this->lineItemTable}
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
            WHERE id = :line_item_id
        SQL;

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

    /**
     * Persist line items for a single customer.
     *
     * @param int $customerId
     * @param array<int, array<string, mixed>> $lineItems
     * @return array{persisted: int, errors: array<string>}
     */
    public function persistLineItemsForCustomer(int $customerId, array $lineItems): array
    {
        if (empty($lineItems)) {
            return ['persisted' => 0, 'errors' => []];
        }

        $persistedCount = 0;
        $errors = [];

        foreach ($lineItems as $index => $item) {
            try {
                $lineItemId = UuidUtil::generateUuid();

                $sql = <<<SQL
                    INSERT INTO {$this->lineItemTable} (
                        id, article_id, article_number,customer_id, name, description,
                        quantity, unit_name, currency, net_amount, gross_amount,
                        tax_rate_percentage, line_total_net, line_total_gross,
                        order_delivery_date, line_order, order_id, created_at
                    ) VALUES (
                        :id, :article_id, :article_number, :customer_id, :name, :description,
                        :quantity, :unit_name, :currency, :net_amount, :gross_amount,
                        :tax_rate_percentage, :line_total_net, :line_total_gross,
                        :order_delivery_date, :line_order, :order_id, NOW()
                    )
                SQL;

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':id' => $lineItemId,
                    ':article_id' => $item['article_id'] ?? null,
                    ':article_number' => $item['article_number'] ?? null,
                    ':customer_id' => $item['customer_id'] ?? null,
                    ':name' => $item['article_name'] ?? $item['name'] ?? null,
                    ':description' => $item['description'] ?? null,
                    ':quantity' => $item['quantity'] ?? null,
                    ':unit_name' => $item['unit_name'] ?? null,
                    ':currency' => $item['currency'] ?? 'EUR',
                    ':net_amount' => $item['net_amount'] ?? null,
                    ':gross_amount' => $item['gross_amount'] ?? null,
                    ':tax_rate_percentage' => $item['tax_rate_percentage'] ?? null,
                    ':line_total_net' => $item['line_total_net'] ?? null,
                    ':line_total_gross' => $item['line_total_gross'] ?? null,
                    ':order_delivery_date' => $item['order_delivery_date'] ?? null,
                    ':line_order' => $item['line_order'] ?? null,
                    ':order_id' => $item['order_id'] ?? null,
                ]);

                $persistedCount++;
            } catch (\Throwable $e) {
                $articleInfo = $item['article_number'] ?? $item['name'] ?? "item #{$index}";
                $errors[] = "Failed to persist {$articleInfo} for customer {$customerId}: " . $e->getMessage();
                Logger::info(end($errors), 'LineItemRepository');
            }
        }

        $persistedIds = $persistedCount > 0 ? array_map(fn($i) => $i['id'], $lineItems) : [];

        return [
            'errors' => $errors, 
            'persisted' => $persistedCount, 
            'persisted_ids' => $persistedIds
        ];
    }
}
