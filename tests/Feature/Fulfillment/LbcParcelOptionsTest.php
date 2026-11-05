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

    public function test_the_list_is_lbcs_packaging_table(): void
    {
        $options = (new LbcFulfillment())->getParcelOptions();

        $this->assertSame([
            '16' => 'Own box',
            '165' => 'Kilobox Mini',
            '6' => 'Kilobox Small',
            '164' => 'Kilobox Slim',
            '63' => 'Kilobox Medium',
            '7' => 'Kilobox Large',
            '64' => 'Kilobox XL',
            '45' => 'N-Pack Small',
            '8' => 'N-Pack Large',
            '62' => 'N-Pouch SS',
            '3' => 'N-Pouch Regular',
            '61' => 'N-Pouch XL',
            '9' => 'X-Pouch',
            '2' => 'Plastic',
            '4' => 'Crate',
            '79' => 'Piece',
            '80' => 'Sack',
        ], array_column($options, 'label', 'value'));
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
