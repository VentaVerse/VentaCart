<?php

namespace Extensions\Banners;

use App\Support\SafeInput;

/**
 * The one definition of what a slide and its layers may hold.
 *
 * Themes draw slide fields straight into links, inline styles and Alpine
 * expressions, so a layer's animation, a colour or a position is code as
 * much as data. Saving checks every field against this list, and the
 * storefront payload passes through it again, so a value stored before
 * these checks existed (or written by an import) reaches no theme raw.
 */
final class BannerData
{
    /** Animations the storefront stylesheet defines (banner-anim-{name}). */
    public const ANIMATIONS = [
        'none',
        'fade-in', 'fade-up', 'fade-down', 'fade-left', 'fade-right',
        'zoom-in', 'zoom-out',
        'slide-up', 'slide-down', 'slide-left', 'slide-right',
        'bounce-in', 'bounce-up',
        'rotate-in', 'flip-x', 'flip-y',
    ];

    /** How the background image fills the slide (CSS background-size). */
    public const BG_SIZES = ['cover', 'contain', 'auto', '100% 100%'];

    public const LAYER_TYPES = ['text', 'image', 'button'];

    public const FONT_WEIGHTS = ['100', '200', '300', '400', '500', '600', '700', '800', '900'];

    public const TEXT_TRANSFORMS = ['none', 'uppercase', 'lowercase', 'capitalize'];

    public const MAX_LAYERS = 30;

    public const CONTENT_MAX = 1000;

    /**
     * Every layer cleaned, in order. A layer of an unknown type, or one that
     * is not a list of fields, is dropped.
     *
     * @return list<array<string, mixed>>
     */
    public static function layers(mixed $layers): array
    {
        if (! is_array($layers)) {
            return [];
        }

        $out = [];
        foreach (array_slice(array_values($layers), 0, self::MAX_LAYERS) as $layer) {
            $clean = self::layer($layer);
            if ($clean !== null) {
                $out[] = $clean;
            }
        }

        return $out;
    }

    /**
     * One layer with only the fields its type uses, each in its own shape:
     * positions as "Npx" or "N%", sizes and timings clamped, colours as hex,
     * choices from their lists, the link and the image path checked.
     *
     * @return array<string, mixed>|null
     */
    public static function layer(mixed $layer): ?array
    {
        if (! is_array($layer)) {
            return null;
        }

        $type = SafeInput::oneOf($layer['type'] ?? null, self::LAYER_TYPES);
        if ($type === null) {
            return null;
        }

        $out = [
            'type' => $type,
            'x' => self::position($layer['x'] ?? '0'),
            'y' => self::position($layer['y'] ?? '0'),
            'opacity' => SafeInput::int($layer['opacity'] ?? 100, 0, 100, 100),
            'animation' => SafeInput::oneOf($layer['animation'] ?? 'none', self::ANIMATIONS, 'none'),
            'delay' => SafeInput::int($layer['delay'] ?? 0, 0, 30000, 0),
            'duration' => SafeInput::int($layer['duration'] ?? 800, 0, 30000, 800),
            'distance' => SafeInput::int($layer['distance'] ?? 30, 0, 2000, 30),
        ];

        if ($type === 'image') {
            $height = $layer['height'] ?? null;

            return $out + [
                'src' => SafeInput::storagePath($layer['src'] ?? ''),
                'width' => SafeInput::int($layer['width'] ?? 200, 1, 5000, 200),
                'height' => $height === null || $height === '' ? null : SafeInput::int($height, 1, 5000),
            ];
        }

        $out += [
            'content' => SafeInput::text($layer['content'] ?? '', self::CONTENT_MAX, true),
            'fontSize' => SafeInput::int($layer['fontSize'] ?? ($type === 'button' ? 14 : 24), 1, 400, 24),
            'fontWeight' => SafeInput::oneOf($layer['fontWeight'] ?? null, self::FONT_WEIGHTS, $type === 'button' ? '600' : '400'),
            'color' => SafeInput::color($layer['color'] ?? null) ?: ($type === 'button' ? '#000000' : '#ffffff'),
            'letterSpacing' => SafeInput::number($layer['letterSpacing'] ?? 0, -50, 100, 0),
            'textTransform' => SafeInput::oneOf($layer['textTransform'] ?? 'none', self::TEXT_TRANSFORMS, 'none'),
        ];

        if ($type === 'button') {
            $out += [
                'url' => SafeInput::link($layer['url'] ?? ''),
                'bgColor' => SafeInput::color($layer['bgColor'] ?? null) ?: '#ffffff',
                'borderRadius' => SafeInput::int($layer['borderRadius'] ?? 0, 0, 999, 0),
                'paddingX' => SafeInput::int($layer['paddingX'] ?? 32, 0, 500, 32),
                'paddingY' => SafeInput::int($layer['paddingY'] ?? 12, 0, 500, 12),
            ];
        }

        return $out;
    }

    /**
     * Why a layer list cannot be saved, one message per refused layer, so
     * the merchant is told rather than finding a link silently gone.
     *
     * @return list<string>
     */
    public static function problems(mixed $layers): array
    {
        if (! is_array($layers)) {
            return ['The layers could not be read. Reload the page and try again.'];
        }

        $problems = [];
        if (count($layers) > self::MAX_LAYERS) {
            $problems[] = 'A slide can hold up to ' . self::MAX_LAYERS . ' layers.';
        }

        foreach (array_values($layers) as $i => $layer) {
            $n = $i + 1;
            if (! is_array($layer) || SafeInput::oneOf($layer['type'] ?? null, self::LAYER_TYPES) === null) {
                $problems[] = "Layer {$n} is not a text, image or button layer.";

                continue;
            }
            $url = $layer['url'] ?? '';
            if (($layer['type'] === 'button') && is_scalar($url) && trim((string) $url) !== '' && SafeInput::link($url) === '') {
                $problems[] = "Layer {$n}: the button link must be a path such as /sale, an anchor such as #top, or an http(s) address, with no spaces.";
            }
            $src = $layer['src'] ?? '';
            if ($layer['type'] === 'image' && is_scalar($src) && trim((string) $src) !== '' && SafeInput::storagePath($src) === '') {
                $problems[] = "Layer {$n}: choose the image from the media library.";
            }
        }

        return $problems;
    }

    /**
     * A position as the editor writes it: a number of pixels or a percentage.
     * A bare number is pixels. Anything else is 0px.
     */
    public static function position(mixed $value): string
    {
        $value = is_scalar($value) && ! is_bool($value) ? strtolower(trim((string) $value)) : '';

        if (preg_match('/^(-?\d{1,5}(?:\.\d{1,2})?)(px|%)?$/', $value, $m) !== 1) {
            return '0px';
        }

        if (($m[2] ?? '') === '%') {
            return SafeInput::number($m[1], -100, 200, 0) . '%';
        }

        return SafeInput::number($m[1], -5000, 5000, 0) . 'px';
    }

    public static function bgSize(mixed $value): string
    {
        return SafeInput::oneOf($value, self::BG_SIZES, 'cover');
    }
}
