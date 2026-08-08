<?php

namespace Extensions\CourierFulfillment\Couriers\Spx;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Low-level SPX Open API client: HMAC-SHA256 request signing + HTTP wrapper.
 *
 * Auth (docs/spx/integration-brief.md §2, verified against the doc's own
 * test vector — see tests/Unit/SpxSignerTest.php):
 *
 *   originalValue = "{app_id}_{timestamp}_{random_num}_{payload}"
 *   check-sign    = hex(HMAC_SHA256(key: app_secret, message: originalValue))
 *
 * `payload` MUST be the literal JSON bytes sent over the wire — re-encoding
 * independently (e.g. letting the HTTP client re-serialize the array) risks
 * a byte-level mismatch (whitespace/escaping) that SPX rejects as
 * `1007 CheckSignVerificationFailedCode`. post() therefore json_encodes the
 * body exactly once and reuses that same string for both signing and
 * Http::withBody() — never $http->post($url, $body), which would let Guzzle
 * re-encode.
 *
 * Two independent credential pairs (integration-brief.md §2):
 *   - app-id / app-secret   — header-level, issued once per integration.
 *   - user_id / user_secret — body-level, per SPX account (3.1 Create
 *     Account), required by every order-related endpoint.
 * Both secrets are stored encrypted (Setting group 'courier'), mirroring
 * QuadXClient's pattern — see SpxFulfillment::settingsSchema().
 */
class SpxClient
{
    private const BASE_URLS = [
        'uat'        => 'https://test-stable.spx.ph/',
        'production' => 'https://spx.ph/',
    ];

    /** App-level credentials present — the minimum needed to sign a request. */
    public function isConfigured(): bool
    {
        return $this->appId() !== null && $this->appSecret() !== null;
    }

    /** Body-level account credentials present — required by every order/account endpoint. */
    public function hasAccountCredentials(): bool
    {
        return $this->userId() !== null && $this->userSecret() !== null;
    }

    public function baseUrl(): string
    {
        $mode = $this->setting('spx_mode', 'uat');
        return self::BASE_URLS[$mode] ?? self::BASE_URLS['uat'];
    }

    // --- Signing ------------------------------------------------------

    /**
     * Pure signer, isolated from Setting/Http so it can be unit-tested
     * directly against integration-brief.md's verified test vector.
     */
    public static function sign(int $appId, int $timestamp, int $randomNum, string $payload, string $appSecret): string
    {
        $original = sprintf('%d_%d_%d_%s', $appId, $timestamp, $randomNum, $payload);

        return hash_hmac('sha256', $original, $appSecret);
    }

    // --- Endpoints ------------------------------------------------------

    /**
     * 3.2 Check Account Credentials — the cheapest authenticated call in the
     * API surface (no order side-effects), used as SPX's "test connection".
     */
    public function verifyAccount(): array
    {
        return $this->post('/open/api/v1/account/verify', [
            'user_id'     => (int) $this->userId(),
            'user_secret' => (string) $this->userSecret(),
        ]);
    }

    // --- HTTP core --------------------------------------------------------

    /**
     * Signs and sends a POST request, unwraps the `{ret_code,message,data}`
     * envelope, and returns `data` on success.
     *
     * @return array the unwrapped `data` payload
     * @throws SpxApiException  ret_code !== 0 (a reachable, well-formed SPX response)
     * @throws \RuntimeException  not configured, transport failure, or a response
     *                            that isn't a well-formed SPX envelope at all
     */
    public function post(string $path, array $body): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('SPX is not configured (missing app-id/secret-key).');
        }

        $url = rtrim($this->baseUrl(), '/') . '/' . ltrim($path, '/');

        // Encoded ONCE — this exact string is what gets signed AND sent.
        $payload = json_encode($body);

        $timestamp = time();
        $randomNum = random_int(1, PHP_INT_MAX);
        $appId = (int) $this->appId();
        $checkSign = self::sign($appId, $timestamp, $randomNum, $payload, (string) $this->appSecret());

        try {
            $response = Http::withHeaders([
                    'app-id'     => $appId,
                    'check-sign' => $checkSign,
                    'timestamp'  => $timestamp,
                    'random-num' => $randomNum,
                ])
                ->withBody($payload, 'application/json')
                ->timeout(40)
                ->post($url);
        } catch (\Throwable $e) {
            Log::error('SPX request failed', ['path' => $path, 'error' => $e->getMessage()]);
            throw new \RuntimeException('SPX request failed: ' . $e->getMessage(), 0, $e);
        }

        $json = $response->json();
        if (!is_array($json) || !array_key_exists('ret_code', $json)) {
            Log::warning('SPX API returned a non-envelope response', [
                'path' => $path, 'status' => $response->status(), 'body' => $response->body(),
            ]);
            throw new \RuntimeException("SPX returned an unexpected response (HTTP {$response->status()}).");
        }

        $retCode = (int) $json['ret_code'];
        $message = (string) ($json['message'] ?? '');

        if ($retCode !== 0) {
            Log::warning('SPX API error', ['path' => $path, 'ret_code' => $retCode, 'message' => $message]);
            throw new SpxApiException($message !== '' ? $message : "SPX error {$retCode}", $retCode);
        }

        $data = $json['data'] ?? [];
        return is_array($data) ? $data : [];
    }

    // --- Credentials (decrypted) ------------------------------

    private function appId(): ?string
    {
        return $this->setting('spx_app_id');
    }

    private function appSecret(): ?string
    {
        return $this->decrypt($this->setting('spx_secret_key'));
    }

    private function userId(): ?string
    {
        return $this->setting('spx_user_id');
    }

    private function userSecret(): ?string
    {
        return $this->decrypt($this->setting('spx_user_secret'));
    }

    private function setting(string $key, ?string $default = null): ?string
    {
        $val = Setting::where('group', 'courier')->where('key', $key)->value('value');
        return ($val === null || $val === '') ? $default : $val;
    }

    private function decrypt(?string $encrypted): ?string
    {
        if (empty($encrypted)) return null;
        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }
}
