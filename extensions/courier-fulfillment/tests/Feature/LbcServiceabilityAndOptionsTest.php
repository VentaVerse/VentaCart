<?php

namespace Tests\Feature\CourierFulfillment;

require_once __DIR__ . '/LbcBookingFixtures.php';

use App\Models\Setting;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcFulfillment;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcPackaging;
use Extensions\CourierFulfillment\Support\ServiceabilityVerdict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * LBC's answer to "will you carry this?" before anything is booked, the
 * rate it quotes as an estimate, and the booking options it offers.
 */
class LbcServiceabilityAndOptionsTest extends TestCase
{
    use LbcBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLbcBooking();
        $this->signIn();
    }

    public function test_a_served_barangay_is_serviceable_and_asked_by_its_psgc_code(): void
    {
        $this->fakeLbc();

        $verdict = (new LbcFulfillment())->checkServiceability($this->bookable());

        $this->assertSame(ServiceabilityVerdict::SERVICEABLE, $verdict->status);
        $validate = Http::recorded(fn (Request $r) => str_contains($r->url(), 'validateServicableArea'))->values();
        $this->assertSame(['psgc_code' => 1380300020], $validate[0][0]->data());
    }

    public function test_an_unserved_barangay_is_refused_in_the_modal_with_a_clear_reason(): void
    {
        $this->fakeLbc(deliveryStatus: 'Not Serviceable');
        $order = $this->bookable();

        $this->getJson($this->queueUrl('/' . $order->id . '/serviceability'))
            ->assertOk()
            ->assertJsonPath('couriers.lbc.status', 'not_serviceable')
            ->assertJsonPath('couriers.lbc.kind', 'destination')
            ->assertJsonPath('couriers.lbc.reason', 'Not Serviceable');
    }

    public function test_an_address_lbc_cannot_find_or_a_silent_lbc_is_not_a_refusal(): void
    {
        $this->fakeLbc();
        $lost = (new LbcFulfillment())->checkServiceability($this->bookable(['shipping_barangay' => 'Nowhere']));
        $this->assertSame(ServiceabilityVerdict::UNKNOWN, $lost->status);
        $this->assertStringContainsString('found no barangay matching "Nowhere"', (string) $lost->reason);
    }

    public function test_an_lbc_that_does_not_answer_is_could_not_check(): void
    {
        Http::fake(['*' => fn () => throw new ConnectionException('timed out')]);

        $silent = (new LbcFulfillment())->checkServiceability($this->bookable());
        $this->assertSame(ServiceabilityVerdict::UNKNOWN, $silent->status);
        $this->assertSame(ServiceabilityVerdict::KIND_UNREACHABLE, $silent->kind);
    }

    public function test_the_address_search_is_remembered_but_coverage_is_asked_every_time(): void
    {
        $this->fakeLbc();
        $lbc = new LbcFulfillment();
        $lbc->checkServiceability($this->bookable());
        $lbc->checkServiceability($this->bookable());

        $this->assertCount(1, Http::recorded(fn (Request $r) => str_contains($r->url(), 'cities/search')));
        $this->assertCount(2, Http::recorded(fn (Request $r) => str_contains($r->url(), 'validateServicableArea')));
    }

    public function test_the_rate_is_an_estimate_for_the_admin_only(): void
    {
        $this->fakeLbc();
        $order = $this->bookable();

        $this->postJson($this->queueUrl('/' . $order->id . '/estimate'), ['courier' => 'lbc'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('label', '₱250.00 (LBC estimate)');

        $this->assertNull((new LbcFulfillment())->estimateFee($this->bookable(['shipping_barangay' => 'Nowhere']), []));
    }

    public function test_the_parcel_list_is_lbcs_table_with_codes(): void
    {
        $options = (new LbcFulfillment())->getParcelOptions();
        $codes = array_column($options, 'value');

        $this->assertSame('16', $codes[0], 'The store\'s own box comes first.');
        $this->assertSame(['16', '165', '6', '164', '63', '7', '64', '45', '8', '62', '3', '61', '9', '2', '4', '79', '80'], $codes);
        $this->assertSame('Kilobox Slim', $options[3]['label']);
        foreach ($codes as $code) {
            $this->assertTrue(LbcPackaging::exists((int) $code));
        }
        $this->assertSame(['CARGO', 'KILOBOX MINI'], [LbcPackaging::productLine(165), LbcPackaging::productName(165)]);
        $this->assertSame(['DOCUMENTS', 'X POUCH'], [LbcPackaging::productLine(9), LbcPackaging::productName(9)]);
    }

    public function test_service_and_payment_lists_are_never_empty_and_drop_off_is_disabled(): void
    {
        $lbc = new LbcFulfillment();

        $this->assertSame([
            ['value' => 'REGULAR', 'label' => 'Rider pickup'],
            ['value' => 'DROPOFF', 'label' => 'Drop off at an LBC branch', 'disabled' => true],
        ], $lbc->getServiceTypes());
        $this->assertSame([['value' => 'account', 'label' => 'Charged to my LBC account']], $lbc->getShippingPaymentOptions());
    }

    public function test_declared_value_rules(): void
    {
        $order = $this->bookable(['total' => 320]);

        $this->assertSame(0.0, LbcFulfillment::declaredValueFor($order, false, false));
        $this->assertSame(500.0, LbcFulfillment::declaredValueFor($order, true, false));
        $this->assertSame(500.0, LbcFulfillment::declaredValueFor($order, false, true), 'COD is insured even when not ticked.');
        $this->assertSame(1999.5, LbcFulfillment::declaredValueFor($this->bookable(['total' => 1999.5]), false, true));
        $this->assertSame(320.0, LbcFulfillment::codAmountFor($order, true));
        $this->assertSame(0.0, LbcFulfillment::codAmountFor($order, false));
    }

    public function test_the_buyer_payment_follows_the_store_mapping_unless_the_caller_says(): void
    {
        Setting::updateOrCreate(['group' => 'courier', 'key' => 'cod_payment_methods'], ['value' => json_encode(['cod'])]);
        $cod = $this->bookable(['payment_method' => 'cod']);
        $prepaid = $this->bookable();

        $this->assertTrue(LbcFulfillment::isCod($cod, []));
        $this->assertFalse(LbcFulfillment::isCod($prepaid, []));
        $this->assertTrue(LbcFulfillment::isCod($prepaid, ['buyer_payment' => 'cod']));
    }

    public function test_missing_settings_keep_lbc_off_and_are_named(): void
    {
        Setting::where('group', 'courier')->where('key', 'lbc_shipper_account_no')->delete();
        Setting::where('group', 'courier')->where('key', 'lbc_tracking_series_end')->update(['value' => '37686XXX']);

        $lbc = new LbcFulfillment();
        $this->assertFalse($lbc->isEnabled());
        $this->assertSame(['Shipper Account No', LbcFulfillment::NO_TRACKING_SERIES], $lbc->configurationIssues());

        $order = $this->bookable();
        $this->assertFalse($lbc->createShipment($order, $this->bookingForm())['success']);
        Http::assertNothingSent();
    }
}
