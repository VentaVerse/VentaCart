<?php

namespace App\Support;

/**
 * The style a merchant gives a row, a column or a placed block, as the
 * storefront paints it.
 *
 * One vocabulary serves all three: layout (spacing, height, alignment),
 * shadow, background, border, colours, typography, the block's title, and
 * the advanced choices (an extra CSS class, an anchor, hiding per device).
 *
 * A closed set of keys with closed sets of values. A colour is either a hex
 * or the key of one of the theme's own colours, which renders as that
 * theme's variable so the row keeps following the look. Lengths are whole
 * pixels inside a fixed range, or '' for "as the theme has it". Anything
 * else is dropped, so an import or a hand-made payload can never put a
 * string into a style or class attribute that CSS did not expect.
 *
 * Every key added after the first version defaults to "unset", and an unset
 * key adds no class and no custom property, so a row saved before it existed
 * renders exactly as it did.
 */
final class RowStyle
{
    public const BG = ['none', 'color', 'gradient', 'image'];
    public const PAD = ['none', 'sm', 'md', 'lg', 'xl'];
    /** contained: on the page's measure; full: background edge to edge, content on the measure; edge: both edge to edge. */
    public const WIDTH = ['contained', 'full', 'edge'];
    public const BORDER = ['none', 'solid', 'dashed', 'dotted', 'double'];
    public const RADIUS = ['none', 'sm', 'md', 'lg'];
    /** custom draws the shadow_* values instead of a ready-made depth. */
    public const SHADOW = ['none', 'sm', 'md', 'lg', 'custom'];
    public const SIZE = ['cover', 'contain', 'auto'];
    public const POSITION = ['center', 'top', 'bottom', 'left', 'right', 'top left', 'top right', 'bottom left', 'bottom right'];
    public const REPEAT = ['no-repeat', 'repeat', 'repeat-x', 'repeat-y'];
    public const ATTACHMENT = ['scroll', 'fixed'];
    /** Where a block's own title sits; '' leaves it to the theme. */
    public const TITLE_ALIGN = ['', 'left', 'center', 'right'];
    /** Text size inside a row or block; '' is the theme's. */
    public const TEXT_SIZE = ['', 'sm', 'lg'];
    /** Which of the theme's two faces the row uses for everything; '' leaves each element its own. */
    public const FONT = ['', 'heading', 'body'];
    /** Heading size inside a row or block; '' is the theme's. */
    public const HEADING_SIZE = ['', 'sm', 'lg', 'xl'];
    /** How the content sits vertically when the box is taller than it; '' is the theme's. */
    public const VALIGN = ['', 'top', 'middle', 'bottom', 'stretch'];
    public const TRANSFORM = ['', 'none', 'uppercase', 'lowercase', 'capitalize'];
    public const WEIGHT = ['', '300', '400', '500', '600', '700', '800', '900'];
    /** Which sides of a block title carry its border. */
    public const TITLE_BORDER_SIDE = ['bottom', 'all'];
    /** 'hide' removes the theme's decorative line under a block title. */
    public const TITLE_LINE = ['', 'hide'];
    public const DEVICES = ['phone', 'tablet', 'desktop'];

    /**
     * Pixel lengths: [min, max]. An empty value means unset.
     *
     * @var array<string, array{int, int}>
     */
    public const LENGTHS = [
        'margin_top' => [-200, 400], 'margin_bottom' => [-200, 400],
        'pad_top' => [0, 400], 'pad_right' => [0, 400], 'pad_bottom' => [0, 400], 'pad_left' => [0, 400],
        'gap' => [0, 120], 'min_height' => [0, 1600],
        'border_top' => [0, 20], 'border_right' => [0, 20], 'border_bottom' => [0, 20], 'border_left' => [0, 20],
        'radius_tl' => [0, 200], 'radius_tr' => [0, 200], 'radius_br' => [0, 200], 'radius_bl' => [0, 200],
        'font_size' => [10, 40], 'line_height' => [100, 250],
        'title_size' => [12, 96], 'title_margin_bottom' => [0, 120], 'title_pad_y' => [0, 80], 'title_pad_x' => [0, 80],
    ];

    /**
     * Letter spacing in pixels, to one decimal: [min, max]. Empty is unset.
     *
     * @var array<string, array{int, int}>
     */
    public const SPACINGS = ['heading_spacing' => [-5, 20], 'title_spacing' => [-5, 20]];

    /**
     * Whole numbers that always have a value: [min, max].
     *
     * @var array<string, array{int, int}>
     */
    public const NUMBERS = [
        'bg_angle' => [0, 360], 'border_width' => [1, 8], 'title_border_width' => [1, 8],
        'shadow_x' => [-100, 100], 'shadow_y' => [-100, 100], 'shadow_blur' => [0, 200], 'shadow_spread' => [-100, 100], 'shadow_opacity' => [0, 100],
    ];

    /** Keys holding a colour (hex or theme colour key). */
    public const COLORS = [
        'bg_color', 'bg_color2', 'text', 'border_color', 'heading_color', 'link_color', 'link_hover_color',
        'shadow_color', 'title_color', 'title_bg', 'title_border_color',
    ];

    public const FLAGS = ['shadow_inset', 'hide_phone', 'hide_tablet', 'hide_desktop'];

    /** The most theme colours one box may override, and the most extra classes. */
    public const MAX_PALETTE = 40;

    public const MAX_CLASSES = 6;

    public const DEFAULTS = [
        'bg' => 'none', 'bg_color' => '', 'bg_color2' => '', 'bg_angle' => 180,
        'bg_image' => '', 'bg_size' => 'cover', 'bg_position' => 'center', 'bg_repeat' => 'no-repeat', 'bg_attachment' => 'scroll',
        'text' => '', 'pad' => 'none', 'width' => 'contained',
        'border' => 'none', 'border_width' => 1, 'border_color' => '', 'radius' => 'none', 'shadow' => 'none',
        'title_align' => '',
        // Colours inside the row: text is above; headings and links are their own.
        'heading_color' => '', 'link_color' => '', 'text_size' => '',
        'font' => '', 'heading_size' => '',
        // Layout
        'margin_top' => '', 'margin_bottom' => '',
        'pad_top' => '', 'pad_right' => '', 'pad_bottom' => '', 'pad_left' => '',
        'gap' => '', 'min_height' => '', 'valign' => '',
        // Shadow, drawn when shadow is 'custom'
        'shadow_x' => 0, 'shadow_y' => 6, 'shadow_blur' => 18, 'shadow_spread' => 0,
        'shadow_color' => '', 'shadow_opacity' => 15, 'shadow_inset' => false,
        // Border per side and corner; an unset side keeps border_width, an unset corner keeps radius
        'border_top' => '', 'border_right' => '', 'border_bottom' => '', 'border_left' => '',
        'radius_tl' => '', 'radius_tr' => '', 'radius_br' => '', 'radius_bl' => '',
        // Colours: theme colours overridden inside this box, by theme colour key
        'link_hover_color' => '', 'palette' => [],
        // Typography
        'font_size' => '', 'line_height' => '',
        'heading_transform' => '', 'heading_weight' => '', 'heading_spacing' => '',
        // A block's title
        'title_color' => '', 'title_bg' => '', 'title_size' => '', 'title_transform' => '', 'title_weight' => '', 'title_spacing' => '',
        'title_border' => 'none', 'title_border_side' => 'bottom', 'title_border_width' => 1, 'title_border_color' => '',
        'title_margin_bottom' => '', 'title_pad_y' => '', 'title_pad_x' => '', 'title_line' => '',
        // Words around a block's title: a line under it and a "view all" link
        // beside it. They draw no wrapper of their own; the theme reads them
        // through titleExtras().
        'subtitle' => '', 'title_link_text' => '', 'title_link_url' => '',
        // Advanced
        'css_class' => '', 'anchor' => '', 'hide_phone' => false, 'hide_tablet' => false, 'hide_desktop' => false,
    ];

    /** Padding each ready-made size stands for, top right bottom left. */
    private const PAD_SIDES = [
        'none' => ['0', '0', '0', '0'],
        'sm' => ['0.75rem', '0.75rem', '0.75rem', '0.75rem'],
        'md' => ['1.5rem', '1.5rem', '1.5rem', '1.5rem'],
        'lg' => ['2.5rem', '1.5rem', '2.5rem', '1.5rem'],
        'xl' => ['4rem', '1.5rem', '4rem', '1.5rem'],
    ];

    private const RADIUS_SIZES = ['none' => '0', 'sm' => '0.375rem', 'md' => '0.75rem', 'lg' => '1.25rem'];

    /** @return array<string, mixed> every key present, every value allowed */
    public static function normalize(mixed $style): array
    {
        $in = is_array($style) ? $style : [];
        $out = self::DEFAULTS;

        $pick = fn (string $key, array $allowed) => in_array($in[$key] ?? null, $allowed, true) ? $in[$key] : self::DEFAULTS[$key];

        $out['bg'] = $pick('bg', self::BG);
        $out['title_align'] = $pick('title_align', self::TITLE_ALIGN);
        $out['text_size'] = $pick('text_size', self::TEXT_SIZE);
        $out['font'] = $pick('font', self::FONT);
        $out['heading_size'] = $pick('heading_size', self::HEADING_SIZE);
        $out['bg_image'] = self::path($in['bg_image'] ?? '');
        $out['bg_size'] = $pick('bg_size', self::SIZE);
        $out['bg_position'] = $pick('bg_position', self::POSITION);
        $out['bg_repeat'] = $pick('bg_repeat', self::REPEAT);
        $out['bg_attachment'] = $pick('bg_attachment', self::ATTACHMENT);
        $out['pad'] = $pick('pad', self::PAD);
        $out['width'] = $pick('width', self::WIDTH);
        $out['border'] = $pick('border', self::BORDER);
        $out['radius'] = $pick('radius', self::RADIUS);
        $out['shadow'] = $pick('shadow', self::SHADOW);
        $out['valign'] = $pick('valign', self::VALIGN);
        $out['heading_transform'] = $pick('heading_transform', self::TRANSFORM);
        $out['title_transform'] = $pick('title_transform', self::TRANSFORM);
        $out['title_border'] = $pick('title_border', self::BORDER);
        $out['title_border_side'] = $pick('title_border_side', self::TITLE_BORDER_SIDE);
        $out['title_line'] = $pick('title_line', self::TITLE_LINE);
        // A weight may arrive as a number from a form.
        foreach (['heading_weight', 'title_weight'] as $key) {
            $w = isset($in[$key]) && is_scalar($in[$key]) ? (string) $in[$key] : '';
            $out[$key] = in_array($w, self::WEIGHT, true) ? $w : '';
        }

        foreach (self::COLORS as $key) {
            $out[$key] = self::color($in[$key] ?? '');
        }
        foreach (self::NUMBERS as $key => [$min, $max]) {
            $out[$key] = isset($in[$key]) && is_numeric($in[$key]) ? max($min, min($max, (int) $in[$key])) : self::DEFAULTS[$key];
        }
        foreach (self::LENGTHS as $key => [$min, $max]) {
            $out[$key] = self::length($in[$key] ?? '', $min, $max);
        }
        foreach (self::SPACINGS as $key => [$min, $max]) {
            $v = $in[$key] ?? '';
            $out[$key] = is_numeric($v) ? max($min, min($max, round((float) $v, 1))) : '';
        }
        foreach (self::FLAGS as $key) {
            $out[$key] = filter_var($in[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        $out['subtitle'] = self::words($in['subtitle'] ?? '', 200);
        $out['title_link_text'] = self::words($in['title_link_text'] ?? '', 60);
        $out['title_link_url'] = self::linkUrl($in['title_link_url'] ?? '');

        $out['palette'] = self::palette($in['palette'] ?? []);
        $out['css_class'] = self::classList($in['css_class'] ?? '');
        $out['anchor'] = self::anchor($in['anchor'] ?? '');

        return $out;
    }

    /**
     * A row's style: its own box, plus one style per column (null for a
     * column left as it is). Columns beyond the most a row can have are
     * dropped.
     *
     * @return array<string, mixed>
     */
    public static function normalizeRow(mixed $style): array
    {
        $out = self::normalize($style);
        $in = is_array($style) && is_array($style['column_styles'] ?? null) ? array_values($style['column_styles']) : [];

        $columns = [];
        foreach (array_slice($in, 0, \App\Models\LayoutRow::MAX_COLUMNS) as $column) {
            $columns[] = is_array($column) && self::isStyled($column) ? self::normalize($column) : null;
        }
        while ($columns !== [] && end($columns) === null) {
            array_pop($columns);
        }
        $out['column_styles'] = $columns;

        return $out;
    }

    /** One column's style out of a row's, or null when that column has none. */
    public static function columnStyle(?array $rowStyle, int $index): ?array
    {
        $style = $rowStyle['column_styles'][$index] ?? null;

        return is_array($style) && self::isStyled($style) ? $style : null;
    }

    /** Does any column of this row carry a style of its own? */
    public static function hasColumnStyles(?array $rowStyle): bool
    {
        foreach ((array) ($rowStyle['column_styles'] ?? []) as $i => $style) {
            if (is_array($style) && self::isStyled($style)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The subtitle and title link a block's title carries, for the theme
     * to draw. A link needs both its words and an address.
     *
     * @return array{subtitle: string, link: ?array{text: string, url: string}}
     */
    public static function titleExtras(?array $style): array
    {
        $n = self::normalize($style ?? []);

        return [
            'subtitle' => $n['subtitle'],
            'link' => $n['title_link_text'] !== '' && $n['title_link_url'] !== ''
                ? ['text' => $n['title_link_text'], 'url' => $n['title_link_url']] : null,
        ];
    }

    /** Anything worth keeping: a look the storefront draws, or words around the title. */
    public static function hasAnything(?array $style): bool
    {
        if (self::isStyled($style)) {
            return true;
        }
        $extras = self::titleExtras($style);

        return $extras['subtitle'] !== '' || $extras['link'] !== null;
    }

    /** Plain words on one line, trimmed to $max characters. */
    private static function words(mixed $value, int $max): string
    {
        if (! is_string($value)) {
            return '';
        }
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return mb_substr($value, 0, $max);
    }

    /**
     * An address a shopper may follow: a path on this store, an anchor, or
     * an http(s) page. Anything else (javascript:, data:, a bare word) is
     * dropped.
     */
    private static function linkUrl(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > 500 || preg_match('/[\s<>"\'`]/', $value)) {
            return '';
        }
        if (preg_match('#^(/(?!/)|\#)#', $value) || preg_match('#^https?://[^/\s]+#i', $value)) {
            return $value;
        }

        return '';
    }

    /** Anything at all set away from the defaults that the storefront would draw? */
    public static function isStyled(?array $style): bool
    {
        if (! $style) {
            return false;
        }
        $n = self::normalize($style);

        // classes() always carries the two base classes; anything beyond them
        // is a choice the merchant made. Text colour, a theme colour override
        // and an anchor draw no class of their own.
        return $n['text'] !== '' || $n['pad'] !== 'none' || $n['palette'] !== [] || $n['anchor'] !== ''
            || count(self::classesOf($n, 'x')) > 2;
    }

    /** Classes the stylesheet keys its rules on. @return list<string> */
    public static function classes(array $style, string $prefix = 'layout-row'): array
    {
        return self::classesOf(self::normalize($style), $prefix);
    }

    /**
     * The merchant's values as custom properties on the element. These are
     * data (a chosen colour, a chosen image, a length), not authored styling:
     * the rules that use them live in resources/css/storefront/layout-rows.css.
     *
     * Every class that reads a property is emitted together with it, on the
     * same element, so a nested box never picks up its parent's value through
     * inheritance by accident.
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
            if ($n['bg_color'] !== '') {
                $vars['--row-bg-color'] = self::css($n['bg_color']);
            }
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

        // Everything below is unset on a style saved before these keys
        // existed, so such a style renders the same properties it always did.
        foreach (['margin_top' => '--row-mt', 'margin_bottom' => '--row-mb', 'gap' => '--row-gap', 'min_height' => '--row-min-h',
            'font_size' => '--row-font-size', 'title_size' => '--row-title-size', 'title_margin_bottom' => '--row-title-mb',
            'title_pad_y' => '--row-title-py', 'title_pad_x' => '--row-title-px'] as $key => $var) {
            if ($n[$key] !== '') {
                $vars[$var] = $n[$key] . 'px';
            }
        }
        if (self::hasSides($n, ['pad_top', 'pad_right', 'pad_bottom', 'pad_left'])) {
            $preset = self::PAD_SIDES[$n['pad']];
            $vars['--row-padding'] = implode(' ', array_map(
                fn ($key, $i) => $n[$key] !== '' ? $n[$key] . 'px' : $preset[$i],
                ['pad_top', 'pad_right', 'pad_bottom', 'pad_left'], [0, 1, 2, 3]
            ));
        }
        if ($n['shadow'] === 'custom') {
            $vars['--row-shadow'] = ($n['shadow_inset'] ? 'inset ' : '') . $n['shadow_x'] . 'px ' . $n['shadow_y'] . 'px '
                . $n['shadow_blur'] . 'px ' . $n['shadow_spread'] . 'px ' . self::translucent($n['shadow_color'], $n['shadow_opacity']);
        }
        if ($n['border'] !== 'none' && self::hasSides($n, ['border_top', 'border_right', 'border_bottom', 'border_left'])) {
            $vars['--row-border-width'] = implode(' ', array_map(
                fn ($key) => ($n[$key] !== '' ? $n[$key] : $n['border_width']) . 'px',
                ['border_top', 'border_right', 'border_bottom', 'border_left']
            ));
        }
        if (self::hasSides($n, ['radius_tl', 'radius_tr', 'radius_br', 'radius_bl'])) {
            $vars['--row-radius'] = implode(' ', array_map(
                fn ($key) => $n[$key] !== '' ? $n[$key] . 'px' : self::RADIUS_SIZES[$n['radius']],
                ['radius_tl', 'radius_tr', 'radius_br', 'radius_bl']
            ));
        }
        if ($n['link_hover_color'] !== '') {
            $vars['--row-link-hover'] = self::css($n['link_hover_color']);
        }
        if ($n['line_height'] !== '') {
            $vars['--row-line-height'] = rtrim(rtrim(number_format($n['line_height'] / 100, 2, '.', ''), '0'), '.');
        }
        if ($n['heading_weight'] !== '') {
            $vars['--row-heading-weight'] = $n['heading_weight'];
        }
        if ($n['heading_spacing'] !== '') {
            $vars['--row-heading-spacing'] = $n['heading_spacing'] . 'px';
        }
        if ($n['title_color'] !== '') {
            $vars['--row-title-color'] = self::css($n['title_color']);
        }
        if ($n['title_bg'] !== '') {
            $vars['--row-title-bg'] = self::css($n['title_bg']);
        }
        if ($n['title_weight'] !== '') {
            $vars['--row-title-weight'] = $n['title_weight'];
        }
        if ($n['title_spacing'] !== '') {
            $vars['--row-title-spacing'] = $n['title_spacing'] . 'px';
        }
        if ($n['title_border'] !== 'none') {
            $vars['--row-title-border'] = $n['title_border_width'] . 'px ' . $n['title_border'] . ' ' . (self::css($n['title_border_color']) ?: 'currentColor');
        }
        // The theme's own colours, redefined inside this box: everything the
        // theme paints from them (accents, buttons, cards) follows.
        foreach ($n['palette'] as $key => $hex) {
            $vars['--color-' . str_replace('_', '-', $key)] = $hex;
        }

        return implode('; ', array_map(fn ($k, $v) => $k . ': ' . $v, array_keys($vars), $vars));
    }

    /**
     * The attributes of the element that wears a style: class (the given
     * base classes, the style's classes, the merchant's own), id and style,
     * escaped and ready to print inside a tag.
     *
     * $before are declarations the caller adds ahead of the style's own,
     * already safe (a column's device widths, written from integers).
     *
     * @param  list<string>  $base
     * @param  list<string>  $before
     */
    public static function attributes(array $style, string $prefix, array $base = [], array $before = []): string
    {
        $n = self::normalize($style);
        $classes = array_merge($base, self::classesOf($n, $prefix), $n['css_class'] !== '' ? explode(' ', $n['css_class']) : []);
        $vars = implode('; ', array_filter(array_merge($before, [self::vars($n)])));

        return ' class="' . e(implode(' ', $classes)) . '"'
            . ($n['anchor'] !== '' ? ' id="' . e($n['anchor']) . '"' : '')
            . ($vars !== '' ? ' style="' . e($vars) . '"' : '');
    }

    /** @return list<string> */
    private static function classesOf(array $n, string $prefix): array
    {
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
        if (in_array($n['shadow'], ['sm', 'md', 'lg'], true)) {
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
        if ($n['font'] !== '') {
            $out[] = $prefix . '--font-' . $n['font'];
        }
        if ($n['heading_size'] !== '') {
            $out[] = $prefix . '--headings-' . $n['heading_size'];
        }

        // Keys added later: each one is a class only when set.
        $flags = [
            'edge' => $n['width'] === 'edge',
            'mt' => $n['margin_top'] !== '',
            'mb' => $n['margin_bottom'] !== '',
            'padding' => self::hasSides($n, ['pad_top', 'pad_right', 'pad_bottom', 'pad_left']),
            'gap' => $n['gap'] !== '',
            'min-h' => $n['min_height'] !== '',
            'valign-' . $n['valign'] => $n['valign'] !== '',
            'shadow-custom' => $n['shadow'] === 'custom',
            'bg-under' => $n['bg'] === 'image' && $n['bg_color'] !== '',
            'border-sides' => $n['border'] !== 'none' && self::hasSides($n, ['border_top', 'border_right', 'border_bottom', 'border_left']),
            'corners' => self::hasSides($n, ['radius_tl', 'radius_tr', 'radius_br', 'radius_bl']),
            'links-hover' => $n['link_hover_color'] !== '',
            'fs' => $n['font_size'] !== '',
            'lh' => $n['line_height'] !== '',
            'headings-tt-' . $n['heading_transform'] => $n['heading_transform'] !== '',
            'headings-w' => $n['heading_weight'] !== '',
            'headings-ls' => $n['heading_spacing'] !== '',
            'title-color' => $n['title_color'] !== '',
            'title-bg' => $n['title_bg'] !== '',
            'title-size' => $n['title_size'] !== '',
            'title-tt-' . $n['title_transform'] => $n['title_transform'] !== '',
            'title-w' => $n['title_weight'] !== '',
            'title-ls' => $n['title_spacing'] !== '',
            'title-border-' . $n['title_border_side'] => $n['title_border'] !== 'none',
            'title-mb' => $n['title_margin_bottom'] !== '',
            'title-py' => $n['title_pad_y'] !== '',
            'title-px' => $n['title_pad_x'] !== '',
            'title-noline' => $n['title_line'] === 'hide',
            'hide-phone' => $n['hide_phone'],
            'hide-tablet' => $n['hide_tablet'],
            'hide-desktop' => $n['hide_desktop'],
        ];
        foreach ($flags as $suffix => $on) {
            if ($on) {
                $out[] = $prefix . '--' . $suffix;
            }
        }

        return $out;
    }

    private static function hasSides(array $n, array $keys): bool
    {
        foreach ($keys as $key) {
            if ($n[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    /** A hex colour, or a theme colour key; anything else is nothing. */
    private static function color(mixed $value): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return strtoupper($value);
        }
        if (preg_match('/^[a-z][a-z0-9_]{1,40}$/', $value)) {
            return $value;
        }

        return '';
    }

    /** Whole pixels inside the range, or '' for unset. A trailing "px" is forgiven. */
    private static function length(mixed $value, int $min, int $max): int|string
    {
        if (is_string($value)) {
            $value = preg_replace('/\s*px$/i', '', trim($value));
        }
        if ($value === '' || $value === null || ! is_numeric($value)) {
            return '';
        }

        return max($min, min($max, (int) round((float) $value)));
    }

    /**
     * Theme colours overridden inside one box, by theme colour key. Only a
     * hex may stand in: a theme colour pointing at another theme colour of
     * the same name would be a loop CSS throws away.
     *
     * @return array<string, string>
     */
    private static function palette(mixed $value): array
    {
        $out = [];
        foreach (is_array($value) ? $value : [] as $key => $hex) {
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]{1,40}$/', $key) || ! is_string($hex) || ! preg_match('/^#[0-9a-fA-F]{6}$/', trim($hex))) {
                continue;
            }
            $out[$key] = strtoupper(trim($hex));
            if (count($out) >= self::MAX_PALETTE) {
                break;
            }
        }
        ksort($out);

        return $out;
    }

    /** Extra class names, space separated: plain CSS identifiers only, a handful at most. */
    private static function classList(mixed $value): string
    {
        $tokens = preg_split('/\s+/', is_string($value) ? trim($value) : '', -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_filter($tokens, fn ($t) => preg_match('/^-?[A-Za-z_][A-Za-z0-9_-]{0,63}$/', $t));

        return implode(' ', array_slice(array_values(array_unique($tokens)), 0, self::MAX_CLASSES));
    }

    /** An id a link can jump to: a letter, then letters, digits, dashes and underscores. */
    private static function anchor(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $value) ? $value : '';
    }

    private static function css(string $color): string
    {
        if ($color === '') {
            return '';
        }

        return str_starts_with($color, '#') ? $color : 'var(--color-' . str_replace('_', '-', $color) . ')';
    }

    /** A colour at an opacity: rgba for a hex, a mix with transparent for a theme colour. */
    private static function translucent(string $color, int $opacity): string
    {
        if ($color === '' || str_starts_with($color, '#')) {
            [$r, $g, $b] = $color === '' ? [0, 0, 0] : sscanf($color, '#%02x%02x%02x');

            return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . rtrim(rtrim(number_format($opacity / 100, 2, '.', ''), '0'), '.') . ')';
        }

        return 'color-mix(in srgb, ' . self::css($color) . ' ' . $opacity . '%, transparent)';
    }

    /** A storage path: no scheme, no traversal, no quotes, no backslashes or control characters. */
    private static function path(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || str_contains($value, '..') || preg_match('/[\'"()<>\\\\;{}]|[\x00-\x1F\x7F]/', $value) || preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) || str_starts_with($value, '//')) {
            return '';
        }

        return ltrim($value, '/');
    }

    private static function url(string $path): string
    {
        return '/storage/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
