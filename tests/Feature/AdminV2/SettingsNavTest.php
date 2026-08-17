<?php

namespace Tests\Feature\AdminV2;

use App\Support\SettingsNav;
use Tests\TestCase;

class SettingsNavTest extends TestCase
{
    public function test_groups_returns_all_three_categories(): void
    {
        // Wave 0 repin: Sales channels retired — payments, shipping, and
        // fulfillment settings all live in the Extensions area now.
        $labels = collect(SettingsNav::groups(null))->pluck('label')->all();
        $this->assertSame(['Store', 'Operations', 'Access & system'], $labels);
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
        $this->assertSame('Store', $item['group']);
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
