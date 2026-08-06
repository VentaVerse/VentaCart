<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

class CorePrimitivesTest extends TestCase
{
    public function test_button_renders_variants_and_loading_state(): void
    {
        $this->blade('<x-admin.button variant="primary">Save</x-admin.button>')
            ->assertSee('Save')->assertSee('bg-v-accent', false);

        $this->blade('<x-admin.button variant="danger" size="sm">Delete</x-admin.button>')
            ->assertSee('Delete')->assertSee('bg-v-danger', false);

        // loading buttons disable themselves and expose a spinner bound to Alpine
        $this->blade('<x-admin.button x-bind:data-loading="saving">Go</x-admin.button>')
            ->assertSee('v-btn-spinner', false);
    }

    public function test_button_renders_as_link_when_href_given(): void
    {
        $this->blade('<x-admin.button href="/x">Open</x-admin.button>')
            ->assertSee('<a ', false)->assertSee('href="/x"', false);
    }

    public function test_icon_renders_svg_for_known_name(): void
    {
        $this->blade('<x-admin.icon name="products" class="w-4 h-4" />')
            ->assertSee('<svg', false)->assertSee('w-4 h-4', false);
    }

    public function test_badge_tones(): void
    {
        $this->blade('<x-admin.badge tone="success">Complete</x-admin.badge>')
            ->assertSee('Complete')->assertSee('text-v-accent', false);
        $this->blade('<x-admin.badge tone="danger">Failed</x-admin.badge>')
            ->assertSee('text-v-danger', false);
    }
}
