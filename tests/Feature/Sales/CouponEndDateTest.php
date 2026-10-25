<?php

namespace Tests\Feature\Sales;

use Extensions\Coupons\Models\Coupon;
use Extensions\Coupons\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A coupon's end date is a whole day: it redeems through the end of that
 * day, and the admin list does not call it expired until then.
 */
class CouponEndDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_coupon_ending_today_is_redeemable_and_not_expired_until_tomorrow(): void
    {
        $manager = app(\App\Services\ExtensionManager::class);
        $manager->install('coupons');
        $manager->bootEnabled();

        $coupon = Coupon::create([
            'code' => 'DEMO10', 'name' => 'Demo 10', 'type' => 'fixed', 'discount_value' => 10,
            'date_end' => '2026-10-31', 'is_active' => true,
        ]);

        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(23, 0));
        $this->assertFalse($coupon->fresh()->is_expired);
        $this->assertTrue(app(CouponService::class)->validate('DEMO10', null, collect(), 100)->success);

        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(0, 1));
        $this->assertTrue($coupon->fresh()->is_expired);
        $this->assertFalse(app(CouponService::class)->validate('DEMO10', null, collect(), 100)->success);
    }
}
