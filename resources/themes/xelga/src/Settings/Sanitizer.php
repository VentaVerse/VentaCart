<?php

namespace Themes\Xelga\Settings;

use App\Support\SafeInput;
use Themes\Xelga\Catalog\SettingSchema;

/**
 * Value in, clean value out, per setting type. A value that does not fit is
 * corrected (clamped, defaulted or stripped) rather than refused, and each
 * correction is reported so the editor can update its state and tell the
 * merchant. Text is stored as typed and escaped when printed; rich text is
 * the only HTML, and it is cleaned here.
 */
final class Sanitizer
{
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];

    /** How each picker names what it stores, in messages. */
    private const RECORD_WORDS = [
        'product-picker' => 'product',
        'category-picker' => 'category',
        'brand-picker' => 'brand',
        'page-picker' => 'page',
        'blog-post-picker' => 'blog post',
    ];

    /** @var list<array{path: string, reason: string, value: mixed}> */
    private array $adjusted = [];

    public function __construct(private readonly SanitizeContext $context)
    {
    }

    /** @return list<array{path: string, reason: string, value: mixed}> */
    public function adjusted(): array
    {
        return $this->adjusted;
    }

    /**
     * Every value of a settings map, cleaned against its schema. Keys the
     * schema does not know are dropped.
     *
     * @param  array<string, SettingSchema>  $schema
     * @return array<string, mixed>
     */
    public function cleanMap(array $schema, mixed $values, string $path): array
    {
        if (! is_array($values) || ($values !== [] && array_is_list($values))) {
            return [];
        }

        $out = [];
        foreach ($values as $key => $value) {
            $setting = $schema[(string) $key] ?? null;
            if ($setting === null || $setting->type === 'scheme-colors') {
                continue;
            }
            $out[(string) $key] = $this->clean($setting, $value, $path . '.' . $key);
        }

        return $out;
    }

    public function clean(SettingSchema $setting, mixed $value, string $path): mixed
    {
        [$clean, $reason] = $this->cleanValue($setting, $value);

        if ($reason !== null) {
            $this->adjusted[] = ['path' => $path, 'reason' => $reason, 'value' => $clean];
        }

        return $clean;
    }

    /** @return array{0: mixed, 1: ?string} the clean value and why it changed, if it did */
    private function cleanValue(SettingSchema $setting, mixed $value): array
    {
        $default = $setting->default;

        switch ($setting->type) {
            case 'text':
            case 'textarea':
                // The framework turns an emptied field into null on the way in.
                $value ??= '';
                if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
                    return [$default ?? '', 'Not text; the default was used.'];
                }
                $clean = SafeInput::text($value, $setting->maxLength(), $setting->type === 'textarea');
                $clean = $setting->type === 'text' ? trim($clean) : $clean;

                return [$clean, $clean === (string) $value ? null : ($this->tooLong($value, $setting) ? 'Shortened to ' . $setting->maxLength() . ' characters.' : 'Control characters or line breaks were removed.')];

            case 'richtext':
                $value ??= '';
                $clean = RichTextPolicy::clean($value);
                if ($clean === null) {
                    return [$default ?? '', 'Longer than ' . RichTextPolicy::MAX . ' characters; the text was not kept.'];
                }

                return [$clean, $clean === $value ? null : 'Only paragraphs, bold, italic, links and lists are kept.'];

            case 'image':
                if ($value === '' || $value === null) {
                    return ['', null];
                }
                $path = SafeInput::storagePath($value);
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if ($path === '' || ! in_array($ext, self::IMAGE_EXTENSIONS, true)) {
                    return ['', 'Not a JPG, PNG, WebP, AVIF or GIF from the media library.'];
                }
                if (! $this->context->fileExists($path)) {
                    return ['', 'That photo is not in the media library.'];
                }

                return [$path, $path === $value ? null : 'The photo path was tidied.'];

            case 'url':
                $clean = LinkValue::clean($value, $setting->followsProducts());
                $given = $value === '' || $value === [] ? null : $value;
                if ($given === null) {
                    return [null, null];
                }
                if ($clean === null) {
                    return [null, 'Not a link the store can use; it was removed.'];
                }

                return [$clean, $clean === $given ? null : 'The link was tidied.'];

            case 'select':
                $allowed = $setting->optionsSource() !== null ? $this->context->options($setting->optionsSource()) : $setting->optionValues();
                // A lint context knows no store: a store-sourced list cannot be checked there.
                if ($setting->optionsSource() === 'menus' && $this->context->menuKeys === null) {
                    return [is_string($value) ? $value : $default, null];
                }
                // Blog categories: empty means every category; a context that
                // knows no store accepts any well-formed id.
                if ($setting->optionsSource() === 'blog_categories') {
                    if ($value === '' || $value === null) {
                        return ['', null];
                    }
                    if (! $this->context->checkFiles) {
                        return is_string($value) && preg_match('/^[1-9][0-9]{0,17}$/', $value) === 1 ? [$value, null] : ['', 'Not a blog category; every category is used.'];
                    }
                    $clean = SafeInput::oneOf(is_int($value) ? (string) $value : $value, $allowed);

                    return $clean === null ? ['', 'That blog category no longer exists; every category is used.'] : [$clean, null];
                }
                $clean = SafeInput::oneOf($value, $allowed);
                if ($clean === null) {
                    return [$default, 'Not one of the choices; the default was used.'];
                }

                return [$clean, null];

            case 'range':
                $number = SafeInput::number($value, $setting->min(), $setting->max());
                if ($number === null) {
                    return [$default, 'Not a number; the default was used.'];
                }
                $snapped = $this->snap((float) $number, $setting);

                return [$snapped, (is_int($value) || is_float($value)) && (float) $value === (float) $snapped ? null : 'Kept between ' . $this->num($setting->min()) . ' and ' . $this->num($setting->max()) . ' in steps of ' . $this->num($setting->step()) . '.'];

            case 'toggle':
                if (is_bool($value)) {
                    return [$value, null];
                }

                return [(bool) $default, 'Not on or off; the default was used.'];

            case 'color-scheme':
                if (is_string($value) && in_array($value, $this->context->schemeIds, true)) {
                    return [$value, null];
                }
                $fallback = in_array($default, $this->context->schemeIds, true) ? $default : ($this->context->schemeIds[0] ?? 'scheme-1');

                return [$fallback, 'That color scheme does not exist; ' . $fallback . ' was used.'];

            case 'menu-picker':
                if (! is_string($value) || $value === '') {
                    return ['', $value === '' || $value === null ? null : 'Not a menu; no menu is shown.'];
                }
                if ($this->context->menuKeys === null) {
                    return preg_match('/^[a-z0-9_-]{1,64}$/', $value) === 1 ? [$value, null] : ['', 'Not a menu; no menu is shown.'];
                }

                return in_array($value, $this->context->menuKeys, true) ? [$value, null] : ['', 'That menu does not exist; no menu is shown.'];

            case 'product-picker':
            case 'category-picker':
            case 'brand-picker':
            case 'page-picker':
            case 'blog-post-picker':
                return $setting->multiple() ? $this->cleanIdList($setting, $value) : $this->cleanId($setting, $value);
        }

        return [$default, 'Unknown setting type; the default was used.'];
    }

    /** @return array{0: ?int, 1: ?string} */
    private function cleanId(SettingSchema $setting, mixed $value): array
    {
        if ($value === null || $value === '' || $value === 0) {
            return [null, null];
        }
        $id = self::id($value);
        $what = self::RECORD_WORDS[$setting->type] ?? 'record';
        if ($id === null) {
            return [null, "Not a {$what}; nothing is chosen."];
        }
        if ($this->context->existing(SanitizeContext::PICKER_RECORDS[$setting->type], [$id]) === []) {
            return [null, "That {$what} no longer exists; nothing is chosen."];
        }

        return [$id, $id === $value ? null : "The {$what} was tidied."];
    }

    /**
     * A hand-picked list keeps the merchant's order. Repeats and ids that no
     * longer exist are dropped, and the list is cut to the setting's most.
     *
     * @return array{0: list<int>, 1: ?string}
     */
    private function cleanIdList(SettingSchema $setting, mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [[], null];
        }
        $what = self::RECORD_WORDS[$setting->type] ?? 'record';
        if (! is_array($value) || ! array_is_list($value)) {
            return [[], "Not a list of {$what}s; nothing is chosen."];
        }

        // Keyed by id, so a repeat costs one lookup; the walk stops at the
        // setting's most, so a huge list never reaches the database whole.
        $seen = [];
        $max = $setting->maxItems();
        foreach ($value as $item) {
            $id = self::id($item);
            if ($id !== null && ! isset($seen[$id])) {
                $seen[$id] = true;
                if (count($seen) >= $max) {
                    break;
                }
            }
        }
        $ids = $this->context->existing(SanitizeContext::PICKER_RECORDS[$setting->type], array_keys($seen));

        if ($ids === $value) {
            return [$ids, null];
        }

        return [$ids, count($value) > $setting->maxItems() ? "Kept the first {$setting->maxItems()} {$what}s." : "{$what}s that no longer exist or were listed twice were left out."];
    }

    /** A positive whole number given as a number or digits, else null. */
    private static function id(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_string($value) && preg_match('/^[1-9][0-9]{0,17}$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function snap(float $number, SettingSchema $setting): int|float
    {
        $steps = round(($number - $setting->min()) / $setting->step());
        $value = round($setting->min() + $steps * $setting->step(), 4);
        $value = max($setting->min(), min($setting->max(), $value));

        return floor($value) === $value ? (int) $value : $value;
    }

    private function num(float $n): string
    {
        return floor($n) === $n ? (string) (int) $n : (string) $n;
    }

    private function tooLong(mixed $value, SettingSchema $setting): bool
    {
        return mb_strlen((string) $value) > $setting->maxLength();
    }
}
