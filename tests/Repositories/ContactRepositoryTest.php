<?php

declare(strict_types=1);

namespace Tests\Repositories;

use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Models\Contact;
use Luxullus\LexBridge\Repositories\ContactRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once dirname(__DIR__, 2) . '/config.php';

final class ContactRepositoryTest extends TestCase
{
    private ContactTestingPDO $pdo;
    private ContactRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new ContactTestingPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->createSchema();
        $this->setDatabaseConnection($this->pdo);

        global $tableNames;
        $tableNames['customer'] = 'customer';
        $tableNames['customers_article'] = 'customers_article';
        $tableNames['articles'] = 'articles';

        $this->repository = new ContactRepository();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseConnection();
        unset($this->repository, $this->pdo);
        parent::tearDown();
    }

    public function testUpdateContactUpdatesMatchingCustomer(): void
    {
        $this->insertCustomer([
            'company_name' => 'Acme GmbH',
            'lex_contact_id' => null,
            'lex_customer_number' => null,
        ]);

        $contact = new Contact([
            'id' => 'contact-123',
            'roles' => [
                'customer' => [
                    'number' => 9001,
                ],
            ],
            'company' => [
                'name' => 'Acme GmbH',
            ],
        ]);

        self::assertTrue($this->repository->updateContact($contact));

        $row = $this->pdo
            ->query("SELECT lex_contact_id, lex_customer_number FROM customer WHERE company_name = 'Acme GmbH'")
            ->fetch();

        self::assertSame('contact-123', $row['lex_contact_id']);
        self::assertSame('9001', (string) $row['lex_customer_number']);
    }

    public function testGetCustomerContactsReturnsMappedRows(): void
    {
        $customerId = $this->insertCustomer([
            'company_name' => 'Beta AG',
            'kundenNummer' => 'B-200',
            'lex_customer_number' => '200',
        ]);
        $otherCustomerId = $this->insertCustomer([
            'company_name' => 'Alpha GmbH',
            'kundenNummer' => 'A-100',
            'lex_customer_number' => '100',
        ]);

        $articleId = $this->insertArticle([
            'article_number' => 'ART-01',
            'name' => 'Service Paket',
        ]);

        $this->linkCustomerArticle($customerId, $articleId);

        $results = $this->repository->getCustomerContacts();

        self::assertCount(2, $results);

        $first = $results[0];
        self::assertSame('Alpha GmbH', $first['companyName']);
        self::assertSame('A-100', $first['customerNumber']);
        self::assertNull($first['articleId']);
        self::assertNull($first['articleLabel']);

        $second = $results[1];
        self::assertSame('Beta AG', $second['companyName']);
        self::assertSame('B-200', $second['customerNumber']);
        self::assertSame($articleId, $second['articleId']);
        self::assertSame('ART-01 - Service Paket', $second['articleLabel']);
    }

    public function testUpdateCustomerArticleMappingAddsAndReassignsArticle(): void
    {
        $customerOne = $this->insertCustomer(['company_name' => 'First GmbH', 'kundenNummer' => 'C1']);
        $customerTwo = $this->insertCustomer(['company_name' => 'Second GmbH', 'kundenNummer' => 'C2']);
        $articleId = $this->insertArticle(['article_number' => 'SKU-1', 'name' => 'Artikel 1']);

        $this->repository->updateCustomerArticleMapping($customerOne, $articleId);

        $mapping = $this->pdo
            ->query('SELECT customer_id, article_id FROM customers_article')
            ->fetchAll();

        self::assertSame([[
            'customer_id' => $customerOne,
            'article_id' => $articleId,
        ]], $mapping);

        $this->repository->updateCustomerArticleMapping($customerTwo, $articleId);

        $mapping = $this->pdo
            ->query('SELECT customer_id, article_id FROM customers_article ORDER BY customer_id')
            ->fetchAll();

        self::assertSame([[
            'customer_id' => $customerTwo,
            'article_id' => $articleId,
        ]], $mapping);
    }

    public function testUpdateCustomerArticleMappingRemovesMappingWhenArticleIsNull(): void
    {
        $customerId = $this->insertCustomer(['company_name' => 'Remove GmbH', 'kundenNummer' => 'RMV']);
        $articleId = $this->insertArticle(['article_number' => 'SKU-DEL', 'name' => 'Artikel']);

        $this->repository->updateCustomerArticleMapping($customerId, $articleId);
        $this->repository->updateCustomerArticleMapping($customerId, null);

        $count = $this->pdo->query('SELECT COUNT(*) FROM customers_article')->fetchColumn();
        self::assertSame('0', (string) $count);
    }

    public function testFindByLexContactIdReturnsContact(): void
    {
        $this->insertCustomer([
            'company_name' => 'Lexware GmbH',
            'lex_contact_id' => 'lx-42',
            'lex_customer_number' => '4200',
            'organization_id' => 'org-1',
            'version' => 3,
            'allow_tax_free_invoices' => 1,
            'archived' => 0,
        ]);

        $contact = $this->repository->findByLexContactId('lx-42');
        self::assertInstanceOf(Contact::class, $contact);
        self::assertSame('lx-42', $contact->lexContactId);
        self::assertSame(4200, $contact->lexCustomerNumber);
        self::assertSame('Lexware GmbH', $contact->companyName);
    }

    public function testFindByLexContactIdReturnsNullWhenMissing(): void
    {
        self::assertNull($this->repository->findByLexContactId('missing'));
    }

    private function createSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE customer (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_name TEXT NOT NULL,
                kundenNummer TEXT,
                lex_customer_number TEXT,
                lex_contact_id TEXT,
                organization_id TEXT,
                version INTEGER,
                allow_tax_free_invoices INTEGER DEFAULT 0,
                archived INTEGER DEFAULT 0
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_number TEXT,
                name TEXT
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE customers_article (
                customer_id INTEGER NOT NULL UNIQUE,
                article_id INTEGER NOT NULL,
                FOREIGN KEY(customer_id) REFERENCES customer(id) ON DELETE CASCADE,
                FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE
            )'
        );
    }

    private function insertCustomer(array $overrides = []): int
    {
        $data = $overrides + [
            'company_name' => 'Company ' . uniqid('', false),
            'kundenNummer' => null,
            'lex_customer_number' => null,
            'lex_contact_id' => null,
            'organization_id' => null,
            'version' => 1,
            'allow_tax_free_invoices' => 0,
            'archived' => 0,
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO customer (company_name, kundenNummer, lex_customer_number, lex_contact_id, organization_id, version, allow_tax_free_invoices, archived)
             VALUES (:company_name, :kundenNummer, :lex_customer_number, :lex_contact_id, :organization_id, :version, :allow_tax_free_invoices, :archived)'
        );

        $stmt->execute([
            ':company_name' => $data['company_name'],
            ':kundenNummer' => $data['kundenNummer'],
            ':lex_customer_number' => $data['lex_customer_number'],
            ':lex_contact_id' => $data['lex_contact_id'],
            ':organization_id' => $data['organization_id'],
            ':version' => $data['version'],
            ':allow_tax_free_invoices' => $data['allow_tax_free_invoices'],
            ':archived' => $data['archived'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertArticle(array $overrides = []): int
    {
        $data = $overrides + [
            'article_number' => 'ART-' . uniqid('', false),
            'name' => 'Artikel ' . uniqid('', false),
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO articles (article_number, name) VALUES (:article_number, :name)'
        );

        $stmt->execute([
            ':article_number' => $data['article_number'],
            ':name' => $data['name'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function linkCustomerArticle(int $customerId, int $articleId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO customers_article (customer_id, article_id) VALUES (:customer_id, :article_id)'
        );

        $stmt->execute([
            ':customer_id' => $customerId,
            ':article_id' => $articleId,
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

final class ContactTestingPDO extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
    }

    public function exec($statement): int|false
    {
        $statement = $this->transformSql($statement);
        return parent::exec($statement);
    }

    public function prepare($statement, $options = []): PDOStatement|false
    {
        $statement = $this->transformSql((string) $statement);
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
        $sql = (string) preg_replace('/\s+FOR\s+UPDATE\b/i', '', $sql);
        $sql = (string) preg_replace('/DATE_SUB\s*\(\s*CURRENT_DATE\s*,\s*INTERVAL\s+1\s+DAY\s*\)/i', "DATE('now','-1 day')", $sql);
        $sql = (string) preg_replace('/NOW\s*\(\s*\)/i', 'CURRENT_TIMESTAMP', $sql);

        return $sql;
    }
}
