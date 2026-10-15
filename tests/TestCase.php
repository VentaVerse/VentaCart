<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A migrated test database has no registry rows (seeders do not run),
     * and a store without an installed theme shows the plain no-theme page.
     * The shipped theme is installed the way the seeder installs it on a
     * fresh store, contract check included. A test about the registry
     * itself sets this false and starts from the bare table.
     */
    protected bool $installsDefaultTheme = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->installsDefaultTheme && in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            app(\App\Services\ExtensionManager::class)->install(
                \App\Services\ThemeService::registryName(\App\Services\ThemeService::THEME)
            );
            // Discovery was cached by that install; a test that points the
            // kernel at fixture extensions must not find a stale list.
            app()->forgetInstance(\App\Services\ExtensionManager::class);
        }
    }

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

    /**
     * Stand in for the extension that owns the automatic email list, so a
     * test about the doorway need not install fulfillment. Bound in the
     * container, which each test rebuilds, so it never outlives the test.
     */
    protected function emailAutomaticallyOn(int ...$orderStatusIds): void
    {
        $this->app->instance(\App\Contracts\OrderEmailPolicy::class, new class($orderStatusIds) implements \App\Contracts\OrderEmailPolicy
        {
            /** @param list<int> $ids */
            public function __construct(private array $ids)
            {
            }

            public function emailsOn(int $orderStatusId): bool
            {
                return in_array($orderStatusId, $this->ids, true);
            }
        });
    }
}
