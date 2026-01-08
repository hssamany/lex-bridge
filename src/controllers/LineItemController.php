<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Controllers;
use Luxullus\LexBridge\Services\LineItemService;


final class LineItemController
{
    private LineItemService $service;

    public function __construct(LineItemService $service)
    {
        $this->service = $service;
    }

    public function getLineItems(array $filters = []): array
    {
        return $this->service->getLineItems($filters);
    }

    public function updateLineItem(array $payload): array
    {
        $lineItemId = isset($payload['line_item_id']) ? trim((string)$payload['line_item_id']) : '';
        if ($lineItemId === '') {
            return [
                'isSuccess' => false,
                'error' => 'line_item_id is required',
            ];
        }

        return $this->service->updateLineItem($lineItemId, $payload);
    }
}
