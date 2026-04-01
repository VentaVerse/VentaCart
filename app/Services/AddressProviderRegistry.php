<?php

namespace App\Services;

use App\Contracts\AddressProvider;

class AddressProviderRegistry
{
    /** @var array<int, AddressProvider> keyed by country ID */
    protected array $providers = [];

    public function register(AddressProvider $provider): void
    {
        if ($provider->countryId()) {
            $this->providers[$provider->countryId()] = $provider;
        }
    }

    public function has(int $countryId): bool
    {
        return isset($this->providers[$countryId]);
    }

    public function get(int $countryId): ?AddressProvider
    {
        return $this->providers[$countryId] ?? null;
    }

    /** @return array<int, AddressProvider> */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * JSON-serializable manifest for the frontend.
     * Keyed by country ID.
     */
    public function toFrontendManifest(): array
    {
        $manifest = [];
        foreach ($this->providers as $countryId => $provider) {
            $manifest[$countryId] = [
                'countryId' => $countryId,
                'countryCode' => $provider->countryCode(),
                'countryName' => $provider->countryName(),
                'phoneCode' => $provider->phoneCode(),
                'fields' => $provider->fields(),
            ];
        }
        return $manifest;
    }
}
