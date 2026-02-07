<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use DateTimeImmutable;

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
        ?array $pricing,
        DateTimeImmutable $deliveryDate,
        float $quantity
    ): array {
        $internalCustomerId = null;
        if (isset($orderRow['customer_internal_id']) && $orderRow['customer_internal_id'] !== null) {
            $internalCustomerId = (int) $orderRow['customer_internal_id'];
        } elseif (isset($orderRow['customer_id'])) {
            $internalCustomerId = (int) $orderRow['customer_id'];
        }

        $payload = [
            'order_id' => (int) $orderRow['order_id'],
            'order_delivery_date' => $deliveryDate->format('Y-m-d'),
            'customer_id' => $internalCustomerId,
            'customer_reference' => isset($orderRow['customer_id']) ? (int) $orderRow['customer_id'] : null,
            'article_id' => isset($orderRow['article_id']) && $orderRow['article_id'] !== null
                ? (int) $orderRow['article_id']
                : null,
            'article_number' => $orderRow['article_number'] ?? null,
            'quantity' => $quantity,
        ];

        return array_merge($payload, $this->buildArticlePricingDetails($pricing, $quantity));
    }

    /**
     * Build article and pricing details for a line item.
     *
     * @param array<string, mixed>|null $pricing Article and price data
     * @param float $quantity Line item quantity
     * @return array<string, mixed> Article and pricing details
     */
    public function buildArticlePricingDetails(?array $pricing, float $quantity): array
    {
        if ($pricing === null) {
            return [];
        }

        $details = [
            'article_name' => $pricing['article']['name'] ?? null,
            'article_description' => $pricing['article']['description'] ?? null,
            'unit_name' => $pricing['article']['unit_name'] ?? null,
        ];

        $priceData = $pricing['price'] ?? null;
        if ($priceData === null) {
            return $details;
        }

        $details['currency'] = $priceData['currency'] ?? null;
        $details['net_amount'] = $priceData['net_amount'] ?? null;
        $details['gross_amount'] = $priceData['gross_amount'] ?? null;
        $details['tax_rate_percentage'] = $priceData['tax_rate_percentage'] ?? null;
        $details['article_valid_from'] = $priceData['valid_from'] ?? null;
        $details['article_valid_until'] = $priceData['valid_until'] ?? null;
        $details['line_total_net'] = $this->calculator->calculateLineTotal($quantity, $priceData['net_amount'] ?? null);
        $details['line_total_gross'] = $this->calculator->calculateLineTotal($quantity, $priceData['gross_amount'] ?? null);

        return $details;
    }
}
