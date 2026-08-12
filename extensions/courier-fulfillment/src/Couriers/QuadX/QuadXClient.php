<?php

namespace Extensions\CourierFulfillment\Couriers\QuadX;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Low-level QuadX API client: JWT/HMAC-SHA256 auth + HTTP wrapper.
 *
 * Auth (verified against staging): JWT signed with HMAC-SHA256.
 *   header  = {"alg":"HS256","typ":"JWT"}
 *   payload = {"sub": <api_key>, "iat": <unix>, "jti": <unique nonce>}
 *   sig     = HMAC_SHA256(b64url(header)."."b64url(payload), <secret_key>)
 * A fresh jti per request (anti-replay) — QuadX rejects repeats.
 */
class QuadXClient
{
    private const BASE_URLS = [
        'staging'    => 'https://api.staging.quadx.xyz/v2',
        'production' => 'https://api.quadx.xyz/v2',
    ];

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null && $this->secretKey() !== null;
    }

    /**
     * Which of isConfigured()'s two mode-scoped fields are missing, labeled
     * exactly as QuadXFulfillment::settingsSchema() labels them — backs
     * QuadXFulfillment::configurationIssues() (CourierFulfillmentInterface's
     * sanctioned evolution #6). Mode-aware the same way apiKey()/secretKey()
     * already are: checks whichever mode (staging/production) is currently
     * selected, never both.
     *
     * @return string[]
     */
    public function missingCredentialLabels(): array
    {
        $missing = [];
        if ($this->apiKey() === null) {
            $missing[] = 'API Key';
        }
        if ($this->secretKey() === null) {
            $missing[] = 'Secret Key';
        }

        return $missing;
    }

    public function baseUrl(): string
    {
        $mode = $this->setting('quadx_mode', 'staging');
        return self::BASE_URLS[$mode] ?? self::BASE_URLS['staging'];
    }

    // --- Endpoints --------------------------------------------------------

    public function createOrder(array $payload): array
    {
        return $this->request('POST', '/orders', $payload);
    }

    public function getOrder(string $trackingNumber): array
    {
        return $this->request('GET', '/orders/' . rawurlencode($trackingNumber));
    }

    public function cancelOrder(string $trackingNumber): array
    {
        return $this->request('PUT', '/orders/' . rawurlencode($trackingNumber) . '/cancel');
    }

    public function getStatuses(): array
    {
        return $this->request('GET', '/orders/statuses');
    }

    /**
     * Rate estimate for an origin/destination pair. Verified live against
     * QuadX staging (owner, 2026-08-12): a serviceable destination answers
     * 200 with `data.attributes.shipping_fee` as a map of parcel category to
     * peso amount; an unserviceable one answers 422 with
     * `errors.detail = "Delivery address is not serviceable."`.
     *
     * It CREATES NOTHING (no waybill, no booking, no reservation), which is
     * what makes it safe to call on every booking-modal open.
     *
     * $timeout is explicit rather than the class default of 40s because the
     * booking modal blocks on this call: see
     * ServiceabilityService::TIMEOUT_SECONDS.
     */
    public function estimateRates(array $attributes, ?int $timeout = null): array
    {
        return $this->request('POST', '/orders/estimates/rates', ['data' => ['attributes' => $attributes]], $timeout);
    }

    /** Single AWB print. Returns the parsed JSON (contains presigned S3 'awb' + 'v35' URLs). */
    public function printAwb(string $trackingNumber, string $type = 'zebra'): array
    {
        return $this->request('GET', '/print/awb/' . rawurlencode($trackingNumber) . '?type=' . urlencode($type));
    }

    // --- HTTP core --------------------------------------------------------

    /**
     * @return array{ok:bool, status:int, body:array, error:?string}
     */
    private function request(string $method, string $path, ?array $body = null, ?int $timeout = null): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'status' => 0, 'body' => [], 'error' => 'QuadX is not configured (missing API key/secret).'];
        }

        $url = $this->baseUrl() . $path;

        try {
            $req = Http::withToken($this->jwt())
                ->acceptJson()
                // A caller that a person is waiting on (the booking modal's
                // serviceability check) passes its own, much shorter budget
                // and both halves of it are bounded: an unresolvable host
                // must fail inside the same window a slow response does.
                ->connectTimeout($timeout ?? 40)
                ->timeout($timeout ?? 40);

            $resp = match (strtoupper($method)) {
                'POST'  => $req->post($url, $body ?? []),
                'PUT'   => $body !== null ? $req->put($url, $body) : $req->put($url),
                default => $req->get($url),
            };

            $json = $resp->json() ?? [];
            $ok = $resp->successful();

            if (!$ok) {
                Log::warning('QuadX API error', ['method' => $method, 'path' => $path, 'status' => $resp->status(), 'body' => $json]);
            }

            return [
                'ok'     => $ok,
                'status' => $resp->status(),
                'body'   => is_array($json) ? $json : [],
                'error'  => $ok ? null : $this->extractError($json, $resp->status()),
            ];
        } catch (\Throwable $e) {
            Log::error('QuadX request failed', ['method' => $method, 'path' => $path, 'error' => $e->getMessage()]);
            return ['ok' => false, 'status' => 0, 'body' => [], 'error' => $e->getMessage()];
        }
    }

    private function jwt(): string
    {
        $header  = $this->b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->b64url(json_encode([
            'sub' => $this->apiKey(),
            'iat' => time(),
            'jti' => (string) Str::uuid(),
        ]));
        $sig = $this->b64url(hash_hmac('sha256', "$header.$payload", $this->secretKey(), true));
        return "$header.$payload.$sig";
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function extractError(array $json, int $status): string
    {
        // QuadX uses a few error shapes across endpoints.
        if (isset($json['errors']['detail'])) {
            // Seen as both a string and an array of field-error strings.
            $d = $json['errors']['detail'];
            return is_array($d) ? implode(' ', array_map(fn ($v) => is_array($v) ? implode(' ', $v) : (string) $v, $d)) : (string) $d;
        }
        if (isset($json['description'])) return (string) $json['description'];
        if (isset($json['message']) && is_string($json['message'])) return $json['message'];
        if (isset($json['parameters']) && is_array($json['parameters'])) {
            $first = reset($json['parameters']);
            if (is_array($first)) return (string) ($first[0] ?? "HTTP $status");
        }
        return "QuadX returned HTTP $status";
    }

    // --- Credentials (mode-aware, decrypted) ------------------------------

    private function apiKey(): ?string
    {
        $mode = $this->setting('quadx_mode', 'staging');
        return $this->decrypt($this->setting("quadx_{$mode}_api_key"));
    }

    private function secretKey(): ?string
    {
        $mode = $this->setting('quadx_mode', 'staging');
        return $this->decrypt($this->setting("quadx_{$mode}_secret_key"));
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
