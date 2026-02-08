<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use InvalidArgumentException;
use Throwable;
use Luxullus\LexBridge\Repositories\OrderRepository;
use Luxullus\LexBridge\Services\Pagination;

final class OrderService
{
    private OrderRepository $repository;

    public function __construct(OrderRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $pagination
     * @return array<string, mixed>
     */
    public function getOrders(array $filters, array $pagination = []): array
    {
        $paginationState = Pagination::normalize($pagination);

        try {
            $result = $this->repository->getOrders($filters, $paginationState);
        } catch (InvalidArgumentException $exception) {
            return [
                'isSuccess' => false,
                'error' => $exception->getMessage(),
            ];
        } catch (Throwable $exception) {
            return [
                'isSuccess' => false,
                'error' => 'Order lookup failed: ' . $exception->getMessage(),
            ];
        }

        $orders = $result['items'] ?? [];
        $totalCount = (int) ($result['total_count'] ?? 0);

        return [
            'isSuccess' => true,
            'orders' => array_map([$this, 'mapOrderRow'], $orders),
            'total_count' => $totalCount,
            'page' => $paginationState['page'],
            'page_size' => $paginationState['page_size'],
            'total_pages' => Pagination::totalPages($totalCount, $paginationState['page_size']),
        ];
    }

    public function generateLineItemsFromOrder(int $orderId): array
    {
        if ($orderId <= 0) {
            return [
                'isSuccess' => false,
                'error' => 'order_id must be a positive integer.',
            ];
        }

        return $this->generateLineItemsFromOrders([$orderId]);
    }

    /**
     * @param array<int, mixed> $orderIds
     */
    public function generateLineItemsFromOrders(array $orderIds): array
    {
        $normalized = $this->normalizeOrderIds($orderIds);

        if (!$normalized['isValid']) {
            return [
                'isSuccess' => false,
                'error' => $normalized['error'],
            ];
        }

        try {
            $results = $this->repository->generateInvoiceLineItemsFromOrders([
                'order_ids' => $normalized['orderIds'],
            ]);
        } catch (Throwable $exception) {
            return [
                'isSuccess' => false,
                'error' => 'Positionsgenerierung fehlgeschlagen: ' . $exception->getMessage(),
            ];
        }

        return $this->buildLineItemsResponse($results, $normalized['orderIds']);
    }

    /**
     * Generate line items from orders and immediately create invoices per customer
     * @param array<int, mixed> $orderIds
     * @return array<string, mixed>
     */
    public function generateInvoicesFromOrders(array $orderIds): array
    {
        $normalized = $this->normalizeOrderIds($orderIds);

        if (!$normalized['isValid']) {
            return [
                'isSuccess' => false,
                'error' => $normalized['error'],
            ];
        }

        try {
            $result = $this->repository->generateInvoicesFromOrders([
                'order_ids' => $normalized['orderIds'],
            ]);
        } catch (Throwable $exception) {
            return [
                'isSuccess' => false,
                'error' => 'Rechnungserstellung fehlgeschlagen: ' . $exception->getMessage(),
            ];
        }

        return array_merge(['isSuccess' => true], $result);
    }

    /**
     * Normalize and validate order IDs from mixed input.
     * 
     * @param array<int, mixed> $orderIds
     * @return array{isValid: bool, orderIds?: array<int>, error?: string}
     */
    private function normalizeOrderIds(array $orderIds): array
    {
        $normalized = [];

        foreach ($orderIds as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }

            $validated = filter_var($candidate, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($validated !== false && $validated !== null) {
                $normalized[] = (int) $validated;
            }
        }

        $normalized = array_values(array_unique($normalized));

        if (!$normalized) {
            return [
                'isValid' => false,
                'error' => 'At least one valid order_id is required.',
            ];
        }

        return [
            'isValid' => true,
            'orderIds' => $normalized,
        ];
    }

    /**
     * Build response structure from repository results.
     * 
     * @param array<int, array<int, array<string, mixed>>> $results
     * @param array<int> $processedOrderIds
     * @return array<string, mixed>
     */
    private function buildLineItemsResponse(array $results, array $processedOrderIds): array
    {
        $lineItems = [];
        $customers = [];

        foreach ($results as $customerId => $customerItems) {
            if (!is_array($customerItems)) {
                continue;
            }

            if ($customerId !== null && $customerId !== '') {
                $customers[] = (int) $customerId;
            }

            foreach ($customerItems as $item) {
                if (is_array($item)) {
                    $lineItems[] = $item;
                }
            }
        }

        return [
            'isSuccess' => true,
            'generatedCount' => count($lineItems),
            'ordersProcessed' => $processedOrderIds,
            'customers' => array_values(array_unique($customers)),
            'lineItems' => $lineItems,
        ];
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function mapOrderRow(array $order): array
    {
        $mapQuantity = static fn($value): ?float => $value === null ? null : (float) $value;

        return [
            'order_id' => isset($order['order_id']) ? (int) $order['order_id'] : null,
            'customer_id' => isset($order['customer_id']) ? (int) $order['customer_id'] : null,
            'customer_number' => $order['customer_number'] ?? null,
            'lex_customer_number' => $order['lex_customer_number'] ?? null,
            'order_year' => isset($order['order_year']) ? (int) $order['order_year'] : null,
            'order_week' => isset($order['order_week']) ? (int) $order['order_week'] : null,
            'article_id' => isset($order['article_id']) && $order['article_id'] !== null ? (int) $order['article_id'] : null,
            'article_number' => $order['article_number'] ?? null,
            'geaendert_am' => $order['GeaendertAm'] ?? null,
            'verarbeitet' => isset($order['verarbeitet']) ? (bool) (int) $order['verarbeitet'] : false,
            'quantities' => [
                'Mo' => $mapQuantity($order['Mo'] ?? null),
                'Di' => $mapQuantity($order['Di'] ?? null),
                'Mi' => $mapQuantity($order['Mi'] ?? null),
                'Do' => $mapQuantity($order['Do'] ?? null),
                'Fr' => $mapQuantity($order['Fr'] ?? null),
            ],
        ];
    }
}
