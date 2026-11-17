{{-- An information page: its template's sections (a special layout when the page has one). --}}
<x-store-layout :title="$page->meta_title ?: $page->title" :meta-description="$page->meta_description">
    @include('xelga::snippets.template', ['pageType' => 'page', 'subject' => $page, 'page' => compact('page')])
</x-store-layout>
