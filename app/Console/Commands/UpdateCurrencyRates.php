<?php

namespace App\Console\Commands;

use App\Services\CurrencyService;
use Illuminate\Console\Command;

class UpdateCurrencyRates extends Command
{
    protected $signature = 'currency:update';
    protected $description = 'Fetch latest exchange rates from ECB and update currency rates';

    public function handle(CurrencyService $service): int
    {
        $this->info('Fetching ECB exchange rates...');

        $result = $service->updateRates();

        if ($result['success']) {
            $this->info("Updated {$result['updated']} currency rate(s).");
            return self::SUCCESS;
        }

        $this->error($result['error']);
        return self::FAILURE;
    }
}
