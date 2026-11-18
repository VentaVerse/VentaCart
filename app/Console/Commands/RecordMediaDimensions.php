<?php

namespace App\Console\Commands;

use App\Models\MediaFile;
use App\Support\MediaLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Records the width and height of library images that have no row yet.
 *
 * Browsing fills these in as images are shown, so running this is optional;
 * it saves the first browse of a large library from measuring everything.
 * It reads one chunk of paths at a time, so memory stays flat however many
 * images there are, and it can be run again at any time.
 */
class RecordMediaDimensions extends Command
{
    protected $signature = 'media:dimensions {--prune : Remove rows whose file is gone} {--force : Prune even when most rows would be removed}';

    protected $description = 'Record the width and height of library images that have none';

    private const CHUNK = 200;

    /** Print a progress line after this many images have been looked at. */
    private const PROGRESS_EVERY = 1000;

    public function handle(): int
    {
        if ($this->option('prune') && ! $this->libraryIsReadable()) {
            $this->error('The media folder is missing, unreadable or empty, so no row can be shown to be orphaned. Nothing was changed.');

            return self::FAILURE;
        }

        $recorded = 0;
        $skipped = 0;
        $seen = 0;
        $batch = [];

        foreach ($this->imagePaths() as $path) {
            $batch[] = $path;

            if (count($batch) < self::CHUNK) {
                continue;
            }

            [$added, $skip] = $this->recordChunk($batch);
            $recorded += $added;
            $skipped += $skip;
            $seen += count($batch);
            $batch = [];

            if ($seen % self::PROGRESS_EVERY === 0) {
                $this->line("Checked {$seen} images...");
            }
        }

        if ($batch !== []) {
            [$added, $skip] = $this->recordChunk($batch);
            $recorded += $added;
            $skipped += $skip;
        }

        $this->info("Recorded {$recorded} images, skipped {$skipped} unreadable.");

        $status = self::SUCCESS;

        if ($this->option('prune')) {
            $status = $this->prune();
        }

        if ($recorded > 0) {
            MediaLibrary::touched();
        }

        return $status;
    }

    /**
     * Image paths under the library root, one at a time, relative to the disk.
     *
     * @return \Generator<int, string>
     */
    private function imagePaths(): \Generator
    {
        $disk = Storage::disk('public');
        $base = rtrim($disk->path(MediaLibrary::ROOT), '/');

        if (! is_dir($base)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = MediaLibrary::ROOT.substr($file->getPathname(), strlen($base));

            if (MediaLibrary::isImage($path)) {
                yield $path;
            }
        }
    }

    /**
     * @param  array<int, string>  $paths
     * @return array{int, int} Rows added, files skipped
     */
    private function recordChunk(array $paths): array
    {
        $known = [];

        // The database narrows; the exact match is decided here, so a
        // case-insensitive collation cannot hide a path whose twin has a row.
        foreach (MediaFile::query()->whereIn('path', $paths)->pluck('path') as $path) {
            $known[$path] = true;
        }

        $now = now();
        $rows = [];
        $skipped = 0;

        foreach ($paths as $path) {
            if (isset($known[$path])) {
                continue;
            }

            // A file over the size limit is left alone, as it is everywhere
            // dimensions are recorded (see MediaLibrary::measure()).
            $size = mb_strlen($path) > MediaLibrary::PATH_MAX ? null : MediaLibrary::measure($path);

            if ($size === null) {
                $skipped++;

                continue;
            }

            $rows[] = ['path' => $path, ...$size, 'created_at' => $now, 'updated_at' => $now];
        }

        if ($rows !== []) {
            MediaFile::query()->insertOrIgnore($rows);
        }

        return [count($rows), $skipped];
    }

    /** A readable media folder with at least one entry; anything else cannot prove a file is gone. */
    private function libraryIsReadable(): bool
    {
        $base = Storage::disk('public')->path(MediaLibrary::ROOT);

        if (! is_dir($base) || ! is_readable($base)) {
            return false;
        }

        $entries = @scandir($base);

        return $entries !== false && count($entries) > 2;
    }

    /**
     * Deletes rows whose file is not on the disk, by primary key.
     *
     * A row whose path cannot be checked is kept. When most rows would go,
     * the disk is more likely unavailable than the library emptied, so that
     * needs --force.
     */
    private function prune(): int
    {
        $disk = Storage::disk('public');
        $gone = [];
        $unchecked = 0;
        $total = 0;

        MediaFile::query()->select(['id', 'path'])->chunkById(self::CHUNK, function ($rows) use ($disk, &$gone, &$unchecked, &$total) {
            foreach ($rows as $row) {
                $total++;

                try {
                    // A path the library would never have stored cannot be
                    // judged by looking for it, so it stays.
                    if (MediaLibrary::safePath($row->path) === false || preg_match('/[\x00-\x1f]/', $row->path)) {
                        $unchecked++;

                        continue;
                    }

                    if (! $disk->exists($row->path)) {
                        $gone[] = $row->id;
                    }
                } catch (\Throwable) {
                    $unchecked++;
                }
            }
        });

        if (count($gone) > 100 && count($gone) * 2 > $total && ! $this->option('force')) {
            $this->error(count($gone)." of {$total} rows look orphaned, which is more than half. Nothing was removed; run again with --force if that is right.");

            return self::FAILURE;
        }

        foreach (array_chunk($gone, self::CHUNK) as $ids) {
            MediaFile::query()->whereKey($ids)->delete();
        }

        if ($gone !== []) {
            MediaLibrary::touched();
        }

        $this->info('Removed '.count($gone)." rows whose file is gone, skipped {$unchecked} that could not be checked.");

        return self::SUCCESS;
    }
}
