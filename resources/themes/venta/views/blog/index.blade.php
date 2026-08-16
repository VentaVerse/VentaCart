<x-store-layout title="Blog">

    {{-- Breadcrumbs --}}
    <div class="page-breadcrumbs">
        <div class="container-store py-3">
            <nav>
                <a href="{{ route('home') }}">Home</a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span>Blog</span>
            </nav>
        </div>
    </div>

    <div class="container-store py-6">
        <div class="flex flex-col lg:flex-row gap-8">

            {{-- Main Content --}}
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-semibold mb-6" class="font-heading">Blog</h1>

                @if ($posts->isNotEmpty())
                    <div class="{{ $view === 'list' ? 'space-y-6' : 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6' }}">
                        @foreach ($posts as $post)
                            @if ($view === 'list')
                                {{-- List View --}}
                                <article class="flex gap-4 border-b border-gray-100 pb-6">
                                    @if ($post->featured_image)
                                        <a href="{{ route('blog.show', $post->slug) }}" class="flex-shrink-0 w-40 h-28 overflow-hidden rounded bg-[var(--color-search-bg)]">
                                            <img src="{{ storage_url($post->featured_image) }}" alt="{{ $post->title }}" class="w-full h-full object-cover">
                                        </a>
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <h2 class="text-base font-semibold mb-1" class="font-heading">
                                            <a href="{{ route('blog.show', $post->slug) }}" class="hover:text-[var(--color-primary)] transition-colors">
                                                {{ $post->title }}
                                            </a>
                                        </h2>
                                        <div class="text-xs text-[var(--color-text-muted)] mb-2">
                                            {{ $post->published_at->format('M d, Y') }}
                                            @if ($post->category)
                                                &middot; {{ $post->category->name }}
                                            @endif
                                            @if ($settings['show_view_count'] ?? true)
                                                &middot; {{ number_format($post->view_count) }} views
                                            @endif
                                        </div>
                                        @if ($post->excerpt)
                                            <p class="text-sm text-[var(--color-text)] line-clamp-2">{{ $post->excerpt }}</p>
                                        @endif
                                    </div>
                                </article>
                            @else
                                {{-- Grid View --}}
                                <article class="border border-[var(--color-border)] rounded overflow-hidden hover:shadow-md transition-shadow">
                                    @if ($post->featured_image)
                                        <a href="{{ route('blog.show', $post->slug) }}" class="block aspect-video overflow-hidden bg-[var(--color-search-bg)]">
                                            <img src="{{ storage_url($post->featured_image) }}" alt="{{ $post->title }}" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                                        </a>
                                    @else
                                        <div class="aspect-video bg-[var(--color-search-bg)] flex items-center justify-center text-[var(--color-input-border)]">
                                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                                        </div>
                                    @endif
                                    <div class="p-4">
                                        <div class="text-xs text-[var(--color-text-muted)] mb-2">
                                            {{ $post->published_at->format('M d, Y') }}
                                            @if ($post->category)
                                                &middot; {{ $post->category->name }}
                                            @endif
                                        </div>
                                        <h2 class="text-sm font-semibold mb-2 line-clamp-2" class="font-heading">
                                            <a href="{{ route('blog.show', $post->slug) }}" class="hover:text-[var(--color-primary)] transition-colors">
                                                {{ $post->title }}
                                            </a>
                                        </h2>
                                        @if ($post->excerpt)
                                            <p class="text-xs text-[var(--color-text)] line-clamp-3">{{ $post->excerpt }}</p>
                                        @endif
                                    </div>
                                </article>
                            @endif
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $posts->links() }}
                    </div>
                @else
                    <div class="text-center py-12 text-[var(--color-text-muted)]">
                        <p>No blog posts yet.</p>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <aside class="lg:w-60 flex-shrink-0 space-y-6">
                @include('blog.partials.sidebar', compact('categories', 'tags'))
            </aside>
        </div>
    </div>
</x-store-layout>
