<?php

namespace Extensions\CourierFulfillment\Console\Commands;

use Extensions\CourierFulfillment\Couriers\Lbc\LbcClient;
use Extensions\CourierFulfillment\Couriers\Lbc\LbcException;
use Illuminate\Console\Command;

/**
 * Read-only Track and Trace lookup for one LBC tracking number. A pickup
 * request creates no tracking event; LBC answers "No Record Found" until the
 * parcel is first scanned.
 */
class LbcTrack extends Command
{
    protected $signature = 'courier:lbc-track {tracking : The LBC tracking number} {--raw : Print the raw SOAP response}';

    protected $description = 'Look up one LBC tracking number (read-only).';

    public function handle(LbcClient $client): int
    {
        $tracking = (string) $this->argument('tracking');
        if (!ctype_digit($tracking)) {
            $this->error('A tracking number is digits only.');

            return self::FAILURE;
        }

        try {
            $result = $client->track($tracking);
        } catch (LbcException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('StatusCode: ' . ($result['status_code'] ?? '(none)'));
        $this->line('StatusMessage: ' . ($result['status_message'] ?? '(none)'));
        foreach ($result['history'] as $event) {
            $this->line(" - {$event['date']} [{$event['status_id']}] {$event['status']}");
        }

        if ($this->option('raw')) {
            $this->line($result['raw']);
        }

        return $result['status_code'] !== null ? self::SUCCESS : self::FAILURE;
    }
}
