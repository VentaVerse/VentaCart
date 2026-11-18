<?php

namespace App\Rules;

use App\Support\MediaLibrary;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Storage;

/**
 * An image value an admin form may store: a picture from the media library.
 *
 * Only a value that is being changed is checked. Whatever the record already
 * held ($unchanged) passes as it is, so a record that points at art from
 * before the library existed can still be saved with other edits. Anything
 * new must be an existing image inside the library, so no form can point a
 * record at a file elsewhere on the public disk.
 */
final class MediaImage implements ValidationRule
{
    /** @param  array<int, mixed>  $unchanged  Values stored on the record before this save. */
    public function __construct(private readonly array $unchanged = [])
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('Choose an image from the media library.');

            return;
        }

        if (in_array($value, $this->unchanged, true)) {
            return;
        }

        $path = MediaLibrary::safePath($value);

        if ($path === false
            || $path !== $value
            || MediaLibrary::isRoot($path)
            || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), MediaLibrary::EXTENSIONS, true)
        ) {
            $fail('Choose an image from the media library.');

            return;
        }

        if (! Storage::disk('public')->exists($path)) {
            $fail('That image was moved or deleted. Choose it again.');
        }
    }
}
