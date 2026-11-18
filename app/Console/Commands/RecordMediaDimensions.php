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
    protected $signature = 'media:dimensions {--prune : Remove rows whose file is gone}';

    protected $description = 'Record the width and height of library images that have none';

    private const CHUNK = 200;

    /** Print a progress line after this many images have been looked at. */
    private const PROGRESS_EVERY = 1000;

    public function handle(): int
    {
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

        if ($this->option('prune')) {
            $this->info('Removed '.$this->prune().' rows whose file is gone.');
        }

        if ($recorded > 0) {
            MediaLibrary::touched();
        }

        return self::SUCCESS;
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

            $meta = mb_strlen($path) > MediaLibrary::PATH_MAX
                ? false
                : @getimagesize(Storage::disk('public')->path($path));

            // A file over the size limit is left alone: it would be refused
            // on upload, and it has no thumbnail to show beside its size.
            if (! $meta || $meta[0] < 1 || $meta[1] < 1
                || $meta[0] > MediaLibrary::MAX_SIDE || $meta[1] > MediaLibrary::MAX_SIDE) {
                $skipped++;

                continue;
            }

            $rows[] = ['path' => $path, 'width' => (int) $meta[0], 'height' => (int) $meta[1], 'created_at' => $now, 'updated_at' => $now];
        }

        if ($rows !== []) {
            MediaFile::query()->insertOrIgnore($rows);
        }

        return [count($rows), $skipped];
    }

    /** Deletes rows whose file is not on the disk, by primary key. */
    private function prune(): int
    {
        $disk = Storage::disk('public');
        $removed = 0;

        MediaFile::query()->select(['id', 'path'])->chunkById(self::CHUNK, function ($rows) use ($disk, &$removed) {
            $gone = $rows->filter(fn (MediaFile $row) => ! $disk->exists($row->path))->pluck('id')->all();

            if ($gone !== []) {
                MediaFile::query()->whereKey($gone)->delete();
                $removed += count($gone);
            }
        });

        if ($removed > 0) {
            MediaLibrary::touched();
        }

        return $removed;
    }
}
