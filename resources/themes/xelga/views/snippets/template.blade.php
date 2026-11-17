{{-- The page's own sections, drawn from its template with the data core's controller gave the page. --}}
{{ app(\Themes\Xelga\Rendering\Storefront::class)->template($pageType, $subject ?? null, $page ?? []) }}
