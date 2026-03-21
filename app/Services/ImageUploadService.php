<?php

namespace App\Services;

use App\Models\MediaFolder;
use App\Models\StoreMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadService
{
    protected int $thumbWidth = 300;
    protected int $thumbHeight = 300;

    public function upload(
        UploadedFile $file,
        int $storeId,
        ?int $folderId = null,
        bool $isTemp = false
    ): StoreMedia {
        $folder = $folderId ? MediaFolder::find($folderId) : null;
        $directory = $this->buildDirectory($storeId, $folder, $isTemp);

        $filename = $this->generateFilename($file);
        $path = $file->storeAs($directory, $filename, 'public');

        $dimensions = $this->getImageDimensions($file);
        $this->generateThumbnail($file, $directory, $filename);

        return StoreMedia::create([
            'store_id' => $storeId,
            'media_folder_id' => $folderId,
            'path' => $path,
            'disk' => 'public',
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'is_temp' => $isTemp,
        ]);
    }

    public function delete(StoreMedia $media): bool
    {
        Storage::disk($media->disk)->delete($media->path);

        // Delete thumbnail
        $info = pathinfo($media->path);
        $thumbPath = $info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');
        Storage::disk($media->disk)->delete($thumbPath);

        return $media->delete();
    }

    public function moveToFolder(StoreMedia $media, ?MediaFolder $folder): StoreMedia
    {
        $newDirectory = $this->buildDirectory($media->store_id, $folder, false);
        $filename = basename($media->path);

        $newPath = $newDirectory . '/' . $filename;

        if ($newPath !== $media->path) {
            Storage::disk($media->disk)->move($media->path, $newPath);

            // Move thumbnail too
            $info = pathinfo($media->path);
            $thumbFilename = $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');
            $oldThumb = $info['dirname'] . '/' . $thumbFilename;
            $newThumb = $newDirectory . '/' . $thumbFilename;

            if (Storage::disk($media->disk)->exists($oldThumb)) {
                Storage::disk($media->disk)->move($oldThumb, $newThumb);
            }

            $media->update([
                'path' => $newPath,
                'media_folder_id' => $folder?->id,
                'is_temp' => false,
            ]);
        }

        return $media;
    }

    protected function buildDirectory(int $storeId, ?MediaFolder $folder, bool $isTemp): string
    {
        $base = "stores/{$storeId}";

        if ($isTemp) {
            return "{$base}/temp";
        }

        if ($folder) {
            return "{$base}/{$folder->slug}";
        }

        return "{$base}/unsorted";
    }

    protected function generateFilename(UploadedFile $file): string
    {
        $ext = $file->getClientOriginalExtension();
        return Str::uuid() . '.' . $ext;
    }

    protected function getImageDimensions(UploadedFile $file): array
    {
        try {
            $size = getimagesize($file->getRealPath());
            if ($size) {
                return ['width' => $size[0], 'height' => $size[1]];
            }
        } catch (\Throwable $e) {
            // Not an image or corrupted
        }

        return ['width' => null, 'height' => null];
    }

    protected function generateThumbnail(UploadedFile $file, string $directory, string $filename): void
    {
        try {
            $source = $file->getRealPath();
            $size = getimagesize($source);

            if (!$size) {
                return;
            }

            $mime = $size['mime'];
            $srcImage = match ($mime) {
                'image/jpeg' => imagecreatefromjpeg($source),
                'image/png' => imagecreatefrompng($source),
                'image/gif' => imagecreatefromgif($source),
                'image/webp' => imagecreatefromwebp($source),
                default => null,
            };

            if (!$srcImage) {
                return;
            }

            $srcW = imagesx($srcImage);
            $srcH = imagesy($srcImage);

            // Calculate proportional thumbnail size
            $ratio = min($this->thumbWidth / $srcW, $this->thumbHeight / $srcH);
            if ($ratio >= 1) {
                imagedestroy($srcImage);
                return; // Image is already smaller than thumb
            }

            $dstW = (int) round($srcW * $ratio);
            $dstH = (int) round($srcH * $ratio);

            $dstImage = imagecreatetruecolor($dstW, $dstH);

            // Preserve transparency for PNG/GIF/WebP
            if (in_array($mime, ['image/png', 'image/gif', 'image/webp'])) {
                imagealphablending($dstImage, false);
                imagesavealpha($dstImage, true);
                $transparent = imagecolorallocatealpha($dstImage, 0, 0, 0, 127);
                imagefilledrectangle($dstImage, 0, 0, $dstW, $dstH, $transparent);
            }

            imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

            $info = pathinfo($filename);
            $thumbFilename = $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');
            $thumbFullPath = storage_path("app/public/{$directory}/{$thumbFilename}");

            // Ensure directory exists
            $dir = dirname($thumbFullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            match ($mime) {
                'image/jpeg' => imagejpeg($dstImage, $thumbFullPath, 85),
                'image/png' => imagepng($dstImage, $thumbFullPath, 8),
                'image/gif' => imagegif($dstImage, $thumbFullPath),
                'image/webp' => imagewebp($dstImage, $thumbFullPath, 85),
                default => null,
            };

            imagedestroy($srcImage);
            imagedestroy($dstImage);
        } catch (\Throwable $e) {
            // Thumbnail generation failed silently — original still works
        }
    }
}
