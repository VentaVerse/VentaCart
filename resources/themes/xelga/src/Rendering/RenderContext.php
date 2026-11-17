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
     * @param  bool  $inSide  whether the section is drawn in a page side
     * @param  array<string, string>  $ids  the id of the first drawn section of each type the document draws
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
        public readonly bool $inSide = false,
        public readonly array $ids = [],
    ) {
    }

    public function withGroup(string $group): self
    {
        return new self($this->pageType, $this->handle, $group, $this->preview, $this->subject, $this->page, $this->span, $this->types, $this->inSide, $this->ids);
    }

    /** The same page, for a section drawn $span twelfths wide (kept within 1 to 12). */
    public function withSpan(int $span): self
    {
        return new self($this->pageType, $this->handle, $this->group, $this->preview, $this->subject, $this->page, max(1, min(12, $span)), $this->types, $this->inSide, $this->ids);
    }

    /**
     * The same page, for a section in a page side: three twelfths wide from
     * 990 pixels, where sides stand beside the content, and the whole page
     * wide below, where they stack above and below it.
     */
    public function inSide(): self
    {
        return new self($this->pageType, $this->handle, $this->group, $this->preview, $this->subject, $this->page, \Themes\Xelga\Documents\Layout::SIDE_SPAN, $this->types, true, $this->ids);
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

    /**
     * @param  list<string>  $types  the section types the document draws
     * @param  array<string, string>  $ids  type => the id of the first drawn section of that type
     */
    public function withTypes(array $types, array $ids = []): self
    {
        $types = array_values(array_unique(array_filter($types, 'is_string')));

        return new self($this->pageType, $this->handle, $this->group, $this->preview, $this->subject, $this->page, $this->span, $types, $this->inSide, array_intersect_key(array_filter($ids, 'is_string'), array_flip($types)));
    }

    /** Whether the document draws a section of this type (a hidden one does not count). */
    public function has(string $type): bool
    {
        return in_array($type, $this->types, true);
    }

    /**
     * The id of the first section of this type the document draws, or null
     * when it draws none, so a section can point the shopper to another.
     */
    public function idOf(string $type): ?string
    {
        return $this->ids[$type] ?? null;
    }

    /** One value core handed the page, or the fallback. */
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->page) ? $this->page[$key] : $default;
    }
}
