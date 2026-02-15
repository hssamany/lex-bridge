<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use DateTimeImmutable;
use Luxullus\LexBridge\Logger;


/**
 * Builds invoice line item payloads from order data and article pricing.
 * Handles payload construction and pricing detail enrichment.
 */
final class OrderLineItemBuilder
{
    private LineItemCalculator $calculator;

    public function __construct(?LineItemCalculator $calculator = null)
    {
        $this->calculator = $calculator ?? new LineItemCalculator();
    }

    /**
     * Build a complete line item payload from order data and pricing information.
     *
     * @param array<string, mixed> $orderRow Raw order data
     * @param array<string, mixed>|null $pricing Article and price information
     * @param DateTimeImmutable $deliveryDate Calculated delivery date
     * @param float $quantity Normalized quantity
     * @return array<string, mixed> Complete line item payload
     */
    public function buildLineItemPayload(
        array $orderRow,
        ?array $articlePrice,
        DateTimeImmutable $deliveryDate,
        float $quantity
        ): array {

        
        $payload = [
            'order_id' => (int) $orderRow['order_id'],
            'order_delivery_date' => $deliveryDate->format('Y-m-d'),
            'customer_id' => (int)($orderRow['customer_id']??0),
            'article_id' => (int) $orderRow['article_id'],
            'article_number' => $orderRow['article_number'] ?? null,
            'article_name' => $orderRow['article_name'] ?? null,
            'line_order' => $orderRow['line_order'] ?? null,
            'quantity' => $quantity,            
            'article_description' => $orderRow['article_description'] ?? null,

            // Pricing details from articlePrice (fallback if articlePrice is null)
            'unit_name' => $articlePrice['unit_name'] ?? null,
            'currency' => $articlePrice['currency'] ?? null,
            'net_amount' => $articlePrice['net_amount'] ?? null, // Unit price for net amount
            'gross_amount' => $articlePrice['gross_amount'] ?? null, // Unit price for gross amount
            'tax_rate_percentage' => $articlePrice['tax_rate_percentage'] ?? null,
            'article_valid_from' => $articlePrice['valid_from'] ?? null,
            'article_valid_until' => $articlePrice['valid_until'] ?? null,
            'line_total_net' => $this->calculator->calculateLineTotal($quantity, $articlePrice['net_amount'] ?? null),
            'line_total_gross' => $this->calculator->calculateLineTotal($quantity, $articlePrice['gross_amount'] ?? null),
        ];
        
        return $payload;
    }    
}
