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
    private LineItemRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new LineItemTestingPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->createSchema($this->pdo);
        $this->setDatabaseConnection($this->pdo);

        global $tableNames;
        $tableNames['invoice_line_items'] = 'invoice_line_items';
        $tableNames['invoices'] = 'invoices';
        $tableNames['customer'] = 'customer';

        $this->repository = new LineItemRepository();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseConnection();
        unset($this->repository, $this->pdo);
        parent::tearDown();
    }

    public function testFindLineItemsReturnsJoinedRowsWithFilters(): void
    {
        $customerA = $this->insertCustomer(['id' => 1, 'company_name' => 'Acme GmbH', 'kundenNummer' => 'ACM-01']);
        $customerB = $this->insertCustomer(['id' => 2, 'company_name' => 'Beta AG', 'kundenNummer' => 'BET-02']);

        $invoiceA = $this->insertInvoice(['id' => 'inv-a', 'contact_id' => $customerA, 'voucher_date' => '2024-01-10']);
        $invoiceB = $this->insertInvoice(['id' => 'inv-b', 'contact_id' => $customerB, 'voucher_date' => '2024-02-05']);

        $this->insertLineItem([
            'id' => 'li-1',
            'invoice_id' => $invoiceA,
            'created_at' => '2024-01-11 08:00:00',
            'line_order' => 2,
            'name' => 'Service A2',
        ]);

        $this->insertLineItem([
            'id' => 'li-2',
            'invoice_id' => $invoiceA,
            'created_at' => '2024-01-12 08:00:00',
            'line_order' => 1,
            'name' => 'Service A1',
        ]);

        $this->insertLineItem([
            'id' => 'li-3',
            'invoice_id' => $invoiceB,
            'created_at' => '2024-03-01 09:00:00',
            'line_order' => 1,
            'name' => 'Service B1',
        ]);

        $items = $this->repository->findLineItems([
            'created_at_from' => '2024-01-01 00:00:00',
            'created_at_to' => '2024-02-01 00:00:00',
            'customer_id' => $customerA,
        ]);

        self::assertCount(2, $items);
        self::assertSame(['li-2', 'li-1'], array_column($items, 'id'));
        self::assertSame('Service A1', $items[0]['name']);
        self::assertSame('Acme GmbH', $items[0]['company_name']);
        self::assertSame('ACM-01', $items[0]['customer_number']);
        self::assertSame('2024-01-10', $items[0]['voucher_date']);
    }

    public function testFindLineItemsCapsResultsAtTwoHundred(): void
    {
        $customerId = $this->insertCustomer();
        $invoiceId = $this->insertInvoice(['contact_id' => $customerId]);

        for ($i = 0; $i < 230; $i++) {
            $this->insertLineItem([
                'id' => sprintf('li-%03d', $i),
                'invoice_id' => $invoiceId,
                'created_at' => sprintf('2024-01-01 00:%02d:00', $i % 60),
                'line_order' => $i,
            ]);
        }

        $items = $this->repository->findLineItems();
        self::assertCount(200, $items);
        $ids = array_column($items, 'id');
        sort($ids, SORT_STRING);
        self::assertSame('li-007', $ids[0]);
        self::assertSame('li-229', $ids[199]);
    }

    public function testFindLineItemByIdReturnsRow(): void
    {
        $invoiceId = $this->insertInvoice();
        $this->insertLineItem([
            'id' => 'li-test',
            'invoice_id' => $invoiceId,
            'name' => 'Lookup',
            'net_amount' => 10.5,
            'gross_amount' => 12.5,
        ]);

        $row = $this->repository->findLineItemById('li-test');
        self::assertNotNull($row);
        self::assertSame('Lookup', $row['name']);
        self::assertSame(10.5, (float) $row['net_amount']);
        self::assertArrayNotHasKey('company_name', $row);
    }

    public function testFindLineItemByIdReturnsNullWhenMissing(): void
    {
        self::assertNull($this->repository->findLineItemById('missing'));
    }

    public function testUpdateLineItemUpdatesArticleFieldsAndTotals(): void
    {
        $invoiceId = $this->insertInvoice();

        $this->insertLineItem([
            'id' => 'li-update',
            'invoice_id' => $invoiceId,
            'quantity' => 3,
            'line_total_net' => 30.0,
            'line_total_gross' => 36.0,
        ]);

        $data = [
            'article_id' => 5,
            'article_number' => 'ART-5',
            'article_label' => 'Artikel 5',
            'article_name' => 'New Name',
            'currency' => 'EUR',
            'net_amount' => 12.5,
            'gross_amount' => 15.5,
            'tax_rate_percentage' => 19.0,
            'article_valid_from' => '2024-01-01',
            'article_valid_until' => '2024-12-31',
        ];

        self::assertTrue($this->repository->updateLineItem('li-update', $data));

        $row = $this->pdo->query("SELECT article_id, article_number, article_label, name, currency, net_amount, gross_amount, tax_rate_percentage, line_total_net, line_total_gross, article_valid_from, article_valid_until, updated_at FROM invoice_line_items WHERE id = 'li-update'")->fetch();

        self::assertSame(5, (int) $row['article_id']);
        self::assertSame('ART-5', $row['article_number']);
        self::assertSame('Artikel 5', $row['article_label']);
        self::assertSame('New Name', $row['name']);
        self::assertSame('EUR', $row['currency']);
        self::assertSame(12.5, (float) $row['net_amount']);
        self::assertSame(15.5, (float) $row['gross_amount']);
        self::assertSame(19.0, (float) $row['tax_rate_percentage']);
        self::assertSame(37.5, (float) $row['line_total_net']);
        self::assertSame(46.5, (float) $row['line_total_gross']);
        self::assertSame('2024-01-01', $row['article_valid_from']);
        self::assertSame('2024-12-31', $row['article_valid_until']);
        self::assertNotNull($row['updated_at']);
    }

    public function testUpdateLineItemLeavesTotalsWhenAmountsMissing(): void
    {
        $invoiceId = $this->insertInvoice();

        $this->insertLineItem([
            'id' => 'li-update-null',
            'invoice_id' => $invoiceId,
            'quantity' => 4,
            'line_total_net' => 40.0,
            'line_total_gross' => 47.6,
        ]);

        self::assertTrue($this->repository->updateLineItem('li-update-null', [
            'article_id' => null,
            'article_number' => null,
            'article_label' => null,
            'article_name' => 'Retain totals',
            'currency' => null,
            'net_amount' => null,
            'gross_amount' => null,
            'tax_rate_percentage' => 7.0,
            'article_valid_from' => null,
            'article_valid_until' => null,
        ]));

        $row = $this->pdo->query("SELECT name, line_total_net, line_total_gross, tax_rate_percentage FROM invoice_line_items WHERE id = 'li-update-null'")->fetch();
        self::assertSame('Retain totals', $row['name']);
        self::assertSame(40.0, (float) $row['line_total_net']);
        self::assertSame(47.6, (float) $row['line_total_gross']);
        self::assertSame(7.0, (float) $row['tax_rate_percentage']);
    }

    private function createSchema(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE customer (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_name TEXT,
            kundenNummer TEXT
        )');

        $pdo->exec('CREATE TABLE invoices (
            id TEXT PRIMARY KEY,
            contact_id INTEGER,
            voucher_date TEXT
        )');

        $pdo->exec('CREATE TABLE invoice_line_items (
            id TEXT PRIMARY KEY,
            invoice_id TEXT,
            customer_number TEXT,
            order_id TEXT,
            order_delivery_date TEXT,
            line_order INTEGER,
            name TEXT,
            description TEXT,
            quantity REAL,
            currency TEXT,
            net_amount REAL,
            gross_amount REAL,
            tax_rate_percentage REAL,
            line_total_net REAL,
            line_total_gross REAL,
            article_id INTEGER,
            article_number TEXT,
            article_label TEXT,
            article_valid_from TEXT,
            article_valid_until TEXT,
            created_at TEXT,
            updated_at TEXT
        )');
    }

    private function insertCustomer(array $overrides = []): int
    {
        $data = $overrides + [
            'id' => null,
            'company_name' => 'Customer ' . uniqid('', false),
            'kundenNummer' => null,
        ];

        if ($data['id'] !== null) {
            $stmt = $this->pdo->prepare('INSERT INTO customer (id, company_name, kundenNummer) VALUES (:id, :company_name, :kundenNummer)');
            $stmt->execute([
                ':id' => $data['id'],
                ':company_name' => $data['company_name'],
                ':kundenNummer' => $data['kundenNummer'],
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO customer (company_name, kundenNummer) VALUES (:company_name, :kundenNummer)');
            $stmt->execute([
                ':company_name' => $data['company_name'],
                ':kundenNummer' => $data['kundenNummer'],
            ]);
        }

        return $data['id'] !== null ? (int) $data['id'] : (int) $this->pdo->lastInsertId();
    }

    private function insertInvoice(array $overrides = []): string
    {
        $data = $overrides + [
            'id' => 'inv-' . uniqid('', false),
            'contact_id' => null,
            'voucher_date' => '2024-01-01',
        ];

        $stmt = $this->pdo->prepare('INSERT INTO invoices (id, contact_id, voucher_date) VALUES (:id, :contact_id, :voucher_date)');
        $stmt->execute([
            ':id' => $data['id'],
            ':contact_id' => $data['contact_id'],
            ':voucher_date' => $data['voucher_date'],
        ]);

        return (string) $data['id'];
    }

    private function insertLineItem(array $overrides = []): string
    {
        $data = $overrides + [
            'id' => 'li-' . uniqid('', false),
            'invoice_id' => null,
            'line_order' => 0,
            'name' => 'Item',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];

        $stmt = $this->pdo->prepare('INSERT INTO invoice_line_items (
            id, invoice_id, customer_number, order_id, order_delivery_date, line_order, name, description, quantity, currency, net_amount, gross_amount,
            tax_rate_percentage, line_total_net, line_total_gross, article_id, article_number, article_label, article_valid_from, article_valid_until,
            created_at, updated_at
        ) VALUES (
            :id, :invoice_id, :customer_number, :order_id, :order_delivery_date, :line_order, :name, :description, :quantity, :currency, :net_amount, :gross_amount,
            :tax_rate_percentage, :line_total_net, :line_total_gross, :article_id, :article_number, :article_label, :article_valid_from, :article_valid_until,
            :created_at, :updated_at
        )');

        $stmt->execute([
            ':id' => $data['id'],
            ':invoice_id' => $data['invoice_id'],
            ':customer_number' => $data['customer_number'] ?? null,
            ':order_id' => $data['order_id'] ?? null,
            ':order_delivery_date' => $data['order_delivery_date'] ?? null,
            ':line_order' => $data['line_order'],
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':quantity' => $data['quantity'] ?? null,
            ':currency' => $data['currency'] ?? null,
            ':net_amount' => $data['net_amount'] ?? null,
            ':gross_amount' => $data['gross_amount'] ?? null,
            ':tax_rate_percentage' => $data['tax_rate_percentage'] ?? null,
            ':line_total_net' => $data['line_total_net'] ?? null,
            ':line_total_gross' => $data['line_total_gross'] ?? null,
            ':article_id' => $data['article_id'] ?? null,
            ':article_number' => $data['article_number'] ?? null,
            ':article_label' => $data['article_label'] ?? null,
            ':article_valid_from' => $data['article_valid_from'] ?? null,
            ':article_valid_until' => $data['article_valid_until'] ?? null,
            ':created_at' => $data['created_at'],
            ':updated_at' => $data['updated_at'],
        ]);

        return (string) $data['id'];
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
}

final class LineItemTestingPDO extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
    }

    public function prepare(string $statement, array $options = []): PDOStatement|false
    {
        $statement = $this->transformSql($statement);
        return parent::prepare($statement, $options);
    }

    private function transformSql(string $sql): string
    {
        return (string) preg_replace('/NOW\s*\(\s*\)/i', 'CURRENT_TIMESTAMP', $sql);
    }
}
