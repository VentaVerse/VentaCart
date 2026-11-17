{{-- The blog: its template's sections, with the posts the blog extension found for this page. --}}
<x-store-layout title="Blog">
    @include('xelga::snippets.template', ['pageType' => 'blog', 'page' => compact('posts', 'categories', 'tags', 'view', 'settings', 'search')])
</x-store-layout>
