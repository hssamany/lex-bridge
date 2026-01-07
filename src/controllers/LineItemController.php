<?php
declare(strict_types=1);

class LineItemController
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
}
