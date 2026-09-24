<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_decimal_arithmetic_preserves_kobo(): void
    {
        $this->assertSame(1001, Money::minor('10.01'));
        $this->assertSame(0.3, Money::major(Money::minor('0.1') + Money::minor('0.2')));
    }

    public function test_fractional_kobo_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::minor('1.001');
    }

    public function test_negative_amounts_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::minor('-1');
    }
}
