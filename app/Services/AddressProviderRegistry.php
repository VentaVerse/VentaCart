<?php

namespace App\Services;

use App\Contracts\AddressProvider;

class AddressProviderRegistry
{
    /** @var array<string, AddressProvider> keyed by ISO2 */
    protected array $providers = [];

    public function register(AddressProvider $provider): void
    {
        $this->providers[$provider->countryCode()] = $provider;
    }

    public function has(string $countryCode): bool
    {
        return isset($this->providers[$countryCode]);
    }

    public function get(string $countryCode): ?AddressProvider
    {
        return $this->providers[$countryCode] ?? null;
    }

    /** @return array<string, AddressProvider> */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * JSON-serializable manifest for the frontend.
     * Keyed by country code.
     */
    public function toFrontendManifest(): array
    {
        $manifest = [];
        foreach ($this->providers as $code => $provider) {
            $manifest[$code] = [
                'countryCode' => $provider->countryCode(),
                'countryName' => $provider->countryName(),
                'phoneCode' => $provider->phoneCode(),
                'fields' => $provider->fields(),
            ];
        }
        return $manifest;
    }
}
