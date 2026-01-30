<?php

declare(strict_types=1);

namespace Tests\Repositories;

use Exception;
use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Models\Invoice;
use Luxullus\LexBridge\Models\InvoiceLineItem;
use Luxullus\LexBridge\Repositories\InvoiceRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once dirname(__DIR__, 2) . '/config.php';

final class InvoiceRepositoryTest extends TestCase
{
    private InvoiceTestingPDO $pdo;
    private InvoiceRepository $repository;
    private ?string $previousErrorLog = null;
    private ?string $previousLogErrors = null;
    private ?string $tempErrorLog = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new InvoiceTestingPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->createSchema($this->pdo);
        $this->setDatabaseConnection($this->pdo);

        $errorLog = ini_get('error_log');
        $this->previousErrorLog = $errorLog !== false ? $errorLog : null;

        $logErrors = ini_get('log_errors');
        $this->previousLogErrors = $logErrors !== false ? $logErrors : null;

        $tempFile = tempnam(sys_get_temp_dir(), 'inv_repo_log_');
        if ($tempFile !== false) {
            $this->tempErrorLog = $tempFile;
            ini_set('error_log', $tempFile);
        } else {
            $this->tempErrorLog = null;
        }

        ini_set('log_errors', '1');

        global $tableNames;
        $tableNames['invoices'] = 'invoices';
        $tableNames['customer'] = 'customer';
        $tableNames['invoice_line_items'] = 'invoice_line_items';

        $this->repository = new InvoiceRepository();
    }

    protected function tearDown(): void
    {
        if ($this->tempErrorLog !== null && file_exists($this->tempErrorLog)) {
            @unlink($this->tempErrorLog);
        }

        if ($this->previousErrorLog !== null) {
            ini_set('error_log', $this->previousErrorLog);
        } else {
            ini_restore('error_log');
        }

        if ($this->previousLogErrors !== null) {
            ini_set('log_errors', $this->previousLogErrors);
        } else {
            ini_restore('log_errors');
        }

        $this->tempErrorLog = null;
        $this->previousErrorLog = null;
        $this->previousLogErrors = null;

        $this->resetDatabaseConnection();
        unset($this->repository, $this->pdo);

        parent::tearDown();
    }

    public function testFindByIdReturnsInvoiceWithLineItems(): void
    {
        $customerId = $this->insertCustomer([
            'id' => 1,
            'company_name' => 'Acme GmbH',
            'lex_contact_id' => 'lex-1',
            'kundenNummer' => 'ACM-01',
        ]);

        $invoiceId = $this->insertInvoice([
            'id' => 'inv-1',
            'contact_id' => $customerId,
            'voucher_date' => '2024-01-10',
            'title' => 'Invoice 1',
            'status' => 'draft',
            'total_gross_amount' => 120.0,
            'total_net_amount' => 100.0,
            'currency' => 'EUR',
            'created_at' => '2024-01-10 10:00:00',
        ]);

        $this->insertLineItem([
            'id' => 'li-1',
            'invoice_id' => $invoiceId,
            'line_order' => 2,
            'name' => 'Consulting B',
            'type' => 'service',
            'quantity' => 1,
            'net_amount' => 50.0,
            'gross_amount' => 59.5,
            'line_total_net' => 50.0,
            'line_total_gross' => 59.5,
        ]);

        $this->insertLineItem([
            'id' => 'li-2',
            'invoice_id' => $invoiceId,
            'line_order' => 1,
            'name' => 'Consulting A',
            'type' => 'service',
            'quantity' => 2,
            'net_amount' => 25.0,
            'gross_amount' => 29.75,
            'line_total_net' => 50.0,
            'line_total_gross' => 59.5,
        ]);

        $invoice = $this->repository->findById('inv-1');

        self::assertInstanceOf(Invoice::class, $invoice);
        self::assertSame('inv-1', $invoice->id);
        self::assertSame('lex-1', $invoice->lexContactId);
        self::assertSame('Acme GmbH', $invoice->companyName);
        self::assertIsArray($invoice->lineItems);
        self::assertCount(2, $invoice->lineItems);
        self::assertInstanceOf(InvoiceLineItem::class, $invoice->lineItems[0]);
        self::assertSame(1, $invoice->lineItems[0]->lineOrder);
        self::assertSame('Consulting A', $invoice->lineItems[0]->name);
    }

    public function testFindByIdReturnsNullWhenInvoiceMissing(): void
    {
        self::assertNull($this->repository->findById('missing'));
    }

    public function testFindAllAppliesFiltersAndCountsItems(): void
    {
        $contactA = $this->insertCustomer(['company_name' => 'Alpha GmbH']);
        $contactB = $this->insertCustomer(['company_name' => 'Beta AG']);

        $invA1 = $this->insertInvoice([
            'id' => 'inv-a1',
            'contact_id' => $contactA,
            'voucher_date' => '2024-01-15',
            'status' => 'draft',
            'created_at' => '2024-01-16 09:00:00',
        ]);
        $this->insertLineItem(['invoice_id' => $invA1, 'name' => 'Service A1']);

        $this->insertInvoice([
            'id' => 'inv-a2',
            'contact_id' => $contactA,
            'voucher_date' => '2024-05-01',
            'status' => 'transmitted',
            'created_at' => '2024-05-02 10:00:00',
        ]);

        $this->insertInvoice([
            'id' => 'inv-b1',
            'contact_id' => $contactB,
            'voucher_date' => '2023-12-30',
            'status' => 'draft',
            'created_at' => '2023-12-30 12:00:00',
        ]);

        $results = $this->repository->findAll([
            'status' => 'draft',
            'contact_id' => $contactA,
            'from_date' => '2024-01-01',
            'to_date' => '2024-12-31',
        ]);

        self::assertCount(1, $results);
        $row = $results[0];
        self::assertSame('inv-a1', $row['id']);
        self::assertSame('draft', $row['status']);
        self::assertSame(1, (int) $row['item_count']);
        self::assertSame('Alpha GmbH', $row['company_name']);
    }

    public function testFindByContactIdReturnsInvoicesForSpecificCustomer(): void
    {
        $contactA = $this->insertCustomer(['company_name' => 'Customer A']);
        $contactB = $this->insertCustomer(['company_name' => 'Customer B']);

        $invA1 = $this->insertInvoice(['contact_id' => $contactA, 'id' => 'inv-a1']);
        $invA2 = $this->insertInvoice(['contact_id' => $contactA, 'id' => 'inv-a2']);
        $this->insertInvoice(['contact_id' => $contactB, 'id' => 'inv-b1']);

        $invoices = $this->repository->findByContactId($contactA);
        $ids = array_column($invoices, 'id');

        sort($ids);
        self::assertSame(['inv-a1', 'inv-a2'], $ids);
    }

    public function testFindByStatusReturnsMatchingInvoices(): void
    {
        $contactId = $this->insertCustomer();
        $this->insertInvoice(['id' => 'inv-draft', 'status' => 'draft', 'contact_id' => $contactId]);
        $this->insertInvoice(['id' => 'inv-error', 'status' => 'transmission_error', 'contact_id' => $contactId]);

        $drafts = $this->repository->findByStatus('draft');
        self::assertCount(1, $drafts);
        self::assertSame('inv-draft', $drafts[0]['id']);
    }

    public function testFindLineItemsByInvoiceIdOrdersByLineOrder(): void
    {
        $invoiceId = $this->insertInvoice(['id' => 'inv-line-items']);

        $this->insertLineItem(['invoice_id' => $invoiceId, 'line_order' => 3, 'name' => 'C']);
        $this->insertLineItem(['invoice_id' => $invoiceId, 'line_order' => 1, 'name' => 'A']);
        $this->insertLineItem(['invoice_id' => $invoiceId, 'line_order' => 2, 'name' => 'B']);

        $items = $this->repository->findLineItemsByInvoiceId($invoiceId);

        self::assertCount(3, $items);
        self::assertSame(['A', 'B', 'C'], array_map(static fn(InvoiceLineItem $item): string => $item->name, $items));
    }

    public function testCreateInvoiceWithItemsReturnsDatabaseErrorWhenProcedureMissing(): void
    {
        $result = $this->repository->createInvoiceWithItems(1, 'EUR', [['article_id' => 1, 'quantity' => 1]]);

        self::assertNull($result['invoice_id']);
        self::assertSame(-1, (int) $result['error_code']);
        self::assertStringContainsString('Database error', $result['error_message']);
    }

    public function testCreateInvoicesForPendingLineItemsCreatesInvoicesAndUpdatesRows(): void
    {
        $customerId = $this->insertCustomer([
            'id' => 10,
            'kundenNummer' => 'CUST-10',
        ]);

        $this->insertLineItem([
            'id' => 'li-11',
            'customer_number' => 'CUST-10',
            'invoice_id' => null,
            'quantity' => 2,
            'net_amount' => 15.0,
            'gross_amount' => 17.85,
            'line_total_net' => null,
            'line_total_gross' => null,
            'line_order' => 0,
        ]);

        $this->insertLineItem([
            'id' => 'li-12',
            'customer_number' => 'CUST-10',
            'invoice_id' => null,
            'quantity' => 1,
            'net_amount' => 20.0,
            'gross_amount' => 23.80,
            'line_total_net' => 20.0,
            'line_total_gross' => 23.8,
            'line_order' => 5,
        ]);

        $result = $this->repository->createInvoicesForPendingLineItems('2024-03-15');

        self::assertArrayHasKey('createdInvoices', $result);
        self::assertCount(1, $result['createdInvoices']);
        self::assertSame([], $result['skippedLineItems']);

        $created = $result['createdInvoices'][0];
        self::assertSame($customerId, $created['customer_id']);
        self::assertSame('CUST-10', $created['customer_number']);
        self::assertSame(2, $created['line_item_count']);
        self::assertNotEmpty($created['invoice_id']);

        $invoiceRow = $this->pdo->query("SELECT voucher_date, status, currency, total_net_amount, total_gross_amount FROM invoices WHERE id = '{$created['invoice_id']}'")->fetch();
        self::assertSame('2024-03-15', $invoiceRow['voucher_date']);
        self::assertSame('draft', $invoiceRow['status']);
        self::assertSame('EUR', $invoiceRow['currency']);
        self::assertEquals(50.0, (float) $invoiceRow['total_net_amount']);
        self::assertEquals(59.5, round((float) $invoiceRow['total_gross_amount'], 2));

        $lineItems = $this->pdo
            ->query("SELECT invoice_id, line_order, line_total_net, line_total_gross FROM invoice_line_items WHERE invoice_id = '{$created['invoice_id']}' ORDER BY id")
            ->fetchAll();

        self::assertSame($created['invoice_id'], $lineItems[0]['invoice_id']);
        self::assertSame(1, (int) $lineItems[0]['line_order']);
        self::assertEquals(30.0, (float) $lineItems[0]['line_total_net']);
        self::assertEquals(35.7, round((float) $lineItems[0]['line_total_gross'], 2));
        self::assertSame(5, (int) $lineItems[1]['line_order']);
    }

    public function testCreateInvoicesForPendingLineItemsSkipsUnknownOrPreassignedItems(): void
    {
        $this->insertLineItem([
            'id' => 'li-20',
            'customer_number' => 'UNKNOWN',
            'invoice_id' => null,
        ]);

        $this->insertLineItem([
            'id' => 'li-21',
            'customer_number' => 'CUST-42',
            'invoice_id' => 'existing-invoice',
        ]);

        $result = $this->repository->createInvoicesForPendingLineItems('2024-04-01');

        self::assertSame(['li-20'], $result['skippedLineItems']);
        self::assertSame([], $result['createdInvoices']);

        $row = $this->pdo->query("SELECT invoice_id FROM invoice_line_items WHERE id = 'li-21'")->fetch();
        self::assertSame('existing-invoice', $row['invoice_id']);
    }

    public function testCreateInvoicesForPendingLineItemsReturnsErrorWhenTransactionFails(): void
    {
        $pdo = new FailingTransactionInvoicePDO();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $this->createSchema($pdo);

        $this->setDatabaseConnection($pdo);
        $this->pdo = $pdo;
        $repository = new InvoiceRepository();

        $this->insertCustomer(['id' => 30, 'kundenNummer' => 'CUST-30']);
        $this->insertLineItem([
            'id' => 'li-30',
            'customer_number' => 'CUST-30',
            'invoice_id' => null,
        ]);

        $result = $repository->createInvoicesForPendingLineItems('2024-05-01');
        self::assertArrayHasKey('error', $result);
        self::assertSame([], $result['createdInvoices']);
        self::assertSame([], $result['skippedLineItems']);
    }

    public function testCreateInvoicesForPendingLineItemsViaStoredProcReturnsErrorWhenProcedureMissing(): void
    {
        $result = $this->repository->createInvoicesForPendingLineItemsViaStoredProc('2024-06-01');

        self::assertSame([], $result['createdInvoices']);
        self::assertSame([], $result['skippedLineItems']);
        self::assertArrayHasKey('error', $result);
        self::assertStringContainsString('Database error', $result['error']);
    }

    public function testUpdateAfterTransmissionPersistsLexwareAttributes(): void
    {
        $invoiceId = $this->insertInvoice(['id' => 'inv-trans', 'status' => 'draft']);

        $lexwareResponse = [
            'id' => 'lex-99',
            'resourceUri' => '/invoices/lex-99',
            'version' => 3,
            'createdDate' => '2024-02-10T12:34:56Z',
            'updatedDate' => '2024-02-11T08:00:00Z',
        ];

        self::assertTrue($this->repository->updateAfterTransmission($invoiceId, $lexwareResponse));

        $row = $this->pdo->query("SELECT status, lex_id, lex_resource_uri, lex_version, lex_created_date, lex_updated_date, transmitted_at, last_error_message FROM invoices WHERE id = '$invoiceId'")->fetch();
        self::assertSame('transmitted', $row['status']);
        self::assertSame('lex-99', $row['lex_id']);
        self::assertSame('/invoices/lex-99', $row['lex_resource_uri']);
        self::assertSame('3', (string) $row['lex_version']);
        self::assertSame('2024-02-10 12:34:56', $row['lex_created_date']);
        self::assertSame('2024-02-11 08:00:00', $row['lex_updated_date']);
        self::assertNotNull($row['transmitted_at']);
        self::assertNull($row['last_error_message']);
    }

    public function testUpdateAfterTransmissionReturnsFalseWhenBeginTransactionFails(): void
    {
        $pdo = new FailingTransactionInvoicePDO();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->setDatabaseConnection($pdo);
        $this->pdo = $pdo;

        $repository = new InvoiceRepository();
        self::assertFalse($repository->updateAfterTransmission('inv', []));
    }

    public function testUpdateWithErrorUpdatesInvoiceRecord(): void
    {
        $invoiceId = $this->insertInvoice(['id' => 'inv-error', 'status' => 'draft']);

        self::assertTrue($this->repository->updateWithError($invoiceId, 'Transmission failed', 'E123'));

        $row = $this->pdo->query("SELECT status, last_error_message, last_error_code, transmission_attempts, last_transmission_attempt FROM invoices WHERE id = '$invoiceId'")->fetch();
        self::assertSame('transmission_error', $row['status']);
        self::assertSame('Transmission failed', $row['last_error_message']);
        self::assertSame('E123', $row['last_error_code']);
        self::assertSame('1', (string) $row['transmission_attempts']);
        self::assertNotNull($row['last_transmission_attempt']);
    }

    public function testUpdateWithErrorReturnsFalseOnException(): void
    {
        $pdo = new ThrowingPrepareInvoicePDO();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->setDatabaseConnection($pdo);
        $this->pdo = $pdo;

        $repository = new InvoiceRepository();
        self::assertFalse($repository->updateWithError('inv', 'error'));
    }

    public function testUpdateStatusPersistsNewStatus(): void
    {
        $invoiceId = $this->insertInvoice(['id' => 'inv-status', 'status' => 'draft']);

        self::assertTrue($this->repository->updateStatus($invoiceId, 'archived'));

        $row = $this->pdo->query("SELECT status FROM invoices WHERE id = '$invoiceId'")->fetch();
        self::assertSame('archived', $row['status']);
    }

    private function createSchema(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE customer (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_name TEXT,
            lex_contact_id TEXT,
            kundenNummer TEXT
        )');

        $pdo->exec('CREATE TABLE invoices (
            id TEXT PRIMARY KEY,
            contact_id INTEGER,
            voucher_date TEXT,
            title TEXT,
            status TEXT,
            total_net_amount REAL,
            total_gross_amount REAL,
            currency TEXT,
            tax_type TEXT DEFAULT "net",
            created_at TEXT,
            updated_at TEXT,
            transmitted_at TEXT,
            transmission_attempts INTEGER DEFAULT 0,
            last_error_message TEXT,
            last_error_code TEXT,
            last_transmission_attempt TEXT,
            lex_id TEXT,
            lex_resource_uri TEXT,
            lex_version INTEGER DEFAULT 0,
            lex_created_date TEXT,
            lex_updated_date TEXT
        )');

        $pdo->exec('CREATE TABLE invoice_line_items (
            id TEXT PRIMARY KEY,
            invoice_id TEXT,
            customer_number TEXT,
            line_order INTEGER DEFAULT 0,
            quantity REAL,
            net_amount REAL,
            gross_amount REAL,
            line_total_net REAL,
            line_total_gross REAL,
            currency TEXT,
            type TEXT DEFAULT "service",
            name TEXT,
            description TEXT,
            unit_name TEXT,
            tax_rate_percentage REAL,
            discount_percentage REAL,
            created_at TEXT,
            updated_at TEXT
        )');
    }

    private function insertCustomer(array $overrides = []): int
    {
        $data = $overrides + [
            'id' => null,
            'company_name' => 'Customer ' . uniqid('', false),
            'lex_contact_id' => null,
            'kundenNummer' => null,
        ];

        if ($data['id'] !== null) {
            $stmt = $this->pdo->prepare('INSERT INTO customer (id, company_name, lex_contact_id, kundenNummer) VALUES (:id, :company_name, :lex_contact_id, :kundenNummer)');
            $stmt->execute([
                ':id' => $data['id'],
                ':company_name' => $data['company_name'],
                ':lex_contact_id' => $data['lex_contact_id'],
                ':kundenNummer' => $data['kundenNummer'],
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO customer (company_name, lex_contact_id, kundenNummer) VALUES (:company_name, :lex_contact_id, :kundenNummer)');
            $stmt->execute([
                ':company_name' => $data['company_name'],
                ':lex_contact_id' => $data['lex_contact_id'],
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
            'title' => 'Invoice',
            'status' => 'draft',
            'total_net_amount' => 0.0,
            'total_gross_amount' => 0.0,
            'currency' => 'EUR',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];

        $stmt = $this->pdo->prepare('INSERT INTO invoices (id, contact_id, voucher_date, title, status, total_net_amount, total_gross_amount, currency, created_at, updated_at)
            VALUES (:id, :contact_id, :voucher_date, :title, :status, :total_net_amount, :total_gross_amount, :currency, :created_at, :updated_at)');

        $stmt->execute([
            ':id' => $data['id'],
            ':contact_id' => $data['contact_id'],
            ':voucher_date' => $data['voucher_date'],
            ':title' => $data['title'],
            ':status' => $data['status'],
            ':total_net_amount' => $data['total_net_amount'],
            ':total_gross_amount' => $data['total_gross_amount'],
            ':currency' => $data['currency'],
            ':created_at' => $data['created_at'],
            ':updated_at' => $data['updated_at'],
        ]);

        return (string) $data['id'];
    }

    private function insertLineItem(array $overrides = []): string
    {
        $data = $overrides + [
            'id' => 'li-' . uniqid('', false),
            'invoice_id' => null,
            'customer_number' => null,
            'line_order' => 0,
            'quantity' => 1,
            'net_amount' => 0.0,
            'gross_amount' => 0.0,
            'line_total_net' => 0.0,
            'line_total_gross' => 0.0,
            'currency' => 'EUR',
            'type' => 'service',
            'name' => 'Item',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];

        $stmt = $this->pdo->prepare('INSERT INTO invoice_line_items (
            id, invoice_id, customer_number, line_order, quantity, net_amount, gross_amount, line_total_net, line_total_gross,
            currency, type, name, description, unit_name, tax_rate_percentage, discount_percentage, created_at, updated_at
        ) VALUES (
            :id, :invoice_id, :customer_number, :line_order, :quantity, :net_amount, :gross_amount, :line_total_net, :line_total_gross,
            :currency, :type, :name, :description, :unit_name, :tax_rate_percentage, :discount_percentage, :created_at, :updated_at
        )');

        $stmt->execute([
            ':id' => $data['id'],
            ':invoice_id' => $data['invoice_id'],
            ':customer_number' => $data['customer_number'],
            ':line_order' => $data['line_order'],
            ':quantity' => $data['quantity'],
            ':net_amount' => $data['net_amount'],
            ':gross_amount' => $data['gross_amount'],
            ':line_total_net' => $data['line_total_net'],
            ':line_total_gross' => $data['line_total_gross'],
            ':currency' => $data['currency'],
            ':type' => $data['type'],
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':unit_name' => $data['unit_name'] ?? null,
            ':tax_rate_percentage' => $data['tax_rate_percentage'] ?? null,
            ':discount_percentage' => $data['discount_percentage'] ?? null,
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

class InvoiceTestingPDO extends PDO
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

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $query = $this->transformSql($query);

        if ($fetchMode === null) {
            return parent::query($query);
        }

        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    private function transformSql(string $sql): string
    {
        $sql = (string) preg_replace('/NOW\s*\(\s*\)/i', 'CURRENT_TIMESTAMP', $sql);
        return $sql;
    }
}

final class FailingTransactionInvoicePDO extends InvoiceTestingPDO
{
    public function beginTransaction(): bool
    {
        throw new Exception('forced failure');
    }

    public function inTransaction(): bool
    {
        return false;
    }

    public function rollBack(): bool
    {
        return true;
    }
}

final class ThrowingPrepareInvoicePDO extends InvoiceTestingPDO
{
    public function prepare(string $statement, array $options = []): PDOStatement|false
    {
        throw new Exception('forced failure');
    }
}
