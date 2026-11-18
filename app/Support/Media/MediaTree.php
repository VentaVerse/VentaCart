<?php

namespace App\Support\Media;

use App\Support\MediaLibrary;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Every folder in the library, with how many images each holds.
 *
 * The sidebar shows the whole tree at once, so it is built in one pass: one
 * listing of every file, each image added to its folder and every folder
 * above it, and one listing of every folder so empty ones appear too. A
 * library with a few dozen folders and thousands of images costs two disk
 * walks, never one per folder.
 *
 * The result is cached; MediaLibrary::touched() clears it on every write.
 */
final class MediaTree
{
    /**
     * Folders in natural, case-insensitive order by path, each followed by
     * its own subfolders. A count includes every subfolder.
     *
     * @return list<array{path: string, name: string, parent: ?string, count: int}>
     */
    public static function all(): array
    {
        return Cache::remember(MediaLibrary::TREE_CACHE_KEY, MediaLibrary::TREE_TTL, fn () => self::build());
    }

    /**
     * Each folder's count, keyed by path.
     *
     * @return array<string, int>
     */
    public static function counts(): array
    {
        return array_column(self::all(), 'count', 'path');
    }

    /** @return list<array{path: string, name: string, parent: ?string, count: int}> */
    private static function build(): array
    {
        $disk = Storage::disk('public');
        $root = MediaLibrary::ROOT;

        MediaLibrary::ensure($root);

        $counts = [$root => 0];

        foreach ($disk->allDirectories($root) as $dir) {
            if (MediaLibrary::safePath($dir) !== false) {
                $counts[$dir] = 0;
            }
        }

        foreach ($disk->allFiles($root) as $file) {
            if (! MediaLibrary::isImage($file)) {
                continue;
            }

            $folder = $file;

            while (($slash = strrpos($folder, '/')) !== false) {
                $folder = substr($folder, 0, $slash);

                if (isset($counts[$folder])) {
                    $counts[$folder]++;
                }
            }
        }

        $paths = array_map('strval', array_keys($counts));
        usort($paths, [self::class, 'compare']);

        return array_map(fn (string $path) => [
            'path' => $path,
            'name' => $path === $root ? 'Media' : basename($path),
            'parent' => $path === $root ? null : substr($path, 0, strrpos($path, '/')),
            'count' => $counts[$path],
        ], $paths);
    }

    /**
     * Compared one folder name at a time, so a folder's subfolders come
     * straight after it rather than after a sibling whose name only shares
     * its start. Two names differing only by case keep a fixed order.
     */
    private static function compare(string $a, string $b): int
    {
        $left = explode('/', $a);
        $right = explode('/', $b);

        foreach ($left as $i => $part) {
            if (! isset($right[$i])) {
                return 1;
            }

            $order = strnatcasecmp($part, $right[$i]) ?: strcmp($part, $right[$i]);

            if ($order !== 0) {
                return $order;
            }
        }

        return count($left) <=> count($right);
    }
}
