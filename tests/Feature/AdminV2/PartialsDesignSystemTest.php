<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

/**
 * The four partials that were the last unconverted surface in the admin:
 * the media manager, the single image picker, the dual listbox and the
 * payment availability editor. Between them they held every raw Tailwind
 * palette utility and every non-token radius in the admin tree.
 *
 * These are file-content assertions rather than rendered-page assertions
 * because two of the four paint themselves from JavaScript (the product
 * image grid is built with createElement, so its class strings never appear
 * in a server response) and because the point being pinned is that the
 * source carries no colour outside the token bridge.
 */
class PartialsDesignSystemTest extends TestCase
{
    /** @return array<string, string> */
    private function partials(): array
    {
        $base = resource_path('views/admin');

        return [
            'image_manager' => $base . '/catalog/products/partials/image_manager.blade.php',
            'single_image_picker' => $base . '/partials/single_image_picker.blade.php',
            'dual-listbox' => $base . '/partials/dual-listbox.blade.php',
            'availability' => $base . '/settings/payments/partials/availability.blade.php',
        ];
    }

    public function test_partials_carry_no_raw_tailwind_palette_utilities(): void
    {
        // Every Tailwind palette family the audit found in these files, plus
        // the rest of the default ramp so a future edit cannot reintroduce a
        // different hue. `black` and `white` are excluded deliberately: a
        // scrim is bg-black/45 in x-admin.modal too, and that is the house
        // spelling for an overlay.
        $families = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
        $pattern = '/\b(?:bg|text|border|ring|from|via|to|placeholder|divide|outline|decoration|shadow|accent|caret|fill|stroke)-(?:' . $families . ')-\d{2,3}\b/';

        foreach ($this->partials() as $label => $path) {
            $this->assertFileExists($path);
            preg_match_all($pattern, file_get_contents($path), $hits);
            $this->assertSame([], $hits[0], "{$label} still writes raw palette utilities");
        }
    }

    public function test_partials_use_only_token_radii(): void
    {
        foreach ($this->partials() as $label => $path) {
            preg_match_all('/\brounded(?:-[trbl]{1,2})?-(?:sm|md|lg|xl|2xl|3xl|full)\b/', file_get_contents($path), $hits);
            $this->assertSame([], $hits[0], "{$label} still writes non-token radii");
        }
    }

    public function test_partials_carry_no_native_confirm_or_alert(): void
    {
        foreach ($this->partials() as $label => $path) {
            $source = file_get_contents($path);
            $this->assertDoesNotMatchRegularExpression('/(?<![\w$.])confirm\s*\(/', $source, "{$label} still calls native confirm()");
            $this->assertDoesNotMatchRegularExpression('/(?<![\w$.])alert\s*\(/', $source, "{$label} still calls native alert()");
        }
    }

    public function test_partials_carry_no_style_attributes_or_style_blocks(): void
    {
        foreach ($this->partials() as $label => $path) {
            $source = file_get_contents($path);
            $this->assertStringNotContainsString('<style', $source, "{$label} has a <style> block");
            $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=\s*"/', $source, "{$label} has an inline style attribute");
        }
    }

    public function test_every_image_in_the_partials_carries_an_alt(): void
    {
        foreach ($this->partials() as $label => $path) {
            preg_match_all('/<img\b[^>]*>/', file_get_contents($path), $tags);
            foreach ($tags[0] as $tag) {
                $this->assertMatchesRegularExpression('/\salt=/', $tag, "{$label} has an <img> with no alt: {$tag}");
            }
        }
    }

    public function test_no_em_or_en_dashes_in_the_partials(): void
    {
        foreach ($this->partials() as $label => $path) {
            $source = file_get_contents($path);
            $this->assertStringNotContainsString("\u{2014}", $source, "{$label} contains an em dash");
            $this->assertStringNotContainsString("\u{2013}", $source, "{$label} contains an en dash");
        }
    }

    public function test_availability_reuses_the_shared_listbox_for_shipping_keys(): void
    {
        // The shipping-method picker used to be a hand-copied second listbox
        // with its own selectedKeys state. The shared partial preserves
        // non-numeric ids verbatim, so the copy was removable; pin that it
        // stays removed and that the submitted field name is unchanged.
        $source = file_get_contents($this->partials()['availability']);

        $this->assertStringContainsString("'name' => 'shipping_method_keys'", $source);
        $this->assertStringNotContainsString('selectedKeys', $source);
        // 5 include conditions + shipping methods + 5 exclude conditions.
        $this->assertSame(11, substr_count($source, "@include('admin.partials.dual-listbox'"));
    }
}
