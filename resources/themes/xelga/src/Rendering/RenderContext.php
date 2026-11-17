<?php

namespace Themes\Xelga\Rendering;

/**
 * What a section knows about the page it is drawn on: the page type, the
 * template drawn, the subject (the product, category, brand, page or post)
 * and the data core's controller handed the page view, so a page's main
 * section shows exactly what core worked out for this shopper.
 */
final class RenderContext
{
    /**
     * @param  'header'|'template'|'footer'  $group
     * @param  array<string, mixed>  $page  the view data core's controller passed to the page
     */
    public function __construct(
        public readonly string $pageType,
        public readonly string $handle,
        public readonly string $group,
        public readonly bool $preview,
        public readonly mixed $subject = null,
        public readonly array $page = [],
    ) {
    }

    public function withGroup(string $group): self
    {
        return new self($this->pageType, $this->handle, $group, $this->preview, $this->subject, $this->page);
    }

    /** One value core handed the page, or the fallback. */
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->page) ? $this->page[$key] : $default;
    }
}
