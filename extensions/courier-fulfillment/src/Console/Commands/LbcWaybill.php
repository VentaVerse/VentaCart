<?php

namespace Extensions\CourierFulfillment\Console\Commands;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcClient;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcException;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcParcel;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcPickupRequestBuilder;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcPickupService;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcWaybillBuilder;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Extensions\CourierFulfillment\Models\TrackingNumber;
use Illuminate\Console\Command;

/**
 * Renders the LBC Air Waybill PDF for a tracking number already spent on an
 * order. The waybill endpoint renders anything it is sent, so the label is
 * rebuilt from the same inputs the pickup request used; pass the pickup's
 * transaction and preferred dates so the facts match. Books nothing.
 */
class LbcWaybill extends Command
{
    protected $signature = 'courier:lbc-waybill
        {order : The order id}
        {tracking : The tracking number the pickup request used}
        {--output= : Where to write the PDF}
        {--transaction-date= : The pickup request TransactionDate, MM/DD/YYYY HH:MM:SS Asia/Manila}
        {--pickup-address= : Pickup address id (default: the default pickup address)}
        {--pkg=16} {--weight=1} {--length=20} {--width=15} {--height=10} {--declared-value=500}
        {--mode=REGULAR}';

    protected $description = 'Render the LBC Air Waybill PDF for a booked tracking number.';

    public function handle(LbcPickupService $service, LbcClient $client): int
    {
        $order = Order::with('products')->find((int) $this->argument('order'));
        $tracking = (string) $this->argument('tracking');
        $output = (string) $this->option('output');

        if ($order === null || !ctype_digit($tracking) || $output === '') {
            $this->error('An existing order, a tracking number and --output are required.');

            return self::FAILURE;
        }

        // A label only for a number this store actually spent on this order.
        $spent = TrackingNumber::query()
            ->where('number', (int) $tracking)
            ->where('order_id', $order->id)
            ->where('status', TrackingNumber::SPENT)
            ->exists();
        if (!$spent) {
            $this->error("Tracking number {$tracking} was not spent on order {$order->id}.");

            return self::FAILURE;
        }

        $from = $this->option('pickup-address') !== null
            ? PickupAddress::find((int) $this->option('pickup-address'))
            : PickupAddress::defaultAddress();
        if ($from === null) {
            $this->error('No pickup address found.');

            return self::FAILURE;
        }

        try {
            $transactionAt = $this->option('transaction-date')
                ? CarbonImmutable::createFromFormat(LbcPickupRequestBuilder::DATE_FORMAT, (string) $this->option('transaction-date'), LbcPickupRequestBuilder::TIMEZONE)
                : null;

            $parcel = new LbcParcel(
                pkg: (int) $this->option('pkg'),
                weightKg: (float) $this->option('weight'),
                lengthCm: (float) $this->option('length'),
                widthCm: (float) $this->option('width'),
                heightCm: (float) $this->option('height'),
                declaredValue: (float) $this->option('declared-value'),
            );

            $draft = $service->prepare($order, $from, $parcel, LbcPickupService::businessDaysAhead(1), strtoupper((string) $this->option('mode')), $transactionAt ?: null);
            $fields = $service->fieldsFor($draft, (int) $tracking);
            $pdf = $client->generateWaybill((new LbcWaybillBuilder())->label($fields, $fields['Shipper'], (string) $order->courier_comment));
        } catch (LbcException|\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($pdf === null) {
            $this->error('LBC returned no PDF.');

            return self::FAILURE;
        }

        file_put_contents($output, $pdf);
        $this->info('Waybill written to ' . $output . ' (' . strlen($pdf) . ' bytes).');

        return self::SUCCESS;
    }
}
