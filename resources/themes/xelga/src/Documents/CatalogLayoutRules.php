<?php

namespace Themes\Xelga\Documents;

use Themes\Xelga\Catalog\Catalog;

/**
 * The layout rules of one document, read from the section catalog.
 * Resolved as app(LayoutRules::class, ['handle' => $handle]).
 */
final class CatalogLayoutRules implements LayoutRules
{
    public function __construct(private readonly string $handle) {}

    /**
     * A type the catalog does not know (an extension that is away, a
     * section a theme update removed) counts as the narrowest span, so it
     * keeps its place until its schema can say otherwise.
     */
    public function minSpan(array $section): int
    {
        $schema = Catalog::section((string) ($section['type'] ?? ''));
        if ($schema === null) {
            return Layout::SIDE_SPAN;
        }

        $settings = (array) ($section['settings'] ?? []);
        $variant = $settings['variant'] ?? ($schema->settings()['variant'] ?? null)?->default;

        return $schema->minSpan(is_string($variant) ? $variant : null);
    }

    public function isMain(array $section): bool
    {
        $schema = Catalog::section((string) ($section['type'] ?? ''));

        return $schema !== null && ($schema->raw['main'] ?? false) === true;
    }

    public function sidesAllowed(): bool
    {
        return Layout::sidesAllowed($this->handle);
    }
}
