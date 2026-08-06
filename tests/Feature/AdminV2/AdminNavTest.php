<?php

namespace Tests\Feature\AdminV2;

use App\Support\AdminNav;
use Tests\TestCase;

class AdminNavTest extends TestCase
{
    public function test_groups_returns_dashboard_and_all_sections(): void
    {
        $groups = AdminNav::groups(null); // null user = no permission filtering
        $labels = collect($groups)->pluck('label')->all();

        $this->assertContains('Sales', $labels);
        $this->assertContains('Catalog', $labels);
        $this->assertContains('System', $labels);
    }

    public function test_every_item_route_exists(): void
    {
        foreach (AdminNav::groups(null) as $group) {
            foreach ($group['items'] as $item) {
                $this->assertTrue(
                    app('router')->has($item['route']),
                    "Route {$item['route']} for '{$item['label']}' is not defined"
                );
            }
        }
    }

    public function test_palette_entries_are_flat_and_resolvable(): void
    {
        $entries = AdminNav::paletteEntries(null);
        $this->assertNotEmpty($entries);
        foreach ($entries as $e) {
            $this->assertArrayHasKey('label', $e);
            $this->assertArrayHasKey('url', $e);
            $this->assertStringStartsWith('http', $e['url']);
        }
    }
}
