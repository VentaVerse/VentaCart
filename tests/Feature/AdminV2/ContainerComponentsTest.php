<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

class ContainerComponentsTest extends TestCase
{
    public function test_card_renders_title_body_and_footer(): void
    {
        $this->blade('<x-admin.card title="Store details" description="Basic info."><p>Body here</p><x-slot:footer>Foot</x-slot:footer></x-admin.card>')
            ->assertSeeInOrder(['Store details', 'Basic info.', 'Body here', 'Foot']);
    }

    /**
     * x-admin.stat is gone: the figure is the promoted measure block and the
     * last three callers (SEO dashboard, SEO 404s, stock alerts) converted in
     * phase 3, so the v2.0 component has no callers left. The pin follows the
     * measure to its replacement, and gets stronger: it now also holds the
     * figure's contract that the accent is IDENTITY, so it lands as a class on
     * the block rather than as a colour on the value.
     */
    public function test_figure_renders_mono_value_and_identity_accent(): void
    {
        $this->blade('<x-admin.figure label="TODAY" value="₱48,230" accent="violet" note="6 more than yesterday" />')
            ->assertSee('TODAY')
            ->assertSee('₱48,230')
            ->assertSee('6 more than yesterday')
            ->assertSee('v-num', false)
            ->assertSee('v-stat--violet', false);
    }

    public function test_empty_state_renders_icon_title_and_action(): void
    {
        $this->blade('<x-admin.empty-state icon="products" title="No products yet" description="Add your first product."><x-admin.button>Add product</x-admin.button></x-admin.empty-state>')
            ->assertSee('No products yet')->assertSee('Add your first product.')->assertSee('Add product');
    }
}
