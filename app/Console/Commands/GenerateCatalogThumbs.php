<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Small versions of images that arrived without them.
 *
 * ImageUploadService writes a `_thumb` beside everything uploaded through
 * Venta, so anything added here already has one. The migrated OpenCart
 * catalogue did not come through that path: 1,880 products whose list
 * thumbnails were being painted from 1440px originals into a 40px box,
 * 1.87MB of a 2.40MB page, the largest single file 280KB for forty pixels.
 *
 * thumb_url() picks these up the moment they exist and falls back to the
 * original where they do not, so this is safe to run, safe to interrupt, and
 * safe to run again.
 */
class GenerateCatalogThumbs extends Command
{
    protected $signature = 'catalog:thumbs
                            {--width=300 : Longest edge of the generated thumbnail}
                            {--force : Rebuild thumbnails that already exist}
                            {--path=products : Folder under the public disk to walk}';

    protected $description = 'Generate the missing _thumb version of catalog images';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('GD is not loaded, so nothing can be resized.');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $size = max(64, (int) $this->option('width'));
        $force = (bool) $this->option('force');

        $files = collect($disk->allFiles((string) $this->option('path')))
            ->reject(fn ($f) => str_contains($f, '_thumb.'))
            ->filter(fn ($f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true))
            ->values();

        if ($files->isEmpty()) {
            $this->info('Nothing to do.');

            return self::SUCCESS;
        }

        $made = 0;
        $skipped = 0;
        $failed = 0;
        $savedBytes = 0;

        $bar = $this->output->createProgressBar($files->count());
        $bar->start();

        foreach ($files as $file) {
            $info = pathinfo($file);
            $thumb = $info['dirname'] . '/' . $info['filename'] . '_thumb.' . $info['extension'];

            if (! $force && $disk->exists($thumb)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $result = $this->resize($disk->path($file), $disk->path($thumb), $size);

            if ($result === null) {
                $failed++;
            } elseif ($result === 0) {
                // Already smaller than the target; the original IS the thumb.
                $skipped++;
            } else {
                $made++;
                $savedBytes += $result;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            '%s made, %s skipped, %s failed. Roughly %s saved on every list that paints them.',
            number_format($made),
            number_format($skipped),
            number_format($failed),
            $this->humanBytes($savedBytes)
        ));

        return self::SUCCESS;
    }

    /**
     * @return int|null bytes saved, 0 when the original was already small
     *                  enough to be its own thumbnail, null on failure
     */
    private function resize(string $source, string $destination, int $max): ?int
    {
        try {
            $meta = @getimagesize($source);

            if (! $meta) {
                return null;
            }

            $src = match ($meta['mime']) {
                'image/jpeg' => @imagecreatefromjpeg($source),
                'image/png' => @imagecreatefrompng($source),
                'image/gif' => @imagecreatefromgif($source),
                'image/webp' => @imagecreatefromwebp($source),
                default => null,
            };

            if (! $src) {
                return null;
            }

            $srcW = imagesx($src);
            $srcH = imagesy($src);
            $ratio = min($max / $srcW, $max / $srcH);

            if ($ratio >= 1) {
                imagedestroy($src);

                return 0;
            }

            $dstW = max(1, (int) round($srcW * $ratio));
            $dstH = max(1, (int) round($srcH * $ratio));
            $dst = imagecreatetruecolor($dstW, $dstH);

            if (in_array($meta['mime'], ['image/png', 'image/gif', 'image/webp'], true)) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefilledrectangle($dst, 0, 0, $dstW, $dstH, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            }

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

            $dir = dirname($destination);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            match ($meta['mime']) {
                'image/jpeg' => imagejpeg($dst, $destination, 82),
                'image/png' => imagepng($dst, $destination, 8),
                'image/gif' => imagegif($dst, $destination),
                'image/webp' => imagewebp($dst, $destination, 82),
                default => null,
            };

            imagedestroy($src);
            imagedestroy($dst);

            $before = @filesize($source) ?: 0;
            $after = @filesize($destination) ?: 0;

            return max(0, $before - $after);
        } catch (\Throwable $e) {
            // One unreadable file must not stop a catalogue-wide backfill.
            return null;
        }
    }

    private function humanBytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1) . ' ' . $unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1) . ' TB';
    }
}
