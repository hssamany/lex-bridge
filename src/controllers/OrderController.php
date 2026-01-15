<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Controllers;

use Luxullus\LexBridge\Services\OrderService;

final class OrderController
{
    private OrderService $service;

    public function __construct(OrderService $service)
    {
        $this->service = $service;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getOrders(array $filters): array
    {
        return $this->service->getOrders($filters);
    }

    public function generateLineItemsForOrder(int $orderId): array
    {
        return $this->service->generateLineItemsFromOrder($orderId);
    }

    /**
     * @param array<int, mixed> $orderIds
     * @return array<string, mixed>
     */
    public function generateLineItemsForOrders(array $orderIds): array
    {
        return $this->service->generateLineItemsFromOrders($orderIds);
    }
}
