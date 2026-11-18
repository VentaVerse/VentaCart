<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * The file extension an uploaded image is saved under, read from its bytes.
 *
 * The public disk is served by the web server, so a saved file's ending
 * decides how it is served: a client-chosen ending such as .php or .html
 * could be run as code or rendered as a page on the store's own domain. The
 * name the browser sent is therefore never used for the ending; only the
 * content type found in the file itself, limited to a short list of images.
 */
final class ImageExtension
{
    private const BY_TYPE = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    private const LABELS = ['jpg' => 'JPG', 'png' => 'PNG', 'webp' => 'WebP', 'gif' => 'GIF'];

    /**
     * The extension the file's content calls for, or null when it is not one
     * of the allowed image types.
     *
     * @param  array<int, string>  $allowed
     */
    public static function of(UploadedFile $file, array $allowed = ['jpg', 'png', 'webp']): ?string
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_file($path)) {
            return null;
        }

        // finfo reads the bytes. getMimeType() is not used, because a test
        // double reports its type from the client file name.
        $type = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $extension = self::BY_TYPE[$type] ?? null;

        return $extension !== null && in_array($extension, $allowed, true) ? $extension : null;
    }

    /**
     * Same as of(), but refuses the upload with a validation error on $field.
     *
     * @param  array<int, string>  $allowed
     */
    public static function orRefuse(UploadedFile $file, string $field, array $allowed = ['jpg', 'png', 'webp']): string
    {
        $extension = self::of($file, $allowed);

        if ($extension !== null) {
            return $extension;
        }

        $labels = array_map(fn (string $ext) => self::LABELS[$ext] ?? strtoupper($ext), $allowed);
        $last = array_pop($labels);
        $list = $labels === [] ? $last : implode(', ', $labels) . ' or ' . $last;

        throw ValidationException::withMessages([
            $field => 'That file is not a photo. Use a ' . $list . ' file.',
        ]);
    }
}
