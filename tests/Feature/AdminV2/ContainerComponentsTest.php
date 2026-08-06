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

    public function test_stat_renders_mono_value(): void
    {
        $this->blade('<x-admin.stat label="TODAY" value="₱48,230" />')
            ->assertSee('TODAY')->assertSee('₱48,230')->assertSee('v-num', false);
    }

    public function test_empty_state_renders_icon_title_and_action(): void
    {
        $this->blade('<x-admin.empty-state icon="products" title="No products yet" description="Add your first product."><x-admin.button>Add product</x-admin.button></x-admin.empty-state>')
            ->assertSee('No products yet')->assertSee('Add your first product.')->assertSee('Add product');
    }
}
