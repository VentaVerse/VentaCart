<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every shipped look keeps its small text readable: text_muted, the colour
 * of hints, address lines and footer payment names, must reach WCAG AA
 * (4.5:1) on each light surface it is painted on. Presets are data, so this
 * is the guard that a new or edited preset cannot ship a grey that fades
 * into its own cards (owner report, 2026-08-29: "almost invisible").
 */
class PresetContrastTest extends TestCase
{
    private const SURFACES = ['bg', 'card_bg', 'dropdown_bg', 'payment_badge_bg', 'review_bg', 'sidebar_bg'];

    public function test_every_preset_and_the_default_keep_muted_text_at_aa(): void
    {
        $files = glob(resource_path('themes/venta/presets/*.json'));
        $files[] = resource_path('themes/venta/theme.json');

        foreach ($files as $file) {
            $colors = json_decode((string) file_get_contents($file), true)['colors'] ?? [];
            $muted = $colors['text_muted'] ?? null;
            $this->assertNotNull($muted, basename($file) . ' has no text_muted');

            foreach (self::SURFACES as $surface) {
                if (empty($colors[$surface]) || ! preg_match('/^#[0-9a-f]{6}$/i', $colors[$surface])) {
                    continue;
                }
                $ratio = self::contrast($muted, $colors[$surface]);
                $this->assertGreaterThanOrEqual(4.5, $ratio, sprintf('%s: text_muted %s on %s %s is %.2f:1', basename($file), $muted, $surface, $colors[$surface], $ratio));
            }
        }
    }

    public function test_body_text_reads_on_the_page_where_the_category_dropdown_paints_it(): void
    {
        foreach (glob(resource_path('themes/venta/presets/*.json')) as $file) {
            $colors = json_decode((string) file_get_contents($file), true)['colors'] ?? [];
            $ratio = self::contrast($colors['text'], $colors['bg']);
            $this->assertGreaterThanOrEqual(4.5, $ratio, basename($file) . ': text on bg is ' . round($ratio, 2) . ':1');
        }
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channel = function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel(substr($hex, 0, 2)) + 0.7152 * $channel(substr($hex, 2, 2)) + 0.0722 * $channel(substr($hex, 4, 2));
    }

    private static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }
}
