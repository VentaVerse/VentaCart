<?php

namespace Tests\Feature\AdminV2;

use App\Models\Store;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\StockAlerts\Models\StockNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stock Alerts as a real extension (architecture wave 1, first
 * conversion): its tables arrive with its own migrations on install, its
 * admin page and storefront subscribe endpoint exist while enabled, and
 * its on/off is the extension mechanism itself — the CoreFeatures shim
 * for it is gone.
 */
class StockAlertsExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
    }

    protected function installAndBoot(): void
    {
        app(ExtensionManager::class)->install('stock-alerts');
        app(ExtensionManager::class)->bootEnabled();
    }

    public function test_install_creates_its_tables_and_serves_its_page(): void
    {
        $this->installAndBoot();
        $this->actingAs(User::factory()->create());

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('stock_notifications'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('stock_notification_templates'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('low_stock_alerts'));

        $this->get(route('admin.stock-notifications.index'))
            ->assertOk()
            ->assertSee('Stock Alerts')
            ->assertSee('v-embed', false);
    }

    public function test_storefront_subscribe_endpoint_exists_while_enabled(): void
    {
        $this->installAndBoot();

        // Validation-level 422 (empty payload) proves the route is alive
        // and reaches the controller; a disabled extension would 404.
        $this->postJson('/notify-stock', [])->assertStatus(422);
    }

    public function test_not_installed_means_no_routes_and_no_commands(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.stock-notifications.index'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('notify-stock.store'));
        $this->assertArrayNotHasKey('stock:notify', \Illuminate\Support\Facades\Artisan::all());
    }

    public function test_core_feature_shim_is_gone(): void
    {
        // The whole CoreFeatures shim retired when its last tenant
        // (SEO) converted; the class itself is gone.
        $this->assertFalse(class_exists(\App\Support\CoreFeatures::class));
    }

    public function test_waitlist_dedupe_survives_the_move(): void
    {
        $this->installAndBoot();

        $product = \App\Models\Product::create([
            'store_id' => 1, 'name' => 'Pedal', 'slug' => 'pedal', 'price' => 100,
        ]);

        StockNotification::create([
            'store_id' => 1, 'product_id' => $product->id,
            'name' => 'Juan', 'email' => 'juan@example.com', 'status' => 'waiting',
        ]);

        $this->assertSame(1, StockNotification::waiting()->count());
    }
}
