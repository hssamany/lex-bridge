<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

final class Pagination
{
    public const DEFAULT_PAGE_SIZE = 10;
    public const PAGE_SIZES = [10, 25, 50, 100];

    /**
     * @param array<string, mixed> $pagination
     * @return array{page:int,page_size:int,offset:int,limit:int}
     */
    public static function normalize(array $pagination = []): array
    {
        $page = isset($pagination['page']) ? (int) $pagination['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }

        $pageSize = isset($pagination['page_size']) ? (int) $pagination['page_size'] : self::DEFAULT_PAGE_SIZE;
        if (!in_array($pageSize, self::PAGE_SIZES, true)) {
            $pageSize = self::DEFAULT_PAGE_SIZE;
        }

        $offset = ($page - 1) * $pageSize;

        return [
            'page' => $page,
            'page_size' => $pageSize,
            'offset' => $offset,
            'limit' => $pageSize,
        ];
    }

    public static function totalPages(int $totalCount, int $pageSize): int
    {
        if ($pageSize <= 0) {
            return 1;
        }

        return max(1, (int) ceil($totalCount / $pageSize));
    }
}
