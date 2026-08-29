<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Models\LayoutRow;
use App\Models\LayoutStylePreset;
use App\Models\User;
use App\Support\RowStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A row's style (Plaza plan, phase 2): a closed set of choices the storefront
 * paints from custom properties, colours from the theme's own palette or a
 * hex, named presets a merchant keeps per theme.
 */
class LayoutRowStyleTest extends TestCase
{
    use RefreshDatabase;

    private function row(array $attrs = []): LayoutRow
    {
        return LayoutRow::create($attrs + [
            'theme' => 'venta', 'page_type' => 'home', 'region' => 'content_bottom',
            'sort_order' => 0, 'columns' => [8], 'is_enabled' => true,
        ]);
    }

    private function place(LayoutRow $row, string $title = 'Brands'): LayoutPlacement
    {
        return LayoutPlacement::create([
            'theme' => 'venta', 'page_type' => 'home', 'region' => 'content_bottom', 'row_id' => $row->id,
            'widget' => 'catalog.brands', 'title' => $title, 'sort_order' => 0, 'is_enabled' => true,
        ]);
    }

    public function test_only_known_keys_and_values_survive(): void
    {
        $n = RowStyle::normalize(['bg' => 'color', 'bg_color' => 'card_bg', 'pad' => 'huge', 'width' => 'full',
            'text' => 'javascript:alert(1)', 'border' => 'solid', 'border_width' => 40, 'radius' => 'lg', 'shadow' => 'md', 'evil' => 'x']);

        $this->assertSame('color', $n['bg']);
        $this->assertSame('card_bg', $n['bg_color']);
        $this->assertSame('none', $n['pad'], 'An unknown padding falls back to none.');
        $this->assertSame('full', $n['width']);
        $this->assertSame('', $n['text'], 'A colour that is neither hex nor theme key is nothing.');
        $this->assertSame(8, $n['border_width'], 'Border width is clamped.');
        $this->assertArrayNotHasKey('evil', $n);
        $this->assertSame('#1B3D5F', RowStyle::normalize(['bg_color' => '#1b3d5f'])['bg_color']);
    }

    public function test_an_image_path_cannot_escape_storage(): void
    {
        $this->assertSame('', RowStyle::normalize(['bg' => 'image', 'bg_image' => '../../.env'])['bg_image']);
        $this->assertSame('', RowStyle::normalize(['bg' => 'image', 'bg_image' => 'https://evil.test/x.png'])['bg_image']);
        $this->assertSame('', RowStyle::normalize(['bg' => 'image', 'bg_image' => "a\")}; body{x:url(\""])['bg_image']);
        $this->assertSame('banners/hero.jpg', RowStyle::normalize(['bg' => 'image', 'bg_image' => '/banners/hero.jpg'])['bg_image']);
    }

    public function test_a_theme_colour_renders_as_the_themes_variable_and_a_hex_as_itself(): void
    {
        $vars = RowStyle::vars(['bg' => 'color', 'bg_color' => 'card_bg', 'text' => '#FFFFFF', 'border' => 'dashed', 'border_width' => 2]);

        $this->assertStringContainsString('--row-bg: var(--color-card-bg)', $vars);
        $this->assertStringContainsString('--row-text: #FFFFFF', $vars);
        $this->assertStringContainsString('--row-border: 2px dashed currentColor', $vars);
    }

    public function test_a_styled_one_column_row_gets_a_wrapper_the_theme_can_paint(): void
    {
        $row = $this->row(['style' => ['bg' => 'color', 'bg_color' => 'primary', 'pad' => 'lg', 'width' => 'full', 'shadow' => 'sm']]);
        $this->place($row, 'Painted Brands');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('layout-row layout-row--1 layout-row--styled layout-row--pad-lg layout-row--full layout-row--bg layout-row--shadow-sm', $html);
        $this->assertStringContainsString('style="--row-bg: var(--color-primary)"', $html);
        $this->assertStringContainsString('Painted Brands', $html);
    }

    public function test_an_image_background_carries_its_url_and_placement(): void
    {
        $row = $this->row(['style' => ['bg' => 'image', 'bg_image' => 'banners/hero one.jpg', 'bg_size' => 'contain', 'bg_attachment' => 'fixed']]);
        $this->place($row);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('--row-bg: url(/storage/banners/hero%20one.jpg)', $html);
        $this->assertStringContainsString('--row-bg-size: contain', $html);
        $this->assertStringContainsString('--row-bg-attachment: fixed', $html);
    }

    public function test_a_plain_row_is_still_drawn_bare(): void
    {
        $row = $this->row(['style' => RowStyle::DEFAULTS]);
        $this->place($row, 'Bare Brands');

        $this->get('/')->assertOk()->assertSee('Bare Brands', false)->assertDontSee('layout-row', false);
    }

    public function test_the_editor_saves_a_rows_style_and_ships_the_palette_and_presets(): void
    {
        $this->actingAs(User::factory()->create());
        LayoutStylePreset::create(['theme' => 'venta', 'name' => 'Dark band', 'style' => ['bg' => 'color', 'bg_color' => '#111111', 'text' => '#FFFFFF']]);
        LayoutStylePreset::create(['theme' => 'other', 'name' => 'Not mine', 'style' => []]);

        $this->post(route('admin.layout.update'), ['payload' => json_encode([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0],
            'rows' => [['id' => null, 'region' => 'content_top', 'columns' => [8], 'enabled' => true,
                'style' => ['bg' => 'gradient', 'bg_color' => 'primary', 'bg_color2' => '#000000', 'bg_angle' => 90, 'pad' => 'xl', 'nope' => 1],
                'placements' => [['id' => null, 'column' => 0, 'widget' => 'catalog.brands', 'title' => 'Styled', 'enabled' => true]]]],
        ])])->assertRedirect()->assertSessionHas('success');

        $style = LayoutRow::sole()->style;
        $this->assertSame('gradient', $style['bg']);
        $this->assertSame(90, $style['bg_angle']);
        $this->assertSame('xl', $style['pad']);
        $this->assertArrayNotHasKey('nope', $style);

        $html = $this->get(route('admin.layout', ['page' => 'home']))->assertOk()->getContent();
        $this->assertStringContainsString('Dark band', $html);
        $this->assertStringNotContainsString('Not mine', $html, 'Presets belong to a theme.');
        $this->assertStringContainsString('themeColors:', $html);
        $this->assertStringContainsString('Row style', $html);

        $this->get('/')->assertOk()->assertSee('--row-bg: linear-gradient(90deg, var(--color-primary), #000000)', false);
    }

    public function test_presets_are_kept_and_dropped_by_their_own_controls(): void
    {
        $this->actingAs(User::factory()->create());

        $created = $this->postJson(route('admin.layout.style-presets.store'), ['name' => 'Soft card', 'style' => ['pad' => 'md', 'radius' => 'md', 'shadow' => 'sm', 'junk' => true]])
            ->assertOk()->assertJsonPath('name', 'Soft card')->assertJsonPath('style.radius', 'md')->json();

        $this->assertArrayNotHasKey('junk', $created['style']);
        $this->assertDatabaseHas('layout_style_presets', ['id' => $created['id'], 'theme' => 'venta']);

        $this->postJson(route('admin.layout.style-presets.store'), ['name' => '', 'style' => []])->assertStatus(422);

        $foreign = LayoutStylePreset::create(['theme' => 'other', 'name' => 'Theirs', 'style' => []]);
        $this->deleteJson(route('admin.layout.style-presets.destroy', $foreign))->assertNotFound();

        $this->deleteJson(route('admin.layout.style-presets.destroy', $created['id']))->assertOk();
        $this->assertDatabaseMissing('layout_style_presets', ['id' => $created['id']]);
    }

    public function test_the_preset_controls_are_behind_admin_auth(): void
    {
        $this->postJson(route('admin.layout.style-presets.store'), ['name' => 'x', 'style' => []])->assertUnauthorized();
    }
}
