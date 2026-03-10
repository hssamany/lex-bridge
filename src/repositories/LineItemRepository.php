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

        // >>> Create filters.
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

        // <<< End filters.

        // Build WHERE clause
        $whereSql = '';
        if ($where) {
            $whereSql = ' WHERE ' . implode(' AND ', $where);
        }

        // First get total count for pagination
        $countSql = <<<SQL
            SELECT COUNT(*) AS total
            {$fromSql}
            {$whereSql}
        SQL;

        // Now fetch paginated count results
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $totalCount = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        // Assemble main SQL query with pagination
            $sql = <<<SQL
            {$selectSql}
            {$fromSql}
            {$whereSql}
            ORDER BY c.id, li.created_at DESC, li.line_order ASC
            LIMIT :limit OFFSET :offset
        SQL;

        // Prepare and execute main query
        $stmt = $this->db->prepare($sql);
        array_walk($params, fn($value, $name) => $stmt->bindValue($name, $value));
        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                updated_at = CURRENT_TIMESTAMP
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
     * @param array<int, array<string, mixed>> $lineItems
     * @return array{persisted: int, errors: array<string>}
     */
    public function persistLineItemsForCustomer(array $lineItems): array
    {
        if (empty($lineItems)) {
            return ['persisted' => 0, 'errors' => [], 'persisted_ids' => []];
        }

        $columns = [
            'id',
            'article_id',
            'article_number',
            'customer_id',
            'name',
            'description',
            'quantity',
            'unit_name',
            'currency',
            'net_amount',
            'gross_amount',
            'tax_rate_percentage',
            'line_total_net',
            'line_total_gross',
            'order_delivery_date',
            'line_order',
            'order_id',
            'created_at',
            'updated_at'
        ];

        $valuesSql = [];
        $params = [];
        $insertedIds = [];

        $this->db->beginTransaction();
        try {
            foreach ($lineItems as $i => $item) {
                $lineItemId = $item['id'] ?? UuidUtil::generateUuid();
                $insertedIds[] = $lineItemId;
                $customerId = $item['customer_id'] ?? null;
                if (!$customerId) {
                    throw new \InvalidArgumentException("Missing customer_id for line item at index {$i}");
                }
                $customerId = (int)$customerId;
                $placeholders = [];
                foreach ($columns as $col) {
                    if ($col === 'created_at' || $col === 'updated_at') {
                        $placeholders[] = 'CURRENT_TIMESTAMP';
                        continue;
                    }
                    $ph = ":{$col}_{$i}";
                    $placeholders[] = $ph;
                    switch ($col) {
                        case 'id':
                            $params[$ph] = $lineItemId;
                            break;
                        case 'article_id':
                            $params[$ph] = $item['article_id'] ?? null;
                            break;
                        case 'article_number':
                            $params[$ph] = $item['article_number'] ?? null;
                            break;
                        case 'customer_id':
                            $params[$ph] = $customerId;
                            break;
                        case 'name':
                            $params[$ph] = $item['article_name'] ?? $item['name'] ?? null;
                            break;
                        case 'description':
                            $params[$ph] = $item['description'] ?? null;
                            break;
                        case 'quantity':
                            $params[$ph] = $item['quantity'] ?? null;
                            break;
                        case 'unit_name':
                            $params[$ph] = $item['unit_name'] ?? null;
                            break;
                        case 'currency':
                            $params[$ph] = $item['currency'] ?? 'EUR';
                            break;
                        case 'net_amount':
                            $params[$ph] = $item['net_amount'] ?? null;
                            break;
                        case 'gross_amount':
                            $params[$ph] = $item['gross_amount'] ?? null;
                            break;
                        case 'tax_rate_percentage':
                            $params[$ph] = $item['tax_rate_percentage'] ?? null;
                            break;
                        case 'line_total_net':
                            $params[$ph] = $item['line_total_net'] ?? null;
                            break;
                        case 'line_total_gross':
                            $params[$ph] = $item['line_total_gross'] ?? null;
                            break;
                        case 'order_delivery_date':
                            $params[$ph] = $item['order_delivery_date'] ?? null;
                            break;
                        case 'line_order':
                            $params[$ph] = $item['line_order'] ?? null;
                            break;
                        case 'order_id':
                            $params[$ph] = $item['order_id'] ?? null;
                            break;
                    }
                }

                $valuesSql[] = '('.implode(', ', $placeholders).')';
            }

            $columnsSql = implode(', ', $columns);
            $valuesSqlStr = implode(",\n", $valuesSql);
            $sql = <<<SQL
                INSERT INTO {$this->lineItemTable} 
                ({$columnsSql})
                VALUES 
                {$valuesSqlStr}
            SQL;

            // Replace all created_at and updated_at placeholders with NOW() in SQL
            $sql = preg_replace('/:(created_at|updated_at)_\d+/', 'CURRENT_TIMESTAMP', $sql);

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $this->db->commit();

            return [
                'persisted' => count($insertedIds),
                'persisted_ids' => $insertedIds
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
