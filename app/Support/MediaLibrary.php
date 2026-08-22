<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
    /** The folders the admin offers as a starting point. */
    public const ROOTS = ['products', 'brands', 'categories', 'blog', 'banners'];

    /**
     * Everywhere a picker may go, which is the roots plus the retired
     * OpenCart tree.
     *
     * `catalog/` is not offered as a root and is not where anything new is
     * written, but 17 live products still point into its 1,904 files, so
     * blocking it outright would make those images unpickable. It stays
     * reachable and stays unadvertised.
     */
    private const REACHABLE = ['products', 'brands', 'categories', 'blog', 'banners', 'catalog'];

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public static function root(?string $root): string
    {
        return in_array($root, self::ROOTS, true) ? $root : 'products';
    }

    /**
     * A path we are willing to act on, or false.
     *
     * Traversal was the only thing the old check looked for, and the `root`
     * parameter was only ever a starting folder: once any path was supplied
     * it was listed directly, so a product picker could walk into bank-qr/
     * and hand the store's payment QR code to a delete button.
     *
     * An ALLOW list rather than a deny list, because a deny list only refuses
     * the folders somebody remembered. Anything that is not a media root is
     * not media: identity, bank-qr, theme, _tmp, and whatever a future
     * package drops on the public disk.
     */
    public static function safePath(?string $path): string|false
    {
        $path = trim(str_replace('\\', '/', (string) $path), '/');

        if ($path === '') {
            return '';
        }

        if (str_contains($path, '..') || str_contains($path, './') || str_starts_with($path, '.')) {
            return false;
        }

        $head = explode('/', $path)[0];

        return in_array($head, self::REACHABLE, true) ? $path : false;
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
            // At the disk root the only folders offered are media roots; deeper
            // in, every subfolder of a reachable root is fair game.
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
        // typing "wampler" means everything Wampler, and the brand is the
        // FOLDER; matching only basenames returned nothing for the commonest
        // search anyone would run.
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
     * `thumb` is the point: 4,959 small versions were being generated and then
     * excluded from browse, so every modal painted a 1440px original into a
     * 100px frame.
     *
     * @param  array<int, string>  $files
     * @param  array<string, int>  $usage
     */
    public static function describe(array $files, array $usage): array
    {
        $disk = Storage::disk('public');

        return collect($files)->map(fn ($path) => [
            'name' => basename($path),
            'path' => $path,
            'url' => storage_url($path),
            'thumb' => thumb_url($path),
            'size' => $disk->exists($path) ? $disk->size($path) : 0,
            'modified' => $disk->exists($path) ? $disk->lastModified($path) : 0,
            'usedBy' => (int) ($usage[$path] ?? 0),
        ])->values()->all();
    }

    /** @return array<int, array{name: string, path: string}> */
    public static function crumbs(string $path, string $root): array
    {
        if ($path === '' || $path === $root) {
            return [];
        }

        $parts = explode('/', $path);
        $out = [];
        $so_far = '';

        foreach ($parts as $part) {
            $so_far = ltrim($so_far . '/' . $part, '/');
            $out[] = ['name' => $part, 'path' => $so_far];
        }

        return $out;
    }

    /** Null at the root, so the up-tile stops offering a trip to nowhere. */
    public static function parent(string $path, string $root): ?string
    {
        if ($path === '' || $path === $root || ! str_contains($path, '/')) {
            return null;
        }

        return substr($path, 0, strrpos($path, '/'));
    }

    public static function store(UploadedFile $file, string $directory): string
    {
        $path = $file->storeAs($directory, self::filename($file->getClientOriginalName(), $directory, $file->getClientOriginalExtension()), 'public');

        self::thumbnail($path);

        return $path;
    }

    public static function putBytes(string $bytes, string $mime, string $directory, string $sourceUrl): string
    {
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? 'jpg';
        $name = self::filename(basename(parse_url($sourceUrl, PHP_URL_PATH) ?: 'image'), $directory, $ext);
        $path = ltrim($directory . '/' . $name, '/');

        Storage::disk('public')->put($path, $bytes);
        self::thumbnail($path);

        return $path;
    }

    /** The file and its thumbnail leave together. */
    public static function forget(string $path): void
    {
        $disk = Storage::disk('public');
        $disk->delete($path);

        $info = pathinfo($path);
        $disk->delete($info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg'));
    }

    /**
     * Moving image FILES is deliberately not a capability here.
     *
     * A tidy-up that merged the six case-duplicate folders (Wampler and
     * wampler, TC Electronic and tc_electronic, and four more) was built and
     * then removed, because repointing the catalogue is not enough:
     * order_products.image holds a denormalised path on 31,278 rows, and
     * order history is the one thing that must not break. Renaming a folder
     * has exactly the same hazard.
     *
     * The library can create folders, and every picker can browse, upload,
     * import and delete. Reorganising what already exists is a migration, not
     * an admin action, and it needs to account for order history.
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

    private static function thumbnail(string $path): void
    {
        if (! extension_loaded('gd')) {
            return;
        }

        try {
            $full = Storage::disk('public')->path($path);
            $meta = @getimagesize($full);

            if (! $meta) {
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
