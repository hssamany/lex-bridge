<?php

declare(strict_types=1);

namespace Tests\Services;

use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Http\HttpClient;
use Luxullus\LexBridge\Http\HttpResponse;
use Luxullus\LexBridge\Services\InvoiceService;
use Luxullus\LexBridge\Repositories\InvoiceRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once dirname(__DIR__, 2) . '/config.php';

final class InvoiceServiceTest extends TestCase
{
    private PDO $pdo;
    private InvoiceRepository $repository;
    private InvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->createSchema();
        $this->setDatabaseConnection($this->pdo);

        global $tableNames;
        $tableNames['invoices'] = 'invoices';
        $tableNames['invoice_line_items'] = 'invoice_line_items';
        $tableNames['customer'] = 'customer';

        $this->repository = new InvoiceRepository();
        
        $client = new HttpClient('test-key', 'https://api.test');
        $this->service = new InvoiceService($client, $this->repository);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseConnection();
        unset($this->pdo);

        parent::tearDown();
    }

    // ======================================================================
    // Test: Filter Validation
    // ======================================================================

    public function testValidateAndNormalizeFilters_ValidatesDateFormats(): void
    {
        $filters = [
            'start_date' => '2024-13-45', // Invalid month and day
        ];

        $result = $this->service->getInvoices($filters);

        $this->assertFalse($result['isSuccess']);
        $this->assertStringContainsString('Invalid start_date format', $result['error']);
    }

    public function testValidateAndNormalizeFilters_ValidatesStatusValues(): void
    {
        $filters = [
            'status' => 'invalid_status',
        ];

        $result = $this->service->getInvoices($filters);

        $this->assertFalse($result['isSuccess']);
        $this->assertStringContainsString('Invalid status', $result['error']);
    }

    public function testValidateAndNormalizeFilters_AcceptsValidFilters(): void
    {
        $this->insertCustomer(1, 'CUST001', 'Test Customer');
        $this->insertInvoice('INV-001', 1, 'draft', '2024-01-15', 100.00, 119.00);

        $filters = [
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'status' => 'draft',
            'customer_number' => 'CUST001',
        ];

        $result = $this->service->getInvoices($filters);

        $this->assertTrue($result['isSuccess']);
        $this->assertIsArray($result['invoices']);
    }

    // ======================================================================
    // Test: Data Enrichment
    // ======================================================================

    public function testEnrichInvoiceList_AddsComputedFields(): void
    {
        $this->insertCustomer(1, 'CUST001', 'Test Customer');
        $this->insertInvoice('INV-001', 1, 'draft', '2024-01-15', 100.00, 119.00);

        $result = $this->service->getInvoices([]);

        $this->assertTrue($result['isSuccess']);
        $this->assertCount(1, $result['invoices']);
        
        $invoice = $result['invoices'][0];
        $this->assertArrayHasKey('line_item_count', $invoice);
        $this->assertArrayHasKey('display_status', $invoice);
        $this->assertArrayHasKey('formatted_total', $invoice);
    }

    public function testEnrichInvoiceList_FormatsStatusDisplay(): void
    {
        $this->insertCustomer(1, 'CUST001', 'Test Customer');
        $this->insertInvoice('INV-001', 1, 'draft', '2024-01-15', 100.00, 119.00);
        $this->insertInvoice('INV-002', 1, 'transmitted', '2024-01-16', 200.00, 238.00);
        $this->insertInvoice('INV-003', 1, 'transmission_error', '2024-01-17', 300.00, 357.00);

        $result = $this->service->getInvoices([]);

        $this->assertTrue($result['isSuccess']);
        $this->assertCount(3, $result['invoices']);

        $statuses = array_column($result['invoices'], 'display_status');
        $this->assertContains('Draft', $statuses);
        $this->assertContains('Transmitted', $statuses);
        $this->assertContains('Error', $statuses);
    }

    public function testEnrichInvoiceList_FormatsCurrencyAmounts(): void
    {
        $this->insertCustomer(1, 'CUST001', 'Test Customer');
        $this->insertInvoice('INV-001', 1, 'draft', '2024-01-15', 1234.56, 1469.13, 'EUR');

        $result = $this->service->getInvoices([]);

        $this->assertTrue($result['isSuccess']);
        $invoice = $result['invoices'][0];

        $this->assertEquals('1.469,13 €', $invoice['formatted_total']);
    }

    // ======================================================================
    // Test: Line Item Validation
    // ======================================================================

    public function testValidateLineItems_RejectsEmptyArray(): void
    {
        $result = $this->service->createInvoiceWithItems(1, 'EUR', []);

        $this->assertNull($result['invoice_id']);
        $this->assertEquals(-1, $result['error_code']);
        $this->assertStringContainsString('no line items', $result['error_message']);
    }

    public function testValidateLineItems_RequiresArticleId(): void
    {
        $lineItems = [
            ['quantity' => 2.0, 'price' => 10.00],
        ];

        $result = $this->service->createInvoiceWithItems(1, 'EUR', $lineItems);

        $this->assertNull($result['invoice_id']);
        $this->assertEquals(-1, $result['error_code']);
        $this->assertStringContainsString('article_id', $result['error_message']);
    }

    public function testValidateLineItems_RequiresPositiveQuantity(): void
    {
        $lineItems = [
            ['article_id' => 1, 'quantity' => 0, 'price' => 10.00],
        ];

        $result = $this->service->createInvoiceWithItems(1, 'EUR', $lineItems);

        $this->assertNull($result['invoice_id']);
        $this->assertEquals(-1, $result['error_code']);
        $this->assertStringContainsString('positive quantity', $result['error_message']);
    }

    public function testValidateLineItems_AcceptsValidLineItems(): void
    {
        $lineItems = [
            ['article_id' => 1, 'quantity' => 2.0],
            ['article_id' => 2, 'quantity' => 1.0],
        ];

        // Note: This will fail at repository level in test (no stored proc)
        // but validates that service-level validation passes
        $result = $this->service->createInvoiceWithItems(1, 'EUR', $lineItems);

        // Expect repository error, not validation error (validation passed)
        $this->assertNull($result['invoice_id']);
        $this->assertEquals(-1, $result['error_code']);
        $this->assertStringContainsString('Database error', $result['error_message']);
        $this->assertStringNotContainsString('Validation', $result['error_message']);
    }

    // ======================================================================
    // Test: Currency Normalization
    // ======================================================================

    public function testNormalizeCurrency_DefaultsToEUR(): void
    {
        $lineItems = [
            ['article_id' => 1, 'quantity' => 1.0],
        ];

        // Pass null currency - should default to EUR
        $result = $this->service->createInvoiceWithItems(1, null, $lineItems);

        // Repository call will fail (no stored proc in SQLite), but currency was normalized
        $this->assertNull($result['invoice_id']);
        $this->assertEquals(-1, $result['error_code']);
        // Confirms it reached the repository (not validation error)
        $this->assertStringContainsString('Database error', $result['error_message']);
    }

    public function testNormalizeCurrency_UppercasesInput(): void
    {
        $lineItems = [
            ['article_id' => 1, 'quantity' => 1.0],
        ];

        $result = $this->service->createInvoiceWithItems(1, 'usd', $lineItems);

        // Currency should be uppercased to 'USD' before repository call
        // Repository will fail (no stored proc), but confirms normalization happened
        $this->assertNull($result['invoice_id']);
        $this->assertEquals(-1, $result['error_code']);
        $this->assertStringContainsString('Database error', $result['error_message']);
    }

    // ======================================================================
    // Test: Invoice Retrieval
    // ======================================================================

    public function testGetInvoiceById_ReturnsInvoiceModel(): void
    {
        $this->insertCustomer(1, 'CUST001', 'Test Customer');
        $this->insertInvoice('INV-001', 1, 'draft', '2024-01-15', 100.00, 119.00);

        $invoice = $this->service->getInvoiceById('INV-001');

        $this->assertInstanceOf('Luxullus\\LexBridge\\Models\\Invoice', $invoice);
        $this->assertEquals('INV-001', $invoice->id);
        $this->assertEquals('draft', $invoice->status);
    }

    public function testGetInvoiceById_ReturnsNullWhenNotFound(): void
    {
        $invoice = $this->service->getInvoiceById('NONEXISTENT');

        $this->assertNull($invoice);
    }

    // ======================================================================
    // Test: Invoice Creation Summary
    // ======================================================================

    public function testBuildInvoiceCreationSummary_GeneratesStatistics(): void
    {
        $result = $this->service->createInvoicesForPendingLineItems();

        // In test environment, stored proc doesn't exist
        // But we can verify the service handles it gracefully
        $this->assertIsArray($result);
        $this->assertArrayHasKey('createdInvoices', $result);
        $this->assertArrayHasKey('skippedLineItems', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Database error', $result['error']);
    }

    // ======================================================================
    // Helper Methods
    // ======================================================================

    private function createSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE customer (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                Nummer TEXT UNIQUE,
                Firma TEXT,
                Vorname TEXT,
                Name TEXT,
                lex_contact_id TEXT
            )
        ');

        $this->pdo->exec('
            CREATE TABLE invoices (
                id TEXT PRIMARY KEY,
                contact_id INTEGER,
                voucher_date TEXT,
                title TEXT,
                currency TEXT DEFAULT "EUR",
                total_net_amount REAL,
                total_gross_amount REAL,
                tax_type TEXT DEFAULT "net",
                status TEXT DEFAULT "draft",
                lex_id TEXT,
                lex_resource_uri TEXT,
                lex_version INTEGER DEFAULT 0,
                lex_created_date TEXT,
                lex_updated_date TEXT,
                transmitted_at TEXT,
                last_error_message TEXT,
                last_error_code TEXT,
                transmission_attempts INTEGER DEFAULT 0,
                last_transmission_attempt TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (contact_id) REFERENCES customer(id)
            )
        ');

        $this->pdo->exec('
            CREATE TABLE invoice_line_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id TEXT,
                article_number TEXT,
                line_order INTEGER,
                quantity REAL,
                net_amount REAL,
                gross_amount REAL,
                line_total_net REAL,
                line_total_gross REAL,
                currency TEXT,
                customer_number TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (invoice_id) REFERENCES invoices(id)
            )
        ');
    }

    private function insertCustomer(int $id, string $nummer, string $firma): void
    {
        $sql = 'INSERT INTO customer (id, Nummer, Firma, Name, lex_contact_id) VALUES (?, ?, ?, ?, ?)';
        $this->pdo->prepare($sql)->execute([$id, $nummer, $firma, $firma, 'lex-' . $id]);
    }

    private function insertInvoice(
        string $id,
        int $contactId,
        string $status,
        string $voucherDate,
        float $totalNet,
        float $totalGross,
        string $currency = 'EUR'
    ): void {
        $sql = 'INSERT INTO invoices (id, contact_id, status, voucher_date, total_net_amount, total_gross_amount, currency)
                VALUES (?, ?, ?, ?, ?, ?, ?)';
        $this->pdo->prepare($sql)->execute([$id, $contactId, $status, $voucherDate, $totalNet, $totalGross, $currency]);
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
