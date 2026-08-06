<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiKitTest extends TestCase
{
    use RefreshDatabase;

    public function test_ui_kit_renders_every_component_section(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.ui-kit'))
            ->assertOk()
            ->assertSeeInOrder(['Buttons', 'Badges', 'Forms', 'Cards', 'Table', 'Feedback', 'Empty states']);
    }
}
