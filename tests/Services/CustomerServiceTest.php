<?php

declare(strict_types=1);

namespace Tests\Services;

use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Http\HttpClient;
use Luxullus\LexBridge\Http\HttpResponse;
use Luxullus\LexBridge\Services\CustomerService;
use Luxullus\LexBridge\Repositories\CustomerRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once dirname(__DIR__, 2) . '/config.php';

final class CustomerServiceTest extends TestCase
{
    private PDO $pdo;
    private CustomerRepository $repository;
    private CustomerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->createSchema();
        $this->setDatabaseConnection($this->pdo);

        global $tableNames;
        $tableNames['customer'] = 'customer';
        $tableNames['customers_article'] = 'customers_article';
        $tableNames['articles'] = 'articles';

        $this->repository = new CustomerRepository();
        
        $client = new HttpClient('test-key', 'https://api.test');
        $this->service = new CustomerService($client, $this->repository);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseConnection();
        unset($this->pdo);

        parent::tearDown();
    }

    public function testSearchCustomersReturnsEmptyArrayForNullQuery(): void
    {
        $result = $this->service->searchCustomers(null);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    public function testSearchCustomersReturnsEmptyArrayForEmptyQuery(): void
    {
        $result = $this->service->searchCustomers('   ');

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    public function testSearchCustomersFindsCustomersByNumber(): void
    {
        $this->seedCustomer('CUST-001', 'Acme Corp');
        $this->seedCustomer('CUST-002', 'Beta Ltd');

        $result = $this->service->searchCustomers('CUST-001');

        self::assertCount(1, $result);
        self::assertSame('CUST-001', $result[0]->customer_number);
        self::assertSame('Acme Corp', $result[0]->company_name);
    }

    public function testSearchCustomersFindsCustomersByName(): void
    {
        $this->seedCustomer('CUST-001', 'Acme Corp');
        $this->seedCustomer('CUST-002', 'Beta Ltd');

        $result = $this->service->searchCustomers('Beta');

        self::assertCount(1, $result);
        self::assertSame('CUST-002', $result[0]->customer_number);
        self::assertSame('Beta Ltd', $result[0]->company_name);
    }

    public function testListContactsReturnsEnrichedDataWithArticleLabel(): void
    {
        $customerId = $this->seedCustomer('CUST-001', 'Acme Corp');
        $articleId = $this->seedArticle('ART-100', 'Premium Service');
        $this->linkCustomerToArticle($customerId, $articleId);

        $result = $this->service->listContacts();

        self::assertCount(1, $result);
        
        $contact = $result[0];
        self::assertSame($customerId, $contact['customerId']);
        self::assertSame('Acme Corp', $contact['companyName']);
        self::assertSame('CUST-001', $contact['customerNumber']);
        self::assertSame($articleId, $contact['articleId']);
        self::assertSame('ART-100 - Premium Service', $contact['articleLabel']);
    }

    public function testListContactsHandlesNullArticleLabel(): void
    {
        $this->seedCustomer('CUST-001', 'Acme Corp');

        $result = $this->service->listContacts();

        self::assertCount(1, $result);
        self::assertNull($result[0]['articleLabel']);
    }

    public function testUpdateCustomerArticleDeletesMappingWhenArticleIdIsNull(): void
    {
        $customerId = $this->seedCustomer('CUST-001', 'Acme Corp');
        $articleId = $this->seedArticle('ART-100', 'Premium Service');
        $this->linkCustomerToArticle($customerId, $articleId);

        $result = $this->service->updateCustomerArticle($customerId, null);

        self::assertTrue($result['isSuccess']);
        self::assertSame(200, $result['statusCode']);
        self::assertStringContainsString('entfernt', $result['message']);
        
        $mapping = $this->repository->findCustomerArticleMapping($customerId);
        self::assertNull($mapping);
    }

    public function testUpdateCustomerArticleCreatesNewMapping(): void
    {
        $customerId = $this->seedCustomer('CUST-001', 'Acme Corp');
        $articleId = $this->seedArticle('ART-100', 'Premium Service');

        $result = $this->service->updateCustomerArticle($customerId, $articleId);

        self::assertTrue($result['isSuccess']);
        self::assertSame(200, $result['statusCode']);
        self::assertStringContainsString('aktualisiert', $result['message']);
        
        $mapping = $this->repository->findCustomerArticleMapping($customerId);
        self::assertNotNull($mapping);
        self::assertSame($articleId, (int) $mapping['article_id']);
    }

    public function testUpdateCustomerArticleUpdatesExistingMapping(): void
    {
        $customerId = $this->seedCustomer('CUST-001', 'Acme Corp');
        $oldArticleId = $this->seedArticle('ART-100', 'Basic Service');
        $newArticleId = $this->seedArticle('ART-200', 'Premium Service');
        
        $this->linkCustomerToArticle($customerId, $oldArticleId);

        $result = $this->service->updateCustomerArticle($customerId, $newArticleId);

        self::assertTrue($result['isSuccess']);
        
        $mapping = $this->repository->findCustomerArticleMapping($customerId);
        self::assertSame($newArticleId, (int) $mapping['article_id']);
    }

    public function testUpdateCustomerArticleClearsArticleFromOtherCustomers(): void
    {
        $customer1Id = $this->seedCustomer('CUST-001', 'Acme Corp');
        $customer2Id = $this->seedCustomer('CUST-002', 'Beta Ltd');
        $articleId = $this->seedArticle('ART-100', 'Exclusive Service');
        
        $this->linkCustomerToArticle($customer1Id, $articleId);

        // Assign same article to customer2
        $this->service->updateCustomerArticle($customer2Id, $articleId);

        // Verify customer1 no longer has mapping
        $mapping1 = $this->repository->findCustomerArticleMapping($customer1Id);
        self::assertNull($mapping1);
        
        // Verify customer2 has the mapping
        $mapping2 = $this->repository->findCustomerArticleMapping($customer2Id);
        self::assertNotNull($mapping2);
        self::assertSame($articleId, (int) $mapping2['article_id']);
    }

    public function testSyncContactsPersistsAndReturnsEnrichedData(): void
    {
        $this->seedCustomer('CUST-001', 'Acme GmbH');
        
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

        $response = new HttpResponse(200, json_encode($payload));
        $client = new HttpClient('test-key', 'https://api.test');
        
        $fetcher = function (int $page) use ($response): HttpResponse {
            return $response;
        };

        $service = new CustomerService($client, $this->repository, $fetcher);
        $result = $service->syncContacts();

        self::assertTrue($result['response']->isSuccess());
        self::assertNull($result['error']);
        self::assertCount(1, $result['contacts']);
        
        $contact = $result['contacts'][0];
        self::assertSame('Acme GmbH', $contact['companyName']);
        self::assertSame('9001', (string) $contact['lexCustomerNumber']);
    }

    public function testFindContactByLexwareIdReturnsNullWhenNotFound(): void
    {
        $result = $this->service->findContactByLexwareId('non-existent-id');

        self::assertNull($result);
    }

    public function testFindContactByLexwareIdReturnsContactWhenFound(): void
    {
        $this->seedCustomerWithLexwareData(
            'CUST-001',
            'Acme Corp',
            'lex-contact-123',
            '9999'
        );

        $result = $this->service->findContactByLexwareId('lex-contact-123');

        self::assertNotNull($result);
        self::assertSame('lex-contact-123', $result->lexContactId);
        self::assertSame(9999, $result->lexCustomerNumber);
        self::assertSame('Acme Corp', $result->companyName);
    }

    private function createSchema(): void
    {
        $this->pdo->exec('CREATE TABLE customer (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            Name TEXT,
            Nummer TEXT,
            lex_customer_number TEXT,
            lex_contact_id TEXT,
            organization_id TEXT,
            version INTEGER,
            allow_tax_free_invoices INTEGER,
            archived INTEGER
        )');

        $this->pdo->exec('CREATE TABLE articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            article_number TEXT,
            name TEXT
        )');

        $this->pdo->exec('CREATE TABLE customers_article (
            customer_id INTEGER NOT NULL,
            article_id INTEGER NOT NULL,
            FOREIGN KEY(customer_id) REFERENCES customer(id) ON DELETE CASCADE,
            FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE
        )');
    }

    private function seedCustomer(string $number, string $name): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO customer (Nummer, Name) VALUES (:number, :name)'
        );
        $stmt->execute([
            ':number' => $number,
            ':name' => $name
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    private function seedCustomerWithLexwareData(
        string $number,
        string $name,
        string $lexContactId,
        string $lexCustomerNumber
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO customer (Nummer, Name, lex_contact_id, lex_customer_number) 
             VALUES (:number, :name, :lex_contact_id, :lex_customer_number)'
        );
        $stmt->execute([
            ':number' => $number,
            ':name' => $name,
            ':lex_contact_id' => $lexContactId,
            ':lex_customer_number' => $lexCustomerNumber
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    private function seedArticle(string $number, string $name): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO articles (article_number, name) VALUES (:number, :name)'
        );
        $stmt->execute([
            ':number' => $number,
            ':name' => $name
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    private function linkCustomerToArticle(int $customerId, int $articleId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO customers_article (customer_id, article_id) VALUES (:customer, :article)'
        );
        $stmt->execute([
            ':customer' => $customerId,
            ':article' => $articleId
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
}
