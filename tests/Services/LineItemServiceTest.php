<?php

declare(strict_types=1);

namespace Tests\Services;

use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Repositories\LineItemRepository;
use Luxullus\LexBridge\Services\LineItemService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once dirname(__DIR__, 2) . '/config.php';

final class LineItemServiceTest extends TestCase
{
    private LineItemServiceTestingPDO $pdo;
    private LineItemService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new LineItemServiceTestingPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->createSchema($this->pdo);
        $this->setDatabaseConnection($this->pdo);

        global $tableNames;
        $tableNames['invoice_line_items'] = 'invoice_line_items';
        $tableNames['invoices'] = 'invoices';
        $tableNames['customer'] = 'customer';

        $repository = new LineItemRepository();
        $this->service = new LineItemService($repository);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseConnection();
        unset($this->service, $this->pdo);
        parent::tearDown();
    }

    public function testGetLineItems_ReturnsEnrichedArray(): void
    {
        $customerId = $this->insertCustomer(['id' => 1, 'Name' => 'Test GmbH', 'Nummer' => 'T-001']);
        $invoiceId = $this->insertInvoice(['id' => 'inv-1', 'contact_id' => $customerId, 'voucher_date' => '2024-01-15']);
        $this->insertLineItem([
            'id' => 'li-1',
            'invoice_id' => $invoiceId,
            'name' => 'Service X',
            'quantity' => 2,
            'net_amount' => 100.0,
            'line_order' => 1,
            'created_at' => '2024-01-15 10:00:00',
        ]);

        $result = $this->service->getLineItems();

        self::assertTrue($result['isSuccess']);
        self::assertCount(1, $result['lineItems']);

        $item = $result['lineItems'][0];
        self::assertSame('li-1', $item['id']);
        self::assertSame('Service X', $item['name']);
        self::assertSame('Test GmbH', $item['customer_name']);
        self::assertSame('T-001', $item['customer_number']);
        self::assertSame('inv-1', $item['invoice_id']);
        self::assertSame(2.0, $item['quantity']);
        self::assertSame(100.0, $item['net_amount']);
    }

    public function testGetLineItems_AppliesFilters(): void
    {
        $customerA = $this->insertCustomer(['id' => 1]);
        $customerB = $this->insertCustomer(['id' => 2]);

        $invoiceA = $this->insertInvoice(['id' => 'inv-a', 'contact_id' => $customerA]);
        $invoiceB = $this->insertInvoice(['id' => 'inv-b', 'contact_id' => $customerB]);

        $this->insertLineItem(['invoice_id' => $invoiceA, 'created_at' => '2024-01-15 10:00:00']);
        $this->insertLineItem(['invoice_id' => $invoiceB, 'created_at' => '2024-01-16 10:00:00']);

        $result = $this->service->getLineItems(['customer_id' => $customerA]);

        self::assertTrue($result['isSuccess']);
        self::assertCount(1, $result['lineItems']);
    }

    public function testUpdateLineItem_CalculatesTotalsWhenAmountsProvided(): void
    {
        $invoiceId = $this->insertInvoice();
        $this->insertLineItem([
            'id' => 'li-calc',
            'invoice_id' => $invoiceId,
            'quantity' => 5,
            'line_total_net' => 0.0,
            'line_total_gross' => 0.0,
        ]);

        $result = $this->service->updateLineItem('li-calc', [
            'article_name' => 'Widget',
            'net_amount' => 10.0,
            'gross_amount' => 11.9,
            'tax_rate_percentage' => 19.0,
        ]);

        self::assertTrue($result['isSuccess']);
        self::assertNotNull($result['lineItem']);

        $row = $this->pdo->query("SELECT line_total_net, line_total_gross FROM invoice_line_items WHERE id = 'li-calc'")->fetch();
        self::assertSame(50.0, (float) $row['line_total_net']);  // 10.0 * 5
        self::assertSame(59.5, (float) $row['line_total_gross']);  // 11.9 * 5
    }

    public function testUpdateLineItem_PreservesTotalsWhenAmountsNull(): void
    {
        $invoiceId = $this->insertInvoice();
        $this->insertLineItem([
            'id' => 'li-preserve',
            'invoice_id' => $invoiceId,
            'quantity' => 3,
            'line_total_net' => 75.0,
            'line_total_gross' => 89.25,
        ]);

        $result = $this->service->updateLineItem('li-preserve', [
            'article_name' => 'Keep Totals',
            'net_amount' => null,
            'gross_amount' => null,
        ]);

        self::assertTrue($result['isSuccess']);

        $row = $this->pdo->query("SELECT line_total_net, line_total_gross FROM invoice_line_items WHERE id = 'li-preserve'")->fetch();
        self::assertSame(75.0, (float) $row['line_total_net']);
        self::assertSame(89.25, (float) $row['line_total_gross']);
    }

    public function testUpdateLineItem_PreservesTotalsWhenQuantityMissing(): void
    {
        $invoiceId = $this->insertInvoice();
        $this->insertLineItem([
            'id' => 'li-no-qty',
            'invoice_id' => $invoiceId,
            'quantity' => null,
            'line_total_net' => 100.0,
            'line_total_gross' => 119.0,
        ]);

        $result = $this->service->updateLineItem('li-no-qty', [
            'net_amount' => 50.0,
            'gross_amount' => 59.5,
        ]);

        self::assertTrue($result['isSuccess']);

        $row = $this->pdo->query("SELECT line_total_net, line_total_gross FROM invoice_line_items WHERE id = 'li-no-qty'")->fetch();
        self::assertSame(100.0, (float) $row['line_total_net']);
        self::assertSame(119.0, (float) $row['line_total_gross']);
    }

    public function testUpdateLineItem_ReturnsErrorWhenLineItemNotFound(): void
    {
        $result = $this->service->updateLineItem('missing-id', []);

        self::assertFalse($result['isSuccess']);
        self::assertSame('Line item not found', $result['error']);
    }

    public function testUpdateLineItem_SanitizesCurrency(): void
    {
        $invoiceId = $this->insertInvoice();
        $this->insertLineItem(['id' => 'li-currency', 'invoice_id' => $invoiceId, 'quantity' => 1]);

        $result = $this->service->updateLineItem('li-currency', [
            'currency' => '  usd  ',
        ]);

        self::assertTrue($result['isSuccess']);

        $row = $this->pdo->query("SELECT currency FROM invoice_line_items WHERE id = 'li-currency'")->fetch();
        self::assertSame('USD', $row['currency']);
    }

    public function testUpdateLineItem_SanitizesDecimalValues(): void
    {
        $invoiceId = $this->insertInvoice();
        $this->insertLineItem(['id' => 'li-decimal', 'invoice_id' => $invoiceId, 'quantity' => 1]);

        $result = $this->service->updateLineItem('li-decimal', [
            'net_amount' => '12,50',  // German format with comma
            'gross_amount' => '14.88',  // Standard format
            'tax_rate_percentage' => '19',
        ]);

        self::assertTrue($result['isSuccess']);

        $row = $this->pdo->query("SELECT net_amount, gross_amount, tax_rate_percentage FROM invoice_line_items WHERE id = 'li-decimal'")->fetch();
        self::assertSame(12.5, (float) $row['net_amount']);
        self::assertSame(14.88, (float) $row['gross_amount']);
        self::assertSame(19.0, (float) $row['tax_rate_percentage']);
    }

    public function testUpdateLineItem_SanitizesDateTimeValues(): void
    {
        $invoiceId = $this->insertInvoice();
        $this->insertLineItem(['id' => 'li-dates', 'invoice_id' => $invoiceId, 'quantity' => 1]);

        $result = $this->service->updateLineItem('li-dates', [
            'article_valid_from' => '2024-01-15',
            'article_valid_until' => '2024-12-31T23:59:59',
        ]);

        self::assertTrue($result['isSuccess']);

        $row = $this->pdo->query("SELECT article_valid_from, article_valid_until FROM invoice_line_items WHERE id = 'li-dates'")->fetch();
        self::assertSame('2024-01-15 00:00:00', $row['article_valid_from']);
        self::assertSame('2024-12-31 23:59:59', $row['article_valid_until']);
    }

    public function testUpdateLineItem_HandlesEmptyStringsAsNull(): void
    {
        $invoiceId = $this->insertInvoice();
        $this->insertLineItem(['id' => 'li-empty', 'invoice_id' => $invoiceId, 'quantity' => 1]);

        $result = $this->service->updateLineItem('li-empty', [
            'article_id' => '',
            'article_number' => '  ',
            'net_amount' => '',
            'article_valid_from' => '',
        ]);

        self::assertTrue($result['isSuccess']);

        $row = $this->pdo->query("SELECT article_id, article_number, net_amount, article_valid_from FROM invoice_line_items WHERE id = 'li-empty'")->fetch();
        self::assertNull($row['article_id']);
        self::assertNull($row['article_number']);
        self::assertNull($row['net_amount']);
        self::assertNull($row['article_valid_from']);
    }

    private function createSchema(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE customer (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lex_contact_id TEXT,
            Nummer TEXT,
            Name TEXT
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
            line_order INTEGER DEFAULT 0,
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
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');
    }

    private function insertCustomer(array $overrides = []): int
    {
        $data = $overrides + [
            'id' => null,
            'Name' => 'Customer ' . uniqid('', false),
            'Nummer' => null,
            'lex_contact_id' => null,
        ];

        if ($data['id'] !== null) {
            $stmt = $this->pdo->prepare('INSERT INTO customer (id, lex_contact_id, Nummer, Name) VALUES (:id, :lex_contact_id, :Nummer, :Name)');
            $stmt->execute([
                ':id' => $data['id'],
                ':lex_contact_id' => $data['lex_contact_id'],
                ':Nummer' => $data['Nummer'],
                ':Name' => $data['Name'],
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO customer (lex_contact_id, Nummer, Name) VALUES (:lex_contact_id, :Nummer, :Name)');
            $stmt->execute([
                ':lex_contact_id' => $data['lex_contact_id'],
                ':Nummer' => $data['Nummer'],
                ':Name' => $data['Name'],
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

final class LineItemServiceTestingPDO extends PDO
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
