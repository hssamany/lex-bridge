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

        try {
            $results = $this->repository->generateInvoiceLineItemsFromOrders([
                'order_id' => $orderId,
            ]);
        } catch (Throwable $exception) {
            return [
                'isSuccess' => false,
                'error' => 'Line item generation failed: ' . $exception->getMessage(),
            ];
        }

        $lineItems = [];
        foreach ($results as $customerItems) {
            if (!is_array($customerItems)) {
                continue;
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
            'order_year' => isset($order['order_year']) ? (int) $order['order_year'] : null,
            'order_week' => isset($order['order_week']) ? (int) $order['order_week'] : null,
            'article_id' => isset($order['article_id']) && $order['article_id'] !== null ? (int) $order['article_id'] : null,
            'article_number' => $order['article_number'] ?? null,
            'geaendert_am' => $order['GeaendertAm'] ?? null,
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
