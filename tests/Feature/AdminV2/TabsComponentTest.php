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

    public function test_unknown_tab_query_param_falls_back_to_default_when_names_given(): void
    {
        $this->get('/?tab=bogus'); // seed request with an invalid ?tab= value
        $this->blade(<<<'BLADE'
            <x-admin.tabs default="config" param="tab" :names="['config', 'availability']">
                <x-slot:nav>
                    <x-admin.tabs.tab name="config">Configuration</x-admin.tabs.tab>
                    <x-admin.tabs.tab name="availability">Availability</x-admin.tabs.tab>
                </x-slot:nav>
                <x-admin.tabs.panel name="config">CONFIG BODY</x-admin.tabs.panel>
                <x-admin.tabs.panel name="availability">AVAIL BODY</x-admin.tabs.panel>
            </x-admin.tabs>
            BLADE)
            // The Alpine tab: starts out as the raw (invalid) query value...
            ->assertSee("tab: 'bogus'", false)
            // ...but init() resets it to the default when names doesn't include it.
            ->assertSee("this.tab = 'config'", false)
            ->assertSee('names: JSON.parse', false);
    }
}
