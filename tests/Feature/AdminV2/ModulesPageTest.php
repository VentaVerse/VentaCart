<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_modules_page_renders_cards_with_status(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.extensions.index'))
            ->assertOk()
            ->assertSee('v3-embed', false)   // v3 settings shell
            ->assertSee('Stock Alerts')->assertSee('Abandoned Carts')->assertSee('SEO')
            ->assertSee('Configure');
    }

    /**
     * The standard button set (owner ruling 2026-08-18): a not-installed
     * row offers Install and nothing else; an installed row offers
     * Uninstall, Enable/Disable, and Configure.
     */
    public function test_rows_carry_the_standard_button_set(): void
    {
        $this->actingAs(User::factory()->create());

        // Not installed: the row is Install-only — no uninstall endpoint
        // rendered for it. (Theme ships installed via its continuity
        // migration, so the page as a whole legitimately carries that
        // row's Uninstall; the pin is per-row.)
        $this->get(route('admin.extensions.index'))
            ->assertOk()
            ->assertSee(route('admin.extensions.install', 'stock-alerts'), false)
            ->assertDontSee(route('admin.extensions.uninstall', 'stock-alerts'), false)
            ->assertSee(route('admin.extensions.uninstall', 'theme'), false);

        app(\App\Services\ExtensionManager::class)->install('stock-alerts');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        $response = $this->get(route('admin.extensions.index'))->assertOk();
        $response->assertSee('Uninstall');
        $response->assertSee('Disable');
        $response->assertSee(route('admin.extensions.uninstall', 'stock-alerts'), false);
        $response->assertSee(route('admin.stock-notifications.index'), false); // its Configure link
    }

    /**
     * The rundown carries each row's category (owner ruling 2026-08-18):
     * Fulfillment reads Shipping, the residual features read Modules.
     */
    public function test_all_extensions_rundown_names_each_rows_category(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.extensions.index'))
            ->assertOk()
            ->assertSee('v3-dir__cat', false)
            ->assertSeeInOrder(['Fulfillment', 'Shipping'], false)
            ->assertSeeInOrder(['Stock Alerts', 'Modules'], false);
    }

    /**
     * Modules is the residual category page: everything that is not a
     * payment or shipping concern, with the same lifecycle actions.
     */
    public function test_modules_category_page_excludes_typed_extensions(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.extensions.modules'))
            ->assertOk()
            ->assertSee('Modules')
            ->assertSee('Stock Alerts')->assertSee('Abandoned Carts')
            ->assertSee('Banners')->assertSee('SEO')
            ->assertDontSee('Fulfillment');
    }
}
