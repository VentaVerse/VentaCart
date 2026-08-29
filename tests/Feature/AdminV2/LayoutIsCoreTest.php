<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Layout editor is core (owner ruling 2026-08-29). Switching the theme
 * editor extension off, or never installing it, must not hide the merchant's
 * own layouts: the page, its save and its schemas all answer without it.
 */
class LayoutIsCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_layout_page_answers_without_the_theme_extension(): void
    {
        // Nothing installs or boots the theme extension here, on purpose.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.theme.index'));

        $this->actingAs(User::factory()->create())
            ->get(route('admin.layout', ['page' => 'home']))
            ->assertOk()
            ->assertSee('Above the content', false)
            ->assertDontSee('href="' . url('/') . '/opensesame/extensions/theme"', false);
    }

    public function test_a_save_and_the_schemas_work_without_the_theme_extension(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.layout.update'), ['payload' => json_encode([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0],
            'rows' => [['id' => null, 'region' => 'content_top', 'columns' => [4, 4], 'enabled' => true,
                'placements' => [['id' => null, 'column' => 1, 'widget' => 'catalog.brands', 'title' => 'Core Brands', 'enabled' => true]]]],
        ])])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('layout_placements', ['title' => 'Core Brands', 'column_index' => 1]);
        $this->assertSame(1, LayoutPlacement::count());

        $this->get(route('admin.layout.schemas'))->assertOk()->assertJsonStructure(['catalog.products']);
    }
}
