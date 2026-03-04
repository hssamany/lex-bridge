<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Controllers;

use Luxullus\LexBridge\Services\OrderService;

final class OrderController
{
    private OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getOrders(array $filters, array $pagination = []): array
    {
        
        return $this->orderService->getOrders($filters, $pagination);
    }

    public function generateLineItemsForOrder(int $orderId): array
    {
        return $this->orderService->generateLineItemsFromOrder($orderId);
    }

    /**
     * @param array<int, mixed> $orderIds
     * @return array<string, mixed>
     */
    public function generateLineItemsForOrders(array $orderIds): array
    {
        return $this->orderService->generateLineItemsFromOrders($orderIds);
    }

    /**
     * @param array<int, mixed> $orderIds
     * @return array<string, mixed>
     */
    public function generateInvoicesFromOrders(array $orderIds): array
    {
        return $this->orderService->generateInvoicesFromOrders($orderIds);
    }
}
