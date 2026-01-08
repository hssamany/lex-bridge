<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Controllers;

use Luxullus\LexBridge\Services\ArticleService;

final class ArticleController
{
    private ArticleService $service;

    public function __construct(ArticleService $service)
    {
        $this->service = $service;
    }

    public function searchArticles(?string $query): array
    {
        return $this->service->searchArticles($query);
    }

    public function syncArticles(?int $page = null): array
    {
        return $this->service->syncArticlesFromLexware($page);
    }
}
