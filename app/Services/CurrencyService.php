<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Facades\Http;

class CurrencyService
{
    private const ECB_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

    public function fetchEcbRates(): array
    {
        $response = Http::timeout(10)->get(self::ECB_URL);

        if (!$response->successful()) {
            return [];
        }

        $xml = simplexml_load_string($response->body());
        if ($xml === false) {
            return [];
        }

        $rates = ['EUR' => 1.0];
        $ns = $xml->getNamespaces(true);
        $cubeRoot = $xml->children($ns['gesmes'] ?? '');

        foreach ($xml->Cube->Cube->Cube as $rate) {
            $code = (string) $rate['currency'];
            $value = (float) $rate['rate'];
            $rates[$code] = $value;
        }

        return $rates;
    }

    public function updateRates(): array
    {
        $ecbRates = $this->fetchEcbRates();

        if (empty($ecbRates)) {
            return ['success' => false, 'error' => 'Could not fetch ECB rates', 'updated' => 0];
        }

        $defaultCurrency = Currency::where('is_default', true)->first();
        if (!$defaultCurrency) {
            return ['success' => false, 'error' => 'No default currency set', 'updated' => 0];
        }

        $baseCode = $defaultCurrency->code;
        $baseEcbRate = $ecbRates[$baseCode] ?? null;

        if ($baseEcbRate === null) {
            return ['success' => false, 'error' => "Default currency {$baseCode} not found in ECB data", 'updated' => 0];
        }

        $updated = 0;
        $currencies = Currency::where('is_default', false)->where('is_enabled', true)->get();

        foreach ($currencies as $currency) {
            $ecbRate = $ecbRates[$currency->code] ?? null;
            if ($ecbRate !== null) {
                $relativeRate = $ecbRate / $baseEcbRate;
                $currency->update(['rate' => $relativeRate]);
                $updated++;
            }
        }

        return ['success' => true, 'updated' => $updated, 'error' => null];
    }
}
