<?php

namespace Themes\Xelga\Rendering;

use App\Support\ImageRenditions;
use App\Support\SafeInput;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Themes\Xelga\Settings\Sanitizer;

/**
 * A photo stored in the media library, ready for an <img>: its address and,
 * when the file can be read, its width and height, so the page keeps its
 * place while the photo loads. A path that no longer names a file, or
 * names one that is not a JPG, PNG, WebP, AVIF or GIF, gives null, and the
 * view leaves the photo out.
 *
 * The srcset names core's smaller copies of the photo (ImageRenditions);
 * a view prints it with sources(), giving the width the photo is drawn at,
 * so a phone downloads a copy near its own size instead of the upload.
 */
final class Picture
{
    /** The widest the page's content is drawn, in CSS pixels (the --page-width token). */
    public const PAGE_WIDTH = 1280;

    /** The window width from which photos are sized in pixels for PAGE_WIDTH rather than in vw. */
    private const WIDE_FROM = 1320;

    /** @var array<string, ?array> */
    private static array $memo = [];

    /** @return array{src: string, path: string, srcset: string, width: ?int, height: ?int}|null */
    public static function of(mixed $path): ?array
    {
        $path = SafeInput::storagePath($path);
        // Checked again here, not only on save: a path can also come from a
        // core record or an older draft, and only a photo is drawn as one.
        if ($path === '' || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), Sanitizer::IMAGE_EXTENSIONS, true)) {
            return null;
        }
        if (array_key_exists($path, self::$memo)) {
            return self::$memo[$path];
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return self::$memo[$path] = null;
        }

        $width = $height = null;
        try {
            $size = @getimagesize($disk->path($path));
            if (is_array($size)) {
                [$width, $height] = [(int) $size[0], (int) $size[1]];
            }
        } catch (\Throwable) {
            // A disk without local paths: draw the photo without its size.
        }

        return self::$memo[$path] = ['src' => storage_url($path), 'path' => $path, 'srcset' => ImageRenditions::srcset($path), 'width' => $width ?: null, 'height' => $height ?: null];
    }

    /**
     * The srcset and sizes attributes for a photo (a Picture, a product
     * card's image or a product photo), or nothing when it has no smaller
     * copies yet. $sizes is the CSS width the photo is drawn at.
     */
    public static function sources(?array $picture, string $sizes): HtmlString
    {
        $srcset = (string) ($picture['srcset'] ?? '');
        if ($srcset === '') {
            return new HtmlString('');
        }

        return new HtmlString('srcset="' . e($srcset) . '" sizes="' . e($sizes) . '"');
    }

    /**
     * The width a photo that fills its section is drawn at, for a section
     * $span twelfths wide (a column of a row, or the whole page), as the
     * rows' grid lays it out: from 990 pixels every column at its share of
     * the page (at most PAGE_WIDTH wide), from 750 pixels a column of a
     * two-column row keeps its share while three or four columns go two to
     * a line, and below 750 pixels every column fills the screen ($phone).
     * The last of three columns draws across both halves between 750 and
     * 989 pixels; half is the closer guess for the others and costs that
     * one photo a little sharpness only.
     */
    public static function spanSizes(int $span, string $phone = '100vw'): string
    {
        $span = max(1, min(12, $span));
        $wide = (int) ceil(self::PAGE_WIDTH * $span / 12);
        $desktop = (int) ceil(100 * $span / 12);
        $tablet = self::tabletShare($span);

        // A step that draws as wide as the next one says nothing more.
        $steps = [['(min-width: 990px) ', "{$desktop}vw"], ['(min-width: 750px) ', "{$tablet}vw"], ['', $phone]];
        $out = ["(min-width: " . self::WIDE_FROM . "px) {$wide}px"];
        foreach ($steps as $i => [$query, $width]) {
            if (isset($steps[$i + 1]) && $steps[$i + 1][1] === $width) {
                continue;
            }
            $out[] = $query . $width;
        }

        return implode(', ', $out);
    }

    /**
     * The sizes of a photo that takes part of its section: $fullWidth (a
     * width written for a section the whole page wide) on a full-width
     * row, and the column's own share when the section sits in a column,
     * where the photo can be as wide as the column.
     */
    public static function columnSizes(int $span, string $fullWidth): string
    {
        return $span >= 12 ? $fullWidth : self::spanSizes($span);
    }

    /**
     * The sizes of a photo in a grid of $cols to a line (one to a line on a
     * phone when $mobileCols is 1): $fullWidth (written for a section the
     * whole page wide) on a full-width row, and the column's own share of
     * the grid when the section sits in a column.
     */
    public static function gridSizes(int $span, string $fullWidth, mixed $mobileCols, mixed $cols): string
    {
        return $span >= 12 ? $fullWidth : self::cardSizes($mobileCols, $cols, $span);
    }

    /**
     * The width a shared product card's photo is drawn at: the card grid's
     * share of the page (at most PAGE_WIDTH wide) for its columns, inside a
     * section $span twelfths wide.
     */
    public static function cardSizes(mixed $mobileCols = 2, mixed $cols = 4, int $span = 12): string
    {
        $phone = (int) $mobileCols === 1 ? '100vw' : '50vw';
        $cols = max(1, min(6, (int) $cols ?: 4));
        $span = max(1, min(12, $span));
        $wide = (int) ceil(self::PAGE_WIDTH * $span / 12 / $cols);
        $vw = (int) ceil(100 * $span / 12 / $cols);
        // Three cards to a line on a tablet, across the section's share of the screen.
        $tablet = (int) round(self::tabletShare($span) / 3);

        return "(min-width: " . self::WIDE_FROM . "px) {$wide}px, (min-width: 990px) {$vw}vw, (min-width: 750px) {$tablet}vw, {$phone}";
    }

    /** The share of the screen, in vw, a section $span twelfths wide takes between 750 and 989 pixels. */
    private static function tabletShare(int $span): int
    {
        return match (true) {
            $span >= 12 => 100,
            $span >= 8 => 67,
            default => 50,
        };
    }

    public static function flush(): void
    {
        self::$memo = [];
    }
}
