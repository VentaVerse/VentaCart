<x-store-layout :title="$post->meta_title ?: $post->title" :meta-description="$post->meta_description">

    {{-- Breadcrumbs --}}
    <div class="bg-gray-50 border-b border-gray-200">
        <div class="container-store py-3">
            <nav class="flex items-center gap-2 text-xs text-gray-500">
                <a href="{{ route('home') }}" class="hover:text-gray-700">Home</a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="{{ route('blog.index') }}" class="hover:text-gray-700">Blog</a>
                @if ($post->category)
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <a href="{{ route('blog.index', ['category' => $post->category->slug]) }}" class="hover:text-gray-700">{{ $post->category->name }}</a>
                @endif
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-gray-800 font-medium truncate">{{ $post->title }}</span>
            </nav>
        </div>
    </div>

    <div class="container-store py-6">
        <article class="max-w-3xl mx-auto">
            {{-- Header --}}
            <header class="mb-6">
                <h1 class="text-3xl font-bold mb-3" class="font-heading">{{ $post->title }}</h1>
                <div class="flex items-center gap-3 text-sm text-gray-500">
                    <time>{{ $post->published_at->format('F d, Y') }}</time>
                    @if ($post->category)
                        <span>&middot;</span>
                        <a href="{{ route('blog.index', ['category' => $post->category->slug]) }}" class="hover:text-[var(--color-primary)]">
                            {{ $post->category->name }}
                        </a>
                    @endif
                    @if ($settings['show_view_count'] ?? true)
                        <span>&middot;</span>
                        <span>{{ number_format($post->view_count) }} views</span>
                    @endif
                </div>
            </header>

            {{-- Featured Image --}}
            @if ($post->featured_image)
                <div class="mb-6 rounded overflow-hidden">
                    <img src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->title }}" class="w-full h-auto">
                </div>
            @endif

            {{-- Content --}}
            <div class="prose max-w-none text-gray-700">
                {!! $post->content !!}
            </div>

            {{-- Tags --}}
            @if ($post->tags->isNotEmpty())
                <div class="mt-8 pt-4 border-t border-gray-200">
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($post->tags as $tag)
                            <a href="{{ route('blog.index', ['tag' => $tag->slug]) }}" class="text-xs border border-gray-300 rounded-full px-2.5 py-0.5 text-gray-500 hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] transition-colors">
                                {{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </article>

        {{-- Related Posts --}}
        @if ($relatedPosts->isNotEmpty())
            <div class="max-w-3xl mx-auto mt-10 pt-8 border-t border-gray-200">
                <h2 class="section-title">Related Articles</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach ($relatedPosts as $related)
                        <article class="flex gap-3">
                            @if ($related->featured_image)
                                <a href="{{ route('blog.show', $related->slug) }}" class="flex-shrink-0 w-24 h-24 overflow-hidden rounded bg-gray-100">
                                    <img src="{{ asset('storage/' . $related->featured_image) }}" alt="{{ $related->title }}" class="w-full h-full object-cover">
                                </a>
                            @endif
                            <div>
                                <h3 class="text-sm font-semibold mb-1" class="font-heading">
                                    <a href="{{ route('blog.show', $related->slug) }}" class="hover:text-[var(--color-primary)] transition-colors">
                                        {{ $related->title }}
                                    </a>
                                </h3>
                                <time class="text-xs text-gray-400">{{ $related->published_at->format('M d, Y') }}</time>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-store-layout>
