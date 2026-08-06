<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

class TabsComponentTest extends TestCase
{
    public function test_tabs_render_nav_and_panels_with_default_active(): void
    {
        $this->get('/'); // seed request
        $this->blade(<<<'BLADE'
            <x-admin.tabs default="config" param="tab">
                <x-slot:nav>
                    <x-admin.tabs.tab name="config">Configuration</x-admin.tabs.tab>
                    <x-admin.tabs.tab name="availability">Availability</x-admin.tabs.tab>
                </x-slot:nav>
                <x-admin.tabs.panel name="config">CONFIG BODY</x-admin.tabs.panel>
                <x-admin.tabs.panel name="availability">AVAIL BODY</x-admin.tabs.panel>
            </x-admin.tabs>
            BLADE)
            ->assertSeeInOrder(['Configuration', 'Availability', 'CONFIG BODY', 'AVAIL BODY'])
            ->assertSee("tab: 'config'", false)
            ->assertSee('aria-selected', false)
            ->assertSee('x-cloak', false);
    }
}
