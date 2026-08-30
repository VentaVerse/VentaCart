<?php

namespace App\Support;

/**
 * The style a merchant gives one row of a region, as the storefront paints it.
 *
 * A closed set of keys with closed sets of values. A colour is either a hex
 * or the key of one of the theme's own colours, which renders as that
 * theme's variable so the row keeps following the look. Anything else is
 * dropped, so an import or a hand-made payload can never put a string into
 * a style attribute that CSS did not expect.
 */
final class RowStyle
{
    public const BG = ['none', 'color', 'gradient', 'image'];
    public const PAD = ['none', 'sm', 'md', 'lg', 'xl'];
    public const WIDTH = ['contained', 'full'];
    public const BORDER = ['none', 'solid', 'dashed', 'dotted'];
    public const RADIUS = ['none', 'sm', 'md', 'lg'];
    public const SHADOW = ['none', 'sm', 'md', 'lg'];
    public const SIZE = ['cover', 'contain', 'auto'];
    public const POSITION = ['center', 'top', 'bottom', 'left', 'right'];
    public const REPEAT = ['no-repeat', 'repeat'];
    public const ATTACHMENT = ['scroll', 'fixed'];
    /** Where a block's own title sits; '' leaves it to the theme. */
    public const TITLE_ALIGN = ['', 'left', 'center', 'right'];
    /** Text size inside a row or block; '' is the theme's. */
    public const TEXT_SIZE = ['', 'sm', 'lg'];

    public const DEFAULTS = [
        'bg' => 'none', 'bg_color' => '', 'bg_color2' => '', 'bg_angle' => 180,
        'bg_image' => '', 'bg_size' => 'cover', 'bg_position' => 'center', 'bg_repeat' => 'no-repeat', 'bg_attachment' => 'scroll',
        'text' => '', 'pad' => 'none', 'width' => 'contained',
        'border' => 'none', 'border_width' => 1, 'border_color' => '', 'radius' => 'none', 'shadow' => 'none',
        'title_align' => '',
        // Colours inside the row: text is above; headings and links are their own.
        'heading_color' => '', 'link_color' => '', 'text_size' => '',
    ];

    /** @return array<string, mixed> every key present, every value allowed */
    public static function normalize(mixed $style): array
    {
        $in = is_array($style) ? $style : [];
        $out = self::DEFAULTS;

        $pick = fn (string $key, array $allowed) => in_array($in[$key] ?? null, $allowed, true) ? $in[$key] : self::DEFAULTS[$key];

        $out['bg'] = $pick('bg', self::BG);
        $out['title_align'] = $pick('title_align', self::TITLE_ALIGN);
        $out['heading_color'] = self::color($in['heading_color'] ?? '');
        $out['link_color'] = self::color($in['link_color'] ?? '');
        $out['text_size'] = $pick('text_size', self::TEXT_SIZE);
        $out['bg_color'] = self::color($in['bg_color'] ?? '');
        $out['bg_color2'] = self::color($in['bg_color2'] ?? '');
        $out['bg_angle'] = max(0, min(360, (int) ($in['bg_angle'] ?? 180)));
        $out['bg_image'] = self::path($in['bg_image'] ?? '');
        $out['bg_size'] = $pick('bg_size', self::SIZE);
        $out['bg_position'] = $pick('bg_position', self::POSITION);
        $out['bg_repeat'] = $pick('bg_repeat', self::REPEAT);
        $out['bg_attachment'] = $pick('bg_attachment', self::ATTACHMENT);
        $out['text'] = self::color($in['text'] ?? '');
        $out['pad'] = $pick('pad', self::PAD);
        $out['width'] = $pick('width', self::WIDTH);
        $out['border'] = $pick('border', self::BORDER);
        $out['border_width'] = max(1, min(8, (int) ($in['border_width'] ?? 1)));
        $out['border_color'] = self::color($in['border_color'] ?? '');
        $out['radius'] = $pick('radius', self::RADIUS);
        $out['shadow'] = $pick('shadow', self::SHADOW);

        return $out;
    }

    /** Anything at all set away from the defaults? */
    public static function isStyled(?array $style): bool
    {
        if (! $style) {
            return false;
        }
        $n = self::normalize($style);

        return $n['bg'] !== 'none' || $n['text'] !== '' || $n['pad'] !== 'none' || $n['width'] !== 'contained'
            || $n['border'] !== 'none' || $n['radius'] !== 'none' || $n['shadow'] !== 'none' || $n['title_align'] !== ''
            || $n['heading_color'] !== '' || $n['link_color'] !== '' || $n['text_size'] !== '';
    }

    /** Classes the stylesheet keys its rules on. @return list<string> */
    public static function classes(array $style, string $prefix = 'layout-row'): array
    {
        $n = self::normalize($style);
        $out = [$prefix . '--styled', $prefix . '--pad-' . $n['pad']];
        if ($n['width'] === 'full') {
            $out[] = $prefix . '--full';
        }
        if ($n['bg'] !== 'none') {
            $out[] = $prefix . '--bg';
        }
        if ($n['border'] !== 'none') {
            $out[] = $prefix . '--border';
        }
        if ($n['radius'] !== 'none') {
            $out[] = $prefix . '--radius-' . $n['radius'];
        }
        if ($n['shadow'] !== 'none') {
            $out[] = $prefix . '--shadow-' . $n['shadow'];
        }
        if ($n['title_align'] !== '') {
            $out[] = $prefix . '--title-' . $n['title_align'];
        }
        if ($n['heading_color'] !== '') {
            $out[] = $prefix . '--headings';
        }
        if ($n['link_color'] !== '') {
            $out[] = $prefix . '--links';
        }
        if ($n['text_size'] !== '') {
            $out[] = $prefix . '--size-' . $n['text_size'];
        }

        return $out;
    }

    /**
     * The merchant's values as custom properties on the row element. These
     * are data (a chosen colour, a chosen image), not authored styling: the
     * rules that use them live in the stylesheet.
     */
    public static function vars(array $style): string
    {
        $n = self::normalize($style);
        $vars = [];

        $bg = match ($n['bg']) {
            'color' => self::css($n['bg_color']),
            'gradient' => 'linear-gradient(' . $n['bg_angle'] . 'deg, ' . (self::css($n['bg_color']) ?: 'transparent') . ', ' . (self::css($n['bg_color2']) ?: 'transparent') . ')',
            'image' => $n['bg_image'] !== '' ? 'url(' . self::url($n['bg_image']) . ')' : '',
            default => '',
        };
        if ($bg !== '') {
            $vars['--row-bg'] = $bg;
        }
        if ($n['bg'] === 'image') {
            $vars['--row-bg-size'] = $n['bg_size'];
            $vars['--row-bg-position'] = $n['bg_position'];
            $vars['--row-bg-repeat'] = $n['bg_repeat'];
            $vars['--row-bg-attachment'] = $n['bg_attachment'];
        }
        if ($n['text'] !== '') {
            $vars['--row-text'] = self::css($n['text']);
        }
        if ($n['heading_color'] !== '') {
            $vars['--row-heading'] = self::css($n['heading_color']);
        }
        if ($n['link_color'] !== '') {
            $vars['--row-link'] = self::css($n['link_color']);
        }
        if ($n['border'] !== 'none') {
            $vars['--row-border'] = $n['border_width'] . 'px ' . $n['border'] . ' ' . (self::css($n['border_color']) ?: 'currentColor');
        }

        return implode('; ', array_map(fn ($k, $v) => $k . ': ' . $v, array_keys($vars), $vars));
    }

    /** A hex colour, or a theme colour key; anything else is nothing. */
    private static function color(mixed $value): string
    {
        $value = trim((string) $value);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return strtoupper($value);
        }
        if (preg_match('/^[a-z][a-z0-9_]{1,40}$/', $value)) {
            return $value;
        }

        return '';
    }

    private static function css(string $color): string
    {
        if ($color === '') {
            return '';
        }

        return str_starts_with($color, '#') ? $color : 'var(--color-' . str_replace('_', '-', $color) . ')';
    }

    /** A storage path: no scheme, no traversal, no quotes. */
    private static function path(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || str_contains($value, '..') || preg_match('/[\'"()<>]/', $value) || preg_match('#^[a-z]+:#i', $value)) {
            return '';
        }

        return ltrim($value, '/');
    }

    private static function url(string $path): string
    {
        return '/storage/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
