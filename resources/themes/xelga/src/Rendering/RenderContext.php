<?php

namespace Themes\Xelga\Rendering;

/**
 * What a section knows about the page it is drawn on: the page type, the
 * template drawn, the subject (the product, category, brand, page or post)
 * and the data core's controller handed the page view, so a page's main
 * section shows exactly what core worked out for this shopper.
 *
 * It also says how wide the section is drawn, in twelfths of the page (a
 * column of a row is narrower than the page), so its photos are sized for
 * the column, and which section types the same document draws, so a
 * section can leave out what another section on the page already shows.
 */
final class RenderContext
{
    /**
     * @param  'header'|'template'|'footer'  $group
     * @param  array<string, mixed>  $page  the view data core's controller passed to the page
     * @param  int  $span  how wide the section is drawn, in twelfths of the page (1 to 12)
     * @param  list<string>  $types  the section types the document draws
     */
    public function __construct(
        public readonly string $pageType,
        public readonly string $handle,
        public readonly string $group,
        public readonly bool $preview,
        public readonly mixed $subject = null,
        public readonly array $page = [],
        public readonly int $span = 12,
        public readonly array $types = [],
    ) {
    }

    public function withGroup(string $group): self
    {
        return new self($this->pageType, $this->handle, $group, $this->preview, $this->subject, $this->page, $this->span, $this->types);
    }

    /** The same page, for a section drawn $span twelfths wide (kept within 1 to 12). */
    public function withSpan(int $span): self
    {
        return new self($this->pageType, $this->handle, $this->group, $this->preview, $this->subject, $this->page, max(1, min(12, $span)), $this->types);
    }

    /**
     * How many cards, tiles or posts a line holds: the section's setting on
     * a full-width row, and at most a third of the span in a column, so a
     * card keeps room for its photo and text (never fewer than one).
     */
    public function fitColumns(mixed $wanted, int $default = 4): int
    {
        $wanted = (int) $wanted ?: $default;

        return $this->span >= 12 ? $wanted : max(1, min($wanted, intdiv($this->span, 3)));
    }

    /** @param  list<string>  $types  the section types the document draws */
    public function withTypes(array $types): self
    {
        return new self($this->pageType, $this->handle, $this->group, $this->preview, $this->subject, $this->page, $this->span, array_values(array_unique(array_filter($types, 'is_string'))));
    }

    /** Whether the document draws a section of this type (a hidden one does not count). */
    public function has(string $type): bool
    {
        return in_array($type, $this->types, true);
    }

    /** One value core handed the page, or the fallback. */
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->page) ? $this->page[$key] : $default;
    }
}
