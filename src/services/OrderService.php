<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use InvalidArgumentException;
use Throwable;
use Luxullus\LexBridge\Repositories\OrderRepository;

final class OrderService
{
    private OrderRepository $repository;

    public function __construct(OrderRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getOrders(array $filters): array
    {
        try {
            $orders = $this->repository->getOrders($filters);
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

        return [
            'isSuccess' => true,
            'orders' => array_map([$this, 'mapOrderRow'], $orders),
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
                'isSuccess' => false,
                'error' => 'At least one valid order_id is required.',
            ];
        }

        try {
            $results = $this->repository->generateInvoiceLineItemsFromOrders([
                'order_ids' => $normalized,
            ]);
        } catch (Throwable $exception) {
            return [
                'isSuccess' => false,
                'error' => 'Positionsgenerierung fehlgeschlagen: ' . $exception->getMessage(),
            ];
        }

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
            'ordersProcessed' => $normalized,
            'customers' => array_values(array_unique($customers)),
            'lineItems' => $lineItems,
        ];
    }

    /**
     * Generate line items from orders and immediately create invoices per customer
     * @param array<int, mixed> $orderIds
     * @return array<string, mixed>
     */
    public function generateInvoicesFromOrders(array $orderIds): array
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
                'isSuccess' => false,
                'error' => 'At least one valid order_id is required.',
            ];
        }

        try {
            $result = $this->repository->generateInvoicesFromOrders([
                'order_ids' => $normalized,
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
