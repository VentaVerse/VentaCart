<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Api\FetchesRemoteImages;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One library, one contract.
 *
 * There were five hand-rolled browsers in this admin, all calling the product
 * image endpoints, all diverging: delete existed in two of them, sort in one,
 * multi-select in one, and the root crumb had three different labels. A fix
 * had to be made five times and was usually made once.
 *
 * This is the endpoint half of the one component that replaces them. The
 * shapes it returns are deliberately richer than the old browse, because the
 * things every copy was missing (how many products use this file, how many
 * files are in here, which page am I on) could not be added five times.
 */
class MediaLibraryController extends Controller
{
    use FetchesRemoteImages;

    /** Files per page. A brand folder here holds 1,194 of them. */
    private const PER_PAGE = 120;

    public function browse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'root' => ['nullable', 'string'],
            'path' => ['nullable', 'string'],
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:name,modified'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $root = MediaLibrary::root($validated['root'] ?? null);
        $path = MediaLibrary::safePath($validated['path'] ?? null);

        if ($path === false) {
            return response()->json(['ok' => false, 'message' => 'That path cannot be read.'], 422);
        }

        // An explicit empty path means the library root, not "bounce back to
        // where you started". The old browse rewrote it, which is what made
        // the category picker a trap: its folder did not exist, and both the
        // crumb and the up-tile resolved straight back into it.
        $path = $path === '' ? $root : $path;

        MediaLibrary::ensure($root);

        $search = trim((string) ($validated['q'] ?? ''));
        $sort = $validated['sort'] ?? 'modified';
        $page = max(1, (int) ($validated['page'] ?? 1));

        // A search looks through the whole root, not the folder you happen to
        // be standing in. Searching one folder of sixty-three is the reason a
        // merchant could not find a photo they knew they had.
        $files = $search === ''
            ? MediaLibrary::filesIn($path)
            : MediaLibrary::search($root, $search);

        $files = MediaLibrary::sort($files, $sort);
        $total = count($files);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $slice = array_slice($files, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        return response()->json([
            'ok' => true,
            'root' => $root,
            'path' => $path,
            'parent' => MediaLibrary::parent($path, $root),
            'crumbs' => MediaLibrary::crumbs($path, $root),
            'searching' => $search !== '',
            'folders' => $search === '' ? MediaLibrary::foldersIn($path) : [],
            'files' => MediaLibrary::describe($slice, $this->usage($slice)),
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'path' => ['nullable', 'string'],
            'root' => ['nullable', 'string'],
        ]);

        $path = MediaLibrary::safePath($validated['path'] ?? null);

        if ($path === false) {
            return response()->json(['ok' => false, 'message' => 'That folder cannot be written to.'], 422);
        }

        $path = $path === '' ? MediaLibrary::root($validated['root'] ?? null) : $path;
        MediaLibrary::ensure($path);

        $written = [];

        foreach ($request->file('files') as $file) {
            $written[] = MediaLibrary::store($file, $path);
        }

        return response()->json([
            'ok' => true,
            'files' => MediaLibrary::describe($written, []),
        ]);
    }

    public function importUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url'],
            'path' => ['nullable', 'string'],
            'root' => ['nullable', 'string'],
        ]);

        $path = MediaLibrary::safePath($validated['path'] ?? null);

        if ($path === false) {
            return response()->json(['ok' => false, 'message' => 'That folder cannot be written to.'], 422);
        }

        $path = $path === '' ? MediaLibrary::root($validated['root'] ?? null) : $path;
        MediaLibrary::ensure($path);

        $result = $this->fetchRemoteImage($validated['url'], ['image/jpeg', 'image/png', 'image/webp']);

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'message' => $result['reason']], 422);
        }

        $written = MediaLibrary::putBytes($result['body'], $result['mime'], $path, $validated['url']);

        return response()->json([
            'ok' => true,
            'files' => MediaLibrary::describe([$written], []),
        ]);
    }

    /**
     * Remove files, having already said what they cost.
     *
     * The merchant is told how many products use each one before they press
     * this (browse returns usedBy on every file, and the confirm reads it),
     * so the decision is theirs and it is an informed one. The old endpoint
     * asked nothing and said nothing: one image here is on fourteen products.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paths' => ['required', 'array', 'max:200'],
            'paths.*' => ['required', 'string'],
        ]);

        $usage = $this->usage($validated['paths']);
        $deleted = [];
        $detached = 0;

        foreach ($validated['paths'] as $path) {
            $safe = MediaLibrary::safePath($path);

            if ($safe === false || $safe === '') {
                continue;
            }

            // The rows that pointed at it go too. Leaving them behind is how a
            // product ends up rendering a broken image forever.
            $detached += ProductImage::where('path', $safe)->delete();
            Product::where('image', $safe)->update(['image' => null]);

            MediaLibrary::forget($safe);
            $deleted[] = $safe;
        }

        return response()->json([
            'ok' => true,
            'deleted' => $deleted,
            'detached' => $detached,
            'usedBy' => $usage,
        ]);
    }

    /**
     * Create a folder. Creating only.
     *
     * Renaming was here and is gone: moving image files means every stored
     * path has to move with them, and order_products.image holds a
     * denormalised copy on 31,278 rows. Order history is the one thing that
     * must not break, so reorganising an existing library is a migration with
     * its own plan, not a button in the admin.
     */
    public function folder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:120', 'regex:/^[^\/\\\\:*?"<>|]+$/'],
        ]);

        $parent = MediaLibrary::safePath($validated['path'] ?? null);

        if ($parent === false) {
            return response()->json(['ok' => false, 'message' => 'That folder cannot be written to.'], 422);
        }

        $name = trim($validated['name']);
        $target = ltrim($parent . '/' . $name, '/');

        if (Storage::disk('public')->exists($target)) {
            return response()->json(['ok' => false, 'message' => 'A folder called ' . $name . ' is already here.'], 422);
        }

        MediaLibrary::ensure($target);

        return response()->json(['ok' => true, 'path' => $target]);
    }

    /**
     * How many products point at each of these paths.
     *
     * One grouped query for the whole page rather than one per tile, because
     * the grid renders 120 of them.
     *
     * @param  array<int, string>  $paths
     * @return array<string, int>
     */
    private function usage(array $paths): array
    {
        if ($paths === []) {
            return [];
        }

        $fromImages = ProductImage::whereIn('path', $paths)
            ->selectRaw('path, count(distinct product_id) as n')
            ->groupBy('path')->pluck('n', 'path');

        $fromMain = Product::whereIn('image', $paths)
            ->selectRaw('image as path, count(*) as n')
            ->groupBy('image')->pluck('n', 'path');

        $out = [];

        foreach ($paths as $path) {
            $out[$path] = (int) ($fromImages[$path] ?? 0) + (int) ($fromMain[$path] ?? 0);
        }

        return $out;
    }
}
