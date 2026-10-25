<?php

namespace App\Support;

/**
 * Cleaners for admin-entered values that a theme prints into an attribute,
 * a style or a script: links, colours, image paths, numbers and choices.
 *
 * Blade's escaping stops a value from closing its attribute, but not from
 * being a `javascript:` link, a CSS expression or an Alpine expression. A
 * value that reaches a theme should already be one of the shapes below, so
 * every theme draws the same safe data whatever its views do with it.
 *
 * Each cleaner returns its empty value ('' or the given default) for
 * anything it cannot accept, never a repaired guess.
 */
final class SafeInput
{
    public const LINK_MAX = 2000;

    public const PATH_MAX = 500;

    /** Schemes a link may name. Anything else, `javascript:` first, is refused. */
    private const LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Whitespace and control characters, including the Unicode ones. A
     * browser drops a tab or a newline inside a scheme, so "java<TAB>script:"
     * runs as script: a link that holds any of these is refused outright.
     */
    private const INVISIBLE = '/[\x00-\x20\x7F\x{0080}-\x{00A0}\x{00AD}\x{1680}\x{180E}\x{2000}-\x{200F}\x{2028}-\x{202F}\x{205F}-\x{2064}\x{3000}\x{FEFF}]/u';

    /**
     * A link a theme may put in an href: a path ("/sale", "sale/shoes"), an
     * anchor ("#top"), a query ("?page=2"), or an http(s), mailto or tel
     * address. Surrounding spaces are trimmed; anything inside it that is
     * whitespace, a control character, a quote, an angle bracket or a
     * backslash refuses the whole value. Returns '' when refused or empty.
     */
    public static function link(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            return '';
        }

        $value = trim((string) $value, " \t\n\r\0\x0B");

        return self::isLink($value) ? $value : '';
    }

    /** True when the value is a link link() keeps unchanged. */
    public static function isLink(string $value): bool
    {
        if ($value === '' || strlen($value) > self::LINK_MAX || ! mb_check_encoding($value, 'UTF-8')) {
            return false;
        }

        if (preg_match(self::INVISIBLE, $value) === 1 || preg_match('/[<>"\'`\\\\]/', $value) === 1) {
            return false;
        }

        // A character reference ("&#58;", "&colon;") turns back into the
        // character wherever a theme prints the link unescaped.
        if (preg_match('/&(#|[a-z][a-z0-9]*;)/i', $value) === 1) {
            return false;
        }

        // A protocol-relative address leaves the store on whatever scheme
        // the page is on; it is not a path.
        if (str_starts_with($value, '//')) {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $value, $m) === 1) {
            $scheme = strtolower($m[1]);
            if (! in_array($scheme, self::LINK_SCHEMES, true)) {
                return false;
            }

            return in_array($scheme, ['http', 'https'], true)
                ? preg_match('#^https?://[^/?\#@:]+(:\d{1,5})?([/?\#]|$)#i', $value) === 1
                : strlen($value) > strlen($scheme) + 1;
        }

        // No scheme: a relative path, anchor or query. A colon before the
        // first slash, question mark or hash could still be read as one.
        $head = strcspn($value, '/?#');

        return ! str_contains(substr($value, 0, $head), ':');
    }

    /** A hex colour (#rgb, #rgba, #rrggbb, #rrggbbaa), lower-cased, or ''. */
    public static function color(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $value = strtolower(trim($value));

        return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value) === 1 ? $value : '';
    }

    /**
     * A file path inside the public storage disk ("banners/hero.jpg"), as
     * the media library stores it. No scheme, no leading slash, no ".."
     * segment, no backslash, quote, angle bracket or control character.
     * Spaces and brackets are allowed, since uploaded file names carry them;
     * print it through storage_url(), which encodes them.
     */
    public static function storagePath(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $value = trim($value);

        if ($value === '' || strlen($value) > self::PATH_MAX || ! mb_check_encoding($value, 'UTF-8')) {
            return '';
        }

        if (preg_match('/[\x00-\x1F\x7F<>"\'`\\\\:]/', $value) === 1 || preg_match(self::INVISIBLE, str_replace(' ', '', $value)) === 1) {
            return '';
        }

        if (str_starts_with($value, '/') || in_array('..', explode('/', $value), true)) {
            return '';
        }

        return $value;
    }

    /** A whole number between $min and $max; $default when it is not a number. */
    public static function int(mixed $value, int $min, int $max, ?int $default = null): ?int
    {
        if (is_bool($value) || ! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) round((float) $value)));
    }

    /** A number between $min and $max, kept to two decimals; $default when not a number. */
    public static function number(mixed $value, float $min, float $max, int|float|null $default = null): int|float|null
    {
        if (is_bool($value) || ! is_numeric($value) || ! is_finite((float) $value)) {
            return $default;
        }

        $n = round(max($min, min($max, (float) $value)), 2);

        return floor($n) === $n ? (int) $n : $n;
    }

    /**
     * One of the allowed values, compared as strings (a select sends "4"
     * where the list says 4); $default when it is not in the list.
     *
     * @param  list<string|int>  $allowed
     */
    public static function oneOf(mixed $value, array $allowed, ?string $default = null): ?string
    {
        if (! is_scalar($value)) {
            return $default;
        }

        foreach ($allowed as $option) {
            if ((string) $option === (string) $value) {
                return (string) $option;
            }
        }

        return $default;
    }

    /**
     * Plain text, capped at $max characters, with control characters removed
     * (newlines and tabs are kept when $multiline). The theme escapes it.
     */
    public static function text(mixed $value, int $max, bool $multiline = false): string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return '';
        }

        $value = (string) $value;
        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        $value = $multiline
            ? preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', str_replace("\r\n", "\n", $value))
            : preg_replace('/[\x00-\x1F\x7F]/', ' ', $value);

        return mb_substr((string) $value, 0, $max);
    }
}
