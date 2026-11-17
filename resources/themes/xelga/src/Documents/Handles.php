<?php

namespace Themes\Xelga\Documents;

/**
 * The documents a store has: the theme settings, the header and footer
 * groups, one template per page type, and custom templates (special
 * layouts) named `{page type}.{name}`, such as `product.gift-guide`.
 */
final class Handles
{
    public const SETTINGS = 'settings';

    public const GROUPS = ['header', 'footer'];

    public const TEMPLATES = [
        'home', 'product', 'category', 'brand', 'brand-list', 'search', 'cart',
        'blog', 'blog-post', 'page', 'contact', 'not-found',
    ];

    /** Handles every page reads, besides its own template. */
    public const SHARED = ['settings', 'header', 'footer'];

    /**
     * Page types a custom template can be made for, with the kind of core
     * record it is then assigned to (Assignment::SUBJECTS).
     */
    public const CUSTOMIZABLE = [
        'product' => 'product',
        'category' => 'category',
        'brand' => 'brand',
        'page' => 'page',
        'blog-post' => 'blog_post',
    ];

    /** The name part of a custom template handle: lower case words joined by single hyphens. */
    public const CUSTOM_NAME = '[a-z0-9]+(?:-[a-z0-9]+)*';

    /**
     * Whether the handle names a document the store can have. A custom
     * template handle is only well formed here; whether it exists is a
     * database question (Handles::exists).
     */
    public static function known(string $handle): bool
    {
        return $handle === self::SETTINGS
            || in_array($handle, self::GROUPS, true)
            || in_array($handle, self::TEMPLATES, true)
            || self::isCustom($handle);
    }

    /** A custom template handle: a page type that allows them, a dot, a name. */
    public static function isCustom(string $handle): bool
    {
        if (strlen($handle) > 64 || ! str_contains($handle, '.')) {
            return false;
        }
        [$type, $name] = explode('.', $handle, 2);

        return isset(self::CUSTOMIZABLE[$type]) && strlen($name) <= 48 && preg_match('/\A' . self::CUSTOM_NAME . '\z/', $name) === 1;
    }

    /**
     * Whether the document can be read or saved: every standard handle, and
     * a custom template only once it has been made (TemplateManager::create).
     */
    public static function exists(string $handle): bool
    {
        if (! self::known($handle)) {
            return false;
        }

        return ! self::isCustom($handle) || Document::where('handle', $handle)->where('kind', 'template')->exists();
    }

    public static function kind(string $handle): string
    {
        return match (true) {
            $handle === self::SETTINGS => 'settings',
            in_array($handle, self::GROUPS, true) => 'group',
            default => 'template',
        };
    }

    public static function pageType(string $handle): ?string
    {
        return self::kind($handle) === 'template' ? explode('.', $handle, 2)[0] : null;
    }

    /** The subject kind a custom template of this page type is assigned to, or null. */
    public static function subjectType(string $pageType): ?string
    {
        return self::CUSTOMIZABLE[$pageType] ?? null;
    }
}
