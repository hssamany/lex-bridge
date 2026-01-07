<?php
declare(strict_types=1);

class LineItemService
{
    private LineItemRepository $repository;

    public function __construct(LineItemRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getLineItems(array $filters = []): array
    {
        $items = $this->repository->findLineItems($filters);

        return [
            'lineItems' => array_map(function (array $item) {
                return [
                    'customer_id' => isset($item['customer_id']) ? (int)$item['customer_id'] : null,
                    'customer_number' => isset($item['customer_number']) ? (string)$item['customer_number'] : null,
                    'customer_name' => $item['company_name'] ?? null,
                    'invoice_id' => (string)($item['invoice_id'] ?? ''),
                    'line_order' => isset($item['line_order']) ? (int)$item['line_order'] : null,
                    'name' => $item['name'] ?? '',
                    'quantity' => isset($item['quantity']) ? (float)$item['quantity'] : null,
                    'net_amount' => isset($item['net_amount']) ? (float)$item['net_amount'] : null,
                    'tax_rate_percentage' => isset($item['tax_rate_percentage']) ? (float)$item['tax_rate_percentage'] : null,
                    'line_total_net' => isset($item['line_total_net']) ? (float)$item['line_total_net'] : null,
                    'line_total_gross' => isset($item['line_total_gross']) ? (float)$item['line_total_gross'] : null,
                    'created_at' => $item['created_at'] ?? null,
                    'voucher_date' => $item['voucher_date'] ?? null,
                ];
            }, $items),
            'isSuccess' => true
        ];
    }
}
