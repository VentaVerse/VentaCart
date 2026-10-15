<?php

namespace Tests\Feature\Fulfillment;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Extensions\CourierFulfillment\PlacementMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * The booking form posts "" for a choice nobody has made yet.
 *
 * Laravel's ConvertEmptyStringsToNull turns that "" into null BEFORE
 * validation, so a rule of `sometimes|string` sees a value that is present
 * and not a string, and refuses the whole request. That refusal has now been
 * shipped twice: once for pickup_slot, and again on 2026-09-03 when the
 * three choices started opening on "Choose one" with an empty value.
 *
 * Both times the tests passed, because every one of them posted these keys
 * either with a real value or not at all. Never as "". That is the gap this
 * file exists to close, so the shape the BROWSER actually sends is the shape
 * under test.
 */
class EmptyChoiceSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('courier-fulfillment');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());

        foreach ([
            'quadx_enabled' => '1',
            'quadx_mode' => 'staging',
            'quadx_staging_api_key' => Crypt::encryptString('k'),
            'quadx_staging_secret_key' => Crypt::encryptString('s'),
        ] as $k => $v) {
            Setting::create(['group' => 'courier', 'key' => $k, 'value' => $v]);
        }

        $toPack = OrderStatus::create(['store_id' => 1, 'name' => 'Processing', 'sort_order' => 1]);
        PlacementMap::put($toPack->id, 'to_pack');

        $this->order = Order::create([
            'store_id' => 1, 'first_name' => 'A', 'last_name' => 'Buyer',
            'email' => 'buyer@example.test', 'order_status_id' => $toPack->id,
        ]);
    }

    /**
     * The estimate runs while the operator is still choosing, so an
     * unanswered choice must not be an error. It is a quote we cannot give
     * yet.
     */
    public function test_the_estimate_survives_choices_that_are_still_empty(): void
    {
        $response = $this->postJson(
            route('admin.fulfillment.estimate', $this->order),
            ['courier' => 'quadx', 'service' => '', 'parcel' => '', 'shipping_payment' => '']
        );

        $response->assertOk();
        $response->assertJsonMissingValidationErrors(['service', 'parcel', 'shipping_payment']);
        // It may well answer "no estimate yet"; what it must never do is
        // refuse the request for a field the operator has not reached.
        $this->assertArrayHasKey('ok', $response->json());
    }

    /** Booking without a choice is a real error, and it must say which one. */
    public function test_booking_with_empty_choices_names_the_missing_one(): void
    {
        $response = $this->post(
            route('admin.fulfillment.book', $this->order),
            ['courier' => 'quadx', 'service' => '', 'parcel' => '', 'shipping_payment' => '']
        );

        $error = session('error');

        $this->assertNotNull($error, 'A refused booking must reach the toast channel this page reads.');
        $this->assertStringNotContainsString('failed to pass validation', $error,
            'The generic Laravel message names nothing the operator can act on.');
        $this->assertStringContainsString('schedule', strtolower($error));
        $this->assertStringContainsString('parcel', strtolower($error));
    }
}
