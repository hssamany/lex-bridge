<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Repositories;

use PDO;
use Throwable;
use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Logger;

final class ArticleRepository
{
    private PDO $db;
    private string $articleTable;
    private string $priceTable;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->articleTable = \lexbridge_table('articles');
        $this->priceTable = \lexbridge_table('prices');
    }

    /**
     * Search articles by number or name with current price information.
     *
     * @param array<string, mixed> $filter
     * @return array<int, array<string, mixed>>
     */
    public function searchArticles(?array $filter = []): array
    {
        $queryData = $this->buildSearchQuery($filter);

        $stmt = $this->db->prepare($queryData['sql']);

        foreach ($queryData['params'] as $ph => $val) {
            $stmt->bindValue($ph, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Insert or update article with price information in a transaction.
     *
     * @param array<string, mixed> $articleData Must include: lexware_article_id, article_number, name, unit_name
     * @param array<string, mixed> $priceData Must include: net_amount, gross_amount, tax_rate, currency
     * @return array{created:bool,article_changed:bool,price_changed:bool}
     */
    public function upsertArticle(array $articleData, array $priceData): array
    {
        $this->db->beginTransaction();

        try {
            $lexwareId = $articleData['lexware_article_id'];
            $existing = $this->findByLexwareIdForUpdate($lexwareId);

            if ($existing === null) {
                $articleId = $this->insert($articleData, $priceData);
                $priceChanged = $this->insertPrice($articleId, $priceData);

                $this->db->commit();

                return [
                    'created' => true,
                    'article_changed' => true,
                    'price_changed' => $priceChanged
                ];
            }

            $articleId = (int) $existing['id'];
            $articleChanged = $this->hasArticleChanges($existing, $articleData, $priceData);

            if ($articleChanged) {
                $this->update($articleId, $articleData, $priceData);
            }

            $priceChanged = $this->updatePriceIfChanged($articleId, $existing, $priceData);

            if (!$articleChanged && $priceChanged) {
                $this->update($articleId, $articleData, $priceData);
                $articleChanged = true;
            }

            $this->db->commit();

            return [
                'created' => false,
                'article_changed' => $articleChanged,
                'price_changed' => $priceChanged
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            Logger::exception($exception, 'ArticleRepository - Upsert Failed');
            throw $exception;
        }
    }

    
    public function buildSearchQuery(?array $filter = []): array
    {
        $params = [];
        $whereClauses = [];

        foreach (($filter ?? []) as $column => $value) {
            // Handle text search query
            if ($column === 'q' && is_string($value) && $value !== '') {
                $searchTerm = "%{$value}%";
                $whereClauses[] = "(a.article_number LIKE :search_number OR a.name LIKE :search_name)";
                $params[':search_number'] = $searchTerm;
                $params[':search_name'] = $searchTerm;
                continue;
            }
            
            if (is_array($value) && !empty($value)) {
                // IN clause for arrays
                $inPlaceholders = [];
                foreach ($value as $idx => $item) {
                    $ph = ":{$column}_{$idx}";
                    $inPlaceholders[] = $ph;
                    $params[$ph] = $item;
                }
                $whereClauses[] = "a.$column IN (" . implode(',', $inPlaceholders) . ")";
            } elseif ($value !== null) {
                // Equality for scalars
                $ph = ":{$column}";
                $whereClauses[] = "a.$column = $ph";
                $params[$ph] = $value;
            }
        }

        $where = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $sql = <<<SQL
            SELECT
                a.id,
                a.article_number,
                a.name,
                a.description,
                a.unit_name,
                p.net_amount,
                p.gross_amount,
                p.tax_rate_percentage,
                p.currency,
                p.valid_from,
                p.valid_until
            FROM {$this->articleTable} a
            LEFT JOIN (
                SELECT pr1.*
                FROM {$this->priceTable} pr1
                INNER JOIN (
                    SELECT article_id, MAX(valid_from) AS max_valid_from
                    FROM {$this->priceTable}
                    WHERE valid_from <= CURRENT_DATE
                    GROUP BY article_id
                ) pr2
                ON pr1.article_id = pr2.article_id AND pr1.valid_from = pr2.max_valid_from
                WHERE pr1.valid_from <= CURRENT_DATE
                AND (pr1.valid_until IS NULL OR pr1.valid_until >= CURRENT_DATE)
            ) p ON a.id = p.article_id
            $where
            ORDER BY a.name ASC
        SQL;

        return [
            'sql' => $sql,
            'params' => $params,
        ];
    }

    /**
     * Find article by Lexware ID with row lock.
     */
    private function findByLexwareIdForUpdate(string $lexwareId): ?array
    {
        $sql = <<<SQL
            SELECT
                id,
                article_number,
                name,
                description,
                unit_name,
                net_price,
                gross_price,
                tax_rate
            FROM {$this->articleTable}
            WHERE lexware_article_id = :lexware_id
            LIMIT 1 FOR UPDATE
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':lexware_id', $lexwareId, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Insert new article record.
     *
     * @param array<string, mixed> $article Must include: lexware_article_id, article_number, name, unit_name, description
     * @param array<string, mixed> $price Must include: net_amount, gross_amount, tax_rate
     * @return int Article ID
     */
    private function insert(array $article, array $price): int
    {
        $sql = <<<SQL
            INSERT INTO {$this->articleTable} (
                lexware_article_id,
                article_number,
                name,
                description,
                unit_name,
                net_price,
                gross_price,
                tax_rate,
                active
            )
            VALUES (
                :lexware_id,
                :article_number,
                :name,
                :description,
                :unit_name,
                :net_price,
                :gross_price,
                :tax_rate,
                1
            )
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':lexware_id', $article['lexware_article_id'], PDO::PARAM_STR);
        $stmt->bindValue(':article_number', $article['article_number'], PDO::PARAM_STR);
        $stmt->bindValue(':name', $article['name'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $article['description'], 
            $article['description'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':unit_name', $article['unit_name'], PDO::PARAM_STR);
        $stmt->bindValue(':net_price', $price['net_amount'], PDO::PARAM_STR);
        $stmt->bindValue(':gross_price', $price['gross_amount'], PDO::PARAM_STR);
        $stmt->bindValue(':tax_rate', $price['tax_rate'], PDO::PARAM_STR);

        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update existing article record.
     *
     * @param array<string, mixed> $article
     * @param array<string, mixed> $price
     */
    private function update(int $articleId, array $article, array $price): void
    {
        $sql = <<<SQL
            UPDATE {$this->articleTable}
            SET
                article_number = :article_number,
                name = :name,
                description = :description,
                unit_name = :unit_name,
                net_price = :net_price,
                gross_price = :gross_price,
                tax_rate = :tax_rate,
                updated_at = NOW()
            WHERE id = :id
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $articleId, PDO::PARAM_INT);
        $stmt->bindValue(':article_number', $article['article_number'], PDO::PARAM_STR);
        $stmt->bindValue(':name', $article['name'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $article['description'], 
            $article['description'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':unit_name', $article['unit_name'], PDO::PARAM_STR);
        $stmt->bindValue(':net_price', $price['net_amount'], PDO::PARAM_STR);
        $stmt->bindValue(':gross_price', $price['gross_amount'], PDO::PARAM_STR);
        $stmt->bindValue(':tax_rate', $price['tax_rate'], PDO::PARAM_STR);

        $stmt->execute();
    }

    /**
     * Check if article fields have changed.
     *
     * @param array<string, mixed> $existing Database record
     * @param array<string, mixed> $article Incoming article data
     * @param array<string, mixed> $price Incoming price data
     */
    private function hasArticleChanges(array $existing, array $article, array $price): bool
    {
        return $existing['article_number'] !== $article['article_number']
            || $existing['name'] !== $article['name']
            || ($existing['description'] ?? null) !== $article['description']
            || $existing['unit_name'] !== $article['unit_name']
            || $existing['net_price'] !== $price['net_amount']
            || $existing['gross_price'] !== $price['gross_amount']
            || $existing['tax_rate'] !== $price['tax_rate'];
    }

    /**
     * Insert new price record for an article.
     *
     * @param array<string, mixed> $price Must include: net_amount, gross_amount, tax_rate, currency
     * @return bool Always returns true
     */
    private function insertPrice(int $articleId, array $price): bool
    {
        $sql = <<<SQL
            INSERT INTO {$this->priceTable} (
                article_id,
                net_amount,
                gross_amount,
                tax_rate_percentage,
                currency,
                valid_from,
                valid_until
            )
            VALUES (
                :article_id,
                :net_amount,
                :gross_amount,
                :tax_rate_percentage,
                :currency,
                CURRENT_DATE,
                NULL
            )
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':article_id', $articleId, PDO::PARAM_INT);
        $stmt->bindValue(':net_amount', $price['net_amount'], PDO::PARAM_STR);
        $stmt->bindValue(':gross_amount', $price['gross_amount'], PDO::PARAM_STR);
        $stmt->bindValue(':tax_rate_percentage', $price['tax_rate'], PDO::PARAM_STR);
        $stmt->bindValue(':currency', $price['currency'], PDO::PARAM_STR);

        $stmt->execute();

        return true;
    }

    /**
     * Update price if changed, closing previous price period.
     *
     * @param array<string, mixed> $existing Current article record
     * @param array<string, mixed> $price New price data
     * @return bool True if price was updated
     */
    private function updatePriceIfChanged(int $articleId, array $existing, array $price): bool
    {
        $sql = <<<SQL
            SELECT
                id,
                net_amount,
                gross_amount,
                tax_rate_percentage,
                currency,
                valid_until
            FROM {$this->priceTable}
            WHERE article_id = :article_id
            ORDER BY valid_from DESC, id DESC
            LIMIT 1 FOR UPDATE
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':article_id', $articleId, PDO::PARAM_INT);
        $stmt->execute();

        $latest = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($this->isPriceUnchanged($latest, $price)) {
            return false;
        }

        if ($latest && $latest['valid_until'] === null) {
            $this->closePricePeriod((int) $latest['id']);
        }

        $this->insertPrice($articleId, $price);

        return true;
    }

    /**
     * Check if price values are unchanged.
     *
     * @param array<string, mixed>|null $existing
     * @param array<string, mixed> $price
     */
    private function isPriceUnchanged(?array $existing, array $price): bool
    {
        if ($existing === null) {
            return false;
        }

        return $existing['net_amount'] === $price['net_amount']
            && $existing['gross_amount'] === $price['gross_amount']
            && $existing['tax_rate_percentage'] === $price['tax_rate']
            && strtoupper((string) $existing['currency']) === $price['currency'];
    }

    /**
     * Close price period by setting valid_until to yesterday.
     */
    private function closePricePeriod(int $priceId): void
    {
        $sql = <<<SQL
            UPDATE {$this->priceTable}
            SET valid_until = DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)
            WHERE id = :id
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $priceId, PDO::PARAM_INT);
        $stmt->execute();
    }
}
