<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Controllers;

use Luxullus\LexBridge\Services\ArticleService;
use Luxullus\LexBridge\Logger;

final class ArticleController
{
    private ArticleService $articleService;

    public function __construct(ArticleService $articleService)
    {
        $this->articleService = $articleService;
    }

    public function searchArticles(?string $query): array
    {
        Logger::info("AAAAAAAAAA: " . ($query ?? 'null'));   
        return $this->articleService->searchArticles($query);
    }

    public function syncArticles(?int $page = null): array
    {
        return $this->articleService->syncArticlesFromLexware($page);
    }
}
