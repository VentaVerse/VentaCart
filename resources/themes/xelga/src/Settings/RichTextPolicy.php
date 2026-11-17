<?php

namespace Themes\Xelga\Settings;

use App\Support\SafeInput;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The narrow allowlist for rich text settings: paragraphs, line breaks,
 * bold, italic, links and lists. Section text never needs images, tables
 * or inline styles, so it gets none of them. This runs when a document is
 * saved; the stored HTML is the only value a section view prints raw.
 */
final class RichTextPolicy
{
    public const MAX = 5000;

    private static ?HtmlSanitizer $sanitizer = null;

    /**
     * Clean HTML, or null when the cleaned text is longer than allowed
     * (cutting HTML part way would leave broken markup).
     */
    public static function clean(mixed $html): ?string
    {
        if (! is_string($html)) {
            return '';
        }
        if (strlen($html) > self::MAX * 4) {
            return null;
        }

        $clean = self::sanitizer()->sanitize($html);
        $clean = self::checkLinks($clean);

        return mb_strlen($clean) > self::MAX ? null : trim($clean);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig())
                ->allowElement('p')
                ->allowElement('br')
                ->allowElement('strong')
                ->allowElement('em')
                ->allowElement('ul')
                ->allowElement('ol')
                ->allowElement('li')
                ->allowElement('a', ['href'])
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowRelativeLinks()
                ->forceAttribute('a', 'rel', 'noopener')
                ->withMaxInputLength(self::MAX * 4)
        );
    }

    /**
     * Every href must also pass the store's own link rule; a link that does
     * not keeps its words and loses the address.
     */
    private static function checkLinks(string $html): string
    {
        return (string) preg_replace_callback('/<a\b([^>]*)>/i', function (array $m) {
            if (! preg_match('/\bhref="([^"]*)"/i', $m[1], $href)) {
                return '<a rel="noopener">';
            }
            $decoded = html_entity_decode($href[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $safe = SafeInput::link($decoded);

            return $safe === ''
                ? '<a rel="noopener">'
                : '<a href="' . e($safe) . '" rel="noopener">';
        }, $html);
    }
}
