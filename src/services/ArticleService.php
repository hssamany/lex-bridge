<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use Luxullus\LexBridge\Repositories\ArticleRepository;
use Luxullus\LexBridge\Http\HttpClient;
use Luxullus\LexBridge\Logger;
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
     * Search articles with enriched price information.
     *
     * @param string|null $query
     * @return array<string, mixed>
     */
    public function searchArticles(?string $query): array
    {
        $normalizedQuery = $this->normalizeSearchQuery($query);
        
        if ($normalizedQuery === null || $normalizedQuery === '') {
            return [];
        }

        // Build filter array for text search
        $filter = [
            'name' => $normalizedQuery,
            'article_number' => $normalizedQuery,
        ];
        $articles = $this->repository->searchArticles($filter);

        return $this->enrichArticleSearchResults($articles);
    }

    /**
     * Synchronize articles from Lexware API to local database.
     *
     * @param int|null $page Specific page to sync (null = all pages)
     * @return array{isSuccess:bool,fetched:int,created:int,updated:int,unchanged:int,price_updates:int,errors:array<string>}
     */
    public function syncArticlesFromLexware(?int $page = null): array
    {
        $summary = $this->initializeSyncSummary();
        $nextPage = $page ?? 0;

        while (true) {
            $response = $this->fetchLexwareArticlePage($nextPage);

            if (!$response['isSuccess']) {
                $summary['isSuccess'] = false;
                $summary['errors'][] = $response['error'];
                break;
            }

            $this->processFetchedArticles($response['articles'], $summary);

            if ($page !== null || $response['isLastPage']) {
                break;
            }

            if (!$this->shouldFetchNextPage($response, $nextPage)) {
                break;
            }

            $nextPage = $response['currentPage'] + 1;
        }

        return $this->finalizeSyncSummary($summary);
    }

    /**
     * Normalize search query input.
     */
    private function normalizeSearchQuery(?string $query): ?string
    {
        if ($query === null) {
            return null;
        }

        $trimmed = trim($query);
        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Enrich article search results with properly formatted data.
     *
     * @param array<int, array<string, mixed>> $articles
     * @return array<int, array<string, mixed>>
     */
    private function enrichArticleSearchResults(array $articles): array
    {
        return array_map(function (array $article): array {
            return [
                'id' => isset($article['id']) ? (int) $article['id'] : null,
                'article_number' => $article['article_number'] ?? '',
                'name' => $article['name'] ?? '',
                'net_amount' => $this->parseMoneyValue($article['net_amount'] ?? null),
                'gross_amount' => $this->parseMoneyValue($article['gross_amount'] ?? null),
                'tax_rate_percentage' => $this->parseTaxValue($article['tax_rate_percentage'] ?? null),
                'currency' => $this->normalizeCurrency($article['currency'] ?? null),
                'valid_from' => $article['valid_from'] ?? null,
                'valid_until' => $article['valid_until'] ?? null,
            ];
        }, $articles);
    }

    /**
     * Initialize sync summary structure.
     *
     * @return array{isSuccess:bool,fetched:int,created:int,updated:int,unchanged:int,price_updates:int,errors:array<string>}
     */
    private function initializeSyncSummary(): array
    {
        return [
            'isSuccess' => true,
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'price_updates' => 0,
            'errors' => []
        ];
    }

    /**
     * Fetch a single page of articles from Lexware API.
     *
     * @return array{isSuccess:bool,articles:array<int, array<string, mixed>>,isLastPage:bool,currentPage:int,totalPages:int|null,error:string|null}
     */
    private function fetchLexwareArticlePage(int $page): array
    {
        $response = $this->client->get('/articles?page=' . $page);

        if (!$response->isSuccess()) {
            return [
                'isSuccess' => false,
                'articles' => [],
                'isLastPage' => true,
                'currentPage' => $page,
                'totalPages' => null,
                'error' => $response->getMessage() ?? 'Lexware request failed'
            ];
        }

        $payload = $response->toArray();
        $content = $this->extractArticlesFromPayload($payload);

        return [
            'isSuccess' => true,
            'articles' => $content,
            'isLastPage' => (bool) ($payload['last'] ?? true),
            'currentPage' => isset($payload['number']) ? (int) $payload['number'] : $page,
            'totalPages' => isset($payload['totalPages']) ? (int) $payload['totalPages'] : null,
            'error' => null
        ];
    }

    /**
     * Extract articles array from API response payload.
     *
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractArticlesFromPayload(array $payload): array
    {
        if (!isset($payload['content']) || !is_array($payload['content'])) {
            return [];
        }

        return $payload['content'];
    }

    /**
     * Process fetched articles and update sync summary.
     *
     * @param array<int, array<string, mixed>> $articles
     * @param array<string, mixed> &$summary
     */
    private function processFetchedArticles(array $articles, array &$summary): void
    {
        foreach ($articles as $item) {
            $summary['fetched']++;
            
            $validatedData = $this->validateAndTransformLexwareArticle($item);

            if ($validatedData === null) {
                $lexwareId = isset($item['id']) ? (string) $item['id'] : '(unknown)';
                $summary['errors'][] = 'Skipped article ' . $lexwareId . ' due to missing required fields';
                continue;
            }

            $this->persistArticleWithPricing($validatedData, $summary);
        }
    }

    /**
     * Validate and transform Lexware API article data.
     *
     * @param array<string, mixed> $item
     * @return array{article:array<string, mixed>,price:array<string, mixed>}|null
     */
    private function validateAndTransformLexwareArticle(array $item): ?array
    {
        $lexwareId = $this->extractString($item, 'id');
        $articleNumber = $this->extractString($item, 'articleNumber');
        $title = $this->extractString($item, 'title');
        
        if ($lexwareId === null || $articleNumber === null || $title === null) {
            return null;
        }

        $price = $item['price'] ?? null;
        if (!is_array($price)) {
            return null;
        }

        $priceData = $this->validateAndTransformPriceData($price);
        if ($priceData === null) {
            return null;
        }

        $unit = $this->extractString($item, 'unitName') ?? 'piece';
        $description = $this->extractString($item, 'description');

        return [
            'article' => [
                'lexware_article_id' => $lexwareId,
                'article_number' => $articleNumber,
                'name' => $title,
                'description' => $description,
                'unit_name' => $unit,
            ],
            'price' => $priceData,
        ];
    }

    /**
     * Validate and transform price data from Lexware API.
     *
     * @param array<string, mixed> $price
     * @return array{net_amount:string,gross_amount:string,tax_rate:string,currency:string}|null
     */
    private function validateAndTransformPriceData(array $price): ?array
    {
        $net = $price['netPrice'] ?? null;
        $gross = $price['grossPrice'] ?? null;
        $tax = $price['taxRate'] ?? null;

        if ($net === null || $gross === null || $tax === null) {
            return null;
        }

        $currency = isset($price['currency']) && is_string($price['currency']) && $price['currency'] !== ''
            ? $price['currency']
            : 'EUR';

        return [
            'net_amount' => $this->formatMoney((float) $net),
            'gross_amount' => $this->formatMoney((float) $gross),
            'tax_rate' => $this->formatTax((float) $tax),
            'currency' => $this->normalizeCurrency($currency),
        ];
    }

    /**
     * Persist article with pricing information and update sync summary.
     *
     * @param array{article:array<string, mixed>,price:array<string, mixed>} $data
     * @param array<string, mixed> &$summary
     */
    private function persistArticleWithPricing(array $data, array &$summary): void
    {
        try {
            $result = $this->repository->upsertArticle(
                $data['article'],
                $data['price']
            );

            $this->updateSyncSummaryFromResult($result, $summary);
        } catch (Throwable $exception) {
            $summary['errors'][] = 'Persisting article ' . $data['article']['lexware_article_id'] 
                . ' failed: ' . $exception->getMessage();
            
            Logger::exception($exception, 'ArticleService - Persist Article Failed');
        }
    }

    /**
     * Update sync summary based on repository result.
     *
     * @param array{created:bool,article_changed:bool,price_changed:bool} $result
     * @param array<string, mixed> &$summary
     */
    private function updateSyncSummaryFromResult(array $result, array &$summary): void
    {
        if ($result['created']) {
            $summary['created']++;
        } elseif ($result['article_changed']) {
            $summary['updated']++;
        } else {
            $summary['unchanged']++;
        }

        if ($result['price_changed']) {
            $summary['price_updates']++;
        }
    }

    /**
     * Determine if next page should be fetched.
     *
     * @param array<string, mixed> $response
     */
    private function shouldFetchNextPage(array $response, int $currentPage): bool
    {
        if ($response['isLastPage']) {
            return false;
        }

        $totalPages = $response['totalPages'];
        if ($totalPages !== null && ($currentPage + 1) >= $totalPages) {
            return false;
        }

        return true;
    }

    /**
     * Finalize sync summary with error deduplication and final success status.
     *
     * @param array<string, mixed> $summary
     * @return array{isSuccess:bool,fetched:int,created:int,updated:int,unchanged:int,price_updates:int,errors:array<string>}
     */
    private function finalizeSyncSummary(array $summary): array
    {
        if (!empty($summary['errors'])) {
            $summary['errors'] = array_values(array_unique($summary['errors']));
            $summary['isSuccess'] = false;
        }

        Logger::info(
            'Article sync completed: ' . json_encode([
                'fetched' => $summary['fetched'],
                'created' => $summary['created'],
                'updated' => $summary['updated'],
                'unchanged' => $summary['unchanged'],
                'price_updates' => $summary['price_updates'],
                'error_count' => count($summary['errors'])
            ]),
            'ArticleService'
        );

        return $summary;
    }

    /**
     * Extract and trim string value from array.
     */
    private function extractString(array $data, string $key): ?string
    {
        if (!isset($data[$key])) {
            return null;
        }

        $value = trim((string) $data[$key]);
        return $value !== '' ? $value : null;
    }

    /**
     * Parse and format money value.
     */
    private function parseMoneyValue(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Parse and format tax rate value.
     */
    private function parseTaxValue(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Format money value to 2 decimal places.
     */
    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * Format tax rate to 2 decimal places.
     */
    private function formatTax(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * Normalize currency code to uppercase.
     */
    private function normalizeCurrency(?string $currency): string
    {
        if ($currency === null || $currency === '') {
            return 'EUR';
        }

        return strtoupper(trim($currency));
    }

}
