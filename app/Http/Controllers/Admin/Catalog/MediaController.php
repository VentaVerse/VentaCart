<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Support\Media\MediaReferences;
use App\Support\MediaLibrary;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;

/**
 * The Media library page.
 *
 * The page is the shared library component mounted in manage mode, so
 * browsing, uploading, renaming, moving and deleting all go through the
 * library's own endpoints (MediaLibraryController) and behave the same here
 * as in every image field. This controller only prepares the page.
 */
class MediaController extends Controller
{
    /**
     * A store that moved its images over from an earlier layout keeps them
     * in this folder, which is where many images are left unused. Only this
     * folder is counted because counting the whole library would ask every
     * reference source about every image on each visit to the page. On a
     * store without the folder the panel is not shown.
     */
    private const IMPORTED = MediaLibrary::ROOT . '/catalog';

    public function index(): View
    {
        MediaLibrary::ensure(MediaLibrary::ROOT);

        foreach (MediaLibrary::DEFAULTS as $folder) {
            MediaLibrary::ensure(MediaLibrary::ROOT . '/' . $folder);
        }

        return view('admin.catalog.media.index', [
            'unused' => $this->unused(self::IMPORTED),
        ]);
    }

    /**
     * How many images in a folder (at any depth) nothing uses, out of how
     * many. "Uses" is every registered reference source, a theme's or an
     * extension's included, through MediaReferences::inUse(); a past order
     * line is a record, not a use.
     *
     * @return array{count: int, total: int, path: string}
     */
    private function unused(string $folder): array
    {
        $disk = Storage::disk('public');

        if (! $disk->directoryExists($folder)) {
            return ['count' => 0, 'total' => 0, 'path' => $folder];
        }

        $images = array_values(array_filter($disk->allFiles($folder), fn (string $path) => MediaLibrary::isImage($path)));
        $used = array_filter(MediaReferences::inUse($images), fn (int $users) => $users > 0);

        return [
            'count' => count($images) - count($used),
            'total' => count($images),
            'path' => $folder,
        ];
    }
}
