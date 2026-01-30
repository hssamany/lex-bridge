<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Repositories;

use PDO;
use Throwable;
use Luxullus\LexBridge\Database\Database;

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
     * Search articles by number or name.
     *
     * @param string|null $query
     * @return array<int, array<string, mixed>>
     */
    public function searchArticles(?string $query): array
    {
        $articleTable = $this->articleTable;
        $priceTable = $this->priceTable;

        $driver = (string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $sql = <<<SQL
                SELECT
                    a.id,
                    a.article_number,
                    a.name,
                    lp.net_amount,
                    lp.gross_amount,
                    lp.tax_rate_percentage,
                    lp.currency,
                    lp.valid_from,
                    lp.valid_until
                FROM {$articleTable} a
                LEFT JOIN LATERAL (
                    SELECT
                        pr.net_amount,
                        pr.gross_amount,
                        pr.tax_rate_percentage,
                        pr.currency,
                        pr.valid_from,
                        pr.valid_until
                    FROM {$priceTable} pr
                    WHERE pr.article_id = a.id
                        AND pr.valid_from <= CURRENT_DATE
                        AND (pr.valid_until IS NULL OR pr.valid_until >= CURRENT_DATE)
                    ORDER BY pr.valid_from DESC, pr.id DESC
                    LIMIT 1
                ) AS lp ON TRUE
            SQL;
        } else {
            $sql = <<<SQL
                SELECT
                    a.id,
                    a.article_number,
                    a.name,
                    (
                        SELECT pr.net_amount
                        FROM {$priceTable} pr
                        WHERE pr.article_id = a.id
                            AND pr.valid_from <= CURRENT_DATE
                            AND (pr.valid_until IS NULL OR pr.valid_until >= CURRENT_DATE)
                        ORDER BY pr.valid_from DESC, pr.id DESC
                        LIMIT 1
                    ) AS net_amount,
                    (
                        SELECT pr.gross_amount
                        FROM {$priceTable} pr
                        WHERE pr.article_id = a.id
                            AND pr.valid_from <= CURRENT_DATE
                            AND (pr.valid_until IS NULL OR pr.valid_until >= CURRENT_DATE)
                        ORDER BY pr.valid_from DESC, pr.id DESC
                        LIMIT 1
                    ) AS gross_amount,
                    (
                        SELECT pr.tax_rate_percentage
                        FROM {$priceTable} pr
                        WHERE pr.article_id = a.id
                            AND pr.valid_from <= CURRENT_DATE
                            AND (pr.valid_until IS NULL OR pr.valid_until >= CURRENT_DATE)
                        ORDER BY pr.valid_from DESC, pr.id DESC
                        LIMIT 1
                    ) AS tax_rate_percentage,
                    (
                        SELECT pr.currency
                        FROM {$priceTable} pr
                        WHERE pr.article_id = a.id
                            AND pr.valid_from <= CURRENT_DATE
                            AND (pr.valid_until IS NULL OR pr.valid_until >= CURRENT_DATE)
                        ORDER BY pr.valid_from DESC, pr.id DESC
                        LIMIT 1
                    ) AS currency,
                    (
                        SELECT pr.valid_from
                        FROM {$priceTable} pr
                        WHERE pr.article_id = a.id
                            AND pr.valid_from <= CURRENT_DATE
                            AND (pr.valid_until IS NULL OR pr.valid_until >= CURRENT_DATE)
                        ORDER BY pr.valid_from DESC, pr.id DESC
                        LIMIT 1
                    ) AS valid_from,
                    (
                        SELECT pr.valid_until
                        FROM {$priceTable} pr
                        WHERE pr.article_id = a.id
                            AND pr.valid_from <= CURRENT_DATE
                            AND (pr.valid_until IS NULL OR pr.valid_until >= CURRENT_DATE)
                        ORDER BY pr.valid_from DESC, pr.id DESC
                        LIMIT 1
                    ) AS valid_until
                FROM {$articleTable} a
            SQL;
        }

        $hasQuery = $query !== null && $query !== '';
        
        if ($hasQuery) {
            if ($driver === 'mysql') {
                $sql .= " WHERE article_number LIKE :term_num OR name LIKE :term_name OR CONCAT(article_number, ' - ', name) LIKE :term_combo";
            } else {
                $sql .= " WHERE article_number LIKE :term_num OR name LIKE :term_name OR (article_number || ' - ' || name) LIKE :term_combo";
            }
        }

        $sql .= ' ORDER BY name ASC LIMIT 20';

        $stmt = $this->db->prepare($sql);

        if ($hasQuery) {
            $like = '%' . $query . '%';
            $stmt->bindValue('term_num', $like, PDO::PARAM_STR);
            $stmt->bindValue('term_name', $like, PDO::PARAM_STR);
            $stmt->bindValue('term_combo', $like, PDO::PARAM_STR);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $articleData
     * @param array<string, mixed> $priceData
     * @return array{article_id:int,created:bool,article_changed:bool,price_changed:bool}
     */
    public function upsertLexwareArticle(array $articleData, array $priceData): array
    {
        $normalizedArticle = $this->normalizeArticleData($articleData, $priceData);
        $normalizedPrice = $this->normalizePriceData($priceData);

        $this->db->beginTransaction();

        try {
            $existing = $this->findArticleByLexwareIdForUpdate($articleData['lexware_article_id']);

            $created = false;
            $articleChanged = false;
            $articleId = null;

            if ($existing) {
                $articleId = (int) $existing['id'];
                $articleChanged = $this->hasArticleDifferences($existing, $normalizedArticle);

                if ($articleChanged) {
                    $this->updateArticle($articleId, $normalizedArticle);
                }
            } else {
                $articleId = $this->insertArticle($articleData['lexware_article_id'], $normalizedArticle);
                $created = true;
                $articleChanged = true;
            }

            $priceChanged = $this->ensurePriceRecord($articleId, $normalizedPrice);

            // ensure article monetary fields reflect the newest price
            if (!$articleChanged && $priceChanged) {
                $this->updateArticle($articleId, $normalizedArticle);
                $articleChanged = true;
            }

            $result = [
                'article_id' => $articleId,
                'created' => $created,
                'article_changed' => $articleChanged,
                'price_changed' => $priceChanged
            ];
            $this->db->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function insertArticle(string $lexwareId, array $article): int
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

        $stmt->bindValue(':lexware_id', $lexwareId, PDO::PARAM_STR);
        $stmt->bindValue(':article_number', $article['article_number'], PDO::PARAM_STR);
        $stmt->bindValue(':name', $article['name'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $article['description'], $article['description'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':unit_name', $article['unit_name'], PDO::PARAM_STR);
        $stmt->bindValue(':net_price', $article['net_price'], PDO::PARAM_STR);
        $stmt->bindValue(':gross_price', $article['gross_price'], PDO::PARAM_STR);
        $stmt->bindValue(':tax_rate', $article['tax_rate'], PDO::PARAM_STR);

        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    private function updateArticle(int $articleId, array $article): void
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
        $stmt->bindValue(':description', $article['description'], $article['description'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':unit_name', $article['unit_name'], PDO::PARAM_STR);
        $stmt->bindValue(':net_price', $article['net_price'], PDO::PARAM_STR);
        $stmt->bindValue(':gross_price', $article['gross_price'], PDO::PARAM_STR);
        $stmt->bindValue(':tax_rate', $article['tax_rate'], PDO::PARAM_STR);

        $stmt->execute();
    }

    private function ensurePriceRecord(int $articleId, array $price): bool
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

        if (!$this->hasPriceDifferences($latest, $price)) {
            return false;
        }

        if ($latest && $latest['valid_until'] === null) {
            $closeSql = <<<SQL
                UPDATE {$this->priceTable}
                SET valid_until = DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)
                WHERE id = :id
            SQL;
            $closeStmt = $this->db->prepare($closeSql);
            $closeStmt->bindValue(':id', (int) $latest['id'], PDO::PARAM_INT);
            $closeStmt->execute();
        }

        $insertSql = <<<SQL
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
        $insert = $this->db->prepare($insertSql);
        $insert->bindValue(':article_id', $articleId, PDO::PARAM_INT);
        $insert->bindValue(':net_amount', $price['net_amount'], PDO::PARAM_STR);
        $insert->bindValue(':gross_amount', $price['gross_amount'], PDO::PARAM_STR);
        $insert->bindValue(':tax_rate_percentage', $price['tax_rate_percentage'], PDO::PARAM_STR);
        $insert->bindValue(':currency', $price['currency'], PDO::PARAM_STR);

        $insert->execute();

        return true;
    }

    private function findArticleByLexwareIdForUpdate(string $lexwareId): ?array
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
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $incoming
     */
    private function hasArticleDifferences(array $existing, array $incoming): bool
    {
        $normalizedExisting = [
            'article_number' => $existing['article_number'],
            'name' => $existing['name'],
            'description' => $existing['description'] ?? null,
            'unit_name' => $existing['unit_name'],
            'net_price' => $this->formatMoney($existing['net_price']),
            'gross_price' => $this->formatMoney($existing['gross_price']),
            'tax_rate' => $this->formatTax($existing['tax_rate'])
        ];

        return $normalizedExisting !== $incoming;
    }

    /**
     * @param array<string, mixed>|null $existing
     * @param array<string, mixed> $incoming
     */
    private function hasPriceDifferences(?array $existing, array $incoming): bool
    {
        if ($existing === null) {
            return true;
        }

        if ($this->formatMoney($existing['net_amount']) !== $incoming['net_amount']) {
            return true;
        }

        if ($this->formatMoney($existing['gross_amount']) !== $incoming['gross_amount']) {
            return true;
        }

        if ($this->formatTax($existing['tax_rate_percentage']) !== $incoming['tax_rate_percentage']) {
            return true;
        }

        if (strtoupper((string) $existing['currency']) !== $incoming['currency']) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $article
     * @param array<string, mixed> $price
     * @return array<string, mixed>
     */
    private function normalizeArticleData(array $article, array $price): array
    {
        return [
            'article_number' => $article['article_number'],
            'name' => $article['name'],
            'description' => $article['description'],
            'unit_name' => $article['unit_name'],
            'net_price' => $this->formatMoney($price['net_amount']),
            'gross_price' => $this->formatMoney($price['gross_amount']),
            'tax_rate' => $this->formatTax($price['tax_rate'])
        ];
    }

    /**
     * @param array<string, mixed> $price
     * @return array<string, mixed>
     */
    private function normalizePriceData(array $price): array
    {
        return [
            'net_amount' => $this->formatMoney($price['net_amount']),
            'gross_amount' => $this->formatMoney($price['gross_amount']),
            'tax_rate_percentage' => $this->formatTax($price['tax_rate']),
            'currency' => strtoupper($price['currency'] ?? 'EUR')
        ];
    }

    private function formatMoney(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function formatTax(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
