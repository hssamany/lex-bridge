<?php

declare(strict_types=1);


namespace Luxullus\LexBridge\Repositories;

use PDO;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Luxullus\LexBridge\Database\Database;

class LineItemRepository
{
    /**
     * ISO weekday names mapped to day offsets used when expanding a calendar week.
     */
    private const WEEKDAY_OFFSETS = [
        'Mo' => 0,
        'Di' => 1,
        'Mi' => 2,
        'Do' => 3,
        'Fr' => 4,
    ];

    private \PDO $db;
    private string $lineItemTable;
    private string $invoiceTable;
    private string $customerTable;
    private string $ordersTable;
    private string $articleTable;
    private string $priceTable;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->lineItemTable = \lexbridge_table('invoice_line_items');
        $this->invoiceTable = \lexbridge_table('invoices');
        $this->customerTable = \lexbridge_table('customer');
        $this->ordersTable = \lexbridge_table('orders');
        $this->articleTable = \lexbridge_table('articles');
        $this->priceTable = \lexbridge_table('prices');
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
                    c.customer_number,
                    c.company_name,
                    c.id AS customer_id,
                    li.invoice_id,
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

    /**
     * Prepare invoice line item payloads derived from Orders rows.
     *
     * @param array{
     *     liefer_datum_von?: mixed,
     *     liefer_datum_bis?: mixed,
     *     customer_id?: mixed,
     *     Kunde?: mixed
     * } $filters
     * @return array<int, array<string, mixed>>
     */
    public function generateInvoiceLineItemsFromOrders(array $filters = []): array
    {
        $where = [];
        $params = [];

        $customerId = $filters['customer_id']
            ?? $filters['Kunde']
            ?? null;

        if ($customerId !== null && $customerId !== '') {
            // restrict to a single customer if provided
            $where[] = 'o.Kunde = :customer_id';
            $params[':customer_id'] = (int) $customerId;
        }

        // Do not pick orders already marked as processed
        $where[] = '(o.verarbeitet = 0 OR o.verarbeitet IS NULL)';
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $normalizeDate = static function ($value, string $label): DateTimeImmutable {
            if ($value instanceof DateTimeInterface) {
                return DateTimeImmutable::createFromInterface($value);
            }

            if (is_string($value) && trim($value) !== '') {
                try {
                    return new DateTimeImmutable($value);
                } catch (\Exception $exception) {
                    throw new InvalidArgumentException($label . ' must be a valid date', 0, $exception);
                }
            }

            throw new InvalidArgumentException($label . ' must be a valid date');
        };

        $deliveryFrom = null;
        if (isset($filters['liefer_datum_von']) && $filters['liefer_datum_von'] !== '') {
            $deliveryFrom = $normalizeDate($filters['liefer_datum_von'], 'liefer_datum_von');
        }

        $deliveryTo = null;
        if (isset($filters['liefer_datum_bis']) && $filters['liefer_datum_bis'] !== '') {
            $deliveryTo = $normalizeDate($filters['liefer_datum_bis'], 'liefer_datum_bis');
        }

        // fetch raw orders rows for the selected customers/weeks
        $sql = "SELECT 
                    o.Id AS order_id,
                    o.Kunde AS customer_id,
                    o.Jahr AS order_year,
                    o.KW AS order_week,
                    o.Mo,
                    o.Di,
                    o.Mi,
                    o.Do,
                    o.Fr,
                    o.article_id,
                    o.article_number
                FROM {$this->ordersTable} o
                {$whereSql}
                ORDER BY o.Kunde, o.Jahr, o.KW";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $name => $value) {
            if ($name === ':customer_id') {
                $stmt->bindValue($name, (int) $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue($name, $value);
        }

        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return [];
        }

        $articleCatalog = $this->preloadArticleCatalog($rows, $deliveryFrom, $deliveryTo);

        $results = [];
        $lineCountPerOrder = [];

        foreach ($rows as $row) {
            $year = (int) ($row['order_year'] ?? 0);
            $week = (int) ($row['order_week'] ?? 0);

            if ($year <= 0 || $week <= 0) {
                continue;
            }

            try {
                // monday start for the ISO week
                $weekStart = (new DateTimeImmutable())->setISODate($year, $week, 1);
            } catch (\Exception $e) {
                continue;
            }

            foreach (self::WEEKDAY_OFFSETS as $column => $offset) {
                $quantity = $row[$column] ?? null;

                if ($quantity === null) {
                    continue;
                }

                $quantityValue = (float) $quantity;
                if (abs($quantityValue) < 0.0001) {
                    continue;
                }

                // derive the specific delivery date within the week
                $deliveryDate = $weekStart->modify('+' . $offset . ' day');

                if ($deliveryFrom !== null && $deliveryDate < $deliveryFrom) {
                    continue;
                }

                if ($deliveryTo !== null && $deliveryDate > $deliveryTo) {
                    break; // remaining days in the week would also exceed upper bound
                }

                $customerKey = (int) $row['customer_id'];
                // each customer accumulates a day-by-day quantity table
                $articleId = $row['article_id'] !== null ? (int) $row['article_id'] : null;
                $articleNumber = $row['article_number'] ?? null;

                $pricing = $this->extractArticleSnapshot($articleCatalog, $articleId, $articleNumber, $deliveryDate);

                $lineItem = [
                    'order_id' => (int) $row['order_id'],
                    'article_id' => $articleId,
                    'article_number' => $articleNumber,
                    'delivery_date' => $deliveryDate->format('Y-m-d'),
                    'quantity' => $quantityValue,
                ];

                if ($pricing !== null) {
                    $lineItem['article_name'] = $pricing['article']['name'];
                    $lineItem['article_description'] = $pricing['article']['description'];
                    $lineItem['unit_name'] = $pricing['article']['unit_name'];
                    $priceData = $pricing['price'] ?? null;
                    if ($priceData !== null) {
                        $lineItem['currency'] = $priceData['currency'] ?? null;
                        $lineItem['net_amount'] = $priceData['net_amount'] ?? null;
                        $lineItem['gross_amount'] = $priceData['gross_amount'] ?? null;
                        $lineItem['tax_rate_percentage'] = $priceData['tax_rate_percentage'] ?? null;
                        $lineItem['article_valid_from'] = $priceData['valid_from'] ?? null;
                        $lineItem['article_valid_until'] = $priceData['valid_until'] ?? null;
                        $lineItem['line_total_net'] = $priceData['net_amount'] !== null
                            ? round($quantityValue * (float) $priceData['net_amount'], 2)
                            : null;
                        $lineItem['line_total_gross'] = $priceData['gross_amount'] !== null
                            ? round($quantityValue * (float) $priceData['gross_amount'], 2)
                            : null;
                    }
                }

                $lineCountPerOrder[(int) $row['order_id']] = ($lineCountPerOrder[(int) $row['order_id']] ?? 0) + 1;
                $results[$customerKey][] = $lineItem;
            }
        }

        $this->markOrdersAsProcessed($lineCountPerOrder);

        return $results;
    }

    /**
     * Load article meta data and relevant price histories for all orders in a single query.
     *
     * @param array<int, array<string, mixed>> $orders
     * @return array<string, array{article: array<string, mixed>, prices: array<int, array<string, mixed>>}>
     */
    private function preloadArticleCatalog(array $orders, ?DateTimeImmutable $deliveryFrom, ?DateTimeImmutable $deliveryTo): array
    {
        $articleIds = [];
        $articleNumbers = [];

        foreach ($orders as $order) {
            if (!empty($order['article_id'])) {
                $articleIds[(int) $order['article_id']] = true;
            }

            if (!empty($order['article_number'])) {
                $articleNumbers[(string) $order['article_number']] = true;
            }
        }

        if (!$articleIds && !$articleNumbers) {
            return [];
        }

        $params = [];
        $clauses = [];

        if ($articleIds) {
            $placeholders = [];
            $index = 0;
            foreach (array_keys($articleIds) as $id) {
                $placeholder = ':article_id_' . $index++;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $id;
            }
            $clauses[] = 'a.id IN (' . implode(', ', $placeholders) . ')';
        }

        if ($articleNumbers) {
            $placeholders = [];
            $index = 0;
            foreach (array_keys($articleNumbers) as $number) {
                $placeholder = ':article_number_' . $index++;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $number;
            }
            $clauses[] = 'a.article_number IN (' . implode(', ', $placeholders) . ')';
        }

        $priceJoinParts = [];
        if ($deliveryFrom !== null) {
            $priceJoinParts[] = '(pr.valid_until IS NULL OR pr.valid_until >= :price_from)';
            $params[':price_from'] = $deliveryFrom->format('Y-m-d');
        }

        if ($deliveryTo !== null) {
            $priceJoinParts[] = 'pr.valid_from <= :price_to';
            $params[':price_to'] = $deliveryTo->format('Y-m-d');
        }

        $priceJoinSql = $priceJoinParts ? ' AND ' . implode(' AND ', $priceJoinParts) : '';

        $sql = "SELECT
                    a.id,
                    a.article_number,
                    a.name,
                    a.description,
                    a.unit_name,
                    pr.net_amount,
                    pr.gross_amount,
                    pr.tax_rate_percentage,
                    pr.currency,
                    pr.valid_from,
                    pr.valid_until
                FROM {$this->articleTable} a
                LEFT JOIN {$this->priceTable} pr
                    ON pr.article_id = a.id{$priceJoinSql}";

        if ($clauses) {
            $sql .= ' WHERE ' . implode(' OR ', $clauses);
        }

        $sql .= ' ORDER BY a.id, pr.valid_from DESC, pr.id DESC';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }

        $stmt->execute();

        $byId = [];
        $numberToId = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $articleId = (int) $row['id'];

            if (!isset($byId[$articleId])) {
                $byId[$articleId] = [
                    'article' => [
                        'id' => $articleId,
                        'article_number' => $row['article_number'],
                        'name' => $row['name'],
                        'description' => $row['description'],
                        'unit_name' => $row['unit_name'],
                    ],
                    'prices' => [],
                ];
            }

            if (!empty($row['article_number'])) {
                $numberToId[$row['article_number']] = $articleId;
            }

            if ($row['net_amount'] !== null
                || $row['gross_amount'] !== null
                || $row['tax_rate_percentage'] !== null
                || $row['currency'] !== null
                || $row['valid_from'] !== null
                || $row['valid_until'] !== null
            ) {
                $byId[$articleId]['prices'][] = [
                    'net_amount' => $row['net_amount'],
                    'gross_amount' => $row['gross_amount'],
                    'tax_rate_percentage' => $row['tax_rate_percentage'],
                    'currency' => $row['currency'],
                    'valid_from' => $row['valid_from'],
                    'valid_until' => $row['valid_until'],
                ];
            }
        }

        $catalog = [];

        foreach ($byId as $id => $entry) {
            $catalog['id:' . $id] = $entry;
        }

        foreach ($numberToId as $number => $id) {
            if (isset($catalog['id:' . $id])) {
                $catalog['num:' . $number] = $catalog['id:' . $id];
            }
        }

        return $catalog;
    }

    /**
     * Pick the article meta entry and matching price for the requested delivery date.
     */
    private function extractArticleSnapshot(array $catalog, ?int $articleId, ?string $articleNumber, DateTimeImmutable $deliveryDate): ?array
    {
        $key = null;

        if ($articleId !== null && isset($catalog['id:' . $articleId])) {
            $key = 'id:' . $articleId;
        } elseif ($articleNumber !== null && isset($catalog['num:' . $articleNumber])) {
            $key = 'num:' . $articleNumber;
        }

        if ($key === null) {
            return null;
        }

        $entry = $catalog[$key];
        $price = $this->selectPriceForDate($entry['prices'], $deliveryDate->format('Y-m-d'));

        return [
            'article' => $entry['article'],
            'price' => $price,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $prices
     */
    private function selectPriceForDate(array $prices, string $targetDate): ?array
    {
        foreach ($prices as $price) {
            if ($price['valid_from'] !== null && $price['valid_from'] > $targetDate) {
                continue;
            }

            if ($price['valid_until'] !== null && $price['valid_until'] < $targetDate) {
                continue;
            }

            return $price;
        }

        return null;
    }

    /**
     * Persist processed state for orders that produced at least one line item.
     *
     * @param array<int, int> $lineCountPerOrder
     */
    private function markOrdersAsProcessed(array $lineCountPerOrder): void
    {
        if (!$lineCountPerOrder) {
            return;
        }

        $orderIds = [];
        foreach ($lineCountPerOrder as $orderId => $count) {
            if ($count > 0) {
                $orderIds[] = $orderId;
            }
        }

        if (!$orderIds) {
            return;
        }

        $placeholders = [];
        $params = [];
        foreach ($orderIds as $index => $orderId) {
            $placeholder = ':processed_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $orderId;
        }

        $sql = "UPDATE {$this->ordersTable} SET verarbeitet = 1 WHERE Id IN (" . implode(', ', $placeholders) . ")";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value, PDO::PARAM_INT);
        }

        $stmt->execute();
    }

    public function findLineItemById(string $lineItemId): ?array
    {
        $sql = "SELECT 
                    li.id,
                    li.invoice_id,
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
