<?php

declare(strict_types=1);

namespace Tests\Services;

use Luxullus\LexBridge\Services\LineItemCalculator;
use PHPUnit\Framework\TestCase;

final class LineItemCalculatorTest extends TestCase
{
    private LineItemCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new LineItemCalculator();
    }

    public function testCalculateLineTotalWithDecimalString(): void
    {
        $total = $this->calculator->calculateLineTotal(2.3456, '4.7545');
        self::assertEquals(11.15, $total);
    }

    public function testCalculateLineTotalWithFloat(): void
    {
        $total = $this->calculator->calculateLineTotal(2.3456, 3.995);
        self::assertEquals(9.37, $total);
    }

    public function testCalculateLineTotalReturnsNullWhenAmountMissing(): void
    {
        self::assertNull($this->calculator->calculateLineTotal(5.0, null));
    }

    public function testCalculateLineTotalHandlesInvalidStringViaFallback(): void
    {
        $total = $this->calculator->calculateLineTotal(2.0, 'not-a-number');
        self::assertEquals(0.0, $total);
    }

    public function testNormalizeQuantityUsesFourDecimalPrecision(): void
    {
        $normalized = $this->calculator->normalizeQuantity(2.34567);
        self::assertEqualsWithDelta(2.3457, $normalized, 0.00001);

        $normalizedDown = $this->calculator->normalizeQuantity(2.34564);
        self::assertEqualsWithDelta(2.3456, $normalizedDown, 0.00001);
    }
}
