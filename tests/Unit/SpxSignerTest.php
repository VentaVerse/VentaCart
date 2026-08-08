<?php

namespace Tests\Unit;

use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Couriers\Spx\SpxClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task T1 (fulfillment-workflow-v2 + SPX plan): THE POINT of this task —
 * SpxClient::sign() proven byte-exact against the VERIFIED test vector from
 * docs/spx/integration-brief.md §2. None of these values are real
 * credentials — they're the doc's own published test vector, reproduced
 * verbatim (the credential rule explicitly carves this out: "except the
 * doc test vector's own inputs").
 *
 * Extends the Laravel TestCase (not a plain PHPUnit one) purely so the
 * extension's runtime PSR-4 autoloader is registered before
 * Extensions\CourierFulfillment\* is referenced — sign() itself touches
 * neither the database nor any facade. install() alone only upserts the
 * extension's registry row; it's bootEnabled() that actually calls
 * registerAutoloader() (see ExtensionManager::bootEnabled()), so both are
 * needed here for this file to pass standalone (`--filter=Spx`), not just
 * as part of the full Unit suite where an earlier test happens to leak the
 * process-global autoloader registration. Deliberately NOT applied to the
 * pre-existing QuadXStatusPhaseTest, which is out of this fix's scope.
 */
class SpxSignerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
    }

    public function test_sign_matches_the_verified_integration_brief_test_vector(): void
    {
        $appId = 100000;
        $timestamp = 1677918414;
        $randomNum = 926611981;
        $payload = '{"user_id":239404781503925,"user_secret":"85f0d570-a265-4d9d-857e-b30aa57c4fbe","service_type":1}';
        $appSecret = 'H25HY53GO4BA2GQ';

        $expected = '97e21b23940e4ddc96fb4d2474f02425353d2b0e23aee384f3f94c3d0b9ba17d';

        $this->assertSame($expected, SpxClient::sign($appId, $timestamp, $randomNum, $payload, $appSecret));
    }

    public function test_sign_is_sensitive_to_every_component_of_the_original_value(): void
    {
        $appId = 100000;
        $timestamp = 1677918414;
        $randomNum = 926611981;
        $payload = '{"user_id":239404781503925,"user_secret":"85f0d570-a265-4d9d-857e-b30aa57c4fbe","service_type":1}';
        $appSecret = 'H25HY53GO4BA2GQ';
        $baseline = SpxClient::sign($appId, $timestamp, $randomNum, $payload, $appSecret);

        $this->assertNotSame($baseline, SpxClient::sign($appId + 1, $timestamp, $randomNum, $payload, $appSecret));
        $this->assertNotSame($baseline, SpxClient::sign($appId, $timestamp + 1, $randomNum, $payload, $appSecret));
        $this->assertNotSame($baseline, SpxClient::sign($appId, $timestamp, $randomNum + 1, $payload, $appSecret));
        $this->assertNotSame($baseline, SpxClient::sign($appId, $timestamp, $randomNum, $payload . ' ', $appSecret));
        $this->assertNotSame($baseline, SpxClient::sign($appId, $timestamp, $randomNum, $payload, 'a-different-secret'));
    }
}
