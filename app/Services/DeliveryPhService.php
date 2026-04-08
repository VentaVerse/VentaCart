<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliveryPhService
{
    private const SANDBOX_URL = 'https://us-central1-fleet-staging-2333a.cloudfunctions.net';

    private string $baseUrl;
    private string $email;
    private string $password;
    private string $mode;

    public function __construct()
    {
        $settings = Setting::where('group', 'shipping')
            ->whereIn('key', ['dph_mode', 'dph_email', 'dph_password', 'dph_production_url'])
            ->pluck('value', 'key');

        $this->mode = $settings->get('dph_mode', 'sandbox');
        $this->baseUrl = $this->mode === 'production'
            ? ($settings->get('dph_production_url') ?: self::SANDBOX_URL)
            : self::SANDBOX_URL;

        try {
            $this->email = Crypt::decryptString($settings->get('dph_email', ''));
            $this->password = Crypt::decryptString($settings->get('dph_password', ''));
        } catch (\Throwable) {
            $this->email = '';
            $this->password = '';
        }
    }

    public static function isEnabled(): bool
    {
        return Setting::where('group', 'shipping')
            ->where('key', 'dph_enabled')
            ->value('value') === '1';
    }

    private function getAccessToken(): string
    {
        $auth = Cache::get('dph_auth');

        if ($auth && $auth['access_expires_at'] > now()->timestamp * 1000) {
            return $auth['access_token'];
        }

        // Try refresh
        if ($auth && $auth['refresh_expires_at'] > now()->timestamp * 1000) {
            try {
                $this->refreshToken($auth['refresh_token']);
                return Cache::get('dph_auth')['access_token'];
            } catch (\Throwable $e) {
                Log::warning('DPH token refresh failed, re-logging in', ['error' => $e->getMessage()]);
            }
        }

        // Full login
        $this->login();
        return Cache::get('dph_auth')['access_token'];
    }

    public function login(): void
    {
        if (empty($this->email) || empty($this->password)) {
            throw new \RuntimeException('DPH credentials not configured. Please save your email and password in Settings > Shipping first.');
        }

        $response = Http::post("{$this->baseUrl}/loginV2", [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        $data = $response->json();

        if (!($data['success'] ?? false)) {
            throw new \RuntimeException('DPH login failed: ' . ($data['results'] ?? 'unknown error'));
        }

        $results = $data['results'];

        Cache::put('dph_auth', [
            'access_token' => $results['accessToken'],
            'refresh_token' => $results['refreshToken'],
            'access_expires_at' => $results['accessTokenExpiresAt'],
            'refresh_expires_at' => $results['refreshTokenExpiresAt'],
        ], now()->addDays(30));
    }

    private function refreshToken(string $refreshToken): void
    {
        $response = Http::withHeaders([
            'x-access-token' => "Bearer {$refreshToken}",
        ])->post("{$this->baseUrl}/refreshToken");

        $data = $response->json();

        if (!($data['success'] ?? false)) {
            throw new \RuntimeException('DPH token refresh failed: ' . ($data['results'] ?? 'unknown error'));
        }

        $results = $data['results'];

        Cache::put('dph_auth', [
            'access_token' => $results['accessToken'],
            'refresh_token' => $results['refreshToken'],
            'access_expires_at' => $results['accessTokenExpiresAt'],
            'refresh_expires_at' => $results['refreshTokenExpiresAt'],
        ], now()->addDays(30));
    }

    /**
     * Make an authenticated API request with auto-retry on token expiry.
     */
    protected function apiRequest(string $method, string $endpoint, array $data = [], array $query = []): array
    {
        $attempt = function () use ($method, $endpoint, $data, $query) {
            $token = $this->getAccessToken();

            $request = Http::withHeaders([
                'x-access-token' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ]);

            if (!empty($query)) {
                $request = $request->withQueryParameters($query);
            }

            $response = $method === 'GET'
                ? $request->get("{$this->baseUrl}{$endpoint}")
                : $request->post("{$this->baseUrl}{$endpoint}", $data);

            return $response->json();
        };

        $startTime = microtime(true);
        $result = $attempt();

        // If token expired mid-request, clear cache and retry once
        if (isset($result['success']) && !$result['success'] &&
            is_string($result['results'] ?? null) &&
            str_contains($result['results'], 'TOKEN_EXPIRED')) {
            Cache::forget('dph_auth');
            $result = $attempt();
        }

        $duration = round((microtime(true) - $startTime) * 1000);

        Log::channel('dph')->info("{$method} {$endpoint}", [
            'query' => $query,
            'request' => $data,
            'response' => $result,
            'duration_ms' => $duration,
        ]);

        // Persist to database for admin visibility
        try {
            $success = $result['success'] ?? false;
            $errorMsg = null;
            if (!$success) {
                $errorMsg = $result['message'] ?? ($result['results'] ?? null);
                if (is_array($errorMsg)) {
                    $errorMsg = json_encode($errorMsg);
                }
                $errorMsg = $errorMsg ? mb_substr((string) $errorMsg, 0, 255) : null;
            }

            // Extract order ID from refNo if present
            $orderId = null;
            $refNo = $data['refNo'] ?? ($query['refNo'] ?? null);
            if ($refNo && preg_match('/ORD-(\d+)/', $refNo, $m)) {
                $orderId = $m[1];
            }

            \App\Models\DphApiLog::create([
                'method' => $method,
                'endpoint' => $endpoint,
                'order_id' => $orderId,
                'status_code' => $success ? 200 : 400,
                'response_time_ms' => $duration,
                'request_body' => array_merge($data, $query ? ['_query' => $query] : []),
                'response_body' => $result,
                'success' => $success,
                'error_message' => $errorMsg,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist DPH API log', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    public static function cmToInches(float $cm): float
    {
        return round($cm / 2.54, 2);
    }

    /**
     * Normalize city names for DPH's PSGC format.
     * Our DB stores "Makati" but DPH expects "Makati City" for Metro Manila cities.
     * Our DB also appends "(Capital)" to some HUCs which DPH rejects.
     */
    protected static function normalizeCityForDph(string $city, string $province): string
    {
        // Strip "(Capital)" suffix — our HUC import adds it but DPH rejects it
        $city = preg_replace('/\s*\(Capital\)$/i', '', $city);

        $metroManila = ['Metro Manila', 'NCR', 'National Capital Region'];
        if (!in_array($province, $metroManila, true)) {
            return $city;
        }

        // These already have the correct format or are municipalities
        $skip = ['Quezon City', 'Pateros'];
        if (in_array($city, $skip, true) || str_ends_with($city, ' City')) {
            return $city;
        }

        return $city . ' City';
    }

    /**
     * Get available scheduled courier rates.
     * Used for provincial / outside Metro Manila deliveries.
     */
    public function getScheduledRates(array $pickup, array $delivery, ?array $dimensions = null, ?float $weight = null): array
    {
        $pickup['city'] = self::normalizeCityForDph($pickup['city'] ?? '', $pickup['province'] ?? '');
        $delivery['city'] = self::normalizeCityForDph($delivery['city'] ?? '', $delivery['province'] ?? '');

        $body = [
            'serviceType' => 'scheduled',
            'pickup' => $pickup,
            'delivery' => $delivery,
        ];

        if ($dimensions) {
            $body['dimensions'] = [
                'length' => self::cmToInches($dimensions['length'] ?? 0),
                'width' => self::cmToInches($dimensions['width'] ?? 0),
                'height' => self::cmToInches($dimensions['height'] ?? 0),
            ];
        }

        if ($weight) {
            $body['weight'] = $weight;
        }

        $result = $this->apiRequest('POST', '/v3/getAvailablePartners', $body);

        if (!($result['success'] ?? false)) {
            Log::warning('DPH getScheduledRates failed', ['result' => $result]);
            return [];
        }

        return $result['result'] ?? [];
    }

    /**
     * Get on-demand courier rates (Metro Manila only).
     * Requires lat/long for both pickup and delivery.
     */
    public function getOnDemandRates(array $pickupData, array $deliveryData, array $dimensions, float $weight): array
    {
        $body = [
            'couriers' => [],
            'pickup_province' => $pickupData['province'] ?? 'Metro Manila',
            'data' => [
                'localeKey' => 'en_PH',
                'pickup' => [
                    'name' => $pickupData['name'],
                    'phone' => $pickupData['phone'],
                    'address' => $pickupData['address'],
                    'lat' => (float) $pickupData['lat'],
                    'long' => (float) $pickupData['lng'],
                ],
                'delivery' => [
                    'name' => $deliveryData['name'],
                    'phone' => $deliveryData['phone'],
                    'address' => $deliveryData['address'],
                    'lat' => (float) $deliveryData['lat'],
                    'long' => (float) $deliveryData['lng'],
                ],
                'scheduleAt' => now()->addMinutes(30)->timestamp * 1000,
                'vehicleType' => 'MOTORCYCLE',
                'dimensions' => [
                    'height' => self::cmToInches($dimensions['height'] ?? 0),
                    'width' => self::cmToInches($dimensions['width'] ?? 0),
                    'depth' => self::cmToInches($dimensions['length'] ?? 0),
                    'weight' => $weight,
                ],
            ],
        ];

        $result = $this->apiRequest('POST', '/v3/getOnDemandQuotation', $body);

        if (!($result['success'] ?? false)) {
            Log::warning('DPH getOnDemandRates failed', ['result' => $result]);
            return [];
        }

        return $result['result'] ?? [];
    }

    /**
     * Create a shipment post (book a courier).
     */
    public function createPost(array $pickupDetails, array $deliveryDetails, string $partnerId, string $refNo, bool $isPsgc = true): array
    {
        // Normalize city names for DPH PSGC format
        $pickupDetails['pickupCity'] = self::normalizeCityForDph($pickupDetails['pickupCity'] ?? '', $pickupDetails['province'] ?? '');
        $deliveryDetails['deliveryCity'] = self::normalizeCityForDph($deliveryDetails['deliveryCity'] ?? '', $deliveryDetails['province'] ?? '');

        // Auto-populate metaFields with postal codes if not already set
        if (!isset($deliveryDetails['metaFields'])) {
            $deliveryDetails['metaFields'] = [];
        }
        $deliveryDetails['metaFields'] += [
            'pickup_postal_code' => $pickupDetails['postalCode'] ?? '',
            'delivery_postal_code' => $deliveryDetails['postalCode'] ?? '',
        ];

        // Add time slots (required by some couriers like NinjaVan)
        // Delivery is always full day — couriers don't enforce it anyway
        $deliveryDetails['metaFields']['deliveryTimeSlot'] = '09:00-22:00';
        $pickupTimeSlot = \App\Models\Setting::where('group', 'shipping')->where('key', 'dph_pickup_time_slot')->value('value');
        if ($pickupTimeSlot) {
            $deliveryDetails['metaFields']['pickupTimeSlot'] = $pickupTimeSlot;
        }

        // Sandbox requires additional test metaFields
        if ($this->mode === 'sandbox') {
            $deliveryDetails['metaFields'] += [
                'sampleString' => 'test',
                'sampleDropdown' => 'first',
            ];
        }

        $body = [
            'refNo' => $refNo,
            'isPSGC' => $isPsgc,
            'pickupDetails' => $pickupDetails,
            'deliveryDetails' => $deliveryDetails,
        ];

        Log::info('DPH createPost request', ['partnerId' => $partnerId, 'body' => $body]);

        $result = $this->apiRequest('POST', '/v3/createPost', $body, ['partnerId' => $partnerId]);

        if (!($result['success'] ?? false)) {
            Log::error('DPH createPost failed', ['result' => $result, 'partnerId' => $partnerId, 'body' => $body]);
            throw new \RuntimeException('DPH booking failed: ' . json_encode($result['results'] ?? $result['message'] ?? 'unknown'));
        }

        return $result['result'] ?? $result['results'] ?? [];
    }

    /**
     * Get post details by postId.
     */
    public function getPost(string $postId): array
    {
        $result = $this->apiRequest('GET', '/getPost', [], ['postId' => $postId]);

        if (!($result['success'] ?? false)) {
            Log::warning('DPH getPost failed', ['postId' => $postId, 'result' => $result]);
            return [];
        }

        return $result['result'] ?? $result['results'] ?? [];
    }

    /**
     * Cancel a post.
     */
    public function cancelPost(string $postId, string $trackingNumber, string $courierId): array
    {
        $result = $this->apiRequest('POST', '/v3/cancelPost', [
            'postId' => $postId,
            'trackingNumber' => $trackingNumber,
            'courierId' => $courierId,
        ]);

        if (!($result['success'] ?? false)) {
            Log::error('DPH cancelPost failed', ['postId' => $postId, 'result' => $result]);
            throw new \RuntimeException('DPH cancellation failed: ' . json_encode($result['results'] ?? 'unknown'));
        }

        return $result;
    }

    /**
     * Get list of configured partners.
     */
    public function getPartners(): array
    {
        $result = $this->apiRequest('GET', '/getPartners');

        if (!($result['success'] ?? false)) {
            return [];
        }

        return $result['result'] ?? $result['results'] ?? [];
    }
}
