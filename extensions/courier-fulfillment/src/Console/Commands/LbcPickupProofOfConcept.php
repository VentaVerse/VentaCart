<?php

namespace Extensions\CourierFulfillment\Console\Commands;

use App\Models\Order;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcArea;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcException;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcParcel;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcPickupDraft;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcPickupRequestBuilder;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcPickupResult;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcPickupService;
use Extensions\CourierFulfillment\FulfillmentService;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Extensions\CourierFulfillment\TrackingNumbers\TrackingNumberSeriesException;
use Illuminate\Console\Command;

/**
 * Builds an LBC pickup request for one order and, only with --confirm,
 * sends it once.
 *
 * Without --confirm this is a dry run: it calls LBC's read-only address
 * search and serviceability check, prints the request with the shipper
 * account number and phone numbers masked, and names the tracking number it
 * would use without taking it.
 *
 * Every LBC request is live and LBC has no cancel endpoint, so a confirmed
 * run sends exactly one request and never retries. Cash on delivery is not
 * covered yet, so a COD order is refused.
 */
class LbcPickupProofOfConcept extends Command
{
    protected $signature = 'courier:lbc-poc
        {order : The order id}
        {--pickup-address= : Pickup address id (default: the default pickup address)}
        {--pkg=16 : LBC packaging code (16 is BOX)}
        {--weight=1 : Actual weight in kg}
        {--length=20 : Length in cm}
        {--width=15 : Width in cm}
        {--height=10 : Height in cm}
        {--declared-value=500 : Declared value, at least 500}
        {--mode=REGULAR : REGULAR (a rider collects) or "DROP OFF" (the parcel is brought to a branch)}
        {--business-days=3 : Preferred date, in business days from today}
        {--preferred-time=10:00:00 : Preferred time on that date, Asia/Manila}
        {--output= : Also write the masked request XML to this file}
        {--confirm : Take the next tracking number and send the request, once}';

    protected $description = 'Build an LBC pickup request for an order (dry run), or send it once with --confirm.';

    public function handle(LbcPickupService $service): int
    {
        $order = Order::with('products')->find((int) $this->argument('order'));
        if ($order === null) {
            $this->error('No such order.');

            return self::FAILURE;
        }

        if (FulfillmentService::isCodOrder($order)) {
            $this->error('Cash on delivery orders are not covered by this command yet.');

            return self::FAILURE;
        }

        $from = $this->option('pickup-address') !== null
            ? PickupAddress::find((int) $this->option('pickup-address'))
            : PickupAddress::defaultAddress();
        if ($from === null) {
            $this->error('No pickup address found.');

            return self::FAILURE;
        }

        $mode = strtoupper(trim((string) $this->option('mode')));
        if ($mode === 'DROPOFF' || $mode === 'DROP-OFF') {
            $mode = LbcPickupRequestBuilder::MODE_DROP_OFF;
        }

        try {
            $parcel = new LbcParcel(
                pkg: (int) $this->option('pkg'),
                weightKg: (float) $this->option('weight'),
                lengthCm: (float) $this->option('length'),
                widthCm: (float) $this->option('width'),
                heightCm: (float) $this->option('height'),
                declaredValue: (float) $this->option('declared-value'),
            );

            $preferredAt = LbcPickupService::businessDaysAhead(
                max(1, (int) $this->option('business-days')),
                (string) $this->option('preferred-time'),
            );

            $draft = $service->prepare($order, $from, $parcel, $preferredAt, $mode);
        } catch (LbcException|TrackingNumberSeriesException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->reportAreas($draft);

        return $this->option('confirm') ? $this->send($service, $draft) : $this->dryRun($service, $draft);
    }

    private function dryRun(LbcPickupService $service, LbcPickupDraft $draft): int
    {
        try {
            $preview = $service->preview($draft);
        } catch (LbcException|TrackingNumberSeriesException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $xml = (new LbcPickupRequestBuilder())->toXml(LbcPickupRequestBuilder::mask($preview['fields']));

        $this->line('');
        $this->line('DRY RUN. Nothing was sent and no tracking number was taken.');
        $this->line("Tracking number it would use: {$preview['issued']->number} ({$preview['issued']->remaining} more left in the series after it)");
        $this->line('');
        $this->line($xml);

        $this->writeOutput($xml);

        if ($draft->blockers !== []) {
            $this->warn('A confirmed run would refuse:');
            foreach ($draft->blockers as $blocker) {
                $this->warn(' - ' . $blocker);
            }

            return self::FAILURE;
        }

        $this->info('Nothing blocks this request. Run again with --confirm to send it once.');

        return self::SUCCESS;
    }

    private function send(LbcPickupService $service, LbcPickupDraft $draft): int
    {
        try {
            $sent = $service->send($draft);
        } catch (LbcException|TrackingNumberSeriesException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        /** @var LbcPickupResult $result */
        $result = $sent['result'];
        $number = $sent['issued']->number;

        $this->writeOutput((new LbcPickupRequestBuilder())->toXml(LbcPickupRequestBuilder::mask($sent['fields'])));

        $this->line('');
        $this->line("Tracking number: {$number}");
        $this->line('HTTP status: ' . $result->httpStatus);
        $this->line('IsSuccess: ' . var_export($result->isSuccess, true));
        $this->line('Code: ' . ($result->code() ?? '(none)'));
        $this->line('Message: ' . $result->summary());
        $this->line('Echoed TrackingNo: ' . ($result->trackingNo() ?? '(none)'));
        $this->line('Raw response:');
        $this->line($result->rawBody);

        if ($sent['issued']->nearEnd) {
            $this->warn("Only {$sent['issued']->remaining} tracking numbers are left in the series. Request a new block from LBC.");
        }

        return match ($result->outcome) {
            LbcPickupResult::ACCEPTED => $this->done("LBC saved the request. Tracking number {$number} is spent."),
            LbcPickupResult::REJECTED => $this->failed("LBC rejected the request. Tracking number {$number} was released and is the next one issued."),
            default => $this->failed("The outcome is unknown. Tracking number {$number} is spent; check with LBC before sending again."),
        };
    }

    private function reportAreas(LbcPickupDraft $draft): void
    {
        foreach (['Pickup' => $draft->origin, 'Delivery' => $draft->destination] as $label => $area) {
            /** @var LbcArea $area */
            $this->line(sprintf(
                '%s: %s | PSGC %s | %s | category %s | area %s (from %s)',
                $label,
                $area->fullLocationName ?: trim("{$area->inputBarangay}, {$area->inputCity}, {$area->inputProvince}", ', '),
                $area->psgcCode ?? '-',
                $area->status ?? 'not checked',
                $area->category ?? '-',
                $area->areaCode ?? '-',
                $area->areaSource ?? '-',
            ));
        }
    }

    private function writeOutput(string $xml): void
    {
        $path = $this->option('output');
        if (is_string($path) && $path !== '') {
            file_put_contents($path, $xml);
            $this->line("Masked request written to {$path}");
        }
    }

    private function done(string $message): int
    {
        $this->info($message);

        return self::SUCCESS;
    }

    private function failed(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
