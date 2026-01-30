<?php

declare(strict_types=1);

namespace Tests\Repositories;

use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Repositories\ArticleRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once dirname(__DIR__, 2) . '/config.php';

final class ArticleRepositoryTest extends TestCase
{
    private TestingPDO $pdo;
    private ArticleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new TestingPDO();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->createSchema();
        $this->setDatabaseConnection($this->pdo);

        global $tableNames;
        $tableNames['articles'] = 'articles';
        $tableNames['prices'] = 'prices';

        $this->repository = new ArticleRepository();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseConnection();
        unset($this->repository, $this->pdo);
        parent::tearDown();
    }

    public function testSearchArticlesReturnsResultsWithCurrentPrices(): void
    {
        $alphaId = $this->insertArticle([
            'lexware_article_id' => 'LEX-ALPHA',
            'article_number' => 'ALP-001',
            'name' => 'Alpha Article',
            'description' => 'Alpha description',
            'unit_name' => 'kg',
            'net_price' => '0.00',
            'gross_price' => '0.00',
            'tax_rate' => '19.00',
        ]);

        $betaId = $this->insertArticle([
            'lexware_article_id' => 'LEX-BETA',
            'article_number' => 'BET-002',
            'name' => 'Beta Article',
            'description' => 'Beta description',
            'unit_name' => 'stk',
            'net_price' => '0.00',
            'gross_price' => '0.00',
            'tax_rate' => '7.00',
        ]);

        $this->insertPrice($alphaId, [
            'net_amount' => '4.50',
            'gross_amount' => '5.36',
            'tax_rate_percentage' => '19.00',
            'currency' => 'EUR',
            'valid_from' => '2020-01-01',
            'valid_until' => null,
        ]);

        $this->insertPrice($betaId, [
            'net_amount' => '2.10',
            'gross_amount' => '2.25',
            'tax_rate_percentage' => '7.00',
            'currency' => 'EUR',
            'valid_from' => '2019-01-01',
            'valid_until' => null,
        ]);

        $results = $this->repository->searchArticles(null);

        self::assertCount(2, $results);
        self::assertSame('Alpha Article', $results[0]['name']);
        self::assertSame('4.50', $results[0]['net_amount']);
        self::assertSame('Beta Article', $results[1]['name']);
        self::assertSame('2.10', $results[1]['net_amount']);
    }

    public function testSearchArticlesFiltersByQueryAcrossFields(): void
    {
        $gammaId = $this->insertArticle([
            'lexware_article_id' => 'LEX-GAMMA',
            'article_number' => 'GAM-003',
            'name' => 'Gamma Service',
            'description' => 'Gamma description',
            'unit_name' => 'ltr',
            'net_price' => '0.00',
            'gross_price' => '0.00',
            'tax_rate' => '19.00',
        ]);

        $deltaId = $this->insertArticle([
            'lexware_article_id' => 'LEX-DELTA',
            'article_number' => 'DEL-004',
            'name' => 'Delta Support',
            'description' => 'Delta description',
            'unit_name' => 'stk',
            'net_price' => '0.00',
            'gross_price' => '0.00',
            'tax_rate' => '19.00',
        ]);

        $this->insertPrice($gammaId, [
            'net_amount' => '7.99',
            'gross_amount' => '9.51',
            'tax_rate_percentage' => '19.00',
            'currency' => 'CHF',
            'valid_from' => '2021-06-01',
            'valid_until' => null,
        ]);

        $this->insertPrice($deltaId, [
            'net_amount' => '12.00',
            'gross_amount' => '14.28',
            'tax_rate_percentage' => '19.00',
            'currency' => 'EUR',
            'valid_from' => '2023-01-01',
            'valid_until' => null,
        ]);

        $results = $this->repository->searchArticles('GAM-003 - Gamma');

        self::assertCount(1, $results);
        $match = $results[0];
        self::assertSame('Gamma Service', $match['name']);
        self::assertSame('7.99', $match['net_amount']);
        self::assertSame('CHF', $match['currency']);
    }

    public function testSearchArticlesLimitsResultsToTwentyItems(): void
    {
        for ($index = 1; $index <= 25; $index += 1) {
            $articleId = $this->insertArticle([
                'lexware_article_id' => 'LEX-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'article_number' => 'ART-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'name' => 'Article ' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'description' => 'Description ' . $index,
                'unit_name' => 'stk',
                'net_price' => '0.00',
                'gross_price' => '0.00',
                'tax_rate' => '19.00',
            ]);

            $this->insertPrice($articleId, [
                'net_amount' => '1.' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'gross_amount' => '2.' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'tax_rate_percentage' => '19.00',
                'currency' => 'EUR',
                'valid_from' => '2020-01-01',
                'valid_until' => null,
            ]);
        }

        $results = $this->repository->searchArticles(null);

        self::assertCount(20, $results);
        self::assertSame('Article 01', $results[0]['name']);
        self::assertSame('Article 20', $results[19]['name']);
    }

    public function testUpsertInsertsNewArticleAndPriceWhenMissing(): void
    {
        $result = $this->repository->upsertLexwareArticle([
            'lexware_article_id' => 'LEX-NEW',
            'article_number' => 'NEW-100',
            'name' => 'New Widget',
            'description' => 'Fresh item',
            'unit_name' => 'stk',
        ], [
            'net_amount' => '12.345',
            'gross_amount' => '14.567',
            'tax_rate' => '19.0',
            'currency' => 'eur',
        ]);

        self::assertTrue($result['created']);
        self::assertTrue($result['article_changed']);
        self::assertTrue($result['price_changed']);
        self::assertGreaterThan(0, $result['article_id']);

        $article = $this->pdo->query('SELECT * FROM articles WHERE id = ' . (int) $result['article_id'])->fetch();
        self::assertIsArray($article);
        self::assertSame('NEW-100', $article['article_number']);
        self::assertSame('New Widget', $article['name']);
        self::assertSame('12.35', $article['net_price']);
        self::assertSame('14.57', $article['gross_price']);
        self::assertSame('19.00', $article['tax_rate']);

        $prices = $this->pdo->query('SELECT net_amount, gross_amount, tax_rate_percentage, currency, valid_from, valid_until FROM prices WHERE article_id = ' . (int) $result['article_id'])->fetchAll();
        self::assertCount(1, $prices);
        $price = $prices[0];
        self::assertSame('12.35', $price['net_amount']);
        self::assertSame('14.57', $price['gross_amount']);
        self::assertSame('19.00', $price['tax_rate_percentage']);
        self::assertSame('EUR', strtoupper((string) $price['currency']));
        self::assertNull($price['valid_until']);
    }

    public function testUpsertSkipsUpdatesWhenDataMatchesExisting(): void
    {
        $articleId = $this->insertArticle([
            'lexware_article_id' => 'LEX-SAME',
            'article_number' => 'SAM-200',
            'name' => 'Same Article',
            'description' => 'Unchanged',
            'unit_name' => 'stk',
            'net_price' => '10.00',
            'gross_price' => '11.90',
            'tax_rate' => '19.00',
        ]);

        $this->insertPrice($articleId, [
            'net_amount' => '10.00',
            'gross_amount' => '11.90',
            'tax_rate_percentage' => '19.00',
            'currency' => 'EUR',
            'valid_from' => '2024-01-01',
            'valid_until' => '2024-12-31',
        ]);

        $result = $this->repository->upsertLexwareArticle([
            'lexware_article_id' => 'LEX-SAME',
            'article_number' => 'SAM-200',
            'name' => 'Same Article',
            'description' => 'Unchanged',
            'unit_name' => 'stk',
        ], [
            'net_amount' => '10.00',
            'gross_amount' => '11.90',
            'tax_rate' => '19.00',
            'currency' => 'EUR',
        ]);

        self::assertFalse($result['created']);
        self::assertFalse($result['article_changed']);
        self::assertFalse($result['price_changed']);
        self::assertSame($articleId, $result['article_id']);

        $priceCount = $this->pdo->query('SELECT COUNT(*) FROM prices WHERE article_id = ' . $articleId)->fetchColumn();
        self::assertSame('1', (string) $priceCount);
    }

    public function testUpsertUpdatesArticleAndClosesPreviousPriceWhenChanged(): void
    {
        $articleId = $this->insertArticle([
            'lexware_article_id' => 'LEX-CHANGE',
            'article_number' => 'CHG-300',
            'name' => 'Change Me',
            'description' => 'Old description',
            'unit_name' => 'stk',
            'net_price' => '8.00',
            'gross_price' => '9.52',
            'tax_rate' => '19.00',
        ]);

        $this->insertPrice($articleId, [
            'net_amount' => '8.00',
            'gross_amount' => '9.52',
            'tax_rate_percentage' => '19.00',
            'currency' => 'EUR',
            'valid_from' => '2024-01-01',
            'valid_until' => null,
        ]);

        $result = $this->repository->upsertLexwareArticle([
            'lexware_article_id' => 'LEX-CHANGE',
            'article_number' => 'CHG-300',
            'name' => 'Change Me Updated',
            'description' => 'Updated description',
            'unit_name' => 'stk',
        ], [
            'net_amount' => '9.00',
            'gross_amount' => '10.71',
            'tax_rate' => '19.00',
            'currency' => 'EUR',
        ]);

        self::assertFalse($result['created']);
        self::assertTrue($result['article_changed']);
        self::assertTrue($result['price_changed']);
        self::assertSame($articleId, $result['article_id']);

        $article = $this->pdo->query('SELECT * FROM articles WHERE id = ' . $articleId)->fetch();
        self::assertIsArray($article);
        self::assertSame('Change Me Updated', $article['name']);
        self::assertSame('Updated description', $article['description']);
        self::assertSame('9.00', $article['net_price']);
        self::assertSame('10.71', $article['gross_price']);

        $prices = $this->pdo->query('SELECT id, net_amount, gross_amount, tax_rate_percentage, valid_until FROM prices WHERE article_id = ' . $articleId . ' ORDER BY id ASC')->fetchAll();
        self::assertCount(2, $prices);

        $first = $prices[0];
        $second = $prices[1];

        self::assertNotNull($first['valid_until']);
        $expectedClosed = date('Y-m-d', strtotime('-1 day'));
        self::assertSame($expectedClosed, $first['valid_until']);

        self::assertSame('9.00', $second['net_amount']);
        self::assertSame('10.71', $second['gross_amount']);
        self::assertNull($second['valid_until']);
    }

    private function createSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lexware_article_id TEXT NOT NULL UNIQUE,
                article_number TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT NULL,
                unit_name TEXT NOT NULL,
                net_price TEXT NOT NULL,
                gross_price TEXT NOT NULL,
                tax_rate TEXT NOT NULL,
                active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE prices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id INTEGER NOT NULL,
                net_amount TEXT NOT NULL,
                gross_amount TEXT NOT NULL,
                tax_rate_percentage TEXT NOT NULL,
                currency TEXT NOT NULL,
                valid_from TEXT NOT NULL,
                valid_until TEXT NULL,
                FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE
            )'
        );
    }

    private function insertArticle(array $overrides = []): int
    {
        $data = $overrides + [
            'lexware_article_id' => 'LEX-' . uniqid('', false),
            'article_number' => 'SKU-' . uniqid('', false),
            'name' => 'Article ' . uniqid('', false),
            'description' => null,
            'unit_name' => 'stk',
            'net_price' => '0.00',
            'gross_price' => '0.00',
            'tax_rate' => '19.00',
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO articles (lexware_article_id, article_number, name, description, unit_name, net_price, gross_price, tax_rate, active, created_at, updated_at)
             VALUES (:lexware_article_id, :article_number, :name, :description, :unit_name, :net_price, :gross_price, :tax_rate, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([
            ':lexware_article_id' => $data['lexware_article_id'],
            ':article_number' => $data['article_number'],
            ':name' => $data['name'],
            ':description' => $data['description'],
            ':unit_name' => $data['unit_name'],
            ':net_price' => $data['net_price'],
            ':gross_price' => $data['gross_price'],
            ':tax_rate' => $data['tax_rate'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertPrice(int $articleId, array $overrides = []): void
    {
        $data = $overrides + [
            'net_amount' => '1.00',
            'gross_amount' => '1.19',
            'tax_rate_percentage' => '19.00',
            'currency' => 'EUR',
            'valid_from' => date('Y-m-d'),
            'valid_until' => null,
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO prices (article_id, net_amount, gross_amount, tax_rate_percentage, currency, valid_from, valid_until)
             VALUES (:article_id, :net_amount, :gross_amount, :tax_rate_percentage, :currency, :valid_from, :valid_until)'
        );

        $stmt->execute([
            ':article_id' => $articleId,
            ':net_amount' => $data['net_amount'],
            ':gross_amount' => $data['gross_amount'],
            ':tax_rate_percentage' => $data['tax_rate_percentage'],
            ':currency' => $data['currency'],
            ':valid_from' => $data['valid_from'],
            ':valid_until' => $data['valid_until'],
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

final class TestingPDO extends PDO
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
