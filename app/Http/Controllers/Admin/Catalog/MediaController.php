<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Models\MediaFolder;
use App\Models\StoreMedia;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(
        protected ImageUploadService $uploadService
    ) {}

    public function index(Request $request)
    {
        return view('admin.catalog.media.index');
    }

    public function folders(Request $request): JsonResponse
    {
        $storeId = $request->input('store_id');

        if (!$storeId) {
            return response()->json([]);
        }

        $folders = MediaFolder::where('store_id', $storeId)
            ->withCount('media')
            ->orderBy('name')
            ->get();

        return response()->json($folders);
    }

    public function createFolder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'exists:media_folders,id'],
        ]);

        $folder = MediaFolder::getOrCreate(
            $validated['store_id'],
            $validated['name']
        );

        return response()->json($folder, 201);
    }

    public function updateFolder(Request $request, MediaFolder $folder): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $oldSlug = $folder->slug;
        $newSlug = \Illuminate\Support\Str::slug($validated['name']);

        if ($oldSlug !== $newSlug) {
            // Move files on disk
            $oldDir = "stores/{$folder->store_id}/{$oldSlug}";
            $newDir = "stores/{$folder->store_id}/{$newSlug}";

            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($oldDir)) {
                $files = \Illuminate\Support\Facades\Storage::disk('public')->allFiles($oldDir);
                foreach ($files as $file) {
                    $newPath = str_replace($oldDir, $newDir, $file);
                    \Illuminate\Support\Facades\Storage::disk('public')->move($file, $newPath);
                }
                \Illuminate\Support\Facades\Storage::disk('public')->deleteDirectory($oldDir);
            }

            // Update media paths
            $folder->media()->each(function ($media) use ($oldDir, $newDir) {
                $media->update([
                    'path' => str_replace($oldDir, $newDir, $media->path),
                ]);
            });
        }

        $folder->update([
            'name' => $validated['name'],
            'slug' => $newSlug,
        ]);

        return response()->json($folder);
    }

    public function deleteFolder(MediaFolder $folder): JsonResponse
    {
        if ($folder->hasProductMedia()) {
            return response()->json([
                'message' => 'Cannot delete folder. Some images are used by products.',
            ], 409);
        }

        // Delete all media in folder
        $folder->media->each(function ($media) {
            $this->uploadService->delete($media);
        });

        $folder->delete();

        return response()->json(['message' => 'Folder deleted.']);
    }

    public function list(Request $request): JsonResponse
    {
        $storeId = $request->input('store_id');

        if (!$storeId) {
            return response()->json(['data' => [], 'meta' => []]);
        }

        $query = StoreMedia::where('store_id', $storeId)
            ->where('is_temp', false);

        if ($request->filled('folder_id')) {
            $query->where('media_folder_id', $request->input('folder_id'));
        } elseif ($request->input('unsorted')) {
            $query->whereNull('media_folder_id');
        }

        if ($request->filled('search')) {
            $query->where('original_filename', 'like', '%' . $request->input('search') . '%');
        }

        $sort = $request->input('sort', 'newest');
        $query->orderBy(match ($sort) {
            'oldest' => 'created_at',
            'name' => 'original_filename',
            'size' => 'file_size',
            default => 'created_at',
        }, match ($sort) {
            'oldest', 'name' => 'asc',
            default => 'desc',
        });

        $media = $query->paginate(30);

        $items = $media->getCollection()->map(function ($item) {
            return [
                'id' => $item->id,
                'url' => $item->url,
                'thumbnail_url' => $item->thumbnail_url,
                'original_filename' => $item->original_filename,
                'mime_type' => $item->mime_type,
                'file_size' => $item->human_file_size,
                'dimensions' => $item->dimensions,
                'is_used' => $item->is_used,
                'folder_id' => $item->media_folder_id,
                'created_at' => $item->created_at->format('Y-m-d H:i'),
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'total' => $media->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['image', 'max:3072'],
            'folder_id' => ['nullable', 'exists:media_folders,id'],
            'is_temp' => ['nullable', 'boolean'],
        ]);

        $storeId = $request->input('store_id');
        $folderId = $request->input('folder_id');
        $isTemp = $request->boolean('is_temp');
        $uploaded = [];

        foreach ($request->file('files') as $file) {
            $media = $this->uploadService->upload($file, $storeId, $folderId, $isTemp);
            $uploaded[] = [
                'id' => $media->id,
                'url' => $media->url,
                'thumbnail_url' => $media->thumbnail_url,
                'original_filename' => $media->original_filename,
                'file_size' => $media->human_file_size,
                'dimensions' => $media->dimensions,
            ];
        }

        return response()->json(['uploaded' => $uploaded], 201);
    }

    public function destroy(StoreMedia $medium): JsonResponse
    {
        if ($medium->is_used) {
            $productNames = $medium->productImages()
                ->with('product')
                ->get()
                ->pluck('product.name')
                ->unique()
                ->implode(', ');

            return response()->json([
                'message' => "Cannot delete. Image is used by: {$productNames}",
            ], 409);
        }

        $this->uploadService->delete($medium);

        return response()->json(['message' => 'Image deleted.']);
    }

    public function cleanupTemp(Request $request): JsonResponse
    {
        $storeId = $request->input('store_id');

        if ($storeId) {
            StoreMedia::where('store_id', $storeId)
                ->where('is_temp', true)
                ->where('created_at', '<', now()->subHour())
                ->each(function ($media) {
                    $this->uploadService->delete($media);
                });
        }

        return response()->json(['message' => 'Temp files cleaned.']);
    }
}
