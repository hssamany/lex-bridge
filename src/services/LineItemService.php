<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use Luxullus\LexBridge\Repositories\LineItemRepository;
use Luxullus\LexBridge\Services\Pagination;

final class LineItemService
{
    private LineItemRepository $repository;

    public function __construct(LineItemRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getLineItems(array $filters = [], array $pagination = []): array
    {
        $paginationState = Pagination::normalize($pagination);
        $result = $this->repository->findLineItems($filters, $paginationState);
        $items = $result['items'] ?? [];
        $totalCount = (int) ($result['total_count'] ?? 0);

        return [
            'lineItems' => array_map(function (array $item) {
                return [
                    'id' => isset($item['id']) ? (string)$item['id'] : null,
                    'customer_id' => isset($item['customer_id']) ? (int)$item['customer_id'] : null,
                    'customer_number' => isset($item['customer_number']) ? (string)$item['customer_number'] : null,
                    'customer_name' => $item['company_name'] ?? null,
                    'invoice_id' => (string)($item['invoice_id'] ?? ''),
                    'order_id' => isset($item['order_id']) ? (int)$item['order_id'] : null,
                    'order_delivery_date' => $item['order_delivery_date'] ?? null,
                    'line_order' => isset($item['line_order']) ? (int)$item['line_order'] : null,
                    'name' => $item['name'] ?? '',
                    'quantity' => isset($item['quantity']) ? (float)$item['quantity'] : null,
                    'currency' => $item['currency'] ?? null,
                    'net_amount' => isset($item['net_amount']) ? (float)$item['net_amount'] : null,
                    'gross_amount' => isset($item['gross_amount']) ? (float)$item['gross_amount'] : null,
                    'tax_rate_percentage' => isset($item['tax_rate_percentage']) ? (float)$item['tax_rate_percentage'] : null,
                    'line_total_net' => isset($item['line_total_net']) ? (float)$item['line_total_net'] : null,
                    'line_total_gross' => isset($item['line_total_gross']) ? (float)$item['line_total_gross'] : null,
                    'article_id' => $item['article_id'] ?? null,
                    'article_number' => $item['article_number'] ?? null,
                    'article_label' => $item['article_label'] ?? null,
                    'article_valid_from' => $item['article_valid_from'] ?? null,
                    'article_valid_until' => $item['article_valid_until'] ?? null,
                    'created_at' => $item['created_at'] ?? null,
                    'updated_at' => $item['updated_at'] ?? null,
                    'voucher_date' => $item['voucher_date'] ?? null,
                ];
            }, $items),
            'isSuccess' => true,
            'total_count' => $totalCount,
            'page' => $paginationState['page'],
            'page_size' => $paginationState['page_size'],
            'total_pages' => Pagination::totalPages($totalCount, $paginationState['page_size']),
        ];
    }

    /**
     * Update a line item with validation and total calculation
     */
    public function updateLineItem(string $lineItemId, array $data): array
    {
        // Get current line item to retrieve quantity for calculation
        $currentItem = $this->repository->findLineItemById($lineItemId);
        if ($currentItem === null) {
            return [
                'isSuccess' => false,
                'error' => 'Line item not found',
            ];
        }

        // Sanitize input data
        $payload = [
            'article_id' => $this->sanitizeNullableString($data['article_id'] ?? null),
            'article_number' => $this->sanitizeNullableString($data['article_number'] ?? null),
            'article_label' => $this->sanitizeString($data['article_label'] ?? null),
            'article_name' => $this->sanitizeString($data['article_name'] ?? null),
            'currency' => $this->sanitizeCurrency($data['currency'] ?? null),
            'net_amount' => $this->sanitizeDecimal($data['net_amount'] ?? null),
            'gross_amount' => $this->sanitizeDecimal($data['gross_amount'] ?? null),
            'tax_rate_percentage' => $this->sanitizeDecimal($data['tax_rate_percentage'] ?? null),
            'article_valid_from' => $this->sanitizeDateTime($data['article_valid_from'] ?? null),
            'article_valid_until' => $this->sanitizeDateTime($data['article_valid_until'] ?? null),
        ];

        // Calculate totals if amounts and quantity are present
        $quantity = $currentItem['quantity'] ?? null;
        $payload['line_total_net'] = $this->calculateLineTotal($payload['net_amount'], $quantity, $currentItem['line_total_net'] ?? null);
        $payload['line_total_gross'] = $this->calculateLineTotal($payload['gross_amount'], $quantity, $currentItem['line_total_gross'] ?? null);

        $updated = $this->repository->updateLineItem($lineItemId, $payload);

        if (!$updated) {
            return [
                'isSuccess' => false,
                'error' => 'Line item update failed',
            ];
        }

        $lineItem = $this->repository->findLineItemById($lineItemId);

        return [
            'isSuccess' => true,
            'lineItem' => $lineItem,
        ];
    }

    /**
     * Calculate line total: amount * quantity
     * If amount or quantity is null, return the fallback value
     */
    private function calculateLineTotal(?float $amount, ?float $quantity, ?float $fallback): ?float
    {
        if ($amount === null || $quantity === null) {
            return $fallback;
        }

        return round($amount * $quantity, 2);
    }

    private function sanitizeString(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string)$value);
    }

    private function sanitizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string)$value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function sanitizeCurrency(?string $currency): ?string
    {
        if ($currency === null) {
            return null;
        }

        $trimmed = strtoupper(substr(trim($currency), 0, 3));
        return $trimmed === '' ? null : $trimmed;
    }

    private function sanitizeDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $normalized = str_replace([' ', ','], ['', '.'], $value);
            if (is_numeric($normalized)) {
                return round((float)$normalized, 2);
            }
            return null;
        }

        if (is_numeric($value)) {
            return round((float)$value, 2);
        }

        return null;
    }

    private function sanitizeDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = strtotime((string)$value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
