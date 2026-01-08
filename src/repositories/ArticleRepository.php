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
        $params = [];

        if ($query !== null && $query !== '') {
            $sql .= ' WHERE article_number LIKE :term OR name LIKE :term OR CONCAT(article_number, " - ", name) LIKE :term';
            $params[':term'] = '%' . $query . '%';
        }

        $sql .= ' ORDER BY name ASC LIMIT 20';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
