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
        $sql = 'SELECT id, article_number, name, net_price, gross_price, tax_rate FROM articles';

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
