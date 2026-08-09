<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

class CorePrimitivesTest extends TestCase
{
    public function test_button_renders_variants_and_loading_state(): void
    {
        $this->blade('<x-admin.button variant="primary">Save</x-admin.button>')
            ->assertSee('Save')->assertSee('bg-v-accent', false);

        // Inline destructive controls are outlined, not filled: they sit among
        // ordinary controls and must not outshout the page's primary action.
        // The confirm dialog's own button is the one that gets filled, by the
        // .v-confirm scope in components/buttons.css.
        $this->blade('<x-admin.button variant="danger" size="sm">Delete</x-admin.button>')
            ->assertSee('Delete')
            ->assertSee('v-btn--danger', false)
            ->assertSee('border-v-danger', false)
            ->assertSee('text-v-danger', false)
            ->assertDontSee('bg-v-danger', false);

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
        // A square mark plus a coloured word, never a tinted capsule. The
        // tone reaches the page as a class the stylesheet colours, so the
        // assertion is on the tone and on the mark rather than on a
        // background utility that no longer exists.
        $this->blade('<x-admin.badge tone="success">Complete</x-admin.badge>')
            ->assertSee('Complete')
            ->assertSee('v-badge--success', false)
            ->assertSee('v-badge__dot', false);

        $this->blade('<x-admin.badge tone="danger">Failed</x-admin.badge>')
            ->assertSee('Failed')
            ->assertSee('v-badge--danger', false);

        // The word is never dropped, so colour never carries the meaning
        // alone even when the caller suppresses the mark.
        $this->blade('<x-admin.badge tone="warning" :dot="false">Pending</x-admin.badge>')
            ->assertSee('Pending')
            ->assertSee('v-badge--warning', false)
            ->assertDontSee('v-badge__dot', false);
    }
}
