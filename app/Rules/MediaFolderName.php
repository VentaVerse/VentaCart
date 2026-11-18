<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A name the media library can use for a folder, kept as the merchant typed it.
 *
 * The library refuses any path with "..", "./" or a leading dot in it
 * (MediaLibrary::safePath()), so a folder whose name contained one could be
 * made but never browsed, emptied or removed again. Those names are refused
 * here instead, together with the characters a file system or a URL cannot
 * hold and the invisible control characters a pasted name can carry.
 */
final class MediaFolderName implements ValidationRule
{
    public const MAX = 80;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('Give the folder a name.');

            return;
        }

        $name = trim($value);

        $problem = match (true) {
            mb_strlen($name) > self::MAX => 'A folder name can be at most ' . self::MAX . ' characters.',
            preg_match('/[\x00-\x1F\x7F]/', $name) === 1 => 'A folder name cannot contain line breaks, tabs or other hidden characters.',
            preg_match('/[\/\\\\:*?"<>|]/', $name) === 1 => 'A folder name cannot contain / \\ : * ? " < > or |.',
            str_starts_with($name, '.') => 'A folder name cannot start with a dot.',
            str_ends_with($name, '.') => 'A folder name cannot end with a dot.',
            str_contains($name, '..') => 'A folder name cannot contain two dots in a row.',
            default => null,
        };

        if ($problem !== null) {
            $fail($problem);
        }
    }
}
