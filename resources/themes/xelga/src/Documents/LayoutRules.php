<?php

namespace Themes\Xelga\Documents;

/**
 * What the layout normalizer needs to know about a page's sections: how
 * narrow a column each one can sit in, which one is the page's main
 * section, and whether the page may have sides. Kept apart from the
 * catalog so the normalizer stays a pure function of its input.
 */
interface LayoutRules
{
    /** The narrowest column, in twelfths, the section reads well in. */
    public function minSpan(array $section): int;

    /** Whether the section is the page's main section, which sits only in a one-column row. */
    public function isMain(array $section): bool;

    /** Whether the page may have a left and a right side. */
    public function sidesAllowed(): bool;
}
