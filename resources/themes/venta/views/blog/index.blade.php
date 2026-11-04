<x-store-layout title="Blog">
    <x-storefront.columns>
    <x-storefront.region name="content_top" />

    {{-- Breadcrumbs --}}
    @if (\App\Support\PageSetting::on('show_breadcrumbs'))
    <div class="page-breadcrumbs">
        <div class="container-store py-3">
            <nav>
                <a href="{{ route('home') }}">Home</a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span>Blog</span>
            </nav>
        </div>
    </div>
    @endif

    <div class="container-store py-6">
        <div class="flex flex-col lg:flex-row gap-8">

            {{-- Main Content --}}
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-semibold mb-4" class="font-heading">Blog</h1>

                {{-- Search: the other filters ride along so a search stays inside the chosen category or tag. --}}
                <form method="GET" action="{{ route('blog.index') }}" role="search" class="mb-6 max-w-xl">
                    <label for="blog-search" class="block text-sm font-medium mb-1.5">Search the blog</label>
                    <div class="flex gap-2">
                        @foreach (['category', 'tag', 'view'] as $keep)
                            @if (is_string(request()->query($keep)) && request()->query($keep) !== '')
                                <input type="hidden" name="{{ $keep }}" value="{{ request()->query($keep) }}">
                            @endif
                        @endforeach
                        <input type="search" id="blog-search" name="q" value="{{ old('q', $search) }}"
                            minlength="{{ \Extensions\Blog\Models\BlogPost::SEARCH_MIN }}" maxlength="{{ \Extensions\Blog\Models\BlogPost::SEARCH_MAX }}"
                            placeholder="Search posts" autocomplete="off" @error('q') aria-invalid="true" aria-describedby="blog-search-error" @enderror
                            class="flex-1 min-w-0 min-h-11 px-3 text-sm border border-[var(--color-input-border)] rounded bg-transparent">
                        <button type="submit" class="min-h-11 px-4 text-sm font-medium rounded bg-[var(--color-primary)] text-[var(--color-btn-text)]">Search</button>
                    </div>
                    @error('q')
                        <p id="blog-search-error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </form>

                @if ($search !== null)
                    <p class="text-sm text-[var(--color-text-muted)] mb-6" role="status">
                        {{ $posts->total() }} {{ \Illuminate\Support\Str::plural('post', $posts->total()) }} found for &ldquo;{{ $search }}&rdquo;.
                        <a href="{{ route('blog.index', array_filter(request()->only('category', 'tag', 'view'), 'is_string')) }}" class="underline">Clear search</a>
                    </p>
                @endif

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
                        <p>{{ $search !== null ? 'No posts match your search.' : 'No blog posts yet.' }}</p>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <aside class="lg:w-60 flex-shrink-0 space-y-6">
                @include('blog.partials.sidebar', compact('categories', 'tags'))
            </aside>
        </div>
    </div>
    <x-storefront.region name="content_bottom" />
    </x-storefront.columns>
</x-store-layout>
