<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The media library page.
 *
 * This was 236 lines running a database media library that never held a row.
 * StoreMedia: 0. MediaFolder: 0. product_images.store_media_id: NULL on all
 * 5,067 rows. Eight of its nine routes were reachable, authenticated,
 * state-changing, and called by no view, because the page itself ran on the
 * product image endpoints all along.
 */
class MediaController extends Controller
{

    /**
     * The library page.
     *
     * Everything else this controller held is gone. It ran a database media
     * library (StoreMedia, MediaFolder) that never held a row: eight routes,
     * authenticated and state-changing, that no view ever called, while the
     * page itself quietly ran on the product image endpoints. The one thing
     * worth keeping from it, a delete that refuses to orphan a product, moved
     * to the live path where it can actually run.
     */
    public function index(Request $request)
    {
        MediaLibrary::ensure(MediaLibrary::ROOT);

        foreach (MediaLibrary::DEFAULTS as $folder) {
            MediaLibrary::ensure(MediaLibrary::ROOT . '/' . $folder);
        }

        // The folders that are actually there, not a fixed five. A merchant who
        // makes "summer-sale" gets a card for it like any other.
        $roots = array_map(
            fn ($f) => $f['path'],
            MediaLibrary::foldersIn(MediaLibrary::ROOT)
        );

        $counts = [];
        $folders = [];

        foreach ($roots as $root) {
            // Count what opening the folder will actually show. This used to
            // count recursively while the browser lists one level, so a folder
            // whose pictures sat in a subfolder advertised a number and then
            // opened on nothing. The subfolders are reported separately
            // instead, which is the part the recursive total was hiding.
            $counts[$root] = count(array_filter(
                Storage::disk('public')->files($root),
                fn ($f) => ! str_contains($f, '_thumb.')
                    && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), MediaLibrary::EXTENSIONS, true)
            ));

            $folders[$root] = count(Storage::disk('public')->directories($root));
        }

        return view('admin.catalog.media.index', [
            'roots' => $roots,
            'counts' => $counts,
            'folders' => $folders,
            'orphans' => $this->orphans(),
        ]);
    }

    /**
     * The retired OpenCart tree, counted.
     *
     * It sits on disk beside the live one, browsable, with nothing marking it
     * retired. A merchant cannot tell 1,887 dead files from the live catalogue
     * by looking, so the page says the number out loud.
     */
    private function orphans(): array
    {
        $where = MediaLibrary::ROOT . '/catalog';

        if (! Storage::disk('public')->exists($where)) {
            return ['count' => 0, 'total' => 0, 'where' => $where, 'root' => MediaLibrary::ROOT . '/products'];
        }

        $files = collect(Storage::disk('public')->allFiles($where))
            ->reject(fn ($f) => str_contains($f, '_thumb.'))
            ->filter(fn ($f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), MediaLibrary::EXTENSIONS, true))
            ->values();

        $used = ProductImage::whereIn('path', $files->all())->pluck('path')
            ->merge(Product::whereIn('image', $files->all())->pluck('image'))
            ->unique();

        return [
            'count' => $files->count() - $used->count(),
            'total' => $files->count(),
            'where' => $where,
            'root' => 'products',
        ];
    }
}
