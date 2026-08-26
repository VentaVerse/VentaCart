<?php

namespace Tests\Feature\AdminV2;

use App\Models\Order;
use App\Models\Setting;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Couriers\QuadX\QuadXFulfillment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Default service: Same Day Pickup" must reach the booking payload.
 *
 * It did not. buildPayload() fell back to the literal 'next_day' and never
 * read quadx_default_service, while rateQuote() DID read it — so the operator
 * was quoted on their own choice and then booked on next_day regardless. The
 * booking form compounded it: its service select had no x-model, so the
 * browser submitted whichever option sat first in getServiceTypes(), which is
 * next_day.
 */
class QuadXDefaultServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The extension's PSR-4 autoloader only registers on bootEnabled(),
        // so its classes do not exist until the module is installed.
        $manager = app(ExtensionManager::class);
        $manager->install('courier-fulfillment');
        $manager->bootEnabled();
    }

    private function payloadFor(array $opts): array
    {
        $order = new Order(['store_id' => 1]);

        $quadx = new QuadXFulfillment();
        $method = new \ReflectionMethod($quadx, 'buildPayload');
        $method->setAccessible(true);

        return $method->invoke($quadx, $order, $opts);
    }

    public function test_the_configured_default_service_reaches_the_payload(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'quadx_default_service', 'value' => 'same_day_pickup']);

        $this->assertSame('same_day_pickup', $this->payloadFor([])['service']);
    }

    public function test_an_explicit_choice_still_wins_over_the_default(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'quadx_default_service', 'value' => 'same_day_pickup']);

        $this->assertSame('next_day', $this->payloadFor(['service' => 'next_day'])['service']);
    }

    public function test_next_day_remains_the_fallback_when_nothing_is_configured(): void
    {
        $this->assertSame('next_day', $this->payloadFor([])['service']);
    }

    public function test_the_configured_default_parcel_reaches_the_payload(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'quadx_default_parcel', 'value' => 'pouch']);

        $this->assertSame('pouch', $this->payloadFor([])['shipment']);
    }

    public function test_the_booking_form_models_the_service_select(): void
    {
        // Without x-model the control submits its first option no matter what
        // the state says, which is the half of this bug that lived in Blade.
        $view = file_get_contents(base_path('extensions/courier-fulfillment/resources/views/index.blade.php'));

        $this->assertStringContainsString('<select name="service" x-model="service"', $view);
    }
}
