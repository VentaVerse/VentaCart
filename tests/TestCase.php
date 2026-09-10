<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * An API key with a known secret, for tests that call the ERP endpoints.
     *
     * Defaults to every area, read and write, because most tests are about
     * what an endpoint does rather than what a key may reach. Pass narrower
     * abilities to test the permission itself.
     *
     * @param  array<string, list<string>>|null  $abilities
     */
    protected function apiKey(string $secret = 'erp-test-token', ?array $abilities = null): \App\Models\ApiKey
    {
        return \App\Models\ApiKey::create([
            'label' => 'Test',
            'prefix' => substr($secret, 0, \App\Models\ApiKey::PREFIX_LENGTH),
            'key_hash' => \App\Models\ApiKey::hashSecret($secret),
            'abilities' => $abilities ?? [
                'catalogue' => ['read', 'write'],
                'orders' => ['read', 'write'],
                'fulfillment' => ['read', 'write'],
                'reviews' => ['read', 'write'],
            ],
        ]);
    }

    //
}
