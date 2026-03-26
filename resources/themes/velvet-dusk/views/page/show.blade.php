<x-store-layout :title="$page->meta_title ?: $page->title" :meta-description="$page->meta_description">
    <div class="container-store py-3">
        <nav class="flex items-center gap-2 text-xs text-[#9A8575]">
            <a href="{{ route('home') }}" class="hover:text-[var(--color-primary)]">Home</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-[#E8D5C4]">{{ $page->title }}</span>
        </nav>
    </div>

    <div class="container-store py-6">
        <article class="max-w-3xl mx-auto">
            <h1 class="text-3xl font-bold text-[#F7CCAC] mb-6">{{ $page->title }}</h1>

            <div class="prose prose-invert max-w-none text-[#B0B0B0]">
                {!! $page->body !!}
            </div>
        </article>
    </div>
</x-store-layout>
