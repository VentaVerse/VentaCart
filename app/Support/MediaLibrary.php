<?php

namespace App\Support;

use App\Models\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Where the store's pictures live, and the rules for touching them.
 *
 * Every filesystem decision the admin makes about images used to be spread
 * across two controllers and five copied Alpine components, each with its own
 * idea of what a safe path was, which extensions counted, and whether the
 * thumbnails were files or noise. It is all here now, so a rule is written
 * once and every picker obeys it.
 */
class MediaLibrary
{
    /**
     * The one tree the admin may touch.
     *
     * Everything the merchant owns lives under here and they may arrange it
     * however they like: rename folders, nest them, make new ones. What used
     * to be five fixed roots that could not see each other are now ordinary
     * folders inside this one, so a product photograph can be used on a blog
     * post without being uploaded twice.
     *
     * It also makes the safety rule a sentence instead of a list. Public
     * storage holds things that are not media and must never reach a picker's
     * delete button: bank-qr with the store's payment codes, identity with its
     * logo, theme, _tmp, and whatever a future package drops there. Before,
     * each of those was safe only because somebody remembered to leave it off
     * an allow list. Now they are safe because they are not in here.
     */
    public const ROOT = 'media';

    /** Made on install, and no more special than a folder the merchant makes. */
    public const DEFAULTS = ['products', 'brands', 'categories', 'blog', 'banners'];

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** Largest upload, in kilobytes. */
    public const MAX_KB = 5120;

    /** Most images one upload request may carry. */
    public const MAX_FILES = 20;

    /**
     * Longest side an image may have, in pixels.
     *
     * Making a thumbnail decodes the whole image at about 4 bytes per pixel.
     * 6000 x 6000 is about 144 MB, which fits a 256 MB PHP memory limit with
     * room for the thumbnail; 8000 x 8000 needs about 256 MB on its own and
     * ends the request with a fatal error that no catch block can handle.
     */
    public const MAX_SIDE = 6000;

    /** Images per page when browsing. */
    public const PER_PAGE = 60;

    /** Seconds the folder tree stays cached. */
    public const TREE_TTL = 600;

    /** Cache key of the folder tree, cleared whenever files or folders change. */
    public const TREE_CACHE_KEY = 'media.tree';

    /**
     * Where a picker starts. A caller still says "products" and means the
     * usual place for product photographs, but it is a starting point now,
     * not a cage: the up tile walks out of it into the rest of the library.
     */
    public static function root(?string $root): string
    {
        $root = trim(str_replace('\\', '/', (string) $root), '/');

        if ($root === '' || $root === self::ROOT) {
            return self::ROOT;
        }

        // Accept both "products" and "media/products", so every existing
        // caller keeps working and nothing has to know about the prefix.
        $path = str_starts_with($root, self::ROOT . '/') ? $root : self::ROOT . '/' . $root;

        return self::safePath($path) ?: self::ROOT;
    }

    /**
     * A path we are willing to act on, or false.
     *
     * Traversal was the only thing the old check looked for, and the `root`
     * parameter was only ever a starting folder: once any path was supplied
     * it was listed directly, so a product picker could walk into bank-qr/
     * and hand the store's payment QR code to a delete button.
     *
     * One tree, and the answer is whether the path is in it. Nothing outside
     * the library is media, and nothing outside it can be reached, listed,
     * written to or deleted through here.
     */
    public static function safePath(?string $path): string|false
    {
        $path = trim(str_replace('\\', '/', (string) $path), '/');

        if ($path === '' || $path === self::ROOT) {
            return self::ROOT;
        }

        if (str_contains($path, '..') || str_contains($path, './') || str_starts_with($path, '.')) {
            return false;
        }

        return str_starts_with($path, self::ROOT . '/') ? $path : false;
    }

    public static function ensure(string $path): void
    {
        if ($path !== '' && ! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->makeDirectory($path);
        }
    }

    /** @return array<int, array{name: string, path: string}> */
    public static function foldersIn(string $path): array
    {
        return collect(Storage::disk('public')->directories($path))
            // Every folder inside the library is fair game; the filter is what
            // stops a listing of the disk root offering identity or bank-qr.
            ->filter(fn ($dir) => self::safePath($dir) !== false)
            ->map(fn ($dir) => ['name' => basename($dir), 'path' => $dir])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()->all();
    }

    /** @return array<int, string> */
    public static function filesIn(string $path): array
    {
        return collect(Storage::disk('public')->files($path))
            ->filter(fn ($f) => self::isImage($f))
            ->values()->all();
    }

    /**
     * Every image under a root whose name matches.
     *
     * Search used to filter the folder you were standing in, which meant a
     * merchant had to already know where a photo was to find it.
     *
     * @return array<int, string>
     */
    public static function search(string $root, string $term): array
    {
        $term = mb_strtolower($term);

        // Matched against the path, not just the file's own name. A merchant
        // who files photos in a folder per brand and types the brand's name
        // means everything in that folder; matching only file names would
        // find none of it.
        return collect(Storage::disk('public')->allFiles($root))
            ->filter(fn ($f) => self::isImage($f))
            ->filter(fn ($f) => str_contains(mb_strtolower($f), $term))
            ->values()->all();
    }

    /** @param array<int, string> $files */
    public static function sort(array $files, string $by): array
    {
        $disk = Storage::disk('public');

        if ($by === 'name') {
            usort($files, fn ($a, $b) => strnatcasecmp(basename($a), basename($b)));

            return $files;
        }

        $times = [];
        foreach ($files as $f) {
            $times[$f] = $disk->lastModified($f);
        }
        usort($files, fn ($a, $b) => $times[$b] <=> $times[$a]);

        return $files;
    }

    /**
     * The shape every picker renders.
     *
     * `thumb` is the small copy made on upload, so a grid of tiles does not
     * load every full-size original. `width` and `height` are null when the
     * file cannot be read as an image. `folder` is the folder the image is in.
     *
     * @param  array<int, string>  $files
     * @param  array<string, int>  $usage
     */
    public static function describe(array $files, array $usage): array
    {
        $disk = Storage::disk('public');
        $sizes = self::dimensions($files);

        return collect($files)->map(fn ($path) => [
            'name' => basename($path),
            'path' => $path,
            'folder' => self::parent($path, self::ROOT) ?? '',
            'url' => storage_url($path),
            'thumb' => thumb_url($path),
            'size' => $disk->exists($path) ? $disk->size($path) : 0,
            'modified' => $disk->exists($path) ? $disk->lastModified($path) : 0,
            'width' => $sizes[$path]['width'] ?? null,
            'height' => $sizes[$path]['height'] ?? null,
            'usedBy' => (int) ($usage[$path] ?? 0),
        ])->values()->all();
    }

    /**
     * Width and height for each path, null when the file cannot be read as
     * an image.
     *
     * Stored rows are used as they are. A path with no row (a file copied
     * onto the disk rather than uploaded) is measured from its header and
     * stored, so it is measured once. Two requests can measure the same new
     * file at the same time, so the row is written with insertOrIgnore: the
     * second insert is dropped instead of failing on the unique path.
     *
     * @param  array<int, string>  $paths
     * @return array<string, array{width: int, height: int}|null>
     */
    public static function dimensions(array $paths): array
    {
        $paths = array_values(array_unique(array_map('strval', $paths)));
        $wanted = array_flip($paths);
        $out = array_fill_keys($paths, null);

        foreach (array_chunk($paths, 500) as $chunk) {
            foreach (MediaFile::query()->whereIn('path', $chunk)->get(['path', 'width', 'height']) as $row) {
                // The database narrows; the exact match is decided here, so a
                // case-insensitive collation cannot hand one path another's row.
                if (isset($wanted[$row->path]) && $out[$row->path] === null) {
                    $out[$row->path] = ['width' => (int) $row->width, 'height' => (int) $row->height];
                }
            }
        }

        $now = now();
        $rows = [];

        foreach ($out as $path => $size) {
            if ($size !== null || ($measured = self::measure($path)) === null) {
                continue;
            }
            $out[$path] = $measured;
            $rows[] = ['path' => $path, ...$measured, 'created_at' => $now, 'updated_at' => $now];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            MediaFile::query()->insertOrIgnore($chunk);
        }

        return $out;
    }

    /** Stores the width and height of a file just written, replacing any old row. */
    public static function record(string $path): void
    {
        $size = self::measure($path);

        if ($size === null) {
            return;
        }

        $now = now();
        MediaFile::query()->upsert(
            [['path' => $path, ...$size, 'created_at' => $now, 'updated_at' => $now]],
            ['path'],
            ['width', 'height', 'updated_at'],
        );
    }

    /** The folder tree is cached; anything that adds or removes files or folders clears it. */
    public static function touched(): void
    {
        Cache::forget(self::TREE_CACHE_KEY);
    }

    /** @return array<int, array{name: string, path: string}> */
    public static function crumbs(string $path, string $root): array
    {
        if ($path === '' || $path === self::ROOT) {
            return [];
        }

        // The trail always starts at the library itself, whatever folder the
        // picker was aimed at, because that is now somewhere you can walk back
        // to rather than the edge of the world.
        $parts = explode('/', $path);
        $out = [];
        $so_far = '';

        foreach ($parts as $part) {
            $so_far = ltrim($so_far . '/' . $part, '/');
            $out[] = ['name' => $part, 'path' => $so_far];
        }

        return $out;
    }

    /** Null only at the library itself, where up really is nowhere. */
    public static function parent(string $path, string $root): ?string
    {
        if ($path === '' || $path === self::ROOT || ! str_contains($path, '/')) {
            return null;
        }

        return substr($path, 0, strrpos($path, '/'));
    }

    public static function store(UploadedFile $file, string $directory): string
    {
        $extension = ImageExtension::orRefuse($file, 'files');
        $path = $file->storeAs($directory, self::filename($file->getClientOriginalName(), $directory, $extension), 'public');

        self::thumbnail($path);
        self::record($path);
        self::touched();

        return $path;
    }

    public static function putBytes(string $bytes, string $mime, string $directory, string $sourceUrl): string
    {
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? 'jpg';
        $name = self::filename(basename(parse_url($sourceUrl, PHP_URL_PATH) ?: 'image'), $directory, $ext);
        $path = ltrim($directory . '/' . $name, '/');

        Storage::disk('public')->put($path, $bytes);
        self::thumbnail($path);
        self::record($path);
        self::touched();

        return $path;
    }

    /** The file, its thumbnail and its stored dimensions leave together. */
    public static function forget(string $path): void
    {
        $disk = Storage::disk('public');
        $disk->delete($path);

        $info = pathinfo($path);
        $disk->delete($info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg'));
        ImageRenditions::forget($path);

        // Deleted by primary key after an exact match, so a collation that
        // ignores case cannot take a case twin's row with it.
        $ids = MediaFile::query()->where('path', $path)->get(['id', 'path'])
            ->filter(fn (MediaFile $row) => $row->path === $path)
            ->pluck('id')->all();

        if ($ids !== []) {
            MediaFile::query()->whereKey($ids)->delete();
        }

        self::touched();
    }

    /**
     * Renaming or moving a file or folder is more than a change on disk:
     * every stored copy of the path has to change with it, including the
     * snapshot on past order lines, or those records show a broken image.
     */

    private static function isImage(string $path): bool
    {
        if (str_contains($path, '_thumb.')) {
            return false;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    /** Keeps the merchant's own name, made safe and made unique. */
    private static function filename(string $original, string $directory, string $extension): string
    {
        $stem = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'image';
        $ext = strtolower($extension ?: 'jpg');
        $name = $stem . '.' . $ext;
        $n = 2;

        while (Storage::disk('public')->exists(ltrim($directory . '/' . $name, '/'))) {
            $name = $stem . '-' . $n++ . '.' . $ext;
        }

        return $name;
    }

    /** @return array{width: int, height: int}|null */
    private static function measure(string $path): ?array
    {
        $disk = Storage::disk('public');

        if (! self::isImage($path) || ! $disk->exists($path)) {
            return null;
        }

        $meta = @getimagesize($disk->path($path));

        if (! $meta || $meta[0] < 1 || $meta[1] < 1) {
            return null;
        }

        return ['width' => (int) $meta[0], 'height' => (int) $meta[1]];
    }

    private static function thumbnail(string $path): void
    {
        ImageRenditions::queue($path);

        if (! extension_loaded('gd')) {
            return;
        }

        try {
            $full = Storage::disk('public')->path($path);
            $meta = @getimagesize($full);

            // Decoding needs about 4 bytes per pixel. A file larger than any
            // upload may be (copied onto the disk, or fetched from a web
            // address) is left without a thumbnail rather than exhausting
            // memory, which no catch block can recover from.
            if (! $meta || $meta[0] > self::MAX_SIDE || $meta[1] > self::MAX_SIDE) {
                return;
            }

            $src = match ($meta['mime']) {
                'image/jpeg' => @imagecreatefromjpeg($full),
                'image/png' => @imagecreatefrompng($full),
                'image/gif' => @imagecreatefromgif($full),
                'image/webp' => @imagecreatefromwebp($full),
                default => null,
            };

            if (! $src) {
                return;
            }

            $ratio = min(300 / imagesx($src), 300 / imagesy($src));

            if ($ratio >= 1) {
                imagedestroy($src);

                return;
            }

            $w = (int) round(imagesx($src) * $ratio);
            $h = (int) round(imagesy($src) * $ratio);
            $dst = imagecreatetruecolor($w, $h);

            if (in_array($meta['mime'], ['image/png', 'image/gif', 'image/webp'], true)) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefilledrectangle($dst, 0, 0, $w, $h, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            }

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));

            $info = pathinfo($path);
            $out = Storage::disk('public')->path($info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg'));

            match ($meta['mime']) {
                'image/jpeg' => imagejpeg($dst, $out, 82),
                'image/png' => imagepng($dst, $out, 8),
                'image/gif' => imagegif($dst, $out),
                'image/webp' => imagewebp($dst, $out, 82),
                default => null,
            };

            imagedestroy($src);
            imagedestroy($dst);
        } catch (\Throwable $e) {
            // A picture without a thumbnail is a slow tile, not a failed upload.
        }
    }
}
