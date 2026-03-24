<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    private ?string $siteKey;
    private ?string $secretKey;
    private bool $enabled;

    public function __construct()
    {
        $settings = \App\Models\Setting::where('group', 'security')->pluck('value', 'key');

        $this->enabled = ($settings->get('turnstile_enabled') ?? '0') === '1';
        $this->siteKey = $settings->get('turnstile_site_key') ?: null;

        $encryptedSecret = $settings->get('turnstile_secret_key') ?: null;
        try {
            $this->secretKey = $encryptedSecret ? Crypt::decryptString($encryptedSecret) : null;
        } catch (\Throwable) {
            $this->secretKey = null;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->siteKey && $this->secretKey;
    }

    public function getSiteKey(): ?string
    {
        return $this->siteKey;
    }

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if (!$token) {
            return false;
        }

        try {
            $payload = [
                'secret' => $this->secretKey,
                'response' => $token,
            ];

            if ($remoteIp) {
                $payload['remoteip'] = $remoteIp;
            }

            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', $payload);

            $result = $response->json();

            return ($result['success'] ?? false) === true;
        } catch (\Throwable $e) {
            Log::warning('Turnstile verification failed: ' . $e->getMessage());
            return false;
        }
    }
}
