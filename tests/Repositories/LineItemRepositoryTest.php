<?php
declare(strict_types=1);

namespace Tests\Repositories;


use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Repositories\LineItemRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once dirname(__DIR__, 2) . '/config.php';

final class LineItemRepositoryTest extends TestCase
{
    private LineItemTestingPDO $pdo;
    // Track rollback calls
    private bool $rollbackCalled = false;
    private LineItemRepository $repository;

    /**
     * Create the required SQLite schema for testing.
     */
    private function createSchema(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE customer (
            id INTEGER PRIMARY KEY,
            Name TEXT,
            Nummer TEXT
        )');
        $pdo->exec('CREATE TABLE invoices (
            id INTEGER PRIMARY KEY,
            customer_id INTEGER,
            created_at TEXT,
            FOREIGN KEY (customer_id) REFERENCES customer(id)
        )');
        $pdo->exec('CREATE TABLE invoice_line_items (
            id TEXT PRIMARY KEY,
            article_id INTEGER,
            article_number TEXT,
            customer_id INTEGER,
            name TEXT,
            description TEXT,
            quantity INTEGER,
            unit_name TEXT,
            currency TEXT,
            net_amount REAL,
            gross_amount REAL,
            tax_rate_percentage REAL,
            line_total_net REAL,
            line_total_gross REAL,
            order_delivery_date TEXT,
            line_order INTEGER,
            order_id TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customer(id)
        )');
    }

    /**
     * Insert a customer row and return its ID.
     */
    private function insertCustomer(array $data): int
    {
        $id = $data['id'] ?? random_int(1000, 999999);
        $stmt = $this->pdo->prepare('INSERT INTO customer (id, Name, Nummer) VALUES (:id, :Name, :Nummer)');
        $stmt->execute([
            ':id' => $id,
            ':Name' => $data['Name'],
            ':Nummer' => $data['Nummer'],
        ]);
        return $id;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new LineItemTestingPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->createSchema($this->pdo);
        \Luxullus\LexBridge\Database\Database::setConnection($this->pdo);

        global $tableNames;
        $tableNames['invoice_line_items'] = 'invoice_line_items';
        $tableNames['invoices'] = 'invoices';
        $tableNames['customer'] = 'customer';

        $this->repository = new LineItemRepository();
    }

    protected function tearDown(): void
    {
        \Luxullus\LexBridge\Database\Database::resetConnection();
        unset($this->repository, $this->pdo);
        parent::tearDown();
    }

    // --- BEGIN TESTS ---
    public function testPersistLineItemsForCustomerEmptyInput(): void
    {
        $result = $this->repository->persistLineItemsForCustomer([]);
        self::assertIsArray($result);
        self::assertSame(0, $result['persisted']);
        self::assertEmpty($result['persisted_ids']);
        self::assertEmpty($result['errors']);
    }

    public function testPersistLineItemsForCustomerMissingCustomerId(): void
    {
        $lineItems = [[
            'article_id' => 1,
            'article_number' => 'ART-1',
            // 'customer_id' => missing
            'article_name' => 'No Customer',
            'quantity' => 1,
            'currency' => 'EUR',
            'net_amount' => 10.0,
            'gross_amount' => 12.0,
            'tax_rate_percentage' => 19.0,
            'line_total_net' => 10.0,
            'line_total_gross' => 12.0,
            'order_delivery_date' => '2026-02-16',
            'line_order' => 1,
            'order_id' => 'ORD-1',
        ]];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing customer_id for line item at index 0');
        $this->repository->persistLineItemsForCustomer($lineItems);
    }

    public function testPersistLineItemsForCustomerPartialFailureAllOrNothing(): void
    {
        $customerId = $this->insertCustomer(['id' => 201, 'Name' => 'Partial', 'Nummer' => 'PART-01']);
        $lineItems = [
            [
                'article_id' => 1,
                'article_number' => 'ART-1',
                'customer_id' => $customerId,
                'article_name' => 'Good Item',
                'quantity' => 1,
                'currency' => 'EUR',
                'net_amount' => 10.0,
                'gross_amount' => 12.0,
                'tax_rate_percentage' => 19.0,
                'line_total_net' => 10.0,
                'line_total_gross' => 12.0,
                'order_delivery_date' => '2026-02-16',
                'line_order' => 1,
                'order_id' => 'ORD-1',
            ],
            [
                'article_id' => 2,
                'article_number' => 'ART-2',
                // 'customer_id' => missing
                'article_name' => 'Bad Item',
                'quantity' => 2,
                'currency' => 'EUR',
                'net_amount' => 20.0,
                'gross_amount' => 24.0,
                'tax_rate_percentage' => 19.0,
                'line_total_net' => 40.0,
                'line_total_gross' => 48.0,
                'order_delivery_date' => '2026-02-16',
                'line_order' => 2,
                'order_id' => 'ORD-2',
            ],
        ];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing customer_id for line item at index 1');
        $this->repository->persistLineItemsForCustomer($lineItems);
    }

    public function testPersistLineItemsForCustomerSingleItem(): void
    {
        $customerId = $this->insertCustomer(['id' => 301, 'Name' => 'Single', 'Nummer' => 'SING-01']);
        $lineItems = [[
            'article_id' => 1,
            'article_number' => 'ART-1',
            'customer_id' => $customerId,
            'article_name' => 'Single Item',
            'quantity' => 1,
            'currency' => 'EUR',
            'net_amount' => 10.0,
            'gross_amount' => 12.0,
            'tax_rate_percentage' => 19.0,
            'line_total_net' => 10.0,
            'line_total_gross' => 12.0,
            'order_delivery_date' => '2026-02-16',
            'line_order' => 1,
            'order_id' => 'ORD-1',
        ]];
        $result = $this->repository->persistLineItemsForCustomer($lineItems);
        self::assertIsArray($result);
        self::assertSame(1, $result['persisted']);
        self::assertCount(1, $result['persisted_ids']);
        $stmt = $this->pdo->query('SELECT * FROM invoice_line_items WHERE customer_id = 301');
        $rows = $stmt->fetchAll();
        self::assertCount(1, $rows);
        self::assertSame('Single Item', $rows[0]['name']);
    }

    public function testPersistLineItemsForCustomerLargeBatch(): void
    {
        $customerId = $this->insertCustomer(['id' => 401, 'Name' => 'LargeBatch', 'Nummer' => 'LARGE-01']);
        $lineItems = [];
        for ($i = 0; $i < 50; $i++) {
            $lineItems[] = [
                'article_id' => 1000 + $i,
                'article_number' => 'ART-' . ($i + 1),
                'customer_id' => $customerId,
                'article_name' => 'Batch Item ' . ($i + 1),
                'quantity' => 1,
                'currency' => 'EUR',
                'net_amount' => 10.0,
                'gross_amount' => 12.0,
                'tax_rate_percentage' => 19.0,
                'line_total_net' => 10.0,
                'line_total_gross' => 12.0,
                'order_delivery_date' => '2026-02-16',
                'line_order' => $i + 1,
                'order_id' => 'ORD-' . ($i + 1),
            ];
        }
        $result = $this->repository->persistLineItemsForCustomer($lineItems);
        self::assertIsArray($result);
        self::assertSame(50, $result['persisted']);
        self::assertCount(50, $result['persisted_ids']);
        $stmt = $this->pdo->query('SELECT * FROM invoice_line_items WHERE customer_id = 401');
        $rows = $stmt->fetchAll();
        self::assertCount(50, $rows);
    }

    public function testPersistLineItemsForCustomerDbException(): void
    {
        $customerId = $this->insertCustomer(['id' => 501, 'Name' => 'DbException', 'Nummer' => 'DBX-01']);
        // Generate a fixed UUID for both items to force duplicate id
        $duplicateId = 'fixed-uuid-1234';
        $lineItems = [
            [
                'id' => $duplicateId,
                'article_id' => 1,
                'article_number' => 'ART-1',
                'customer_id' => $customerId,
                'article_name' => 'First',
                'quantity' => 1,
                'currency' => 'EUR',
                'net_amount' => 10.0,
                'gross_amount' => 12.0,
                'tax_rate_percentage' => 19.0,
                'line_total_net' => 10.0,
                'line_total_gross' => 12.0,
                'order_delivery_date' => '2026-02-16',
                'line_order' => 1,
                'order_id' => 'ORD-1',
            ],
            [
                'id' => $duplicateId,
                'article_id' => 1,
                'article_number' => 'ART-1',
                'customer_id' => $customerId,
                'article_name' => 'Duplicate',
                'quantity' => 1,
                'currency' => 'EUR',
                'net_amount' => 10.0,
                'gross_amount' => 12.0,
                'tax_rate_percentage' => 19.0,
                'line_total_net' => 10.0,
                'line_total_gross' => 12.0,
                'order_delivery_date' => '2026-02-16',
                'line_order' => 1,
                'order_id' => 'ORD-1',
            ],
        ];
        try {
            $this->repository->persistLineItemsForCustomer($lineItems);
            self::fail('Expected PDOException was not thrown');
        } catch (\PDOException $e) {
            self::assertStringContainsString('UNIQUE constraint failed: invoice_line_items.id', $e->getMessage());
            self::assertTrue($this->pdo->rollbackCalled, 'Transaction rollback should be called on DB exception');
        }
            [[    'currency' => 'EUR',
                'net_amount' => 10.0,
                'gross_amount' => 12.0,
                'tax_rate_percentage' => 19.0,
                'line_total_net' => 10.0,
                'line_total_gross' => 12.0,
                'order_delivery_date' => '2026-02-16',
                'line_order' => 1,
                'order_id' => 'ORD-1',
            ],
            [
                'id' => $duplicateId,
                'article_id' => 1,
                'article_number' => 'ART-1',
                'customer_id' => $customerId,
                'article_name' => 'Duplicate',
                'quantity' => 1,
                'currency' => 'EUR',
                'net_amount' => 10.0,
                'gross_amount' => 12.0,
                'tax_rate_percentage' => 19.0,
                'line_total_net' => 10.0,
                'line_total_gross' => 12.0,
                'order_delivery_date' => '2026-02-16',
                'line_order' => 1,
                'order_id' => 'ORD-1',
            ],
        ];
    }

    public function testPersistLineItemsForCustomerBatchInsert(): void
    {
        $customerId = $this->insertCustomer(['id' => 101, 'Name' => 'Batch Customer', 'Nummer' => 'BATCH-01']);

        $lineItems = [];
        for ($i = 0; $i < 3; $i++) {
            $lineItems[] = [
                'article_id' => 100 + $i,
                'article_number' => 'ART-' . ($i + 1),
                'customer_id' => $customerId,
                'article_name' => 'Batch Item ' . ($i + 1),
                'description' => 'Desc ' . ($i + 1),
                'quantity' => 2 + $i,
                'unit_name' => 'Stk',
                'currency' => 'EUR',
                'net_amount' => 10.0 + $i,
                'gross_amount' => 12.0 + $i,
                'tax_rate_percentage' => 19.0,
                'line_total_net' => (10.0 + $i) * (2 + $i),
                'line_total_gross' => (12.0 + $i) * (2 + $i),
                'order_delivery_date' => '2026-02-16',
                'line_order' => $i + 1,
                'order_id' => 'ORD-' . ($i + 1),
            ];
        }

        $result = $this->repository->persistLineItemsForCustomer($lineItems);

        self::assertIsArray($result);
        self::assertArrayHasKey('persisted', $result);
        self::assertArrayHasKey('persisted_ids', $result);
        self::assertSame(3, $result['persisted']);
        self::assertCount(3, $result['persisted_ids']);

        // Check that the items are in the DB
        $stmt = $this->pdo->query('SELECT * FROM invoice_line_items WHERE customer_id = 101');
        $rows = $stmt->fetchAll();
        self::assertCount(3, $rows);
        $names = array_column($rows, 'name');
        foreach ($lineItems as $item) {
            self::assertContains($item['article_name'], $names);
        }
    }
}