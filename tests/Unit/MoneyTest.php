<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public static function amounts(): array
    {
        return [
            'zero' => [0, '0.00'],
            'cents only' => [5, '0.05'],
            'whole' => [12500, '125.00'],
            'with cents' => [12550, '125.50'],
            'thousands' => [123456789, '1,234,567.89'],
            'negative' => [-250, '-2.50'],
            'assessment total' => [36300, '363.00'],
        ];
    }

    #[DataProvider('amounts')]
    public function test_formats_integer_cents(int $cents, string $expected): void
    {
        $this->assertSame($expected, Money::format($cents));
    }
}
