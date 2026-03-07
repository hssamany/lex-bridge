<?php

declare(strict_types=1);

namespace Tests\Repositories;

use DateTimeImmutable;
use InvalidArgumentException;
use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Repositories\OrderRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/config.php';

final class OrderRepositoryTest extends TestCase
{
    private PDO $pdo;
    private OrderRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->createSchema();
        $this->setDatabaseConnection($this->pdo);

        global $tableNames;
        $tableNames['orders'] = 'orders';
        $tableNames['customer'] = 'customer';
        $tableNames['articles'] = 'articles';
        $tableNames['prices'] = 'prices';
        $tableNames['customers_article'] = 'customers_article';

        $this->repository = new OrderRepository();
        $this->forceSupportsProcessedFlag(true);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseConnection();
        unset($this->repository, $this->pdo);
        parent::tearDown();
    }

    public function testGetOrdersRequiresStartDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repository->getOrders([]);
    }

    public function testGetOrdersReturnsRowsWithinDateRangeAndCustomer(): void
    {
        $customerId = $this->insertCustomer('Acme GmbH');
        $otherCustomerId = $this->insertCustomer('Globex SE');

        $articleId = $this->insertArticle([
            'article_number' => 'LEG-010',
            'name' => 'Artikel 10',
            'description' => null,
            'unit_name' => 'stk',
        ]);
        $this->insertCustomerArticle($otherCustomerId, $articleId);

        $this->insertOrder([
            'Kunde' => $customerId,
            'Jahr' => 2024,
            'KW' => 3,
            'Mo' => 2.50,
            'Di' => 0,
            'Mi' => 0,
            'Do' => 0,
            'Fr' => 0,
            'order_date' => '2024-01-15',
        ]);

        $secondOrderId = $this->insertOrder([
            'Kunde' => $otherCustomerId,
            'Jahr' => 2024,
            'KW' => 4,
            'Mo' => 1.00,
            'Di' => 1.00,
            'Mi' => 1.00,
            'Do' => 1.00,
            'Fr' => 1.00,
            'order_date' => '2024-01-24',
        ]);

        $orders = $this->repository->getOrders([
            'order_date_from' => '2024-01-01',
            'order_date_to' => '2024-01-31',
            'customer_id' => $otherCustomerId,
        ]);

        self::assertCount(1, $orders['items']);
        $order = $orders['items'][0];
        self::assertSame($secondOrderId, (int) $order['order_id']);
        self::assertSame($otherCustomerId, (int) $order['customer_id']);
        self::assertSame(2024, (int) $order['order_year']);
        self::assertSame(4, (int) $order['order_week']);
        self::assertSame($articleId, (int) $order['article_id']);
        self::assertSame('LEG-010', $order['article_number']);
    }

    public function testGenerateInvoiceLineItemsProducesExpectedPayloadAndMarksOrders(): void
    {
        $customerId = $this->insertCustomer('Acme GmbH');
        $articleId = $this->insertArticle([
            'article_number' => 'SERV-001',
            'name' => 'Service Paket',
            'description' => 'Dienstleistung',
            'unit_name' => 'kg',
        ]);
        $this->insertCustomerArticle($customerId, $articleId);

        $this->insertPrice([
            'article_id' => $articleId,
            'net_amount' => '3.995',
            'gross_amount' => '4.7545',
            'tax_rate_percentage' => '19.00',
            'currency' => 'EUR',
            'valid_from' => '2024-01-01',
            'valid_until' => null,
        ]);

        $orderId = $this->insertOrder([
            'Kunde' => $customerId,
            'Jahr' => 2024,
            'KW' => 5,
            'Mo' => 2.3456,
            'Di' => 0.1000,
            'Mi' => 0,
            'Do' => 0,
            'Fr' => 0,
            'order_date' => '2024-02-01',
        ]);

        $results = $this->repository->generateLineItemsFromOrders([
            'order_ids' => [$orderId],
        ]);

        self::assertArrayHasKey($customerId, $results);
        $lineItems = $results[$customerId];
        self::assertCount(2, $lineItems);

        $first = $lineItems[0];
        $second = $lineItems[1];

        $firstDate = new DateTimeImmutable($first['order_delivery_date']);
        $secondDate = new DateTimeImmutable($second['order_delivery_date']);

        self::assertSame('2024-01-29', $firstDate->format('Y-m-d')); // Monday of KW5
        self::assertSame('2024-01-30', $secondDate->format('Y-m-d'));
        self::assertSame($orderId, $first['order_id']);
        self::assertSame($articleId, $first['article_id']);
        self::assertSame('SERV-001', $first['article_number']);
        self::assertSame('Service Paket', $first['article_name']);
        self::assertSame('EUR', $first['currency']);
        self::assertEqualsWithDelta(2.3456, $first['quantity'], 0.0001);
        self::assertEqualsWithDelta(3.995, $first['net_amount'], 0.0001);
        self::assertEqualsWithDelta(4.7545, $first['gross_amount'], 0.0001);
        self::assertEquals(9.37, $first['line_total_net']);
        self::assertEquals(11.15, $first['line_total_gross']);

        self::assertEqualsWithDelta(0.1, $second['quantity'], 0.0001);
        self::assertEquals(0.40, $second['line_total_net']);
        self::assertEquals(0.48, $second['line_total_gross']);

        $processed = $this->pdo->query('SELECT verarbeitet FROM orders WHERE Id = ' . $orderId)->fetchColumn();
        self::assertSame('1', (string) $processed);
    }

    public function testGenerateInvoiceLineItemsThrowsWhenCustomerMappingMissing(): void
    {
        $customerId = $this->insertCustomer('Ohne Mapping GmbH');
        $orderId = $this->insertOrder([
            'Kunde' => $customerId,
            'Jahr' => 2024,
            'KW' => 12,
            'Mo' => 1,
            'Di' => 0,
            'Mi' => 0,
            'Do' => 0,
            'Fr' => 0,
            'order_date' => '2024-03-18',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fuer Kunde ' . $customerId . ' fehlt eine Artikelzuordnung');
        $this->repository->generateLineItemsFromOrders([
            'order_ids' => [$orderId],
        ]);

        $processed = $this->pdo->query('SELECT verarbeitet FROM orders WHERE Id = ' . $orderId)->fetchColumn();
        self::assertSame('0', (string) $processed);
    }

    public function testGenerateLineItemsThrowsWhenArticleMissing(): void
    {
        $customerId = $this->insertCustomer('Fehlartikel AG');
        $this->insertCustomerArticle($customerId, 999, enforceForeignKeys: false); // points to missing article

        $orderId = $this->insertOrder([
            'Kunde' => $customerId,
            'Jahr' => 2024,
            'KW' => 15,
            'Mo' => 1,
            'Di' => 0,
            'Mi' => 0,
            'Do' => 0,
            'Fr' => 0,
            'order_date' => '2024-04-08',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Artikel-ID 999');
        $this->repository->generateLineItemsFromOrders([
            'order_ids' => [$orderId],
        ]);
    }

    public function testGenerateLineItemsThrowsWhenPriceMissingForDate(): void
    {
        $customerId = $this->insertCustomer('Preislos KG');
        $articleId = $this->insertArticle([
            'article_number' => 'NOPRICE',
            'name' => 'Ohne Preis',
            'description' => null,
            'unit_name' => 'stk',
        ]);
        $this->insertCustomerArticle($customerId, $articleId);

        // price valid only before delivery
        $this->insertPrice([
            'article_id' => $articleId,
            'net_amount' => '5.00',
            'gross_amount' => '5.95',
            'tax_rate_percentage' => '19.00',
            'currency' => 'EUR',
            'valid_from' => '2023-01-01',
            'valid_until' => '2023-12-31',
        ]);

        $orderId = $this->insertOrder([
            'Kunde' => $customerId,
            'Jahr' => 2024,
            'KW' => 1,
            'Mo' => 1,
            'Di' => 0,
            'Mi' => 0,
            'Do' => 0,
            'Fr' => 0,
            'order_date' => '2024-01-02',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('existiert kein gueltiger Preis');
        $this->repository->generateLineItemsFromOrders([
            'order_ids' => [$orderId],
        ]);
    }

    private function createSchema(): void
    {
        $this->pdo->exec('CREATE TABLE customer (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            Name TEXT,
            Nummer TEXT,
            lex_customer_number TEXT
        )');

        $this->pdo->exec('CREATE TABLE orders (
            Id INTEGER PRIMARY KEY AUTOINCREMENT,
            Kunde INTEGER NOT NULL,
            Jahr INTEGER NOT NULL,
            KW INTEGER NOT NULL,
            Mo REAL,
            Di REAL,
            Mi REAL,
            Do REAL,
            Fr REAL,
            order_date DATE,
            verarbeitet INTEGER DEFAULT 0,
            FOREIGN KEY(Kunde) REFERENCES customer(id)
        )');

        $this->pdo->exec('CREATE TABLE articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            article_number TEXT,
            name TEXT,
            description TEXT,
            unit_name TEXT
        )');

        $this->pdo->exec('CREATE TABLE prices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            article_id INTEGER NOT NULL,
            net_amount TEXT,
            gross_amount TEXT,
            tax_rate_percentage TEXT,
            currency TEXT,
            valid_from TEXT,
            valid_until TEXT,
            FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE
        )');

        $this->pdo->exec('CREATE TABLE customers_article (
            customer_id INTEGER NOT NULL UNIQUE,
            article_id INTEGER NOT NULL,
            FOREIGN KEY(customer_id) REFERENCES customer(id) ON DELETE CASCADE,
            FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE
        )');

        
        $this->pdo->exec('DROP TABLE IF EXISTS invoice_line_items;');
        $this->pdo->exec('CREATE TABLE invoice_line_items (
            id CHAR(36) PRIMARY KEY,
            order_id INTEGER,
            line_order INTEGER,
            article_id INTEGER,
            article_number VARCHAR(84),
            name VARCHAR(255),
            description TEXT,
            unit_name VARCHAR(50),
            tax_rate_percentage DECIMAL(5,2),
            quantity DECIMAL(10,3),
            net_amount DECIMAL(10,2),
            gross_amount DECIMAL(10,2),
            currency VARCHAR(3),
            order_delivery_date DATE,
            line_total_net DECIMAL(10,2),
            line_total_gross DECIMAL(10,2),
            created_at TIMESTAMP,
            updated_at TIMESTAMP,
            customer_id INTEGER
        );');
    }
    private function insertOrder(array $data): int
    {
        $columns = ['Kunde', 'Jahr', 'KW', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'order_date'];

        $placeholders = [];
        $params = [];
        foreach ($columns as $column) {
            $placeholder = ':' . $column;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $data[$column] ?? null;
        }

        $sql = 'INSERT INTO orders (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertArticle(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO articles (article_number, name, description, unit_name) VALUES (:number, :name, :description, :unit)');
        $stmt->execute([
            ':number' => $data['article_number'] ?? null,
            ':name' => $data['name'] ?? null,
            ':description' => $data['description'] ?? null,
            ':unit' => $data['unit_name'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertCustomerArticle(int $customerId, int $articleId, bool $enforceForeignKeys = true): void
    {
        $restoreForeignKey = false;
        if (!$enforceForeignKeys) {
            $this->pdo->exec('PRAGMA foreign_keys = OFF');
            $restoreForeignKey = true;
        }

        try {
            $stmt = $this->pdo->prepare('INSERT INTO customers_article (customer_id, article_id) VALUES (:customer, :article)');
            $stmt->execute([
                ':customer' => $customerId,
                ':article' => $articleId,
            ]);
        } finally {
            if ($restoreForeignKey) {
                $this->pdo->exec('PRAGMA foreign_keys = ON');
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertPrice(array $data): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO prices (article_id, net_amount, gross_amount, tax_rate_percentage, currency, valid_from, valid_until) VALUES (:article, :net, :gross, :tax, :currency, :from, :until)');
        $stmt->execute([
            ':article' => $data['article_id'],
            ':net' => $data['net_amount'] ?? null,
            ':gross' => $data['gross_amount'] ?? null,
            ':tax' => $data['tax_rate_percentage'] ?? null,
            ':currency' => $data['currency'] ?? null,
            ':from' => $data['valid_from'] ?? null,
            ':until' => $data['valid_until'] ?? null,
        ]);
    }

    private function insertCustomer(string $name): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO customer (Name) VALUES (:name)');
        $stmt->execute([':name' => $name]);
        return (int) $this->pdo->lastInsertId();
    }

    private function setDatabaseConnection(PDO $pdo): void
    {
        $reflection = new ReflectionClass(Database::class);
        $property = $reflection->getProperty('connection');
        $property->setAccessible(true);
        $property->setValue(null, $pdo);
    }

    private function resetDatabaseConnection(): void
    {
        $reflection = new ReflectionClass(Database::class);
        $property = $reflection->getProperty('connection');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    private function forceSupportsProcessedFlag(bool $supports): void
    {
        $reflection = new ReflectionClass(OrderRepository::class);
        $property = $reflection->getProperty('supportsProcessedFlag');
        $property->setAccessible(true);
        $property->setValue($this->repository, $supports);
    }
}
