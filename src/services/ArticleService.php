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

        return array_map(static function (array $article): array {
            return [
                'id' => isset($article['id']) ? (int) $article['id'] : null,
                'article_number' => $article['article_number'] ?? '',
                'name' => $article['name'] ?? '',
                'net_amount' => isset($article['net_amount']) ? (float) $article['net_amount'] : null,
                'gross_amount' => isset($article['gross_amount']) ? (float) $article['gross_amount'] : null,
                'tax_rate_percentage' => isset($article['tax_rate_percentage']) ? (float) $article['tax_rate_percentage'] : null,
                'currency' => $article['currency'] ?? null,
                'valid_from' => $article['valid_from'] ?? null,
                'valid_until' => $article['valid_until'] ?? null,
            ];
        }, $articles);
    }
}
