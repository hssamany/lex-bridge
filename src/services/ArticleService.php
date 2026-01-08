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
        $normalizedQuery = $query !== null ? trim($query) : null;
        $articles = $this->repository->searchArticles($normalizedQuery);

        return [
            'articles' => array_map(static function (array $article): array {
                return [
                    'id' => isset($article['id']) ? (int) $article['id'] : null,
                    'article_number' => $article['article_number'] ?? '',
                    'name' => $article['name'] ?? '',
                    'net_price' => isset($article['net_price']) ? (float) $article['net_price'] : null,
                    'gross_price' => isset($article['gross_price']) ? (float) $article['gross_price'] : null,
                    'tax_rate' => isset($article['tax_rate']) ? (float) $article['tax_rate'] : null,
                ];
            }, $articles),
            'isSuccess' => true,
        ];
    }
}
