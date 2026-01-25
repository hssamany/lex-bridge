<?php

declare(strict_types=1);

namespace Tests\Services;

use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Http\HttpClient;
use Luxullus\LexBridge\Http\HttpResponse;
use Luxullus\LexBridge\Services\ContactService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once dirname(__DIR__, 2) . '/config.php';

final class ContactServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->createSchema();
        $this->seedCustomer();
        $this->setDatabaseConnection($this->pdo);

        global $tableNames;
        $tableNames['customer'] = 'customer';
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseConnection();
        unset($this->pdo);

        parent::tearDown();
    }

    public function testSyncContactsPersistsApiContactsAndReturnsStoredRows(): void
    {
        $payload = [
            'content' => [
                [
                    'id' => 'lex-123',
                    'roles' => [
                        'customer' => [
                            'number' => 9001,
                        ],
                    ],
                    'company' => [
                        'name' => 'Acme GmbH',
                    ],
                ],
            ],
        ];

        $pageCalls = [];
        $response = new HttpResponse(200, json_encode($payload));
        $service = $this->createServiceWithResponse($response, $pageCalls);

        $result = $service->syncContacts();

        self::assertTrue($result['response']->isSuccess());
        self::assertCount(1, $result['contacts']);
        self::assertSame([0], $pageCalls);
        self::assertNull($result['error']);

        $contact = $result['contacts'][0];
        self::assertSame('Acme GmbH', $contact['companyName']);
        self::assertSame('CUST-001', $contact['customerNumber']);
        self::assertSame('9001', (string) $contact['lexCustomerNumber']);

        $row = $this->pdo
            ->query('SELECT lex_contact_id, lex_customer_number FROM customer WHERE company_name = "Acme GmbH"')
            ->fetch();
        self::assertSame('lex-123', $row['lex_contact_id']);
        self::assertSame('9001', (string) $row['lex_customer_number']);
    }

    public function testSyncContactsReturnsStoredRowsWhenApiFails(): void
    {
        $pageCalls = [];
        $response = new HttpResponse(500, json_encode(['message' => 'server error']));
        $service = $this->createServiceWithResponse($response, $pageCalls);

        $result = $service->syncContacts();

        self::assertFalse($result['response']->isSuccess());
        self::assertCount(1, $result['contacts']);
        self::assertSame([0], $pageCalls);
        self::assertNotNull($result['error']);

        $contact = $result['contacts'][0];
        self::assertSame('Acme GmbH', $contact['companyName']);
        self::assertSame('CUST-001', $contact['customerNumber']);
        self::assertSame('', $contact['lexCustomerNumber']);

        $row = $this->pdo
            ->query('SELECT lex_contact_id FROM customer WHERE company_name = "Acme GmbH"')
            ->fetch();
        self::assertNull($row['lex_contact_id']);
    }

    private function createSchema(): void
    {
        $this->pdo->exec('CREATE TABLE customer (
            company_name TEXT PRIMARY KEY,
            customer_number TEXT,
            lex_customer_number TEXT,
            lex_contact_id TEXT
        )');
    }

    private function seedCustomer(): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO customer (company_name, customer_number) VALUES (:company, :number)');
        $stmt->execute([
            ':company' => 'Acme GmbH',
            ':number' => 'CUST-001',
        ]);
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

    private function createServiceWithResponse(HttpResponse $response, ?array &$pageCalls = null): ContactService
    {
        $client = new HttpClient('test-api-key', 'https://api.example.test');
        $pageCalls ??= [];

        $fetcher = function (int $page) use (&$pageCalls, $response): HttpResponse {
            $pageCalls[] = $page;
            return $response;
        };

        return new ContactService($client, null, $fetcher);
    }
}
