<?php

namespace Tests\Feature\Install;

use App\Models\NavMenu;
use App\Models\NavMenuItem;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A fresh install has a footer menu of the store's own pages, so a footer
 * that draws a menu has links from the first visit.
 */
class FreshInstallFooterMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_store_has_a_footer_menu_of_its_own_pages(): void
    {
        Storage::fake('public');
        NavMenu::where('key', 'footer')->delete();

        $this->seed(ThemeSeeder::class);

        $menu = NavMenu::where('key', 'footer')->first();
        $this->assertNotNull($menu);
        $this->assertSame('Footer menu', $menu->name);

        $items = NavMenuItem::where('menu_id', $menu->id)->orderBy('sort_order')->get();
        $this->assertSame(['search', 'brands', 'contact', 'account'], $items->map(fn ($i) => $i->config['page'] ?? null)->all());
        $this->assertSame(['Search', 'Brands', 'Contact', 'My account'], $items->pluck('label')->all());
        $this->assertTrue($items->every(fn ($i) => $i->type === 'system'));
    }

    public function test_seeding_again_keeps_the_footer_menu_the_store_has(): void
    {
        Storage::fake('public');
        NavMenu::where('key', 'footer')->delete();
        $this->seed(ThemeSeeder::class);

        $menu = NavMenu::where('key', 'footer')->first();
        NavMenuItem::where('menu_id', $menu->id)->where('label', 'Brands')->delete();
        $menu->update(['name' => 'Help']);

        $this->seed(ThemeSeeder::class);

        $this->assertSame(1, NavMenu::where('key', 'footer')->count());
        $this->assertSame('Help', $menu->fresh()->name);
        $this->assertSame(3, NavMenuItem::where('menu_id', $menu->id)->count());
    }
}
