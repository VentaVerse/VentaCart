<?php

namespace Themes\Xelga\Documents;

/**
 * What the layout normalizer needs to know about a page's sections: how
 * narrow a column each one can sit in, which one is the page's main
 * section, which sections keep a row of their own or belong in Content,
 * and whether the page may have areas and sides. Kept apart from the
 * catalog so the normalizer stays a pure function of its input.
 */
interface LayoutRules
{
    /** The narrowest column, in twelfths, the section reads well in. */
    public function minSpan(array $section): int;

    /** Whether the section is the page's main section, which sits only in a one-column row. */
    public function isMain(array $section): bool;

    /** Whether the page may have areas and a left and a right side. */
    public function sidesAllowed(): bool;

    /** Whether the section keeps a one-column row of its own, never a column or a side. */
    public function ownRow(array $section): bool;

    /** Whether the section belongs in Content only, never in Top or Bottom. */
    public function contentOnly(array $section): bool;

    /**
     * Whether the page's page type is there to read: false for the page of
     * an extension that is not registered, which is left as it is stored.
     */
    public function pageTypeKnown(): bool;
}
