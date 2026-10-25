<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Rich text that every theme prints as HTML: a product's description, a
 * category's description, a page's body, a blog post's content.
 *
 * Writing raw HTML (script, event handlers, embeds, forms) is the
 * storefront.code permission, which only a super admin holds. Anyone else
 * who edits these fields, a Store Manager for example, has the HTML they
 * save passed through an allow-list: headings, paragraphs, lists, links,
 * images, tables, quotes, code, video and audio, and inline styles limited
 * to text and box formatting. Embedded frames are kept only from the hosts
 * config('content.embed_hosts') lists.
 *
 * The check runs on save, not on render, so existing content is never
 * rewritten on its own: a field is cleaned only when someone without
 * storefront.code changes it. Commands, queued jobs and the installer run
 * with nobody signed in and are trusted, like the operator who starts them.
 */
final class RichText
{
    /** Inline style properties kept, each with a plain value. */
    private const STYLE_PROPERTIES = [
        'text-align', 'vertical-align', 'color', 'background-color',
        'font-weight', 'font-style', 'font-size', 'line-height', 'letter-spacing',
        'text-decoration', 'text-transform', 'white-space',
        'width', 'height', 'max-width', 'min-width',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'border', 'border-top', 'border-right', 'border-bottom', 'border-left',
        'border-color', 'border-style', 'border-width', 'border-collapse', 'border-radius',
        'float', 'clear', 'list-style-type',
    ];

    private static ?HtmlSanitizer $sanitizer = null;

    private static ?string $sanitizerHosts = null;

    /** The HTML with everything outside the allow-list removed. */
    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        return self::sanitizer()->sanitize($html);
    }

    /**
     * True when whoever is saving may keep raw HTML: nobody signed in
     * outside a web request (a command, a job), or an admin holding
     * storefront.code.
     */
    public static function writerMayUseRawHtml(): bool
    {
        $user = auth('web')->user();

        if ($user === null) {
            // No route means no web request: a command, a job, a seeder.
            // An API call with nobody signed in is not trusted.
            return request()->route() === null;
        }

        return $user instanceof User && Gate::forUser($user)->allows(AdminPermissions::STOREFRONT_CODE);
    }

    /**
     * Clean the model's changed rich text fields when the writer may not
     * keep raw HTML. Called from HasRichText on saving.
     *
     * @param  list<string>  $fields
     */
    public static function guard(Model $model, array $fields): void
    {
        $changed = array_values(array_filter($fields, fn ($field) => $model->isDirty($field) && is_string($model->getAttribute($field))));

        if ($changed === [] || self::writerMayUseRawHtml()) {
            return;
        }

        $removed = false;
        foreach ($changed as $field) {
            $value = (string) $model->getAttribute($field);
            $clean = (string) self::sanitize($value);
            if ($clean !== $value) {
                $model->setAttribute($field, $clean);
                $removed = $removed || self::visiblyDifferent($value, $clean);
            }
        }

        if ($removed && request()->hasSession()) {
            request()->session()->flash('warning', 'Some HTML was removed: scripts, embeds and similar code can only be added by a super admin.');
        }
    }

    /** True when cleaning removed more than the sanitizer's own reformatting. */
    private static function visiblyDifferent(string $before, string $after): bool
    {
        $normalise = fn (string $html) => preg_replace('/\s+/', '', html_entity_decode(strip_tags($html, '<a><img><iframe><video><audio><source><table>')));

        return $normalise($before) !== $normalise($after)
            || preg_match('/<\s*(script|iframe|object|embed|form|style|svg|math)\b|\son[a-z]+\s*=|javascript:/i', $before) === 1;
    }

    private static function sanitizer(): HtmlSanitizer
    {
        $hosts = self::embedHosts();
        $key = implode(',', $hosts);

        if (self::$sanitizer !== null && self::$sanitizerHosts === $key) {
            return self::$sanitizer;
        }

        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https', 'data'])
            ->allowRelativeMedias()
            ->allowAttribute('style', '*')
            ->withAttributeSanitizer(new class(self::STYLE_PROPERTIES) implements AttributeSanitizerInterface
            {
                public function __construct(private array $properties)
                {
                }

                public function getSupportedElements(): ?array
                {
                    return null;
                }

                public function getSupportedAttributes(): ?array
                {
                    return ['style'];
                }

                public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
                {
                    return RichText::style($value, $this->properties);
                }
            })
            ->withMaxInputLength(-1);

        if ($hosts !== []) {
            $config = $config
                ->allowElement('iframe', ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'loading'])
                ->forceAttribute('iframe', 'sandbox', 'allow-scripts allow-same-origin allow-presentation allow-popups')
                ->withAttributeSanitizer(new class($hosts) implements AttributeSanitizerInterface
                {
                    public function __construct(private array $hosts)
                    {
                    }

                    public function getSupportedElements(): ?array
                    {
                        return ['iframe'];
                    }

                    public function getSupportedAttributes(): ?array
                    {
                        return ['src'];
                    }

                    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
                    {
                        $host = strtolower((string) parse_url($value, PHP_URL_HOST));

                        return str_starts_with(strtolower($value), 'https://') && in_array($host, $this->hosts, true) ? $value : null;
                    }
                });
        }

        self::$sanitizerHosts = $key;

        return self::$sanitizer = new HtmlSanitizer($config);
    }

    /** @return list<string> */
    private static function embedHosts(): array
    {
        $hosts = config('content.embed_hosts', []);
        $hosts = is_string($hosts) ? explode(',', $hosts) : (array) $hosts;

        return array_values(array_filter(array_map(fn ($h) => strtolower(trim((string) $h)), $hosts), fn ($h) => preg_match('/^[a-z0-9.-]+$/', $h) === 1));
    }

    /**
     * An inline style kept to the listed properties, each with a value of
     * plain words, numbers, units, hex colours and rgb()/hsl(). Null when
     * nothing is left.
     *
     * @param  list<string>  $properties
     */
    public static function style(string $value, array $properties = self::STYLE_PROPERTIES): ?string
    {
        if (preg_match('/[\\\\<>]|\/\*|expression|url\s*\(|@import|behavior|-moz-binding/i', $value) === 1) {
            return null;
        }

        $kept = [];
        foreach (explode(';', $value) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }
            [$property, $val] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            if (! in_array($property, $properties, true) || $val === '') {
                continue;
            }
            if (preg_match('/^(?:[#a-z0-9.%\s,-]|(?:rgba?|hsla?)\([0-9.,%\s]+\))+$/i', $val) !== 1) {
                continue;
            }
            $kept[] = $property . ': ' . $val;
        }

        return $kept === [] ? null : implode('; ', $kept);
    }
}
