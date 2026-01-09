<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use Luxullus\LexBridge\Repositories\ArticleRepository;
use Luxullus\LexBridge\Http\HttpClient;
use Throwable;

final class ArticleService
{
    private ArticleRepository $repository;
    private HttpClient $client;

    public function __construct(ArticleRepository $repository, HttpClient $client)
    {
        $this->repository = $repository;
        $this->client = $client;
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

    public function syncArticlesFromLexware(?int $page = null): array
    {
        $summary = [
            'isSuccess' => true,
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'price_updates' => 0,
            'errors' => []
        ];

        $nextPage = $page ?? 0;
        $shouldContinue = true;

        while ($shouldContinue) {

            $response = $this->client->get('/articles?page=' . $nextPage);

            if (!$response->isSuccess()) {
                $summary['isSuccess'] = false;
                $summary['errors'][] = $response->getMessage() ?? 'Lexware request failed';
                break;
            }

            $payload = $response->toArray();
            
            $content = isset($payload['content']) && is_array($payload['content'])
                ? $payload['content']
                : [];

            foreach ($content as $item) {
                $summary['fetched']++;
                $lexwareId = isset($item['id']) ? (string) $item['id'] : '(unknown)';
                $mapped = $this->mapLexwareArticle($item);
                if ($mapped === null) {
                    $summary['isSuccess'] = false;
                    $summary['errors'][] = 'Skipped article ' . $lexwareId . ' due to missing required fields';
                    continue;
                }

                try {
                    $result = $this->repository->upsertLexwareArticle($mapped['article'], $mapped['price']);
                    if ($result['created'] ?? false) {
                        $summary['created']++;
                    } elseif ($result['article_changed'] ?? false) {
                        $summary['updated']++;
                    } else {
                        $summary['unchanged']++;
                    }

                    if ($result['price_changed'] ?? false) {
                        $summary['price_updates']++;
                    }
                } catch (Throwable $exception) {
                    $summary['isSuccess'] = false;
                    $summary['errors'][] = 'Persisting article ' . $mapped['article']['lexware_article_id'] . ' failed: ' . $exception->getMessage();
                }
            }

            if ($page !== null) {
                break;
            }

            $isLast = (bool) ($payload['last'] ?? true);
            if ($isLast) {
                break;
            }

            $currentPage = isset($payload['number']) ? (int) $payload['number'] : $nextPage;
            $nextPage = $currentPage + 1;
            $totalPages = isset($payload['totalPages']) ? (int) $payload['totalPages'] : null;
            if ($totalPages !== null && $nextPage >= $totalPages) {
                $shouldContinue = false;
            }
        }

        if (!empty($summary['errors'])) {
            $summary['errors'] = array_values(array_unique($summary['errors']));
        }

        $summary['isSuccess'] = $summary['isSuccess'] && empty($summary['errors']);

        return $summary;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, array<string, mixed>>|null
     */
    private function mapLexwareArticle(array $item): ?array
    {
        $lexwareId = isset($item['id']) ? trim((string) $item['id']) : '';
        $articleNumber = isset($item['articleNumber']) ? trim((string) $item['articleNumber']) : '';
        $title = isset($item['title']) ? trim((string) $item['title']) : '';
        $price = $item['price'] ?? null;

        if ($lexwareId === '' || $articleNumber === '' || $title === '' || !is_array($price)) {
            return null;
        }

        $net = $price['netPrice'] ?? null;
        $gross = $price['grossPrice'] ?? null;
        $tax = $price['taxRate'] ?? null;

        if ($net === null || $gross === null || $tax === null) {
            return null;
        }

        $unit = isset($item['unitName']) ? trim((string) $item['unitName']) : '';
        $description = isset($item['description']) ? trim((string) $item['description']) : null;

        return [
            'article' => [
                'lexware_article_id' => $lexwareId,
                'article_number' => $articleNumber,
                'name' => $title,
                'description' => $description !== '' ? $description : null,
                'unit_name' => $unit !== '' ? $unit : 'piece',
            ],
            'price' => [
                'net_amount' => (float) $net,
                'gross_amount' => (float) $gross,
                'tax_rate' => (float) $tax,
                'currency' => isset($price['currency']) && is_string($price['currency']) && $price['currency'] !== ''
                    ? strtoupper($price['currency'])
                    : 'EUR',
            ],
        ];
    }
}
