<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

/**
 * Domain constants for order processing business rules.
 */
final class OrderDomainConstants
{
    /**
     * ISO weekday names mapped to day offsets used when expanding a calendar week.
     * Maps to Monday (0) through Friday (4).
     */
    public const WEEKDAY_OFFSETS = [
        'Mo' => 0,
        'Di' => 1,
        'Mi' => 2,
        'Do' => 3,
        'Fr' => 4,
    ];

    /**
     * Minimum quantity threshold for line item inclusion.
     * Quantities below this are considered negligible and filtered out.
     */
    public const MIN_QUANTITY_THRESHOLD = 0.0001;

    private function __construct()
    {
        // Prevent instantiation
    }
}
