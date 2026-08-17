<?php

namespace Tests\Feature\AdminV2;

use App\Models\Store;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\AbandonedCarts\Models\AbandonedCartRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Abandoned Carts as a real extension (architecture wave 1, second
 * conversion): its tables arrive with its own migrations on install, its
 * ops pages (Sales) and config pages (Extensions) exist while enabled,
 * and its on/off is the extension mechanism itself — the CoreFeatures
 * shim for it is gone.
 */
class AbandonedCartsExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
    }

    protected function installAndBoot(): void
    {
        app(ExtensionManager::class)->install('abandoned-carts');
        app(ExtensionManager::class)->bootEnabled();
    }

    public function test_install_creates_its_tables_and_serves_its_pages(): void
    {
        $this->installAndBoot();
        $this->actingAs(User::factory()->create());

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('abandoned_cart_rules'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('abandoned_cart_notifications'));

        $this->get(route('admin.abandoned-carts.index'))
            ->assertOk()
            ->assertSee('Abandoned Carts');

        $this->get(route('admin.abandoned-carts.settings'))
            ->assertOk()
            ->assertSee('v3-savebar', false);
    }

    public function test_not_installed_means_no_routes_and_no_commands(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.abandoned-carts.index'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.abandoned-carts.settings'));
        $this->assertArrayNotHasKey('cart:notify', \Illuminate\Support\Facades\Artisan::all());
    }

    public function test_not_installed_dashboard_still_renders(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_core_feature_shim_is_gone(): void
    {
        $this->assertArrayNotHasKey('abandoned-carts', \App\Support\CoreFeatures::FEATURES);
    }

    public function test_template_routes_refuse_foreign_email_templates(): void
    {
        $this->installAndBoot();
        $this->actingAs(User::factory()->create());

        $foreign = \App\Models\EmailTemplate::create([
            'store_id' => 1,
            'slug' => 'order_confirmation',
            'name' => 'Order Confirmation',
            'subject' => 'Your order',
            'body' => 'Hi {customer_name}',
            'type' => 'customer',
            'is_active' => true,
        ]);

        $this->get(route('admin.abandoned-carts.templates.edit', $foreign))->assertNotFound();
        $this->delete(route('admin.abandoned-carts.templates.destroy', $foreign))->assertNotFound();
        $this->post(route('admin.abandoned-carts.templates.toggle', $foreign))->assertNotFound();

        $this->assertDatabaseHas('email_templates', ['id' => $foreign->id, 'is_active' => true]);
    }

    public function test_rules_survive_the_move(): void
    {
        $this->installAndBoot();

        $template = \App\Models\EmailTemplate::create([
            'store_id' => 1,
            'slug' => 'abandoned_cart_reminder',
            'name' => 'Reminder',
            'subject' => 'Come back!',
            'body' => 'Hi {customer_name}',
            'type' => 'customer',
            'is_active' => true,
        ]);

        AbandonedCartRule::create([
            'store_id' => 1,
            'email_template_id' => $template->id,
            'max_sends' => 2,
            'min_cart_age_hours' => 4,
            'priority' => 10,
            'is_active' => true,
        ]);

        $this->assertSame(1, AbandonedCartRule::where('is_active', true)->count());
    }
}
