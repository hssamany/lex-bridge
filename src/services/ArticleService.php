<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use Luxullus\LexBridge\Repositories\ArticleRepository;

final class ArticleService
{
    private ArticleRepository $repository;

    public function __construct(ArticleRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param string|null $query
     * @return array<string, mixed>
     */
    public function searchArticles(?string $query): array
    {
        $articles = $this->repository->searchArticles($query);
        $queryTrimmed = $query !== null ? trim($query) : null;

        $idMatch = null;
        if ($queryTrimmed !== null && preg_match('/^(\d+)\s*-\s*$/', $queryTrimmed, $matches)) {
            $idMatch = (int) $matches[1];
        }

        return [
            'articles' => array_map(static function (array $article) use ($idMatch): array {
                $id = isset($article['id']) ? (int) $article['id'] : null;

                return [
                    'id' => $id,
                    'article_number' => $article['article_number'] ?? '',
                    'name' => $article['name'] ?? '',
                    'net_price' => isset($article['net_price']) ? (float) $article['net_price'] : null,
                    'gross_price' => isset($article['gross_price']) ? (float) $article['gross_price'] : null,
                    'tax_rate' => isset($article['tax_rate']) ? (float) $article['tax_rate'] : null,
                    'isSelected' => $idMatch !== null && $idMatch === $id,
                ];
            }, $articles),
            'isSuccess' => true,
        ];
    }
}
