<x-store-layout :title="$page->meta_title ?: $page->title" :meta-description="$page->meta_description">
    <x-storefront.columns>
    <x-storefront.region name="content_top" />
    <div class="container-store py-3">
        <nav class="flex items-center gap-2 text-xs" style="color: var(--color-text-muted);">
            <a href="{{ route('home') }}" class="hover:text-[var(--color-primary)]">Home</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span style="color: var(--color-text);">{{ $page->title }}</span>
        </nav>
    </div>

    <div class="container-store py-6">
        <article class="max-w-3xl mx-auto">
            <h1 class="text-3xl font-bold mb-6" style="color: var(--color-text-heading);">{{ $page->title }}</h1>

            <div class="prose max-w-none" style="color: var(--color-content-text, var(--color-text));">
                {!! $page->body !!}
            </div>
        </article>
    </div>
    <x-storefront.region name="content_bottom" />
    </x-storefront.columns>
</x-store-layout>
