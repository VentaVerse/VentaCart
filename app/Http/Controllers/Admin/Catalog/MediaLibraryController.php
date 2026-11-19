<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Api\FetchesRemoteImages;
use App\Http\Controllers\Controller;
use App\Rules\MediaFolderName;
use App\Support\Media\MediaMover;
use App\Support\Media\MediaMoveRefused;
use App\Support\Media\MediaReferences;
use App\Support\Media\MediaTree;
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
 * which page is showing. An image in use is never deleted, and renaming or
 * moving one repoints every registered reference to it, order history
 * included, in one transaction.
 */
class MediaLibraryController extends Controller
{
    use FetchesRemoteImages;

    /**
     * Every folder in the library with its image count, in one answer.
     * `total` is every image in the library.
     */
    public function tree(): JsonResponse
    {
        $folders = MediaTree::all();

        return response()->json([
            'ok' => true,
            'folders' => $folders,
            'total' => $folders[0]['count'] ?? 0,
        ]);
    }

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
        $lastPage = max(1, (int) ceil($total / MediaLibrary::PER_PAGE));
        $slice = array_slice($files, ($page - 1) * MediaLibrary::PER_PAGE, MediaLibrary::PER_PAGE);
        $counts = MediaTree::counts();

        return response()->json([
            'ok' => true,
            'root' => $root,
            'path' => $path,
            'parent' => MediaLibrary::parent($path, $root),
            'crumbs' => MediaLibrary::crumbs($path, $root),
            'searching' => $search !== '',
            'folders' => $search === ''
                ? $this->withCounts(MediaLibrary::foldersIn($path), $counts)
                : $this->foldersNamed($root, $search),
            'files' => MediaLibrary::describe($slice, MediaReferences::summary($slice)),
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
        ]);
    }

    /**
     * @param  array<int, array{name: string, path: string}>  $folders
     * @param  array<string, int>  $counts
     * @return array<int, array{name: string, path: string, count: int}>
     */
    private function withCounts(array $folders, array $counts): array
    {
        return array_map(fn (array $folder) => [...$folder, 'count' => $counts[$folder['path']] ?? 0], $folders);
    }

    /**
     * Folders under the root whose own name contains the term, so a search
     * offers the folder as well as the images inside it.
     *
     * @return array<int, array{name: string, path: string, count: int}>
     */
    private function foldersNamed(string $root, string $term): array
    {
        $term = mb_strtolower($term);
        $out = [];

        foreach (MediaTree::all() as $folder) {
            if (str_starts_with($folder['path'], $root . '/') && str_contains(mb_strtolower($folder['name']), $term)) {
                $out[] = ['name' => $folder['name'], 'path' => $folder['path'], 'count' => $folder['count']];
            }
        }

        return $out;
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

        // Same rule as browse: nothing given means the folder this picker is
        // aimed at, anything given is honoured as written.
        $asked = trim((string) ($validated['path'] ?? ''), '/');
        $path = $asked === '' ? MediaLibrary::root($validated['root'] ?? null) : MediaLibrary::safePath($asked);

        if ($path === false) {
            return response()->json(['ok' => false, 'message' => 'That folder cannot be written to.'], 422);
        }

        // The content and length checks run on every file before any is
        // saved, so a refused file leaves nothing of its request behind. No
        // extensions rule: the bytes decide the ending, not the name the
        // browser sent.
        foreach ($request->file('files') as $file) {
            $extension = ImageExtension::orRefuse($file, 'files');

            if (MediaLibrary::tooLong(MediaLibrary::pathFor($file->getClientOriginalName(), $path, $extension))) {
                return response()->json(['ok' => false, 'message' => $this->nameTooLong($file->getClientOriginalName())], 422);
            }
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

        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$result['mime']] ?? 'jpg';

        if (MediaLibrary::tooLong(MediaLibrary::pathFor(MediaLibrary::importName($validated['url']), $path, $ext))) {
            return response()->json(['ok' => false, 'message' => $this->nameTooLong('That image')], 422);
        }

        $written = MediaLibrary::putBytes($result['body'], $result['mime'], $path, $validated['url']);

        return response()->json([
            'ok' => true,
            'files' => MediaLibrary::describe([$written], []),
        ]);
    }

    /**
     * Remove images and empty folders.
     *
     * An image something uses is kept, and the answer names its users so the
     * merchant can change them first; removing the image would leave each of
     * them showing a broken picture. Rows are never detached here. Past order
     * lines are a record of what was sold, not a use, so they do not keep an
     * image. Unused images in the same request are still deleted.
     *
     * A folder goes only when no image is left in it at any depth, so a
     * folder delete can never take an image in use with it.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paths' => ['required', 'array', 'max:200'],
            'paths.*' => ['required', 'string'],
        ]);

        $disk = Storage::disk('public');
        $files = [];
        $folders = [];

        foreach (array_unique($validated['paths']) as $path) {
            $safe = MediaLibrary::safePath($path);

            if ($safe === false || MediaLibrary::isRoot($safe)) {
                continue;
            }

            if ($disk->directoryExists($safe)) {
                $folders[] = $safe;
            } elseif (MediaLibrary::isImage($safe)) {
                $files[] = $safe;
            }
        }

        foreach ($folders as $folder) {
            foreach ($disk->allFiles($folder) as $file) {
                if (MediaLibrary::isImage($file)) {
                    return response()->json(['ok' => false, 'message' => 'Empty the folder first.'], 422);
                }
            }
        }

        $usage = MediaReferences::usage($files);
        $inUse = [];
        $deleted = [];

        foreach ($files as $file) {
            if (($usage[$file] ?? []) !== []) {
                $inUse[$file] = $this->uses($usage[$file]);

                continue;
            }

            MediaLibrary::forget($file);
            $deleted[] = $file;
        }

        foreach ($folders as $folder) {
            $disk->deleteDirectory($folder);
            $deleted[] = $folder;
        }

        MediaLibrary::touched();

        if ($inUse !== []) {
            $count = count($inUse);

            return response()->json([
                'ok' => false,
                'message' => $count === 1
                    ? '1 image is in use and was not deleted.'
                    : $count . ' images are in use and were not deleted.',
                'inUse' => $inUse,
                'deleted' => $deleted,
            ], 409);
        }

        return response()->json(['ok' => true, 'deleted' => $deleted]);
    }

    /** Every kind of user of one image, and how many of each. */
    public function usage(Request $request): JsonResponse
    {
        $validated = $request->validate(['path' => ['required', 'string']]);
        $path = MediaLibrary::safePath($validated['path']);

        if ($path === false) {
            return response()->json(['ok' => false, 'message' => 'That path cannot be read.'], 422);
        }

        $uses = $this->uses(MediaReferences::usage([$path])[$path] ?? []);

        return response()->json([
            'ok' => true,
            'path' => $path,
            'uses' => $uses,
            'total' => array_sum(array_column($uses, 'count')),
        ]);
    }

    /**
     * Rename an image or a folder where it is. An image keeps its extension;
     * a folder keeps its name as typed.
     */
    public function rename(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Give it a name.',
        ]);

        return $this->moving(fn () => ['ok' => true, ...MediaMover::rename($validated['path'], $validated['name'])]);
    }

    /** Move images and folders into another folder. */
    public function move(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paths' => ['required', 'array', 'max:200'],
            'paths.*' => ['required', 'string'],
            'to' => ['required', 'string'],
        ]);

        return $this->moving(fn () => ['ok' => true, ...MediaMover::move($validated['paths'], $validated['to'])]);
    }

    /**
     * A refusal is the merchant's to read. Anything else failed after the
     * mover put every file back, so nothing changed and trying again is safe.
     */
    private function moving(\Closure $work): JsonResponse
    {
        try {
            return response()->json($work());
        } catch (MediaMoveRefused $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => 'Nothing was moved. Try again.'], 500);
        }
    }

    private function nameTooLong(string $what): string
    {
        return $what . ' has a name too long for this folder. Give it a shorter name or choose a folder nearer the top.';
    }

    /**
     * @param  array<string, int>  $kinds  label => users
     * @return list<array{label: string, count: int}>
     */
    private function uses(array $kinds): array
    {
        $out = [];

        foreach ($kinds as $label => $count) {
            $out[] = ['label' => (string) $label, 'count' => $count];
        }

        return $out;
    }

    /**
     * Create a folder. Renaming one is the rename request's job, since it
     * repoints every stored copy of the paths inside it.
     */
    public function folder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['nullable', 'string'],
            'name' => ['required', 'string', new MediaFolderName()],
        ], [
            'name.required' => 'Give the folder a name.',
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

        if (MediaLibrary::tooLong($target)) {
            return response()->json(['ok' => false, 'message' => 'That name is too long.'], 422);
        }

        MediaLibrary::ensure($target);
        MediaLibrary::touched();

        return response()->json(['ok' => true, 'path' => $target]);
    }
}
