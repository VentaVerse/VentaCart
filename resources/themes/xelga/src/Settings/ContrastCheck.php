<?php

namespace Themes\Xelga\Settings;

use Themes\Xelga\Catalog\Catalog;

/**
 * WCAG contrast for the color schemes: text on its background, secondary
 * text, button text and text on the accent. A scheme below the line saves
 * as a draft with a warning, and Publish refuses it, naming the scheme and
 * the pair.
 */
final class ContrastCheck
{
    /** Contrast ratio between two 6-digit hex colors, 1 to 21. */
    public static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        [$hi, $lo] = $la >= $lb ? [$la, $lb] : [$lb, $la];

        return round(($hi + 0.05) / ($lo + 0.05), 2);
    }

    /**
     * Every pair below its minimum, across the schemes given.
     *
     * @param  list<array{id: string, label?: string, colors: array<string, string>}>  $schemes
     * @return list<array{scheme: string, label: string, pair: string, fg: string, bg: string, ratio: float, min: float, message: string}>
     */
    public static function failures(array $schemes): array
    {
        $checks = (array) (Catalog::schemeSetting()->raw['checks'] ?? []);
        $out = [];

        foreach ($schemes as $scheme) {
            $colors = (array) ($scheme['colors'] ?? []);
            // Schemes are shown by their colors, never by a stored name, so a
            // message names one by its number.
            $label = 'Scheme ' . preg_replace('/^scheme-/', '', (string) $scheme['id']);
            foreach ($checks as $check) {
                $fg = $colors[$check['fg']] ?? null;
                $bg = $colors[$check['bg']] ?? null;
                if (! is_string($fg) || ! is_string($bg) || ! self::isHex($fg) || ! self::isHex($bg)) {
                    continue;
                }
                $ratio = self::ratio($fg, $bg);
                $min = (float) $check['min'];
                if ($ratio < $min) {
                    $out[] = [
                        'scheme' => (string) $scheme['id'],
                        'label' => $label,
                        'pair' => (string) $check['label'],
                        'fg' => (string) $check['fg'],
                        'bg' => (string) $check['bg'],
                        'ratio' => $ratio,
                        'min' => $min,
                        'message' => sprintf('%s: %s is %s to 1; it needs at least %s to 1 to be readable.', $label, $check['label'], self::format($ratio), self::format($min)),
                    ];
                }
            }
        }

        return $out;
    }

    private static function format(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    private static function isHex(string $color): bool
    {
        return preg_match('/^#[0-9a-f]{6}$/i', $color) === 1;
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        $linear = array_map(function (int $c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $channels);

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
