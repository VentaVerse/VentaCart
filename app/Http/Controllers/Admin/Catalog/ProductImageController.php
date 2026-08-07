<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Exceptions\UnsafeUrlException;
use App\Http\Controllers\Controller;
use App\Services\SafeUrlFetcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductImageController extends Controller
{
    /**
     * Entity-specific image roots. The OpenCart-era catalog/ umbrella is retired;
     * every upload lands under one of these (products/{brand}/, brands/, …).
     */
    private const IMAGE_ROOTS = ['products', 'brands', 'categories', 'blog', 'banners'];

    /**
     * Resolve the destination directory for an upload.
     *  - pim_token (pre-attach staging) → neutral _tmp/{token}
     *  - {root}/{folder} when a subfolder is given (e.g. products/{brand-slug}/)
     *  - flat roots (brands, categories, …) store at the root itself
     *  - brand-less products quarantine to products/_unbranded so products/ stays clean
     *
     * @return array{0:string,1:string} [directory, kind]
     */
    protected function uploadDirectory(string $root, ?string $folder, ?string $pimToken): array
    {
        if ($pimToken && !$folder) {
            return ["_tmp/{$pimToken}", 'temp'];
        }

        $safe = $folder ? preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $folder) : '';
        if ($safe !== '') {
            return ["{$root}/{$safe}", 'final'];
        }

        $directory = $root === 'products' ? 'products/_unbranded' : $root;

        return [$directory, 'final'];
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'store_id' => ['required', 'exists:stores,id'],
            'root' => ['nullable', Rule::in(self::IMAGE_ROOTS)],
            'folder' => ['nullable', 'string', 'max:200'],
            'pim_token' => ['nullable', 'string'],
        ]);

        $file = $request->file('file');
        $root = $request->input('root', 'products');
        $folder = $request->input('folder');
        $pimToken = $request->input('pim_token');

        // Convert WebP to JPG
        if ($file->getMimeType() === 'image/webp') {
            $file = $this->convertWebpToJpg($file);
        }

        [$directory, $kind] = $this->uploadDirectory($root, $folder, $pimToken);

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
            'root' => ['nullable', Rule::in(self::IMAGE_ROOTS)],
            'folder' => ['nullable', 'string', 'max:200'],
            'pim_token' => ['nullable', 'string'],
        ]);

        $imageUrl = $request->input('url');
        $root = $request->input('root', 'products');
        $catalogPath = $request->input('catalog_path');
        $folder = $request->input('folder');
        $pimToken = $request->input('pim_token');

        // Path-direct destination (the browser's current folder) takes precedence
        // and must be inside an allowed location — reject traversal.
        if (!empty($catalogPath)) {
            $normalized = str_replace('\\', '/', $catalogPath);
            if (str_contains($normalized, '..') || str_contains($normalized, './')) {
                return response()->json(['ok' => false, 'message' => 'Invalid path.'], 403);
            }
        }

        try {
            $response = app(SafeUrlFetcher::class)->get($imageUrl);

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

            if (!empty($catalogPath)) {
                // Browser's current folder is already a full entity path.
                $safe = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $catalogPath);
                $directory = $safe !== '' ? $safe : $root;
                $kind = 'final';
            } else {
                [$directory, $kind] = $this->uploadDirectory($root, $folder, $pimToken);
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
        } catch (UnsafeUrlException $e) {
            return response()->json(['ok' => false, 'message' => 'That URL is not allowed.'], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Failed to download: ' . $e->getMessage()], 422);
        }
    }

    public function browse(Request $request): JsonResponse
    {
        $root = $request->input('root');
        if ($root !== null && !in_array($root, self::IMAGE_ROOTS, true)) {
            return response()->json(['ok' => false, 'message' => 'Invalid root.'], 422);
        }

        $relativePath = $request->input('path', '');

        // Default the initial view to the caller's entity root — the manager opens
        // in-context (products/, brands/, …), never the bare storage root.
        if ($relativePath === '' && $root) {
            $relativePath = $root;
        }

        // Prevent path traversal
        $normalized = str_replace('\\', '/', $relativePath);
        if (str_contains($normalized, '..') || str_contains($normalized, './')) {
            return response()->json(['ok' => false, 'message' => 'Invalid path.'], 403);
        }

        $currentPath = $relativePath ?: '';

        $directories = Storage::disk('public')->directories($currentPath);
        $files = Storage::disk('public')->files($currentPath);

        // Directories to hide from browsing
        $hiddenDirs = ['_tmp'];

        $folders = collect($directories)
            ->reject(function ($dir) use ($hiddenDirs) {
                return in_array(basename($dir), $hiddenDirs);
            })
            ->map(function ($dir) {
                return [
                    'name' => basename($dir),
                    'path' => $dir,
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
        $directory = $request->input('catalog_path', '') ?: '';

        // Prevent path traversal
        $normalized = str_replace('\\', '/', $directory);
        if (str_contains($normalized, '..') || str_contains($normalized, './')) {
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

        // Prevent path traversal
        $normalized = str_replace('\\', '/', $path);
        if (str_contains($normalized, '..') || str_contains($normalized, './') || $normalized === '') {
            return response()->json(['ok' => false, 'message' => 'Invalid path.'], 403);
        }

        Storage::disk('public')->delete($path);

        // Delete thumbnail
        $info = pathinfo($path);
        $thumbPath = $info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');
        Storage::disk('public')->delete($thumbPath);

        return response()->json(['ok' => true]);
    }

    /**
     * Check if a path is within the base directory (no realpath to avoid open_basedir with symlinks).
     */
    protected function isWithinBase(string $base, string $path): bool
    {
        // Normalize: collapse ../ and ./ segments
        $normalized = str_replace('\\', '/', $path);
        if (str_contains($normalized, '..') || str_contains($normalized, './')) {
            return false;
        }

        return str_starts_with($normalized, $base . '/') || $normalized === $base;
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
