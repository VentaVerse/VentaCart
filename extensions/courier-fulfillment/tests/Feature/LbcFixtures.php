<?php

namespace Tests\Feature\CourierFulfillment;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Setting;
use App\Models\Store;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Shared setup for the LBC tests: the extension installed, neutral LBC
 * settings, a pickup address, an order, and faked LBC answers. No test may
 * reach LBC, so stray requests are refused.
 */
trait LbcFixtures
{
    protected const SERIES_FIRST = 10000000000001;

    protected const SERIES_LAST = 10000000000010;

    protected const ACCOUNT_NO = '1000000000000001';

    protected function setUpLbc(): void
    {
        Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();

        Http::preventStrayRequests();

        foreach ([
            'lbc_mode' => 'test',
            'lbc_client_code' => 'DEMO',
            'lbc_shipper_account_no' => self::ACCOUNT_NO,
            'lbc_tracking_series_start' => (string) self::SERIES_FIRST,
            'lbc_tracking_series_end' => (string) self::SERIES_LAST,
            'lbc_test_common_key' => Crypt::encryptString('common-key'),
            'lbc_test_airwaybill_key' => Crypt::encryptString('waybill-key'),
            'lbc_test_pickup_key' => Crypt::encryptString('pickup-key'),
            'lbc_test_track_key' => Crypt::encryptString('track-key'),
        ] as $key => $value) {
            Setting::updateOrCreate(['group' => 'courier', 'key' => $key], ['value' => $value]);
        }
    }

    protected function pickupAddress(): PickupAddress
    {
        return PickupAddress::create([
            'label' => 'Demo warehouse',
            'contact_name' => 'Demo Store',
            'contact_phone' => '09170000001',
            'address_1' => '1 Sample Street',
            'barangay' => 'Kapitolyo',
            'city' => 'Pasig City',
            'state' => 'Metro Manila',
            'postcode' => '1603',
            'country_id' => $this->philippinesId(),
            'is_default' => true,
        ]);
    }

    protected function order(array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'store_id' => Store::query()->value('id'),
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'phone' => '09170000000',
            'shipping_first_name' => 'Juan',
            'shipping_last_name' => 'Dela Cruz',
            'shipping_address_1' => '2 Sample Avenue',
            'shipping_barangay' => 'Poblacion',
            'shipping_city' => 'Makati City',
            'shipping_state' => 'Metro Manila',
            'shipping_postcode' => '1210',
            'shipping_country_id' => $this->philippinesId(),
            'shipping_phone' => '09170000000',
            'payment_method' => 'bank_transfer',
            'payment_method_title' => 'Bank Transfer',
            'subtotal' => 500,
            'shipping_cost' => 0,
            'total' => 500,
        ], $overrides));

        OrderProduct::create([
            'order_id' => $order->id,
            'name' => 'Demo Item',
            'sku' => 'DEMO-001',
            'price' => 500,
            'quantity' => 1,
            'total' => 500,
        ]);

        return $order;
    }

    /** LBC's address search and serviceability answers, faked. */
    protected function fakeLookups(string $deliveryStatus = 'Serviceable', ?string $category = 'METRO MANILA AREA', array $pickup = []): void
    {
        Http::fake(array_merge([
            '*/camsbackend/cities/search*' => function ($request) {
                $term = strtoupper((string) ($request->data()['searchTerm'] ?? ''));

                return Http::response(match (true) {
                    str_contains($term, 'PASIG') => [['cityId' => 1381200000, 'cityName' => 'CITY OF PASIG', 'provinceId' => 1300000000, 'provinceName' => 'NATIONAL CAPITAL REGION NCR']],
                    str_contains($term, 'MAKATI') => [['cityId' => 1380300000, 'cityName' => 'CITY OF MAKATI', 'provinceId' => 1300000000, 'provinceName' => 'NATIONAL CAPITAL REGION NCR']],
                    default => [],
                });
            },
            '*/camsbackend/barangays/search*' => function ($request) {
                $city = (int) ($request->data()['cityId'] ?? 0);

                return Http::response(match ($city) {
                    1381200000 => [['barangayId' => 1381200009, 'barangayName' => 'KAPITOLYO', 'cityId' => 1381200000, 'cityName' => 'CITY OF PASIG', 'provinceName' => 'NATIONAL CAPITAL REGION NCR', 'fullLocationName' => 'NATIONAL CAPITAL REGION NCR, CITY OF PASIG, KAPITOLYO']],
                    1380300000 => [['barangayId' => 1380300020, 'barangayName' => 'POBLACION', 'cityId' => 1380300000, 'cityName' => 'CITY OF MAKATI', 'provinceName' => 'NATIONAL CAPITAL REGION NCR', 'fullLocationName' => 'NATIONAL CAPITAL REGION NCR, CITY OF MAKATI, POBLACION']],
                    default => [],
                });
            },
            '*/camsbackend/validate/validateServicableArea' => function ($request) use ($deliveryStatus, $category) {
                $psgc = (string) ($request->data()['psgc_code'] ?? '');
                $status = $psgc === '1380300020' ? $deliveryStatus : 'Serviceable';
                $serviceable = $status === 'Serviceable';

                return Http::response([
                    'status' => $status,
                    'psgc_code' => $serviceable ? $psgc : null,
                    'rate_details' => ['rate' => $serviceable ? 250 : 0, 'category' => $serviceable ? $category : null, 'remarks' => null],
                ]);
            },
        ], $pickup));
    }

    protected static function pickupAccepted(string $trackingNo): string
    {
        return '<?xml version="1.0" encoding="utf-8"?><ReturnEntity xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<IsSuccess>true</IsSuccess><MessageList><ResponseMessageEntity><Code>100</Code>'
            . '<Message>Successfully saved. 11/05/2026 9:28:50 AM</Message><TrackingNo>' . $trackingNo . '</TrackingNo>'
            . '</ResponseMessageEntity></MessageList></ReturnEntity>';
    }

    protected static function pickupRejected(string $code, string $message): string
    {
        return '<?xml version="1.0" encoding="utf-8"?><ReturnEntity>'
            . '<IsSuccess>false</IsSuccess><MessageList><ResponseMessageEntity><Code>' . $code . '</Code>'
            . '<Message>' . $message . '</Message><TrackingNo></TrackingNo>'
            . '</ResponseMessageEntity></MessageList></ReturnEntity>';
    }

    private function philippinesId(): int
    {
        $id = DB::table('countries')->where('iso2', 'PH')->value('id');

        return (int) ($id ?? DB::table('countries')->insertGetId([
            'iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'phone_code' => '63', 'is_active' => 1, 'sort_order' => 0,
        ]));
    }
}
