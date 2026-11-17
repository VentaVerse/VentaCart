<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The theme core's tests run on: a plain package kept under
     * tests/fixtures/themes, so the suite never depends on the theme that
     * ships with VentaCart and passes with that theme removed.
     */
    public const SAMPLE_THEME = 'sample';

    /**
     * A migrated test database has no registry rows (seeders do not run),
     * and a store without an installed theme shows the plain no-theme page.
     * The sample theme is linked in, made the bundled theme, and installed
     * the way the seeder installs it on a fresh store, contract check
     * included. A test about the registry itself sets this false and starts
     * from the bare table.
     */
    protected bool $installsSampleTheme = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Hooks extensions register live in static lists; none may carry
        // over from an earlier test.
        self::forgetStorefrontHooks();
        $this->beforeApplicationDestroyed(fn () => self::forgetStorefrontHooks());

        $this->linkSampleTheme();
        config(['themes.bundled' => self::SAMPLE_THEME]);

        if ($this->installsSampleTheme && in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            app(\App\Services\ExtensionManager::class)->install(
                \App\Services\ThemeService::registryName(self::SAMPLE_THEME)
            );
            // Discovery was cached by that install; a test that points the
            // kernel at fixture extensions must not find a stale list.
            app()->forgetInstance(\App\Services\ExtensionManager::class);
            // The sample's stylesheet is never built.
            $this->withoutVite();
        }
    }

    /**
     * Take away every storefront hook an extension registered: the fixture
     * extension's page type, and anything registered with core's hook lists.
     */
    public static function forgetStorefrontHooks(): void
    {
        \App\Support\StorefrontPageType::forget('sample_page');
        \App\Support\CartGroups::forget();
        \App\Support\AccountMenu::forget();
        \App\Support\CheckoutPage::forget();
    }

    /**
     * Put the sample theme where themes live, for the length of the test.
     * It is a link to the fixture, so nothing is copied.
     */
    private function linkSampleTheme(): void
    {
        $link = resource_path('themes/' . self::SAMPLE_THEME);
        // A checkout with every shipped theme removed has no themes folder.
        if (! is_dir(dirname($link))) {
            mkdir(dirname($link), 0775, true);
        }
        if (! is_link($link) && ! file_exists($link)) {
            symlink(base_path('tests/fixtures/themes/' . self::SAMPLE_THEME), $link);
        }

        $this->beforeApplicationDestroyed(function () use ($link) {
            if (is_link($link)) {
                unlink($link);
            }
        });
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
