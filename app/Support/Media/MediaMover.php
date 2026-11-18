<?php

namespace App\Support\Media;

use App\Models\MediaFile;
use App\Rules\MediaFolderName;
use App\Services\ThemeService;
use App\Support\ImageRenditions;
use App\Support\MediaLibrary;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Renames and moves images and folders in the library.
 *
 * Moving a file is more than a change on disk: every stored copy of its
 * path has to change with it, past order lines included, or those records
 * show a broken image. So each move renames the file (and its thumbnail) on
 * disk and then, in one transaction for the whole request, points every
 * registered reference at the new path. If any step fails the transaction
 * rolls back and the files are moved back, so disk and database are left
 * exactly as they were and the request moved nothing.
 *
 * Every check is made before anything moves, and names are compared
 * exactly: `Demo` and `demo` are two folders, and a move of one never
 * touches the other.
 */
final class MediaMover
{
    /**
     * Give an image or a folder a new name in the folder it is in.
     *
     * An image keeps its extension and its name is slugged as an upload's
     * is. A folder takes the name as typed, trimmed, under the same rule as
     * a folder that is made.
     *
     * @return array{path: string, updated: array<string, int>}
     *
     * @throws MediaMoveRefused
     */
    public static function rename(string $path, string $name): array
    {
        $from = self::source($path);
        $isFolder = self::isFolder($from);
        $parent = self::parentOf($from);
        $target = $isFolder ? self::folderName($name) : self::fileName($name, $from);
        $to = $parent . '/' . $target;

        if ($to === $from) {
            return ['path' => $from, 'updated' => []];
        }

        if (($taken = self::taken($parent, $target)) !== null) {
            throw new MediaMoveRefused($taken === 'folder'
                ? 'A folder called ' . $target . ' is already here.'
                : 'A file called ' . $target . ' is already in ' . self::folderLabel($parent) . '.');
        }

        self::refuseTooLong($to);

        return ['path' => $to, 'updated' => self::apply([$from => $to])];
    }

    /**
     * Move images and folders into another folder, keeping their names.
     * Anything already in that folder is left where it is.
     *
     * @param  array<int, string>  $paths
     * @return array{moved: array<string, string>, updated: array<string, int>}
     *
     * @throws MediaMoveRefused
     */
    public static function move(array $paths, string $folder): array
    {
        $into = MediaLibrary::safePath($folder);

        if ($into === false || ! self::isFolder($into)) {
            throw new MediaMoveRefused('That folder cannot be written to.');
        }

        $plan = [];
        $names = [];

        foreach (array_values(array_unique(array_map('strval', $paths))) as $path) {
            $from = self::source($path);

            if (self::isFolder($from) && ($into === $from || str_starts_with($into, $from . '/'))) {
                throw new MediaMoveRefused('A folder cannot move into itself or a folder inside it.');
            }

            if (self::parentOf($from) === $into) {
                continue;
            }

            $name = basename($from);
            $taken = isset($names[$name]) ? 'file' : self::taken($into, $name);

            if ($taken !== null) {
                $kind = $taken === 'folder' ? 'folder' : 'file';

                throw new MediaMoveRefused('A ' . $kind . ' called ' . $name . ' is already in ' . self::folderLabel($into) . '.');
            }

            $names[$name] = true;
            $plan[$from] = $into . '/' . $name;
            self::refuseTooLong($plan[$from]);
        }

        // An image inside a folder that is also moving would be moved twice.
        foreach (array_keys($plan) as $inner) {
            foreach (array_keys($plan) as $outer) {
                if (str_starts_with($inner, $outer . '/')) {
                    throw new MediaMoveRefused(basename($inner) . ' is inside ' . basename($outer) . ', which is also being moved.');
                }
            }
        }

        return ['moved' => $plan, 'updated' => $plan === [] ? [] : self::apply($plan)];
    }

    /**
     * Make every planned move, or none of them.
     *
     * @param  array<string, string>  $plan  from => to, every one already checked
     * @return array<string, int> label => rows changed
     */
    private static function apply(array $plan): array
    {
        $disk = Storage::disk('public');
        $done = [];
        $images = [];
        $updated = [];

        try {
            DB::transaction(function () use ($plan, $disk, &$done, &$images, &$updated) {
                foreach ($plan as $from => $to) {
                    if (self::isFolder($from)) {
                        array_push($images, ...array_filter($disk->allFiles($from), [MediaLibrary::class, 'isImage']));
                        self::renameOnDisk($disk, $from, $to, $done);
                    } else {
                        $images[] = $from;
                        self::renameOnDisk($disk, $from, $to, $done);

                        if ($disk->exists(self::thumb($from))) {
                            self::renameOnDisk($disk, self::thumb($from), self::thumb($to), $done);
                        }
                    }

                    self::dropRowsAt($to);

                    foreach (MediaReferences::repoint($from, $to) as $label => $count) {
                        $updated[$label] = ($updated[$label] ?? 0) + $count;
                    }
                }
            });
        } catch (\Throwable $e) {
            foreach (array_reverse($done) as [$from, $to]) {
                if (! @rename($disk->path($to), $disk->path($from))) {
                    report(new \RuntimeException('A library move failed and ' . $to . ' could not be moved back to ' . $from . '.'));
                }
            }

            throw $e;
        }

        // Sized copies are kept by path; the moved images make theirs again
        // the next time they are shown.
        foreach ($images as $image) {
            ImageRenditions::forget($image);
        }

        MediaLibrary::touched();

        if ($updated !== []) {
            // Settings and menus are cached as the storefront reads them.
            app(ThemeService::class)->clearCache();
        }

        return $updated;
    }

    /**
     * One rename on disk, remembered so it can be undone. A folder moves
     * with everything in it in a single rename.
     *
     * @param  array<int, array{0: string, 1: string}>  $done
     */
    private static function renameOnDisk(Filesystem $disk, string $from, string $to, array &$done): void
    {
        // Checked again here: rename() replaces a file that appeared since.
        if (file_exists($disk->path($to)) || ! @rename($disk->path($from), $disk->path($to))) {
            throw new \RuntimeException('Could not move ' . $from . ' to ' . $to . '.');
        }

        $done[] = [$from, $to];
    }

    /**
     * Stored dimensions for a path where no file is. The target was checked
     * to be free, so such a row is left over from a file removed outside the
     * library, and it would stop the moved file's row taking its path.
     */
    private static function dropRowsAt(string $to): void
    {
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $to) . '/%';

        $ids = MediaFile::query()
            ->where(fn ($q) => $q->where('path', $to)->orWhereRaw(DB::getQueryGrammar()->wrap('path') . ' LIKE ? ESCAPE ?', [$like, '\\']))
            ->get(['id', 'path'])
            ->filter(fn (MediaFile $row) => $row->path === $to || str_starts_with($row->path, $to . '/'))
            ->pluck('id')->all();

        if ($ids !== []) {
            MediaFile::query()->whereKey($ids)->delete();
        }
    }

    /** A library path that exists and may be renamed or moved. */
    private static function source(string $path): string
    {
        $safe = MediaLibrary::safePath($path);

        if ($safe === false) {
            throw new MediaMoveRefused('That image or folder is outside the library.');
        }

        if ($safe === MediaLibrary::ROOT) {
            throw new MediaMoveRefused('The library itself cannot be renamed or moved.');
        }

        if (self::isFolder($safe)) {
            return $safe;
        }

        if (! Storage::disk('public')->fileExists($safe)) {
            throw new MediaMoveRefused('That image or folder is no longer there.');
        }

        if (! MediaLibrary::isImage($safe)) {
            throw new MediaMoveRefused('Only images and folders can be renamed or moved.');
        }

        return $safe;
    }

    private static function isFolder(string $path): bool
    {
        return Storage::disk('public')->directoryExists($path);
    }

    private static function parentOf(string $path): string
    {
        return substr($path, 0, (int) strrpos($path, '/'));
    }

    /** "file" or "folder" when a folder already holds something with exactly this name. */
    private static function taken(string $folder, string $name): ?string
    {
        $disk = Storage::disk('public');

        foreach ($disk->directories($folder) as $dir) {
            if (basename($dir) === $name) {
                return 'folder';
            }
        }

        foreach ($disk->files($folder) as $file) {
            if (basename($file) === $name) {
                return 'file';
            }
        }

        return null;
    }

    private static function folderLabel(string $folder): string
    {
        return $folder === MediaLibrary::ROOT ? 'Media' : basename($folder);
    }

    private static function folderName(string $name): string
    {
        $problem = null;

        (new MediaFolderName())->validate('name', $name, function (string $message) use (&$problem) {
            $problem = $message;
        });

        if ($problem !== null) {
            throw new MediaMoveRefused($problem);
        }

        return trim($name);
    }

    /** The typed name slugged as an upload's is, with the image's own extension. */
    private static function fileName(string $name, string $from): string
    {
        $extension = pathinfo($from, PATHINFO_EXTENSION);
        $name = trim($name);

        // "Blue Shirt.jpg" means "Blue Shirt", not "blue-shirtjpg.jpg".
        if ($extension !== '' && str_ends_with(strtolower($name), '.' . strtolower($extension))) {
            $name = substr($name, 0, -strlen($extension) - 1);
        }

        $stem = Str::slug($name);

        if ($stem === '') {
            throw new MediaMoveRefused('Give the image a name.');
        }

        return $extension === '' ? $stem : $stem . '.' . $extension;
    }

    private static function refuseTooLong(string $path): void
    {
        if (mb_strlen($path) > MediaLibrary::PATH_MAX) {
            throw new MediaMoveRefused('That name is too long.');
        }
    }

    private static function thumb(string $path): string
    {
        $info = pathinfo($path);

        return $info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');
    }
}
