<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Models\LayoutRow;
use App\Models\LayoutStylePreset;
use App\Models\LayoutTemplate;
use App\Models\User;
use App\Support\RowStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The style sections a row, a column and a block share: layout, shadow,
 * background, border per side, theme colour overrides, typography, a
 * block's title and the advanced choices. Every value is checked before it
 * can reach a style or class attribute, and a style saved before these
 * sections existed renders the same as it did.
 */
class LayoutStyleSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function row(array $attrs = []): LayoutRow
    {
        return LayoutRow::create($attrs + [
            'theme' => 'venta', 'page_type' => 'home', 'region' => 'content_bottom',
            'sort_order' => 0, 'columns' => [8], 'is_enabled' => true,
        ]);
    }

    private function place(LayoutRow $row, string $title = 'Brands', array $attrs = []): LayoutPlacement
    {
        return LayoutPlacement::create($attrs + [
            'theme' => 'venta', 'page_type' => 'home', 'region' => 'content_bottom', 'row_id' => $row->id,
            'widget' => 'catalog.brands', 'title' => $title, 'sort_order' => 0, 'is_enabled' => true,
        ]);
    }

    public function test_a_style_saved_before_the_new_sections_renders_the_same(): void
    {
        // The full key set a row carried before the new sections existed.
        $old = [
            'bg' => 'gradient', 'bg_color' => 'primary', 'bg_color2' => '#000000', 'bg_angle' => 90,
            'bg_image' => '', 'bg_size' => 'cover', 'bg_position' => 'center', 'bg_repeat' => 'no-repeat', 'bg_attachment' => 'scroll',
            'text' => '#FFFFFF', 'pad' => 'lg', 'width' => 'full', 'border' => 'dashed', 'border_width' => 2, 'border_color' => '',
            'radius' => 'md', 'shadow' => 'sm', 'title_align' => 'center', 'heading_color' => 'primary', 'link_color' => '',
            'text_size' => 'lg', 'font' => 'heading', 'heading_size' => 'xl',
        ];

        $this->assertSame([
            'layout-row--styled', 'layout-row--pad-lg', 'layout-row--full', 'layout-row--bg', 'layout-row--border',
            'layout-row--radius-md', 'layout-row--shadow-sm', 'layout-row--title-center', 'layout-row--headings',
            'layout-row--size-lg', 'layout-row--font-heading', 'layout-row--headings-xl',
        ], RowStyle::classes($old));
        $this->assertSame(
            '--row-bg: linear-gradient(90deg, var(--color-primary), #000000); --row-text: #FFFFFF; --row-heading: var(--color-primary); --row-border: 2px dashed currentColor',
            RowStyle::vars($old)
        );
        $this->assertFalse(RowStyle::isStyled(['bg' => 'none', 'bg_color' => '#123456']), 'An unused colour still draws nothing.');
    }

    public function test_lengths_and_numbers_are_clamped_or_unset(): void
    {
        $n = RowStyle::normalize([
            'margin_top' => '24px', 'margin_bottom' => 9999, 'pad_left' => '-5', 'gap' => 'wide', 'min_height' => '',
            'shadow_blur' => 900, 'line_height' => 160, 'heading_spacing' => '1.25', 'title_spacing' => 'x',
            'heading_weight' => 700, 'title_weight' => '650', 'valign' => 'middle', 'hide_phone' => '1', 'hide_tablet' => 'nope',
        ]);

        $this->assertSame(24, $n['margin_top'], 'A trailing px is forgiven.');
        $this->assertSame(400, $n['margin_bottom']);
        $this->assertSame(0, $n['pad_left']);
        $this->assertSame('', $n['gap'], 'Not a number is unset, not zero.');
        $this->assertSame('', $n['min_height']);
        $this->assertSame(200, $n['shadow_blur']);
        $this->assertSame(160, $n['line_height']);
        $this->assertSame(1.3, $n['heading_spacing']);
        $this->assertSame('', $n['title_spacing']);
        $this->assertSame('700', $n['heading_weight']);
        $this->assertSame('', $n['title_weight'], 'Only the listed weights.');
        $this->assertSame('middle', $n['valign']);
        $this->assertTrue($n['hide_phone']);
        $this->assertFalse($n['hide_tablet']);
    }

    public function test_nothing_from_outside_can_reach_a_style_or_class_attribute(): void
    {
        $n = RowStyle::normalize([
            'css_class' => 'promo "><script>alert(1)</script> band-2 x{color:red} 9bad ok_too',
            'anchor' => 'top" onclick="x',
            'palette' => ['primary' => '#ff0000', 'card_bg' => 'red;}body{display:none', 'Bad-Key' => '#000000', 'btn_text' => 'primary', 5 => '#FFFFFF'],
            'title_color' => 'red; background:url(x)',
            'link_hover_color' => '#12345G',
            'bg' => 'image', 'bg_image' => 'banners\\evil;}.x{',
        ]);

        $this->assertSame('promo band-2 ok_too', $n['css_class']);
        $this->assertSame('', $n['anchor']);
        $this->assertSame(['primary' => '#FF0000'], $n['palette'], 'A theme colour override is a hex under a theme colour key, nothing else.');
        $this->assertSame('', $n['title_color']);
        $this->assertSame('', $n['link_hover_color']);
        $this->assertSame('', $n['bg_image']);
        $this->assertSame('', RowStyle::normalize(['bg_image' => '//evil.test/x.png'])['bg_image']);
        $this->assertSame('section-2', RowStyle::normalize(['anchor' => 'section-2'])['anchor']);

        $attrs = RowStyle::attributes(['css_class' => 'a b', 'anchor' => 'deals', 'text' => '#FFFFFF'], 'layout-block', ['layout-block']);
        $this->assertSame(' class="layout-block layout-block--styled layout-block--pad-none a b" id="deals" style="--row-text: #FFFFFF"', $attrs);
    }

    public function test_the_finer_choices_render_as_custom_properties(): void
    {
        $vars = RowStyle::vars([
            'pad' => 'lg', 'pad_top' => 40, 'pad_left' => 0,
            'border' => 'solid', 'border_width' => 2, 'border_bottom' => 6,
            'radius' => 'sm', 'radius_tl' => 30,
            'shadow' => 'custom', 'shadow_x' => 2, 'shadow_y' => 8, 'shadow_blur' => 20, 'shadow_spread' => -2, 'shadow_color' => '#102030', 'shadow_opacity' => 40, 'shadow_inset' => true,
            'palette' => ['primary' => '#AA0000', 'btn_text' => '#FFFFFF'],
            'line_height' => 150, 'margin_top' => 32, 'gap' => 12,
            'title_border' => 'solid', 'title_border_width' => 3, 'title_border_color' => 'primary',
        ]);

        $this->assertStringContainsString('--row-padding: 40px 1.5rem 2.5rem 0px', $vars, 'An unset side keeps the ready-made padding.');
        $this->assertStringContainsString('--row-border-width: 2px 2px 6px 2px', $vars);
        $this->assertStringContainsString('--row-radius: 30px 0.375rem 0.375rem 0.375rem', $vars);
        $this->assertStringContainsString('--row-shadow: inset 2px 8px 20px -2px rgba(16, 32, 48, 0.4)', $vars);
        $this->assertStringContainsString('--color-btn-text: #FFFFFF; --color-primary: #AA0000', $vars);
        $this->assertStringContainsString('--row-line-height: 1.5', $vars);
        $this->assertStringContainsString('--row-mt: 32px', $vars);
        $this->assertStringContainsString('--row-gap: 12px', $vars);
        $this->assertStringContainsString('--row-title-border: 3px solid var(--color-primary)', $vars);

        $this->assertStringContainsString('color-mix(in srgb, var(--color-primary) 25%, transparent)',
            RowStyle::vars(['shadow' => 'custom', 'shadow_color' => 'primary', 'shadow_opacity' => 25]));

        $classes = RowStyle::classes(['shadow' => 'custom', 'width' => 'edge', 'valign' => 'bottom', 'title_transform' => 'uppercase', 'hide_desktop' => true], 'layout-col');
        $this->assertContains('layout-col--shadow-custom', $classes);
        $this->assertContains('layout-col--edge', $classes);
        $this->assertContains('layout-col--valign-bottom', $classes);
        $this->assertContains('layout-col--title-tt-uppercase', $classes);
        $this->assertContains('layout-col--hide-desktop', $classes);
        $this->assertNotContains('layout-col--shadow-custom-sm', $classes);
    }

    public function test_a_rows_column_styles_are_kept_per_column_and_trimmed(): void
    {
        $n = RowStyle::normalizeRow(['pad' => 'md', 'column_styles' => [
            ['bg' => 'color', 'bg_color' => '#FFFFFF', 'evil' => 1],
            ['bg' => 'none'],
            null,
        ]]);

        $this->assertSame('md', $n['pad']);
        $this->assertCount(1, $n['column_styles'], 'Trailing unstyled columns are dropped.');
        $this->assertSame('#FFFFFF', $n['column_styles'][0]['bg_color']);
        $this->assertArrayNotHasKey('evil', $n['column_styles'][0]);
        $this->assertArrayNotHasKey('column_styles', RowStyle::normalize(['column_styles' => [['pad' => 'sm']]]), 'A block or a preset carries no columns.');
        $this->assertCount(LayoutRow::MAX_COLUMNS, RowStyle::normalizeRow(['column_styles' => array_fill(0, 9, ['pad' => 'sm'])])['column_styles']);
    }

    public function test_responsive_settings_take_phone_widths_and_a_tablet_order(): void
    {
        $r = LayoutRow::normalizeResponsive(['tablet' => [4, 'x'], 'phone' => [8, 0], 'tablet_order' => [2, 1], 'phone_order' => [1, 9]], 2);

        $this->assertSame([4, null], $r['tablet']);
        $this->assertSame([8, null], $r['phone']);
        $this->assertSame([2, 1], $r['tablet_order']);
        $this->assertSame([1, 2], $r['phone_order'], 'An order is clamped to the column count.');
        $this->assertSame([null], LayoutRow::normalizeResponsive(['tablet' => [8]], 1)['phone'], 'A row saved before phone widths stacks as before.');
        $this->assertFalse(LayoutRow::isResponsive(['tablet' => [null, null], 'phone_order' => [1, 2]], 2));
        $this->assertTrue(LayoutRow::isResponsive(['phone' => [4, 4]], 2));
    }

    public function test_the_storefront_draws_columns_blocks_and_titles_with_their_styles(): void
    {
        $row = $this->row([
            'columns' => [4, 4],
            'style' => ['gap' => 8, 'css_class' => 'promo-band', 'anchor' => 'deals', 'hide_phone' => true,
                'column_styles' => [['bg' => 'color', 'bg_color' => '#EEEEEE', 'valign' => 'middle', 'pad_top' => 10]]],
            'responsive' => ['phone' => [4, 4], 'tablet_order' => [2, 1]],
        ]);
        $this->place($row, 'Titled Brands', ['style' => [
            'title_color' => '#FF0000', 'title_transform' => 'uppercase', 'title_line' => 'hide', 'css_class' => 'brand-strip', 'hide_tablet' => true,
        ]]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('class="layout-row layout-row--2 layout-row--phone-cols layout-row--styled layout-row--pad-none layout-row--gap layout-row--hide-phone promo-band" id="deals" style="--row-gap: 8px"', $html);
        $this->assertStringContainsString('class="layout-col layout-col--4 layout-col--styled layout-col--pad-none layout-col--bg layout-col--padding layout-col--valign-middle" style="--col-phone: 4; --col-tablet-order: 2; --row-bg: #EEEEEE; --row-padding: 10px 0 0 0"', $html);
        $this->assertStringContainsString('class="layout-col layout-col--4" style="--col-phone: 4; --col-tablet-order: 1"', $html);
        $this->assertStringContainsString('layout-block--title-color layout-block--title-tt-uppercase layout-block--title-noline layout-block--hide-tablet brand-strip', $html);
        $this->assertStringContainsString('--row-title-color: #FF0000', $html);
        $this->assertStringContainsString('Titled Brands', $html);
    }

    public function test_an_unmoved_column_keeps_its_place_once_another_column_moves(): void
    {
        $row = $this->row([
            'columns' => [3, 3, 2],
            'responsive' => ['phone_order' => [2, 1, 3]],
        ]);
        $this->place($row, 'Ordered Brands');

        $html = $this->get('/')->assertOk()->getContent();

        // The third column stays third. Without an order of its own it would
        // sort as 0 and jump ahead of the two that swapped.
        $this->assertStringContainsString('class="layout-col layout-col--3" style="--col-phone-order: 2"', $html);
        $this->assertStringContainsString('class="layout-col layout-col--3" style="--col-phone-order: 1"', $html);
        $this->assertStringContainsString('class="layout-col layout-col--2" style="--col-phone-order: 3"', $html);
    }

    public function test_a_one_column_row_with_only_a_column_style_still_gets_its_grid(): void
    {
        $row = $this->row(['style' => ['column_styles' => [['shadow' => 'md']]]]);
        $this->place($row, 'Shadowed');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<div class="layout-row layout-row--1" data-layout-row="' . $row->id . '">', $html);
        $this->assertStringContainsString('layout-col--shadow-md', $html);
    }

    public function test_a_save_from_the_layout_editor_keeps_columns_blocks_and_device_settings(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.layout.update'), ['payload' => json_encode([
            'page_type' => 'home', 'columns' => ['left' => 0, 'right' => 0],
            'rows' => [['id' => null, 'region' => 'content_top', 'columns' => [4, 4], 'enabled' => true,
                'style' => ['margin_bottom' => 48, 'palette' => ['primary' => '#00AA00'], 'column_styles' => [null, ['radius_br' => 12]]],
                'responsive' => ['phone' => [8, 4]],
                'placements' => [['id' => null, 'column' => 1, 'widget' => 'catalog.brands', 'title' => 'Kept', 'enabled' => true,
                    'style' => ['title_size' => 30, 'css_class' => 'x" y']]]]],
        ])])->assertRedirect()->assertSessionHas('success');

        $row = LayoutRow::sole();
        $this->assertSame(48, $row->style['margin_bottom']);
        $this->assertSame(['primary' => '#00AA00'], $row->style['palette']);
        $this->assertNull($row->style['column_styles'][0]);
        $this->assertSame(12, $row->style['column_styles'][1]['radius_br']);
        $this->assertSame([8, 4], $row->responsive['phone']);
        $placement = LayoutPlacement::sole();
        $this->assertSame(30, $placement->style['title_size']);
        $this->assertSame('y', $placement->style['css_class'], 'A class name with a quote in it is dropped.');

        // The editor hands back what it loaded, so a second save keeps it.
        $html = $this->get(route('admin.layout', ['page' => 'home']))->assertOk()->getContent();
        $this->assertStringContainsString('column_styles', $html);
        $this->assertStringContainsString('Hide on', $html);
    }

    public function test_presets_keep_the_new_sections_but_never_a_rows_columns(): void
    {
        $this->actingAs(User::factory()->create());

        $created = $this->postJson(route('admin.layout.style-presets.store'), ['name' => 'Tall band', 'style' => [
            'min_height' => 320, 'valign' => 'middle', 'palette' => ['primary' => '#123456'], 'title_transform' => 'uppercase',
            'column_styles' => [['pad' => 'sm']],
        ]])->assertOk()->json();

        $this->assertSame(320, $created['style']['min_height']);
        $this->assertSame(['primary' => '#123456'], $created['style']['palette']);
        $this->assertSame('uppercase', $created['style']['title_transform']);
        $this->assertArrayNotHasKey('column_styles', $created['style']);
        $this->assertSame(320, LayoutStylePreset::sole()->style['min_height']);
    }

    public function test_a_template_carries_block_styles_and_device_settings(): void
    {
        $rows = LayoutTemplate::cleanRows([[
            'columns' => [4, 4], 'enabled' => true,
            'style' => ['column_styles' => [['pad' => 'md']]],
            'responsive' => ['phone_order' => [2, 1]],
            'placements' => [['column' => 0, 'widget' => 'catalog.brands', 'title' => 'T', 'enabled' => true, 'config' => [], 'style' => ['title_color' => '#111111']]],
        ]]);

        $this->assertSame('md', $rows[0]['style']['column_styles'][0]['pad']);
        $this->assertSame([2, 1], $rows[0]['responsive']['phone_order']);
        $this->assertSame('#111111', $rows[0]['placements'][0]['style']['title_color']);

        $units = LayoutTemplate::toUnits($rows);
        $this->assertSame([2, 1], $units[0]['responsive']['phone_order']);
        $this->assertSame('#111111', $units[0]['cols'][0][0]['style']['title_color']);
    }
}
