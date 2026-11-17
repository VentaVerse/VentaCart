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
     * The width a shared product card's photo is drawn at: the card grid's
     * share of the page (at most 1280 pixels wide) for its columns.
     */
    public static function cardSizes(mixed $mobileCols = 2, mixed $cols = 4): string
    {
        $phone = (int) $mobileCols === 1 ? '100vw' : '50vw';
        $cols = max(2, min(6, (int) $cols ?: 4));
        $wide = (int) ceil(1280 / $cols);
        $vw = (int) ceil(100 / $cols);

        return "(min-width: 1320px) {$wide}px, (min-width: 990px) {$vw}vw, (min-width: 750px) 33vw, {$phone}";
    }

    public static function flush(): void
    {
        self::$memo = [];
    }
}
