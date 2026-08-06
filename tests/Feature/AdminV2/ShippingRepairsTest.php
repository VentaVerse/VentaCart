<?php

namespace Tests\Feature\AdminV2;

use App\Services\Shipping\DhlShippingProvider;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ShippingRepairsTest extends TestCase
{
    public function test_dhl_provider_key_matches_its_quote_key_prefix(): void
    {
        $provider = new DhlShippingProvider();
        // Quote keys are "dhl_{service}"; CheckoutService matches getKey().'_' prefix.
        $this->assertSame('dhl', $provider->getKey());
        $this->assertTrue(str_starts_with('dhl_p', $provider->getKey() . '_'));
    }

    public function test_shipping_zones_routes_are_gone(): void
    {
        $this->assertFalse(Route::has('admin.shipping-zones.index'));
        $this->assertFalse(Route::has('admin.shipping-zones.edit'));
    }
}
