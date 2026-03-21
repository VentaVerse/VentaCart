<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DhlExpressService
{
    private const ENDPOINT = 'https://xmlpi-ea.dhl.com/XMLShippingServlet';

    private ?string $siteId;
    private ?string $password;
    private ?string $accountNumber;

    public function __construct()
    {
        $credentials = Setting::where('group', 'shipping')
            ->whereIn('key', ['dhl_site_id', 'dhl_password', 'dhl_account_number'])
            ->pluck('value', 'key');

        $this->siteId = $this->decrypt($credentials->get('dhl_site_id'));
        $this->password = $this->decrypt($credentials->get('dhl_password'));
        $this->accountNumber = $credentials->get('dhl_account_number');
    }

    public function isConfigured(): bool
    {
        return !empty($this->siteId) && !empty($this->password) && !empty($this->accountNumber);
    }

    /**
     * Get rate quotes from DHL XML-PI.
     *
     * @param array $origin  ['country_code', 'city', 'postal_code']
     * @param array $destination ['country_code', 'city', 'postal_code']
     * @param array $pieces  [['weight', 'length', 'width', 'height'], ...]
     * @return array [{code, name, cost, currency, estimated_days, delivery_date}, ...]
     */
    public function getRateQuote(array $origin, array $destination, array $pieces): array
    {
        $piecesXml = '';
        foreach ($pieces as $i => $piece) {
            $id = $i + 1;
            $piecesXml .= '<Piece>'
                . '<PieceID>' . $id . '</PieceID>'
                . '<Height>' . ($piece['height'] ?? 10) . '</Height>'
                . '<Depth>' . ($piece['length'] ?? 10) . '</Depth>'
                . '<Width>' . ($piece['width'] ?? 10) . '</Width>'
                . '<Weight>' . ($piece['weight'] ?? 0.5) . '</Weight>'
                . '</Piece>';
        }

        $shipDate = now()->isWeekend() ? now()->next('Monday')->format('Y-m-d') : now()->format('Y-m-d');
        $messageTime = now()->toIso8601String();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<p:DCTRequest xmlns:p="http://www.dhl.com" xmlns:p1="http://www.dhl.com/datatypes" xmlns:p2="http://www.dhl.com/DCTRequestdatatypes" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.dhl.com DCT-req.xsd">'
            . '<GetQuote>'
            . '<Request>'
            . '<ServiceHeader>'
            . '<MessageTime>' . $messageTime . '</MessageTime>'
            . '<SiteID>' . htmlspecialchars($this->siteId) . '</SiteID>'
            . '<Password>' . htmlspecialchars($this->password) . '</Password>'
            . '</ServiceHeader>'
            . '</Request>'
            . '<From>'
            . '<CountryCode>' . htmlspecialchars($origin['country_code']) . '</CountryCode>'
            . '<Postalcode>' . htmlspecialchars($origin['postal_code'] ?? '') . '</Postalcode>'
            . '<City>' . htmlspecialchars($origin['city'] ?? '') . '</City>'
            . '</From>'
            . '<BkgDetails>'
            . '<PaymentCountryCode>' . htmlspecialchars($origin['country_code']) . '</PaymentCountryCode>'
            . '<Date>' . $shipDate . '</Date>'
            . '<ReadyTime>PT10H00M</ReadyTime>'
            . '<DimensionUnit>CM</DimensionUnit>'
            . '<WeightUnit>KG</WeightUnit>'
            . '<Pieces>' . $piecesXml . '</Pieces>'
            . '<PaymentAccountNumber>' . htmlspecialchars($this->accountNumber) . '</PaymentAccountNumber>'
            . '<IsDutiable>N</IsDutiable>'
            . '</BkgDetails>'
            . '<To>'
            . '<CountryCode>' . htmlspecialchars($destination['country_code']) . '</CountryCode>'
            . '<Postalcode>' . htmlspecialchars($destination['postal_code'] ?? '') . '</Postalcode>'
            . '<City>' . htmlspecialchars($destination['city'] ?? '') . '</City>'
            . '</To>'
            . '</GetQuote>'
            . '</p:DCTRequest>';

        Log::channel('dhl')->debug('DHL GetQuote request', [
            'origin' => $origin,
            'destination' => $destination,
            'pieces' => $pieces,
        ]);

        $response = Http::withHeaders([
            'Content-Type' => 'application/xml',
        ])->timeout(30)->send('POST', self::ENDPOINT, [
            'body' => $xml,
        ]);

        $responseBody = $response->body();

        Log::channel('dhl')->debug('DHL GetQuote response', [
            'status' => $response->status(),
            'body' => $responseBody,
        ]);

        if (!$response->successful()) {
            Log::channel('dhl')->error('DHL API HTTP error', ['status' => $response->status()]);
            return [];
        }

        return $this->parseResponse($responseBody);
    }

    /**
     * Parse the DHL XML response into a structured array.
     */
    private function parseResponse(string $responseBody): array
    {
        try {
            // Strip namespaces for easier parsing
            $cleaned = preg_replace('/(<\/?)[\w]+:/', '$1', $responseBody);
            $xmlObj = simplexml_load_string($cleaned);

            if ($xmlObj === false) {
                Log::channel('dhl')->error('DHL: failed to parse XML response');
                return [];
            }

            // Check for errors
            $condition = $xmlObj->GetQuoteResponse->Note->Condition ?? null;
            if ($condition) {
                $condCode = (string) ($condition->ConditionCode ?? '');
                $condData = (string) ($condition->ConditionData ?? '');
                Log::channel('dhl')->warning('DHL condition', [
                    'code' => $condCode,
                    'data' => $condData,
                ]);
            }

            $services = [];
            $bkgDetails = $xmlObj->GetQuoteResponse->BkgDetails ?? null;

            if (!$bkgDetails) {
                return [];
            }

            $qtdShps = $bkgDetails->QtdShp ?? [];

            foreach ($qtdShps as $qtd) {
                $code = (string) ($qtd->GlobalProductCode ?? '');
                $name = (string) ($qtd->ProductShortName ?? '');
                $currency = (string) ($qtd->CurrencyCode ?? '');
                $deliveryDate = (string) ($qtd->DeliveryDate ?? '');
                $deliveryTime = (string) ($qtd->DeliveryTime ?? '');

                // Get shipping charge from ShippingCharge element
                $cost = 0.0;
                $shippingCharge = (string) ($qtd->ShippingCharge ?? '0');
                $cost = (float) $shippingCharge;

                // Calculate estimated days
                $estimatedDays = null;
                if ($deliveryDate) {
                    try {
                        $delivery = \Carbon\Carbon::parse($deliveryDate);
                        $estimatedDays = (int) now()->diffInDays($delivery);
                    } catch (\Throwable) {
                        // ignore parse errors
                    }
                }

                $services[] = [
                    'code' => $code,
                    'name' => $name,
                    'cost' => $cost,
                    'currency' => $currency,
                    'estimated_days' => $estimatedDays,
                    'delivery_date' => $deliveryDate,
                    'delivery_time' => $deliveryTime,
                ];
            }

            return $services;
        } catch (\Throwable $e) {
            Log::channel('dhl')->error('DHL: exception parsing response', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Try to decrypt a value; return null on failure.
     */
    private function decrypt(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            // Value may not be encrypted (e.g. account number)
            return $value;
        }
    }
}
