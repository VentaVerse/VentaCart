<x-store-layout :title="$page->meta_title ?: $page->title" :meta-description="$page->meta_description">
    <div class="container-store py-3">
        <nav class="flex items-center gap-2 text-xs text-[#8AABC8]">
            <a href="{{ route('home') }}" class="hover:text-[var(--color-primary)]">Home</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-[#E8E2DB]">{{ $page->title }}</span>
        </nav>
    </div>

    <div class="container-store py-6">
        <article class="max-w-3xl mx-auto">
            <h1 class="text-3xl font-bold text-[#F0EDE8] mb-6">{{ $page->title }}</h1>

            <div class="prose prose-invert max-w-none text-[#C0D0E0]">
                {!! $page->body !!}
            </div>
        </article>
    </div>
</x-store-layout>
