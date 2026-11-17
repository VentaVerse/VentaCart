{{-- Page not found: the not-found template inside the store frame. Never indexed. --}}
<x-store-layout title="Page not found">
    @php view()->share('robotsMeta', 'noindex, follow'); @endphp
    @include('xelga::snippets.template', ['pageType' => 'not-found', 'page' => []])
</x-store-layout>
