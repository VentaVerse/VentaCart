<?php

namespace Tests\Feature\AdminV2;

use App\Models\Store;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Support\AdminNav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The self-registration contract (extension architecture wave 0): an
 * extension declares its admin-nav entries in its OWN manifest
 * (extension.json "admin_nav"), the registry merges them at runtime,
 * and the entry exists exactly while the extension is enabled. No core
 * file carries an extension's menu entry anymore; a Venta update that
 * replaces config/admin-nav.php has nothing to revert.
 */
class ExtensionNavContributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
    }

    protected function installAndBoot(): void
    {
        // Routes register at boot; a mid-process install needs the boot
        // pass re-run, same as every fulfillment test does.
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
    }

    /** The ungrouped block at the top of the sidebar, where Dashboard lives. */
    protected function topLevelLabels(): array
    {
        foreach (AdminNav::groups(User::factory()->create()) as $group) {
            if (($group['label'] ?? null) === null) {
                return array_column($group['items'], 'label');
            }
        }

        return [];
    }

    protected function salesLabels(): array
    {
        foreach (AdminNav::groups(User::factory()->create()) as $group) {
            if (($group['label'] ?? null) === 'Sales') {
                return array_column($group['items'], 'label');
            }
        }

        return [];
    }

    public function test_core_config_carries_no_fulfillment_entry(): void
    {
        $flat = json_encode(config('admin-nav'));

        $this->assertStringNotContainsString('fulfillment', $flat);
    }

    /**
     * Owner ruling 2026-09-03: Fulfillment stands on its own above Sales.
     *
     * It is the queue the store works out of all day, so it is not something
     * to go looking for behind a collapsed group. It joins the ungrouped
     * block at the top rather than creating a group of its own, because an
     * extension never creates groups (AdminNav::registry()).
     */
    public function test_enabled_extension_stands_above_sales_not_inside_it(): void
    {
        $this->installAndBoot();

        $top = $this->topLevelLabels();

        $this->assertContains('Fulfillment', $top);
        // 'after: dashboard' places it directly beneath Dashboard.
        $this->assertSame('Fulfillment', $top[array_search('Dashboard', $top, true) + 1]);

        // And it is no longer buried in Sales.
        $this->assertNotContains('Fulfillment', $this->salesLabels());
    }

    public function test_disabled_extension_contributes_nothing(): void
    {
        $this->installAndBoot();
        app(ExtensionManager::class)->disable('courier-fulfillment');

        $this->assertNotContains('Fulfillment', $this->topLevelLabels());
    }

    public function test_not_installed_extension_contributes_nothing(): void
    {
        $this->assertNotContains('Fulfillment', $this->salesLabels());
    }

    public function test_manifest_enabled_setting_still_hides_the_entry(): void
    {
        $this->installAndBoot();

        \App\Models\Setting::create(['group' => 'courier', 'key' => 'fulfillment_enabled', 'value' => '0', 'type' => 'text']);

        $this->assertNotContains('Fulfillment', $this->salesLabels());
    }

    public function test_breadcrumbs_still_derive_from_the_merged_registry(): void
    {
        $this->installAndBoot();
        $this->actingAs(User::factory()->create());

        // One home (owner ruling 2026-08-19): the queue lives under
        // extensions/, so the URL-derived seed resolves to the Extensions
        // group. The Sales entry still points in; which group the sidebar
        // SHOWS is the click-memory nav's business, not this derivation's.
        $active = AdminNav::activeItemKey(config('admin.prefix', 'admin') . '/extensions/courier-fulfillment/settings');

        $this->assertSame(['group' => 'Extensions', 'item' => null], $active);
    }
}
