<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use DateTimeImmutable;

/**
 * Handles date calculations for order processing,
 * particularly ISO week to specific delivery date conversions.
 */
final class OrderDateCalculator
{
    /**
     * Calculate specific delivery dates from an ISO week and year.
     * 
     * @param int $year ISO year
     * @param int $week ISO week number (1-53)
     * @param array<string, float> $weekdayQuantities Map of weekday codes (Mo, Di, etc.) to quantities
     * @param DateTimeImmutable|null $deliveryFrom Optional start date filter
     * @param DateTimeImmutable|null $deliveryTo Optional end date filter
     * @return array<string, array{date: DateTimeImmutable, quantity: float}> Map of weekday code to date and quantity
     * @throws \Exception If invalid ISO date
     */
    public function calculateDeliveryDatesForWeek(
        int $year,
        int $week,
        array $weekdayQuantities,
        ?DateTimeImmutable $deliveryFrom = null,
        ?DateTimeImmutable $deliveryTo = null
    ): array {
        if ($year <= 0 || $week <= 0) {
            return [];
        }

        // Monday start for the ISO week
        $weekStart = (new DateTimeImmutable())->setISODate($year, $week, 1);

        $deliveryDates = [];

        foreach (OrderDomainConstants::WEEKDAY_OFFSETS as $weekdayCode => $dayOffset) {
            // Skip if no quantity for this day
            if (!isset($weekdayQuantities[$weekdayCode])) {
                continue;
            }

            // Calculate the specific delivery date
            $deliveryDate = $weekStart->modify('+' . $dayOffset . ' day');

            // Apply date range filters
            if ($deliveryFrom !== null && $deliveryDate < $deliveryFrom) {
                continue;
            }

            if ($deliveryTo !== null && $deliveryDate > $deliveryTo) {
                continue;
            }

            $deliveryDates[$weekdayCode] = [
                'date' => $deliveryDate,
                'quantity' => $weekdayQuantities[$weekdayCode],
            ];
        }

        return $deliveryDates;
    }

    /**
     * Extract weekday quantities from an order row.
     * 
     * @param array<string, mixed> $orderRow
     * @return array<string, float> Map of weekday code to quantity
     */
    public function extractWeekdayQuantities(array $orderRow): array
    {
        $quantities = [];

        foreach (OrderDomainConstants::WEEKDAY_OFFSETS as $weekdayCode => $_offset) {
            $value = $orderRow[$weekdayCode] ?? null;
            
            if ($value !== null) {
                $quantities[$weekdayCode] = (float) $value;
            }
        }

        return $quantities;
    }
}
