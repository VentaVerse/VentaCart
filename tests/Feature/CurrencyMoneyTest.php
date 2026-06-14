<?php

namespace Tests\Feature;

use App\Models\Currency;
use Tests\TestCase;

/**
 * Unit coverage for the money() display helper. Active-currency resolution from
 * the cookie is verified end-to-end via the running app (curl), since the full
 * test suite can't boot on sqlite (unrelated PH-decouple migrations).
 */
class CurrencyMoneyTest extends TestCase
{
    public function test_converts_by_rate_and_prefixes_symbol(): void
    {
        $usd = new Currency(['symbol' => '$', 'rate' => 0.5]);

        $this->assertSame('$500.00', money(1000, $usd));
        $this->assertSame('$1.65', money(3.30, $usd));
    }

    public function test_base_currency_is_unchanged(): void
    {
        $php = new Currency(['symbol' => '₱', 'rate' => 1]);

        $this->assertSame('₱1,000.00', money(1000, $php));
    }

    public function test_thousands_are_grouped(): void
    {
        $usd = new Currency(['symbol' => '$', 'rate' => 2]);

        $this->assertSame('$2,469.13', money(1234.567, $usd));
    }
}
