<?php

namespace Themes\Xelga\Rendering;

use App\Support\SafeInput;
use Illuminate\Support\Facades\Storage;
use Themes\Xelga\Settings\Sanitizer;

/**
 * A photo stored in the media library, ready for an <img>: its address and,
 * when the file can be read, its width and height, so the page keeps its
 * place while the photo loads. A path that no longer names a file, or
 * names one that is not a JPG, PNG, WebP, AVIF or GIF, gives null, and the
 * view leaves the photo out.
 */
final class Picture
{
    /** @var array<string, ?array> */
    private static array $memo = [];

    /** @return array{src: string, path: string, width: ?int, height: ?int}|null */
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

        return self::$memo[$path] = ['src' => storage_url($path), 'path' => $path, 'width' => $width ?: null, 'height' => $height ?: null];
    }

    public static function flush(): void
    {
        self::$memo = [];
    }
}
