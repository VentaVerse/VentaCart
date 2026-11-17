<?php

namespace App\Support;

use App\Jobs\MakeImageRenditions;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Smaller copies of a stored photo, so a phone is not sent the original.
 *
 * A product card is about 180 CSS pixels wide on a phone and a gallery
 * thumbnail about 70, yet an <img> pointing at the upload downloads all of
 * it, often well over a megabyte. Each photo on the public disk gets WebP
 * copies at fixed widths, and srcset() names them so the browser picks the
 * smallest one that is sharp at its own size and pixel density.
 *
 * The copies live under renditions/, mirroring the original's path, so the
 * media library never lists them as photos of their own. Beside them sits a
 * small manifest recording which widths were made, the original's width and
 * its modification time: drawing a page reads that one file per photo
 * instead of decoding anything, and a replaced original (a newer mtime)
 * makes the copies count as missing until they are made again.
 *
 * Copies are made when a photo is uploaded, by `php artisan
 * images:renditions` for photos that arrived another way, and otherwise on
 * first sight: a page that meets a photo without copies draws the original
 * and queues MakeImageRenditions after the response, so the next visitor
 * gets the small one.
 */
final class ImageRenditions
{
    /** Folder on the public disk that holds every copy. */
    public const FOLDER = 'renditions';

    /** The widths made, in pixels. */
    public const WIDTHS = [320, 640, 1280, 1920];

    /**
     * Originals wider than this are left out of srcset, so no layout asks for
     * a camera-sized file; the widest copy stands in for it.
     */
    public const ORIGINAL_MAX = 2560;

    /** Formats copied. GIF is left alone: a copy would keep only its first frame. */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** A width is made only when the original is at least this much wider. */
    private const MIN_GAIN = 1.2;

    /** Larger photos are not decoded: the memory needed would stall a web server. */
    private const MAX_PIXELS = 64_000_000;

    private const QUALITY = 80;

    /** Most photos one page queues, so a cold page cannot start hundreds of jobs. */
    private const QUEUE_PER_REQUEST = 12;

    /** How long a queued photo is not queued again, whatever its outcome. */
    private const QUEUE_LOCK_SECONDS = 3600;

    /** @var array<string, string> srcset strings already worked out in this process */
    private static array $memo = [];

    /** @var array<string, true> photos met without copies, queued once the response is sent */
    private static array $missing = [];

    private static bool $flushRegistered = false;

    /**
     * The srcset for a stored photo: its copies and, when it is not huge, the
     * original. Empty when the photo has no copies yet, when it is too small
     * to need any, or when it cannot be read; the <img> then keeps its src.
     */
    public static function srcset(?string $path): string
    {
        $path = self::clean($path);
        if ($path === '') {
            return '';
        }
        if (array_key_exists($path, self::$memo)) {
            return self::$memo[$path];
        }

        $disk = self::disk();
        if ($disk === null) {
            return self::$memo[$path] = '';
        }

        $mtime = @filemtime($disk->path($path));
        if ($mtime === false) {
            return self::$memo[$path] = '';
        }

        $manifest = self::readManifest($disk, $path);
        if ($manifest === null || $manifest['source_mtime'] !== $mtime) {
            self::queue($path);

            return self::$memo[$path] = '';
        }
        if ($manifest['widths'] === []) {
            return self::$memo[$path] = '';
        }

        $parts = [];
        foreach ($manifest['widths'] as $width) {
            $parts[] = storage_url(self::path($path, $width)) . ' ' . $width . 'w';
        }
        if ($manifest['width'] <= self::ORIGINAL_MAX) {
            $parts[] = storage_url($path) . ' ' . $manifest['width'] . 'w';
        }

        return self::$memo[$path] = implode(', ', $parts);
    }

    /** Where the copy of $original at $width is stored on the public disk. */
    public static function path(string $original, int $width): string
    {
        return self::FOLDER . '/' . ltrim($original, '/') . '.' . $width . '.webp';
    }

    /** The widths worth making for an original this wide. */
    public static function widthsFor(int $width): array
    {
        return array_values(array_filter(self::WIDTHS, fn (int $w) => $width >= $w * self::MIN_GAIN));
    }

    /**
     * Make the photo's copies and its manifest. Returns how many copies were
     * written; 0 when they were already current (unless forced), the photo
     * is too small to need any, or it cannot be read.
     */
    public static function make(string $path, bool $force = false): int
    {
        $path = self::clean($path);
        $disk = self::disk();
        if ($path === '' || $disk === null || ! extension_loaded('gd')) {
            return 0;
        }

        $full = $disk->path($path);
        $mtime = @filemtime($full);
        if ($mtime === false) {
            return 0;
        }
        if (! $force && ($manifest = self::readManifest($disk, $path)) !== null && $manifest['source_mtime'] === $mtime) {
            return 0;
        }

        $meta = @getimagesize($full);
        if (! is_array($meta) || ! in_array($meta[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            self::writeManifest($disk, $path, $mtime, 0, []);

            return 0;
        }

        [$w, $h] = [(int) $meta[0], (int) $meta[1]];
        if ($w < 1 || $h < 1 || $w * $h > self::MAX_PIXELS || ! self::reserveMemory($w, $h)) {
            Log::notice('Image copies skipped: the photo is too large to resize here.', ['path' => $path, 'width' => $w, 'height' => $h]);
            self::writeManifest($disk, $path, $mtime, $w, []);

            return 0;
        }

        $source = self::load($full, (int) $meta[2]);
        if ($source === null) {
            self::writeManifest($disk, $path, $mtime, $w, []);

            return 0;
        }

        // The original's displayed width: a phone photo stored sideways with
        // a rotate flag shows its height as its width.
        $width = imagesx($source);
        $height = imagesy($source);
        $made = [];

        try {
            foreach (self::widthsFor($width) as $target) {
                $targetHeight = max(1, (int) round($height * $target / $width));
                $copy = imagecreatetruecolor($target, $targetHeight);
                imagealphablending($copy, false);
                imagesavealpha($copy, true);
                imagefilledrectangle($copy, 0, 0, $target, $targetHeight, imagecolorallocatealpha($copy, 0, 0, 0, 127));
                imagecopyresampled($copy, $source, 0, 0, 0, 0, $target, $targetHeight, $width, $height);

                $out = $disk->path(self::path($path, $target));
                self::ensureDirectory(dirname($out));
                $temp = $out . '.' . bin2hex(random_bytes(4)) . '.tmp';
                $ok = imagewebp($copy, $temp, self::QUALITY);
                imagedestroy($copy);

                if (! $ok || ! @rename($temp, $out)) {
                    @unlink($temp);
                    break;
                }
                $made[] = $target;
            }
        } finally {
            imagedestroy($source);
        }

        // Only the widths actually written are named, so a half-finished run
        // never sends a browser to a missing file.
        self::writeManifest($disk, $path, $mtime, $width, $made);

        return count($made);
    }

    /** Remove the photo's copies and manifest, when the photo itself is deleted. */
    public static function forget(string $path): void
    {
        $path = self::clean($path);
        $disk = self::disk();
        if ($path === '' || $disk === null) {
            return;
        }

        $disk->delete([...array_map(fn (int $w) => self::path($path, $w), self::WIDTHS), self::manifestPath($path)]);
        unset(self::$memo[$path]);
    }

    /** Queue the photo's copies for after the response (an upload, an import). */
    public static function queue(string $path): void
    {
        $path = self::clean($path);
        if ($path === '' || isset(self::$missing[$path]) || count(self::$missing) >= self::QUEUE_PER_REQUEST) {
            return;
        }
        self::$missing[$path] = true;

        if (! self::$flushRegistered) {
            self::$flushRegistered = true;
            app()->terminating(static fn () => self::dispatchQueued());
        }
    }

    /** Send the photos met without copies to the queue, each at most once an hour. */
    public static function dispatchQueued(): void
    {
        $paths = array_keys(self::$missing);
        self::$missing = [];
        self::$flushRegistered = false;

        foreach ($paths as $path) {
            try {
                if (Cache::add('image-renditions:' . sha1($path), true, self::QUEUE_LOCK_SECONDS)) {
                    MakeImageRenditions::dispatch($path);
                }
            } catch (\Throwable $e) {
                // The page is already sent; a photo without copies is only slower.
                report($e);
            }
        }
    }

    /** Forget what this process worked out, so a long-running worker sees new copies. */
    public static function flush(): void
    {
        self::$memo = [];
    }

    private static function clean(?string $path): string
    {
        $path = SafeInput::storagePath($path);
        if ($path === '' || str_starts_with($path, self::FOLDER . '/')) {
            return '';
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true) ? $path : '';
    }

    /** The public disk, when its files have local paths; copies are not made on remote disks. */
    private static function disk(): ?Filesystem
    {
        if (config('filesystems.disks.public.driver') !== 'local') {
            return null;
        }

        return Storage::disk('public');
    }

    private static function manifestPath(string $path): string
    {
        return self::FOLDER . '/' . ltrim($path, '/') . '.json';
    }

    /** @return array{source_mtime: int, width: int, widths: list<int>}|null */
    private static function readManifest(Filesystem $disk, string $path): ?array
    {
        $raw = @file_get_contents($disk->path(self::manifestPath($path)));
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($data) || ! is_int($data['source_mtime'] ?? null) || ! is_int($data['width'] ?? null) || ! is_array($data['widths'] ?? null)) {
            return null;
        }
        $widths = array_values(array_filter($data['widths'], fn ($w) => is_int($w) && in_array($w, self::WIDTHS, true)));

        return ['source_mtime' => $data['source_mtime'], 'width' => $data['width'], 'widths' => $widths];
    }

    /** @param list<int> $widths */
    private static function writeManifest(Filesystem $disk, string $path, int $mtime, int $width, array $widths): void
    {
        $file = $disk->path(self::manifestPath($path));
        self::ensureDirectory(dirname($file));
        $temp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temp, json_encode(['source_mtime' => $mtime, 'width' => $width, 'widths' => $widths])) === false || ! @rename($temp, $file)) {
            @unlink($temp);
        }
        unset(self::$memo[$path]);
    }

    private static function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    /** Decoded and turned upright, or null when the file cannot be decoded. */
    private static function load(string $full, int $type): ?\GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($full),
            IMAGETYPE_PNG => @imagecreatefrompng($full),
            IMAGETYPE_WEBP => @imagecreatefromwebp($full),
            default => false,
        };
        if (! $image instanceof \GdImage) {
            return null;
        }
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = (int) ((@exif_read_data($full) ?: [])['Orientation'] ?? 1);
            $image = self::upright($image, $orientation);
        }

        return $image;
    }

    /** Apply a camera's EXIF orientation, as browsers do when they show the original. */
    private static function upright(\GdImage $image, int $orientation): \GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);
        if (! $rotated instanceof \GdImage) {
            return $image;
        }
        imagedestroy($image);

        return $rotated;
    }

    /**
     * Make room for decoding: GD holds four bytes a pixel, twice over while
     * a sideways photo is turned. False when the limit cannot be raised.
     */
    private static function reserveMemory(int $width, int $height): bool
    {
        $limit = self::bytes((string) ini_get('memory_limit'));
        if ($limit < 0) {
            return true;
        }
        $need = memory_get_usage() + $width * $height * 8 + 1920 * 1920 * 4 + 32 * 1024 * 1024;
        if ($need <= $limit) {
            return true;
        }

        return @ini_set('memory_limit', (string) $need) !== false && self::bytes((string) ini_get('memory_limit')) >= $need;
    }

    private static function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
