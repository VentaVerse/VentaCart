<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Api\FetchesRemoteImages;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Media\MediaReferences;
use App\Support\ImageExtension;
use App\Support\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The endpoints behind the admin's one image browser.
 *
 * Every picker in the admin uses these, so a fix is made once. Browse says,
 * for each image, how many records use it, how many files a folder holds and
 * which page is showing, so a merchant can decide before deleting.
 */
class MediaLibraryController extends Controller
{
    use FetchesRemoteImages;

    /** Files per page. One folder can hold more than a thousand images. */
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

        // No path at all means the folder this picker is aimed at. A path that
        // is given is honoured exactly, including the library root itself, so
        // walking up out of a folder is possible: the old browse rewrote an
        // empty path back to the root, which is what made the category picker
        // a trap when its folder did not exist.
        $asked = trim((string) ($validated['path'] ?? ''), '/');
        $path = $asked === '' ? $root : MediaLibrary::safePath($asked);

        if ($path === false) {
            return response()->json(['ok' => false, 'message' => 'That path cannot be read.'], 422);
        }

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
            'files' => MediaLibrary::describe($slice, MediaReferences::inUse($slice)),
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        // Each refusal names the file and what is allowed, one message per
        // file, so the merchant knows which photo to replace and with what.
        $names = [];
        foreach ((array) $request->file('files', []) as $i => $file) {
            $names["files.{$i}"] = $file instanceof \Illuminate\Http\UploadedFile ? $file->getClientOriginalName() : 'That file';
        }
        $side = MediaLibrary::MAX_SIDE;
        $validated = $request->validate([
            'files' => ['required', 'array', 'max:' . MediaLibrary::MAX_FILES],
            'files.*' => [
                'bail', 'required', 'image', 'mimes:jpg,jpeg,png,webp',
                'max:' . MediaLibrary::MAX_KB,
                'dimensions:max_width=' . $side . ',max_height=' . $side,
            ],
            'path' => ['nullable', 'string'],
            'root' => ['nullable', 'string'],
        ], [
            'files.required' => 'Choose a photo to upload.',
            'files.max' => 'Upload at most ' . MediaLibrary::MAX_FILES . ' photos at a time.',
            'files.*.image' => ':attribute is not a photo. Use a JPG, PNG or WebP file.',
            'files.*.mimes' => ':attribute is not a JPG, PNG or WebP file.',
            'files.*.max' => ':attribute is larger than ' . intdiv(MediaLibrary::MAX_KB, 1024) . ' MB. Make it smaller and upload it again.',
            'files.*.dimensions' => ':attribute is larger than ' . $side . ' x ' . $side . ' pixels. Make it smaller and upload it again.',
            'files.*.uploaded' => ':attribute did not finish uploading. It may be larger than the server allows.',
        ], $names);

        // The content check runs on every file before any is saved, so a
        // refused file leaves nothing of its request behind. No extensions
        // rule: the bytes decide the ending, not the name the browser sent.
        foreach ($request->file('files') as $file) {
            ImageExtension::orRefuse($file, 'files');
        }

        // Same rule as browse: nothing given means the folder this picker is
        // aimed at, anything given is honoured as written.
        $asked = trim((string) ($validated['path'] ?? ''), '/');
        $path = $asked === '' ? MediaLibrary::root($validated['root'] ?? null) : MediaLibrary::safePath($asked);

        if ($path === false) {
            return response()->json(['ok' => false, 'message' => 'That folder cannot be written to.'], 422);
        }

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

        // Same rule as browse: nothing given means the folder this picker is
        // aimed at, anything given is honoured as written.
        $asked = trim((string) ($validated['path'] ?? ''), '/');
        $path = $asked === '' ? MediaLibrary::root($validated['root'] ?? null) : MediaLibrary::safePath($asked);

        if ($path === false) {
            return response()->json(['ok' => false, 'message' => 'That folder cannot be written to.'], 422);
        }

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
     * The merchant is told how many records use each one before they press
     * this (browse returns usedBy on every file, and the confirm reads it),
     * so the decision is theirs and an informed one. The response repeats
     * those counts so the admin can say what the delete affected.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paths' => ['required', 'array', 'max:200'],
            'paths.*' => ['required', 'string'],
        ]);

        $usage = MediaReferences::inUse($validated['paths']);
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
     * Create a folder.
     *
     * Renaming or moving a folder is not done here: every stored copy of a
     * path inside it has to move too, including the snapshot on past order
     * lines, or those records show a broken image.
     */
    public function folder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:80', 'regex:/^[^\/\\\\:*?"<>|]+$/', 'not_regex:/^\./'],
        ], [
            'name.not_regex' => 'A folder name cannot start with a dot.',
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
        MediaLibrary::touched();

        return response()->json(['ok' => true, 'path' => $target]);
    }
}
