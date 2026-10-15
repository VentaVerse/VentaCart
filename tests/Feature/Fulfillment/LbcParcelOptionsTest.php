<?php

namespace Tests\Feature\Fulfillment;

use App\Models\Store;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcFulfillment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The packaging LBC will accept, named the way the packer says it.
 *
 * Values are PKG codes from Table 2.0 of LBC_Pickup_Request_API_V2
 * 2026-02-04.pdf and go into the payload verbatim, so a typo here books the
 * wrong product silently: LBC validates that the code exists, not that it is
 * the one meant.
 */
class LbcParcelOptionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The extension registers its own PSR-4 autoloader only when it boots, so
     * the courier class does not exist until then.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('courier-fulfillment');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
    }

    public function test_the_list_is_the_products_this_store_ships(): void
    {
        $options = (new LbcFulfillment())->getParcelOptions();

        $this->assertSame([
            '45' => 'N-Pack Small',
            '8' => 'N-Pack Large',
            '165' => 'KB Mini',
            '6' => 'KB Small',
            '164' => 'KB Slim',
            '63' => 'KB Medium',
            '7' => 'KB Large',
            '64' => 'KB XL',
            '16' => 'Own packaging',
        ], array_column($options, 'label', 'value'));
    }

    /** LBC's catalogue also carries packaging this store does not send. */
    public function test_packaging_the_store_does_not_ship_is_not_offered(): void
    {
        $codes = array_column((new LbcFulfillment())->getParcelOptions(), 'value');

        // PLASTIC 2, CRATE 4, PIECE 79, SACK 80, and the document-only pouches.
        foreach (['2', '4', '79', '80', '3', '9', '61', '62'] as $unshipped) {
            $this->assertNotContains($unshipped, $codes, "PKG {$unshipped} should not be offered.");
        }
    }

    /** Own packaging is the one with no standard box behind it. */
    public function test_own_packaging_is_the_code_that_needs_typed_dimensions(): void
    {
        $this->assertSame('16', LbcFulfillment::OWN_PACKAGING_PKG);
        $this->assertContains(
            LbcFulfillment::OWN_PACKAGING_PKG,
            array_column((new LbcFulfillment())->getParcelOptions(), 'value')
        );
    }
}
