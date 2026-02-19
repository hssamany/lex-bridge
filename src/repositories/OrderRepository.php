<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Repositories;

use PDO;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;
use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Logger;
use Luxullus\LexBridge\Services\LineItemCalculator;
use Luxullus\LexBridge\Services\OrderDateCalculator;
use Luxullus\LexBridge\Services\OrderLineItemBuilder;

use Luxullus\LexBridge\Services\OrderDomainConstants;
use Luxullus\LexBridge\Repositories\ArticleRepository;
use Luxullus\LexBridge\Repositories\LineItemRepository;
use Luxullus\LexBridge\Utils\InputFilter;

final class OrderRepository
{
    
    private PDO $db;
    private string $priceTable;
    private string $ordersTable;
    private string $articleTable;
    private string $customerTable;
    private string $customerArticleTable;
    private LineItemCalculator $calculator;
    private OrderDateCalculator $dateCalculator;
    private OrderLineItemBuilder $lineItemBuilder;
    private ?bool $supportsProcessedFlag = null;
    private LineItemRepository $lineItemRepository;
    private ArticleRepository $articleRepository;
    private InvoiceRepository $invoiceRepository;

    public function __construct(
        ?LineItemCalculator $calculator = null,
        ?OrderDateCalculator $dateCalculator = null,
        ?OrderLineItemBuilder $lineItemBuilder = null,
        ?ArticleRepository $articleRepository = null,
        ?LineItemRepository $lineItemRepository = null,
        ?InvoiceRepository $invoiceRepository = null
    ) {
        $this->db = Database::getConnection();
        $this->ordersTable = \lexbridge_table('orders');
        $this->articleTable = \lexbridge_table('articles');
        $this->priceTable = \lexbridge_table('prices');
        $this->customerArticleTable = \lexbridge_table('customers_article');
        $this->customerTable = \lexbridge_table('customer');
        $this->calculator = $calculator ?? new LineItemCalculator();
        $this->dateCalculator = $dateCalculator ?? new OrderDateCalculator();
        $this->lineItemBuilder = $lineItemBuilder ?? new OrderLineItemBuilder($this->calculator);
        $this->articleRepository = $articleRepository ?? new ArticleRepository();
        $this->lineItemRepository = $lineItemRepository ?? new LineItemRepository();
        $this->invoiceRepository = $invoiceRepository ?? new InvoiceRepository();
    }

    /**
     * Retrieve orders filtered by change date and optional customer.
     *
     * @param array{
     *     geaendertAm_from: mixed,
     *     geaendertAm_to?: mixed,
     *     customer_id?: mixed
     * } $filters
     * @param array{limit:int,offset:int} $pagination
     * @return array{items:array<int,array<string,mixed>>,total_count:int}
     */
    public function getOrders(array $filters, array $pagination = ['limit' => 25, 'offset' => 0]): array
    {
        
        // Use InputFilter utility for date normalization and validation
        $changedFrom = InputFilter::filterDateValueProvided($filters, 'geaendertAm_from', true, false);
        $changedTo = InputFilter::filterDateValueProvided($filters, 'geaendertAm_to', false, true)
            ?? new DateTimeImmutable('now', $changedFrom->getTimezone());

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

        // Check if verarbeitet column exists
        $verarbeitetSelect = $this->supportsProcessedFlag() 
            ? 'COALESCE(o.verarbeitet, 0) AS verarbeitet' 
            : '0 AS verarbeitet';

        // First, let's check if there are ANY orders in the table
        $countSql = <<<SQL
            SELECT 
                COUNT(*) as total,
                MIN(GeaendertAm) as min_date,
                MAX(GeaendertAm) as max_date
            FROM {$this->ordersTable}
        SQL;

        $countStmt = $this->db->query($countSql);
        $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);

        $sqlConditions = implode(' AND ', $conditions);
        
        $fromSql = <<<SQL
            FROM {$this->ordersTable} o
            LEFT JOIN {$this->customerTable} c ON c.id = o.Kunde
            LEFT JOIN {$this->customerArticleTable} ca ON ca.customer_id = c.id
            LEFT JOIN {$this->articleTable} a ON a.id = ca.article_id
            WHERE {$sqlConditions}
        SQL;

        $countSql = <<<SQL
            SELECT COUNT(DISTINCT o.Id) as total {$fromSql}
        SQL;

        $countStmt = $this->db->prepare($countSql);

        foreach ($params as $name => $value) {

            if ($name === ':customer_id') {
                $countStmt->bindValue($name, $value, PDO::PARAM_INT);
                continue;
            }

            $countStmt->bindValue($name, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $totalCount = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = <<<SQL
            SELECT
                o.Id AS order_id,
                -- o.Kunde AS customer_id,
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
                c.Nummer AS customer_number,
                c.lex_customer_number,
                c.id AS customer_id,
                {$verarbeitetSelect}

            {$fromSql}
            ORDER BY o.GeaendertAm ASC, o.Id ASC
            LIMIT :limit OFFSET :offset 
        SQL;

        $stmt = $this->db->prepare($sql);

        foreach ($params as $name => $value) 
        {
            if ($name === ':customer_id') {
                $stmt->bindValue($name, $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue($name, $value, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);

        $stmt->execute();

        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        Logger::log('OrderRepository', 'Found %d orders', count($orders));

        return [
            'items' => $orders ?: [],
            'total_count' => $totalCount,
        ];
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
    public function generateLineItemsFromOrders(array $filters = []): array
    {
        
        $where = [];
        $params = [];
        $paramTypes = [];

        $customerId = $filters['customer_id'] ?? $filters['Kunde'] ?? null;

        if ($customerId !== null && $customerId !== '') {
            // restrict to a single customer if provided
            $where[] = 'o.Kunde = :customer_id';
            $params[':customer_id'] = (int) $customerId;
            $paramTypes[':customer_id'] = PDO::PARAM_INT;
        }

        $orderIdsFilter = [];

        if (InputFilter::filterValueProvided($filters, 'order_id')) {
            $orderIdsFilter[] = (int) $filters['order_id'];
        }

        if (InputFilter::filterValueProvided($filters, 'order_ids') && is_array($filters['order_ids'])) {
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

        // Remove duplicates and invalid IDs (non-positive integers) from orderIdsFilter
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

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $deliveryFrom = null;

        if (InputFilter::filterValueProvided($filters, 'liefer_datum_von')) {
            $deliveryFrom = $this->normalizeBoundaryDate($filters['liefer_datum_von'], 'liefer_datum_von');
        }

        $deliveryTo = null;
        if (InputFilter::filterValueProvided($filters, 'liefer_datum_bis')) {
            $deliveryTo = $this->normalizeBoundaryDate($filters['liefer_datum_bis'], 'liefer_datum_bis');
        }

        // fetch raw orders rows for the selected customers/weeks
        $sql = <<<SQL
            SELECT 
                o.Id AS order_id,
                o.Jahr AS order_year,
                o.KW AS order_week,
                o.Mo,
                o.Di,
                o.Mi,
                o.Do,
                o.Fr,
                ca.article_id,
                a.article_number,
                a.name AS article_name,
                c.id AS customer_id
                    
                FROM {$this->ordersTable} o
                LEFT JOIN {$this->customerTable} c
                    ON c.id = o.Kunde
                LEFT JOIN {$this->customerArticleTable} ca
                    ON ca.customer_id = c.id
                LEFT JOIN {$this->articleTable} a
                    ON a.id = ca.article_id
                {$whereSql}
                ORDER BY o.Id ASC, o.Kunde, o.Jahr, o.KW
        SQL;

        $stmt = $this->db->prepare($sql);

        foreach ($params as $name => $value) {

            if (isset($paramTypes[$name])) {
                $stmt->bindValue($name, $value, $paramTypes[$name]);
                continue;
            }

            $stmt->bindValue($name, $value);
        }

        $stmt->execute();

        // fetch all matching order rows with article info in one go
        $orderRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$orderRows) {
            return [];
        }

        // Collect all unique article IDs from $orderRows
        $articleIds = array_unique(array_map('intval', array_filter(array_column($orderRows, 'article_id'))));

        // Fetch all articles with their most recent price in one call
        $articles = $this->articleRepository->searchArticles(['id' => $articleIds]);
        $articleMap = array_column($articles, null, 'id');

        $results = [];
        $lineCountPerOrder = [];
        $missingMappings = [];
        $missingArticles = [];
        $missingPrices = [];

        $formatList = static function (array $values): string {

        $unique = array_values(array_unique(array_map(static fn($value) => (string) $value, $values)));
            
            if (!$unique)  
                return '-';

            $slice = array_slice($unique, 0, 5);
            $list = implode(', ', $slice);

            if (count($unique) > 5) 
                $list .= ', ...';

            return $list;
        };


        // Process each order row to generate line items
        foreach ($orderRows as $orderRow) 
        {
            $year = (int) ($orderRow['order_year'] ?? 0);
            $week = (int) ($orderRow['order_week'] ?? 0);

            // Extract weekday quantities from order row
            $weekdayQuantities = $this->dateCalculator->extractWeekdayQuantities($orderRow);

            // Calculate delivery dates for this week with filters applied
            try {
                $deliveryDates = $this->dateCalculator->calculateDeliveryDatesForWeek(
                    $year,
                    $week,
                    $weekdayQuantities,
                    $deliveryFrom,
                    $deliveryTo
                );
            } catch (\Exception $e) {
                continue;
            }

            $lineOrder = 0;
            $orderLineItems = [];

            foreach ($deliveryDates as $weekdayCode => $dateInfo)  {
                $deliveryDate = $dateInfo['date'];
                $quantity = $dateInfo['quantity'];

                $quantityValue = $this->calculator->normalizeQuantity($quantity);

                if (abs($quantityValue) < OrderDomainConstants::MIN_QUANTITY_THRESHOLD) {
                    continue;
                }

                $lineOrder++;
                $orderRow['line_order'] = $lineOrder;

                $orderId = (int) $orderRow['order_id'];
                $customerKey = (int) ($orderRow['customer_id'] ?? 0);
                $articleId = $orderRow['article_id'] !== null ? (int) $orderRow['article_id'] : null;

                if ($articleId === null) {
                    throw new \RuntimeException('Fuer Kunde ' . $customerKey . ' fehlt eine Artikelzuordnung');
                }

                $article = $articleMap[$articleId] ?? null;
                if ($article === null) {
                    throw new \RuntimeException('Artikel-ID ' . $articleId . ' nicht gefunden');
                }

                $lineItem = $this->lineItemBuilder->buildLineItemPayload($orderRow, $article, $deliveryDate, $quantityValue);

                // Check for missing price
                if (!isset($lineItem['net_amount']) || !isset($lineItem['gross_amount']) || $lineItem['net_amount'] === null || $lineItem['gross_amount'] === null) {
                    throw new \RuntimeException('existiert kein gueltiger Preis');
                }

                $orderLineItems[] = $lineItem;
                $results[$customerKey][] = $lineItem;
            }

            // Persist all line items for this order in a single call
            if (!empty($orderLineItems)) {
                $this->lineItemRepository->persistLineItemsForCustomer($orderLineItems);
                $orderId = (int) $orderRow['order_id'];
                $lineCountPerOrder[$orderId] = ($lineCountPerOrder[$orderId] ?? 0) + count($orderLineItems);
            }
        }

        $this->markOrdersAsProcessed($lineCountPerOrder);

        return $results;
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
     * Persist processed state for orders that produced at least one line item.
     *
     * @param array<int, int> $lineCountPerOrder
     */
    private function markOrdersAsProcessed(array $lineCountPerOrder): void
    {
        if (!$lineCountPerOrder || !$this->supportsProcessedFlag()) {
            return;
        }

        // Get order IDs that had line items generated (i.e. processed/verarbeitet)
        $orderIds = array_keys(array_filter($lineCountPerOrder, fn($count) => $count > 0));

        $placeholders = [];
        $params = [];

        foreach ($orderIds as $index => $orderId) {
            $placeholder = ':processed_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $orderId;
        }


        $sql = <<<SQL
            UPDATE {$this->ordersTable}
            SET verarbeitet = 1
            WHERE Id IN (%s)
        SQL;
        
        $sql = sprintf($sql, implode(', ', $placeholders));

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

    /**
     * Generate invoices directly from orders:
     * 1. Generate line items from orders grouped by customer
     * 2. Persist line items to invoice_line_items table
     * 3. Create invoices for each customer using stored procedure
     *
     * @param array<string, mixed> $filters
     * @return array{lineItemsGenerated: int, invoicesCreated: int, customers: array<int, int>, error?: string}
     */
    public function generateInvoicesFromOrders(array $filters = []): array
    {

        try {
            
            // Step 1: Generate and persist line items from orders
            $lineItemsByCustomer = $this->generateLineItemsFromOrders($filters);

            if (empty($lineItemsByCustomer)) {
                return $lineItemsByCustomer;
            }

            // Step 2: Count line items and customers
            $totalLineItems = 0;
            $customersProcessed = [];
            $persistedLineItemIds = [];

            foreach ($lineItemsByCustomer as $customerId => $customerLineItems) {

                if (!is_array($customerLineItems) || empty($customerLineItems)) {
                    continue;
                }

                $totalLineItems += count($customerLineItems);
                $customersProcessed[] = (int)$customerId;
                $ids = array_filter(array_column($customerLineItems, 'id'));
                $persistedLineItemIds = array_merge($persistedLineItemIds, $ids);
            }


            // Step 3: Create invoices for pending line items using stored procedure
            $invoiceResult = $this->invoiceRepository->createInvoiceWithItems($persistedLineItemIds);

            $invoicesCreated = $invoiceResult['createdInvoices'] ?? [];
                        
            $response = [
                'lineItemsGenerated' => $totalLineItems,
                'invoicesCreated' => count($invoicesCreated),
                'invoices' => $invoicesCreated ,
                'customers' => $customersProcessed,
                'persistedIds' => $persistedLineItemIds,
            ];

            return $response;

        } catch (\Throwable $exception) {

            Logger::exception($exception, 'OrderRepository');
            
            return [
                'lineItemsGenerated' => 0,
                'invoicesCreated' => 0,
                'customers' => [],
                'error' => $exception->getMessage(),
            ];
        }
    }
}
