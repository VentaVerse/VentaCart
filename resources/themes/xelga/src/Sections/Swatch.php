<?php

namespace Themes\Xelga\Sections;

/**
 * Color dots for variant values. A value whose name is a known color word
 * ("Navy", "Dark green", "Oat") gets that color; anything else ("Pattern
 * A") gets a plain ring with the name read aloud. The colors live in
 * css/components/swatches.css as one rule per key, so no style is printed
 * in the page; run `php artisan xelga:lint` after changing this list.
 */
final class Swatch
{
    /** @var array<string, string> color word => hex */
    public const COLORS = [
        'black' => '#16181b', 'white' => '#ffffff', 'ivory' => '#f6f1e3', 'cream' => '#f1e8d2', 'oat' => '#ddd0b6',
        'sand' => '#d6c3a0', 'beige' => '#d9c8a9', 'camel' => '#b98a55', 'tan' => '#c19a6b', 'khaki' => '#a8996a',
        'brown' => '#6b4a32', 'chocolate' => '#4b2f20', 'rust' => '#a0472a', 'terracotta' => '#bf5f3e', 'orange' => '#e07a2e',
        'mustard' => '#c9a227', 'yellow' => '#ecc94b', 'gold' => '#c8a24a', 'olive' => '#6b6a2f', 'sage' => '#9aab8f',
        'green' => '#3b7a4a', 'dark-green' => '#1f4a2f', 'forest' => '#24452f', 'mint' => '#a8d5ba', 'teal' => '#2a7a7a',
        'blue' => '#2f5fa8', 'light-blue' => '#9ec3e6', 'sky' => '#8ec0e8', 'navy' => '#1f2a44', 'denim' => '#3b5878',
        'indigo' => '#33367a', 'purple' => '#6a3f8f', 'lilac' => '#c3a9d6', 'lavender' => '#b9a8de', 'plum' => '#5d2e4f',
        'pink' => '#e8a1b6', 'blush' => '#e9bfbf', 'rose' => '#c96d7c', 'red' => '#b8282e', 'wine' => '#6b1f2e',
        'burgundy' => '#6d1f2c', 'maroon' => '#5e1a22', 'coral' => '#e57a63', 'gray' => '#8a8f96', 'grey' => '#8a8f96',
        'light-gray' => '#c9ccd0', 'light-grey' => '#c9ccd0', 'dark-gray' => '#4a4e54', 'dark-grey' => '#4a4e54',
        'charcoal' => '#36393e', 'silver' => '#bfc3c8', 'stone' => '#a9a296', 'taupe' => '#8b7d6b', 'natural' => '#e6dcc6',
        'ecru' => '#e8dfc8', 'linen' => '#e9e1d0', 'mocha' => '#7a5c48', 'espresso' => '#3c2a21', 'clear' => '#eef2f4',
    ];

    /** The color key for a value's name, or '' when it names no known color. */
    public static function key(string $label): string
    {
        $slug = trim((string) preg_replace('/[^a-z]+/', '-', mb_strtolower(trim($label))), '-');

        return isset(self::COLORS[$slug]) ? $slug : '';
    }

    /** The stylesheet rules for every color key. */
    public static function css(): string
    {
        $rules = ['/* Generated from Themes\\Xelga\\Sections\\Swatch::COLORS. */'];
        foreach (self::COLORS as $key => $hex) {
            $rules[] = ".swatch[data-swatch=\"{$key}\"]{--swatch:{$hex}}";
        }

        return implode("\n", $rules) . "\n";
    }
}
