<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Repositories;

use PDO;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;
use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Services\LineItemCalculator;

class OrderRepository
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

    private const MIN_QUANTITY_THRESHOLD = 0.0001;

    private PDO $db;
    private string $priceTable;
    private string $ordersTable;
    private string $articleTable;
    private string $customerTable;
    private string $customerArticleTable;
    private LineItemCalculator $calculator;
    private ?bool $supportsProcessedFlag = null;

    public function __construct(?LineItemCalculator $calculator = null)
    {
        $this->db = Database::getConnection();
        $this->ordersTable = \lexbridge_table('orders');
        $this->articleTable = \lexbridge_table('articles');
        $this->priceTable = \lexbridge_table('prices');
        $this->customerArticleTable = \lexbridge_table('customers_article');
        $this->customerTable = \lexbridge_table('customer');
        $this->calculator = $calculator ?? new LineItemCalculator();
    }

    /**
     * Retrieve orders filtered by change date and optional customer.
     *
     * @param array{
     *     geaendertAm_from: mixed,
     *     geaendertAm_to?: mixed,
     *     customer_id?: mixed
     * } $filters
     * @return array<int, array<string, mixed>>
     */
    public function getOrders(array $filters): array
    {
        if (!$this->filterValueProvided($filters, 'geaendertAm_from')) {
            throw new InvalidArgumentException('Filter "geaendertAm_from" is required.');
        }

        $changedFrom = $this->normalizeBoundaryDate($filters['geaendertAm_from'], 'geaendertAm_from')
            ->setTime(0, 0, 0);

        if ($this->filterValueProvided($filters, 'geaendertAm_to')) {
            $changedTo = $this->normalizeBoundaryDate($filters['geaendertAm_to'], 'geaendertAm_to');
        } else {
            $changedTo = new DateTimeImmutable('now', $changedFrom->getTimezone());
        }

        $changedTo = $changedTo->setTime(23, 59, 59);

        if ($changedTo < $changedFrom) {
            throw new InvalidArgumentException('Filter "geaendertAm_to" must be on or after "geaendertAm_from".');
        }

        $conditions = [
            'o.GeaendertAm >= :changed_from',
            'o.GeaendertAm <= :changed_to',
        ];
        $params = [
            ':changed_from' => $changedFrom->format('Y-m-d H:i:s'),
            ':changed_to' => $changedTo->format('Y-m-d H:i:s'),
        ];

        $customerReference = $filters['customer_id'] ?? null;
        if ($customerReference !== null && $customerReference !== '') {
            $conditions[] = 'o.Kunde = :customer_id';
            $params[':customer_id'] = (int) $customerReference;
        }

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
                    ca.article_id,
                    a.article_number,
                    o.GeaendertAm,
                    c.kundenNummer AS customer_number,
                    c.lex_customer_number
                FROM {$this->ordersTable} o
                LEFT JOIN {$this->customerTable} c
                    ON CAST(c.kundenNummer AS UNSIGNED) = o.Kunde -- orders.Kunde stores the external customer number
                LEFT JOIN {$this->customerArticleTable} ca
                    ON ca.customer_id = c.id
                LEFT JOIN {$this->articleTable} a
                    ON a.id = ca.article_id
                WHERE " . implode(' AND ', $conditions) . '
                ORDER BY o.GeaendertAm ASC, o.Id ASC';

        $stmt = $this->db->prepare($sql);

        foreach ($params as $name => $value) 
        {
            if ($name === ':customer_id') {
                $stmt->bindValue($name, $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue($name, $value, PDO::PARAM_STR);
        }

        $stmt->execute();

        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $orders ?: [];
    }

    /**
     * Prepare invoice line item payloads derived from Orders rows.
     *
     * @param array{
     *     liefer_datum_von?: mixed,
     *     liefer_datum_bis?: mixed,
     *     customer_id?: mixed,
     *     Kunde?: mixed,
     *     order_id?: mixed,
     *     order_ids?: array<mixed>
     * } $filters
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function generateInvoiceLineItemsFromOrders(array $filters = []): array
    {
        $where = [];
        $params = [];
        $paramTypes = [];

        $customerId = $filters['customer_id']
            ?? $filters['Kunde']
            ?? null;

        if ($customerId !== null && $customerId !== '') {
            // restrict to a single customer if provided
            $where[] = 'o.Kunde = :customer_id';
            $params[':customer_id'] = (int) $customerId;
            $paramTypes[':customer_id'] = PDO::PARAM_INT;
        }

        $orderIdsFilter = [];

        if (isset($filters['order_id']) && $filters['order_id'] !== null && $filters['order_id'] !== '') {
            $orderIdsFilter[] = (int) $filters['order_id'];
        }

        if (isset($filters['order_ids']) && is_array($filters['order_ids'])) {
            foreach ($filters['order_ids'] as $candidate) {
                if ($candidate === null || $candidate === '') {
                    continue;
                }

                $validated = filter_var($candidate, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);

                if ($validated !== false && $validated !== null) {
                    $orderIdsFilter[] = (int) $validated;
                }
            }
        }

        $orderIdsFilter = array_values(array_unique(array_filter($orderIdsFilter, static fn(int $id): bool => $id > 0)));

        if ($orderIdsFilter) {
            $placeholders = [];

            foreach ($orderIdsFilter as $index => $orderId) {
                $placeholder = ':order_id_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $orderId;
                $paramTypes[$placeholder] = PDO::PARAM_INT;
            }

            $where[] = 'o.Id IN (' . implode(', ', $placeholders) . ')';
        }

        // Do not pick orders already marked as processed
        if ($this->supportsProcessedFlag()) {
            $where[] = '(o.verarbeitet = 0 OR o.verarbeitet IS NULL)';
        }

        $whereSql = $where
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $deliveryFrom = null;
        if ($this->filterValueProvided($filters, 'liefer_datum_von')) {
            $deliveryFrom = $this->normalizeBoundaryDate($filters['liefer_datum_von'], 'liefer_datum_von');
        }

        $deliveryTo = null;
        if ($this->filterValueProvided($filters, 'liefer_datum_bis')) {
            $deliveryTo = $this->normalizeBoundaryDate($filters['liefer_datum_bis'], 'liefer_datum_bis');
        }

        // fetch raw orders rows for the selected customers/weeks
        $sql = "SELECT 
                    o.Id AS order_id,
                    o.Kunde AS customer_id,
                    c.id AS customer_internal_id,
                    o.Jahr AS order_year,
                    o.KW AS order_week,
                    o.Mo,
                    o.Di,
                    o.Mi,
                    o.Do,
                    o.Fr,
                    ca.article_id,
                    a.article_number
                FROM {$this->ordersTable} o
                LEFT JOIN {$this->customerTable} c
                    ON CAST(c.kundenNummer AS UNSIGNED) = o.Kunde
                LEFT JOIN {$this->customerArticleTable} ca
                    ON ca.customer_id = c.id
                LEFT JOIN {$this->articleTable} a
                    ON a.id = ca.article_id
                {$whereSql}
                ORDER BY o.Kunde, o.Jahr, o.KW";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $name => $value) {

            if (isset($paramTypes[$name])) {
                $stmt->bindValue($name, $value, $paramTypes[$name]);
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
        $missingMappings = [];
        $missingArticles = [];
        $missingPrices = [];

        $formatList = static function (array $values): string {
            $unique = array_values(array_unique(array_map(static fn($value) => (string) $value, $values)));
            if (!$unique) {
                return '-';
            }

            $slice = array_slice($unique, 0, 5);
            $list = implode(', ', $slice);

            if (count($unique) > 5) {
                $list .= ', ...';
            }

            return $list;
        };

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

                $quantityValue = $this->calculator->normalizeQuantity((float) $quantity);

                if (abs($quantityValue) < self::MIN_QUANTITY_THRESHOLD) {
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

                $orderId = (int) $row['order_id'];
                $customerReference = isset($row['customer_id']) ? (int) $row['customer_id'] : 0;
                $customerInternalId = isset($row['customer_internal_id']) ? (int) $row['customer_internal_id'] : 0;
                $customerKey = $customerReference !== 0 ? $customerReference : $customerInternalId;
                $articleId = $row['article_id'] !== null ? (int) $row['article_id'] : null;
                $articleNumber = $row['article_number'] ?? null;

                if ($articleId === null) {
                    if (!isset($missingMappings[$customerKey])) {
                        $missingMappings[$customerKey] = [
                            'orders' => [],
                        ];
                    }

                    $missingMappings[$customerKey]['orders'][$orderId] = true;
                    continue 2;
                }

                $pricing = $this->extractArticleSnapshot($articleCatalog, $articleId, $articleNumber, $deliveryDate);

                if ($pricing === null) {
                    if (!isset($missingArticles[$articleId])) {
                        $missingArticles[$articleId] = [
                            'orders' => [],
                            'customer_ids' => [],
                            'article_id' => $articleId,
                            'article_number' => $articleNumber,
                        ];
                    }

                    $missingArticles[$articleId]['orders'][$orderId] = true;
                    $missingArticles[$articleId]['customer_ids'][$customerKey] = true;
                    if ($articleNumber !== null && $articleNumber !== '') {
                        $missingArticles[$articleId]['article_number'] = $articleNumber;
                    }

                    continue 2;
                }

                $priceData = $pricing['price'] ?? null;

                if ($priceData === null) {
                    if (!isset($missingPrices[$articleId])) {
                        $missingPrices[$articleId] = [
                            'orders' => [],
                            'dates' => [],
                            'customer_ids' => [],
                            'article_id' => $articleId,
                            'article_number' => $pricing['article']['article_number'] ?? $articleNumber,
                        ];
                    }

                    $missingPrices[$articleId]['orders'][$orderId] = true;
                    $missingPrices[$articleId]['customer_ids'][$customerKey] = true;
                    $missingPrices[$articleId]['dates'][$deliveryDate->format('Y-m-d')] = true;
                    if (!empty($articleNumber)) {
                        $missingPrices[$articleId]['article_number'] = $articleNumber;
                    }

                    continue;
                }

                $lineItem = $this->buildLineItemPayload($row, $pricing, $deliveryDate, $quantityValue);

                $lineCountPerOrder[$orderId] = ($lineCountPerOrder[$orderId] ?? 0) + 1;
                $results[$customerKey][] = $lineItem;
            }
        }

        if ($missingMappings || $missingArticles || $missingPrices) {
            $messages = [];

            if ($missingMappings) {
                foreach ($missingMappings as $customerId => $info) {
                    $orders = $formatList(array_keys($info['orders'] ?? []));
                    $messages[] = sprintf(
                        'Fuer Kunde %d fehlt eine Artikelzuordnung (Bestellungen: %s).',
                        $customerId,
                        $orders
                    );
                }
            }

            if ($missingArticles) {
                foreach ($missingArticles as $articleId => $info) {
                    $orders = $formatList(array_keys($info['orders'] ?? []));
                    $customers = $formatList(array_keys($info['customer_ids'] ?? []));
                    $articleNumber = $info['article_number'] ?? 'unbekannt';
                    $messages[] = sprintf(
                        'Artikel-ID %d (Nr. %s) wurde nicht gefunden (Kunden: %s, Bestellungen: %s).',
                        $articleId,
                        $articleNumber,
                        $customers,
                        $orders
                    );
                }
            }

            if ($missingPrices) {
                foreach ($missingPrices as $articleId => $info) {
                    $orders = $formatList(array_keys($info['orders'] ?? []));
                    $dates = $formatList(array_keys($info['dates'] ?? []));
                    $articleNumber = $info['article_number'] ?? 'unbekannt';
                    $messages[] = sprintf(
                        'Fuer Artikel-ID %d (Nr. %s) existiert kein gueltiger Preis fuer Lieferdatum/Lieferdaten %s (Bestellungen: %s).',
                        $articleId,
                        $articleNumber,
                        $dates,
                        $orders
                    );
                }
            }

            throw new RuntimeException(implode(' ', $messages));
        }

        $this->markOrdersAsProcessed($lineCountPerOrder);

        return $results;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function filterValueProvided(array $filters, string $key): bool
    {
        if (!array_key_exists($key, $filters)) {
            return false;
        }

        $value = $filters[$key];

        return $value !== null && $value !== '';
    }

    private function normalizeBoundaryDate(mixed $value, string $filterKey): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                throw new InvalidArgumentException(sprintf('Filter "%s" expects a non-empty string.', $filterKey));
            }

            $date = DateTimeImmutable::createFromFormat('Y-m-d', $trimmed);
            if ($date instanceof DateTimeImmutable) {
                return $date;
            }

            try {
                return new DateTimeImmutable($trimmed);
            } catch (\Exception $e) {
                throw new InvalidArgumentException(sprintf('Filter "%s" contains an invalid date: %s', $filterKey, $trimmed));
            }
        }

        throw new InvalidArgumentException(sprintf('Unsupported value provided for filter "%s".', $filterKey));
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
     * @param array<string, mixed> $orderRow
     * @param array<string, mixed>|null $pricing
     * @return array<string, mixed>
     */
    private function buildLineItemPayload(array $orderRow, ?array $pricing, DateTimeImmutable $deliveryDate, float $quantity): array
    {
        $internalCustomerId = null;
        if (isset($orderRow['customer_internal_id']) && $orderRow['customer_internal_id'] !== null) {
            $internalCustomerId = (int) $orderRow['customer_internal_id'];
        } elseif (isset($orderRow['customer_id'])) {
            $internalCustomerId = (int) $orderRow['customer_id'];
        }

        $payload = [
            'order_id' => (int) $orderRow['order_id'],
            'order_delivery_date' => $deliveryDate->format('Y-m-d'),
            'customer_id' => $internalCustomerId,
            'customer_reference' => isset($orderRow['customer_id']) ? (int) $orderRow['customer_id'] : null,
            'article_id' => isset($orderRow['article_id']) && $orderRow['article_id'] !== null
                ? (int) $orderRow['article_id']
                : null,
            'article_number' => $orderRow['article_number'] ?? null,
            'quantity' => $quantity,
        ];

        return array_merge($payload, $this->buildArticlePricingDetails($pricing, $quantity));
    }

    /**
     * @param array<string, mixed>|null $pricing
     * @return array<string, mixed>
     */
    private function buildArticlePricingDetails(?array $pricing, float $quantity): array
    {
        if ($pricing === null) {
            return [];
        }

        $details = [
            'article_name' => $pricing['article']['name'] ?? null,
            'article_description' => $pricing['article']['description'] ?? null,
            'unit_name' => $pricing['article']['unit_name'] ?? null,
        ];

        $priceData = $pricing['price'] ?? null;
        if ($priceData === null) {
            return $details;
        }

        $details['currency'] = $priceData['currency'] ?? null;
        $details['net_amount'] = $priceData['net_amount'] ?? null;
        $details['gross_amount'] = $priceData['gross_amount'] ?? null;
        $details['tax_rate_percentage'] = $priceData['tax_rate_percentage'] ?? null;
        $details['article_valid_from'] = $priceData['valid_from'] ?? null;
        $details['article_valid_until'] = $priceData['valid_until'] ?? null;
        $details['line_total_net'] = $this->calculator->calculateLineTotal($quantity, $priceData['net_amount'] ?? null);
        $details['line_total_gross'] = $this->calculator->calculateLineTotal($quantity, $priceData['gross_amount'] ?? null);

        return $details;
    }

    /**
     * Persist processed state for orders that produced at least one line item.
     *
     * @param array<int, int> $lineCountPerOrder
     */
    private function markOrdersAsProcessed(array $lineCountPerOrder): void
    {
        if (!$lineCountPerOrder || !$this->supportsProcessedFlag()) {
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

        $sql = "
            UPDATE {$this->ordersTable} 
            SET verarbeitet = 1 
            WHERE Id IN (" . implode(', ', $placeholders) . ")
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value, PDO::PARAM_INT);
        }

        $stmt->execute();
    }

    private function supportsProcessedFlag(): bool
    {
        if ($this->supportsProcessedFlag !== null) {
            return $this->supportsProcessedFlag;
        }

        try {

            $sql = "SHOW COLUMNS FROM {$this->ordersTable} LIKE 'verarbeitet'";
            
            $stmt = $this->db->query($sql);

            if ($stmt === false) {
                $this->supportsProcessedFlag = false;
                return $this->supportsProcessedFlag;
            }

            $this->supportsProcessedFlag = $stmt->fetch(PDO::FETCH_ASSOC) !== false;

            return $this->supportsProcessedFlag;

        } catch (\Throwable $exception) {

            $this->supportsProcessedFlag = false;

            return $this->supportsProcessedFlag;
        }
    }
}
