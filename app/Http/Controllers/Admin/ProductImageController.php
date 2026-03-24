<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'store_id' => ['required', 'exists:stores,id'],
            'folder' => ['nullable', 'string', 'max:200'],
            'pim_token' => ['nullable', 'string'],
        ]);

        $file = $request->file('file');
        $folder = $request->input('folder');
        $pimToken = $request->input('pim_token');

        // Convert WebP to JPG
        if ($file->getMimeType() === 'image/webp') {
            $file = $this->convertWebpToJpg($file);
        }

        // Determine storage path
        if ($pimToken && !$folder) {
            $directory = "catalog/_tmp/{$pimToken}";
            $kind = 'temp';
        } else {
            $safeName = $folder ? preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $folder) : 'unsorted';
            $directory = "catalog/{$safeName}";
            $kind = 'final';
        }

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, 'public');

        $this->generateThumbnail($path);

        return response()->json([
            'ok' => true,
            'path' => $path,
            'url' => storage_url($path),
            'kind' => $kind,
        ]);
    }

    public function importUrl(Request $request): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url'],
            'store_id' => ['required', 'exists:stores,id'],
            'folder' => ['nullable', 'string', 'max:200'],
            'pim_token' => ['nullable', 'string'],
        ]);

        $imageUrl = $request->input('url');
        $folder = $request->input('folder') ?? $request->input('catalog_path');
        $pimToken = $request->input('pim_token');

        try {
            $response = Http::timeout(15)->get($imageUrl);

            if (!$response->successful()) {
                return response()->json(['ok' => false, 'message' => 'Failed to download image.'], 422);
            }

            $contentType = $response->header('Content-Type');
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

            if (!in_array($contentType, $allowedTypes)) {
                return response()->json(['ok' => false, 'message' => 'URL is not a valid image.'], 422);
            }

            $ext = match ($contentType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'jpg', // will convert
                'image/gif' => 'gif',
                default => 'jpg',
            };

            if ($pimToken && !$folder) {
                $directory = "catalog/_tmp/{$pimToken}";
                $kind = 'temp';
            } else {
                $safeName = $folder ? preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $folder) : 'unsorted';
                $directory = "catalog/{$safeName}";
                $kind = 'final';
            }

            $filename = Str::uuid() . '.' . $ext;
            $fullPath = "{$directory}/{$filename}";

            Storage::disk('public')->put($fullPath, $response->body());

            // Convert WebP
            if ($contentType === 'image/webp') {
                $fullPath = $this->convertStoredWebpToJpg($fullPath);
            }

            $this->generateThumbnail($fullPath);

            return response()->json([
                'ok' => true,
                'path' => $fullPath,
                'url' => storage_url($fullPath),
                'kind' => $kind,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Failed to download: ' . $e->getMessage()], 422);
        }
    }

    public function browse(Request $request): JsonResponse
    {
        $relativePath = $request->input('path', '');

        $basePath = 'catalog';
        $currentPath = $relativePath ? "{$basePath}/{$relativePath}" : $basePath;

        // Prevent path traversal
        $realBase = Storage::disk('public')->path($basePath);
        $realCurrent = Storage::disk('public')->path($currentPath);
        if (!str_starts_with(realpath($realCurrent) ?: $realCurrent, realpath($realBase) ?: $realBase)) {
            return response()->json(['ok' => false, 'message' => 'Invalid path.'], 403);
        }

        $directories = Storage::disk('public')->directories($currentPath);
        $files = Storage::disk('public')->files($currentPath);

        $folders = collect($directories)
            ->reject(function ($dir) {
                // Hide temp upload directory from browser
                return basename($dir) === '_tmp';
            })
            ->map(function ($dir) use ($basePath) {
                return [
                    'name' => basename($dir),
                    'path' => str_replace($basePath . '/', '', $dir),
                ];
            })->values();

        $imageFiles = collect($files)
            ->filter(function ($file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            })
            ->reject(function ($file) {
                return str_contains($file, '_thumb.');
            })
            ->map(function ($file) {
                return [
                    'name' => basename($file),
                    'path' => $file,
                    'url' => storage_url($file),
                    'size' => Storage::disk('public')->size($file),
                    'modified' => Storage::disk('public')->lastModified($file),
                ];
            })
            ->values();

        $parent = $relativePath ? dirname($relativePath) : null;
        if ($parent === '.') {
            $parent = '';
        }

        return response()->json([
            'ok' => true,
            'folders' => $folders,
            'files' => $imageFiles,
            'parent' => $parent,
            'path' => $relativePath ?: '',
        ]);
    }

    public function uploadToCatalog(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'catalog_path' => ['nullable', 'string'],
        ]);

        $file = $request->file('file');
        $catalogPath = $request->input('catalog_path', '');

        $basePath = 'catalog';
        $directory = $catalogPath ? "{$basePath}/{$catalogPath}" : $basePath;

        // Prevent path traversal
        $realBase = Storage::disk('public')->path($basePath);
        $realDir = Storage::disk('public')->path($directory);
        if (!str_starts_with(realpath($realDir) ?: $realDir, realpath($realBase) ?: $realBase)) {
            return response()->json(['ok' => false, 'message' => 'Invalid path.'], 403);
        }

        if ($file->getMimeType() === 'image/webp') {
            $file = $this->convertWebpToJpg($file);
        }

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, 'public');

        $this->generateThumbnail($path);

        return response()->json([
            'ok' => true,
            'path' => $path,
            'url' => storage_url($path),
            'name' => $filename,
            'size' => Storage::disk('public')->size($path),
        ]);
    }

    public function delete(Request $request): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string'],
        ]);

        $path = $request->input('path');

        // Ensure path is within the catalog directory
        if (!str_starts_with($path, 'catalog/')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized.'], 403);
        }

        // Prevent deleting from _tmp of other sessions, etc. — just verify it's a real catalog path
        $realBase = Storage::disk('public')->path('catalog');
        $realFile = Storage::disk('public')->path($path);
        if (!str_starts_with(realpath($realFile) ?: $realFile, realpath($realBase) ?: $realBase)) {
            return response()->json(['ok' => false, 'message' => 'Invalid path.'], 403);
        }

        Storage::disk('public')->delete($path);

        // Delete thumbnail
        $info = pathinfo($path);
        $thumbPath = $info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');
        Storage::disk('public')->delete($thumbPath);

        return response()->json(['ok' => true]);
    }

    protected function generateThumbnail(string $path): void
    {
        try {
            $fullPath = Storage::disk('public')->path($path);
            $size = getimagesize($fullPath);
            if (!$size) {
                return;
            }

            $srcImage = match ($size['mime']) {
                'image/jpeg' => imagecreatefromjpeg($fullPath),
                'image/png' => imagecreatefrompng($fullPath),
                'image/gif' => imagecreatefromgif($fullPath),
                'image/webp' => imagecreatefromwebp($fullPath),
                default => null,
            };

            if (!$srcImage) {
                return;
            }

            $srcW = imagesx($srcImage);
            $srcH = imagesy($srcImage);
            $ratio = min(300 / $srcW, 300 / $srcH);

            if ($ratio >= 1) {
                imagedestroy($srcImage);
                return;
            }

            $dstW = (int) round($srcW * $ratio);
            $dstH = (int) round($srcH * $ratio);
            $dstImage = imagecreatetruecolor($dstW, $dstH);

            if (in_array($size['mime'], ['image/png', 'image/gif', 'image/webp'])) {
                imagealphablending($dstImage, false);
                imagesavealpha($dstImage, true);
                $transparent = imagecolorallocatealpha($dstImage, 0, 0, 0, 127);
                imagefilledrectangle($dstImage, 0, 0, $dstW, $dstH, $transparent);
            }

            imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

            $info = pathinfo($fullPath);
            $thumbPath = $info['dirname'] . '/' . $info['filename'] . '_thumb.' . $info['extension'];

            match ($size['mime']) {
                'image/jpeg' => imagejpeg($dstImage, $thumbPath, 85),
                'image/png' => imagepng($dstImage, $thumbPath, 8),
                'image/gif' => imagegif($dstImage, $thumbPath),
                'image/webp' => imagewebp($dstImage, $thumbPath, 85),
                default => null,
            };

            imagedestroy($srcImage);
            imagedestroy($dstImage);
        } catch (\Throwable $e) {
            // Silent fail — original still works
        }
    }

    protected function convertWebpToJpg($file)
    {
        $src = imagecreatefromwebp($file->getRealPath());
        $tmpPath = tempnam(sys_get_temp_dir(), 'webp') . '.jpg';
        imagejpeg($src, $tmpPath, 90);
        imagedestroy($src);

        return new \Illuminate\Http\UploadedFile($tmpPath, pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.jpg', 'image/jpeg', null, true);
    }

    protected function convertStoredWebpToJpg(string $path): string
    {
        $fullPath = Storage::disk('public')->path($path);
        $src = imagecreatefromwebp($fullPath);
        $newPath = preg_replace('/\.webp$/i', '.jpg', $path);
        $newFullPath = Storage::disk('public')->path($newPath);

        imagejpeg($src, $newFullPath, 90);
        imagedestroy($src);
        Storage::disk('public')->delete($path);

        return $newPath;
    }
}
