<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

final class LineItemCalculator
{
    public function calculateLineTotal(float $quantity, float|string|null $unitAmount): ?float
    {
        if ($unitAmount === null) {
            return null;
        }

        $quantityDecimal = $this->toDecimalString($quantity, 6);
        $amountDecimal = $this->toDecimalString($unitAmount, 6);

        if ($quantityDecimal === null || $amountDecimal === null) {
            return round($quantity * (float) $unitAmount, 2);
        }

        if (function_exists('bcmul')) {
            $product = bcmul($quantityDecimal, $amountDecimal, 6);
            return round((float) $product, 2);
        }

        return round((float) $quantityDecimal * (float) $amountDecimal, 2);
    }

    public function normalizeQuantity(float $quantity): float
    {
        $decimal = $this->toDecimalString($quantity, 4);

        if ($decimal !== null && function_exists('bcadd')) {
            return (float) bcadd($decimal, '0', 4);
        }

        return round($quantity, 4);
    }

    private function toDecimalString(float|string $value, int $scale): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            return $this->normalizeDecimalString($trimmed, $scale);
        }

        return number_format($value, $scale, '.', '');
    }

    private function normalizeDecimalString(string $value, int $scale): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        if (function_exists('bcadd')) {
            return bcadd($value, '0', $scale);
        }

        return number_format((float) $value, $scale, '.', '');
    }
}
