<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Repositories;

use PDO;
use Luxullus\LexBridge\Database\Database;

final class ArticleRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Search articles by number or name.
     *
     * @param string|null $query
     * @return array<int, array<string, mixed>>
     */
    public function searchArticles(?string $query): array
    {
                $sql = <<<SQL
                        SELECT
                                a.id,
                                a.article_number,
                                a.name,
                                (
                                        SELECT pr.net_amount
                                        FROM prices pr
                                        WHERE pr.article_id = a.id
                                            AND pr.valid_from <= CURRENT_DATE
                                            AND (pr.valid_until IS NULL OR pr.valid_until >= CURRENT_DATE)
                                        ORDER BY pr.valid_from DESC, pr.id DESC
                                        LIMIT 1
                                ) AS net_amount,
                                (
                                        SELECT pr.gross_amount
                                        FROM prices pr
                                        WHERE pr.article_id = a.id
                                            AND pr.valid_from <= CURRENT_DATE
                                            AND (pr.valid_until IS NULL OR pr.valid_until >= CURRENT_DATE)
                                        ORDER BY pr.valid_from DESC, pr.id DESC
                                        LIMIT 1
                                ) AS gross_amount,
                                (
                                        SELECT pr.tax_rate_percentage
                                        FROM prices pr
                                        WHERE pr.article_id = a.id
                                            AND pr.valid_from <= CURRENT_DATE
                                            AND (pr.valid_until IS NULL OR pr.valid_until >= CURRENT_DATE)
                                        ORDER BY pr.valid_from DESC, pr.id DESC
                                        LIMIT 1
                                ) AS tax_rate_percentage,
                                (
                                        SELECT pr.currency
                                        FROM prices pr
                                        WHERE pr.article_id = a.id
                                            AND pr.valid_from <= CURRENT_DATE
                                            AND (pr.valid_until IS NULL OR pr.valid_until >= CURRENT_DATE)
                                        ORDER BY pr.valid_from DESC, pr.id DESC
                                        LIMIT 1
                                ) AS currency,
                                (
                                        SELECT pr.valid_from
                                        FROM prices pr
                                        WHERE pr.article_id = a.id
                                            AND pr.valid_from <= CURRENT_DATE
                                            AND (pr.valid_until IS NULL OR pr.valid_until >= CURRENT_DATE)
                                        ORDER BY pr.valid_from DESC, pr.id DESC
                                        LIMIT 1
                                ) AS valid_from,
                                (
                                        SELECT pr.valid_until
                                        FROM prices pr
                                        WHERE pr.article_id = a.id
                                            AND pr.valid_from <= CURRENT_DATE
                                            AND (pr.valid_until IS NULL OR pr.valid_until >= CURRENT_DATE)
                                        ORDER BY pr.valid_from DESC, pr.id DESC
                                        LIMIT 1
                                ) AS valid_until
                        FROM articles a
                SQL;

        $hasQuery = $query !== null && $query !== '';
        if ($hasQuery) {
            $sql .= " WHERE article_number LIKE :term_num OR name LIKE :term_name OR CONCAT(article_number, ' - ', name) LIKE :term_combo";
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
}
