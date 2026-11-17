<?php

namespace Tests\Feature\AdminV2;

use App\Support\SettingsNav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_are_the_settings_hub_groups(): void
    {
        // Payments, shipping and fulfillment settings live in the Extensions area.
        $labels = collect(SettingsNav::groups(null))->pluck('label')->all();
        $this->assertSame(['Store', 'Commerce', 'Communication', 'System'], $labels);
    }

    /** One list drives the hub, the sub-menu, the palette and the section pages. */
    public function test_there_is_one_settings_registry(): void
    {
        $this->assertFileDoesNotExist(config_path('settings-nav.php'));
        $this->assertNull(config('settings-nav'));
    }

    /** Every section page has a card, and every card wears its page's title. */
    public function test_every_section_page_has_an_item_named_as_its_title(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());

        foreach (['general', 'currencies', 'catalog', 'checkout', 'mail', 'security', 'automations'] as $section) {
            $item = SettingsNav::find($section);
            $this->assertNotNull($item, "No settings item for {$section}");
            $html = $this->get(SettingsNav::url($item))->assertOk()->getContent();
            $this->assertStringContainsString('<h1 class="v-head__title">' . $item['label'] . '</h1>', html_entity_decode($html));
        }

        $this->get(route('admin.settings.index'))->assertOk()
            ->assertSee('href="' . route('admin.settings.section', 'catalog') . '"', false)
            ->assertSee('Products per page, sorting, filters, and badges.');
    }

    public function test_every_item_resolves_to_a_real_route(): void
    {
        foreach (SettingsNav::groups(null) as $group) {
            foreach ($group['items'] as $item) {
                $this->assertTrue(
                    app('router')->has($item['route']),
                    "Route {$item['route']} for settings card '{$item['label']}' is not defined"
                );
            }
        }
    }

    public function test_find_returns_item_with_group(): void
    {
        $item = SettingsNav::find('mail');
        $this->assertNotNull($item);
        $this->assertSame('Communication', $item['group']);
        $this->assertSame('Notifications', $item['label']);
        $this->assertSame('mail', $item['section']);
        $this->assertNull(SettingsNav::find('nonexistent'));
    }

    public function test_palette_entries_are_prefixed_and_flat(): void
    {
        $entries = SettingsNav::paletteEntries(null);
        $this->assertNotEmpty($entries);
        foreach ($entries as $e) {
            $this->assertStringStartsWith('Settings: ', $e['label']);
            $this->assertSame('Settings', $e['group']);
            $this->assertStringStartsWith('http', $e['url']);
        }
    }
}
