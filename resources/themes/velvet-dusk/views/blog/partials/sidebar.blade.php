{{-- Blog Categories --}}
@if ($categories->isNotEmpty())
    <div>
        <h3 class="text-sm font-semibold mb-3" class="font-heading">Categories</h3>
        <div class="space-y-1.5">
            <a href="{{ route('blog.index') }}" class="block text-sm {{ !request('category') ? 'font-medium text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">
                All Posts
            </a>
            @foreach ($categories as $cat)
                <a href="{{ route('blog.index', ['category' => $cat->slug]) }}" class="flex items-center justify-between text-sm {{ request('category') === $cat->slug ? 'font-medium text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">
                    {{ $cat->name }}
                    <span class="text-xs text-gray-400">{{ $cat->posts_count }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endif

{{-- Tags --}}
@if ($tags->isNotEmpty())
    <div>
        <h3 class="text-sm font-semibold mb-3" class="font-heading">Tags</h3>
        <div class="flex flex-wrap gap-1.5">
            @foreach ($tags as $tag)
                <a href="{{ route('blog.index', ['tag' => $tag->slug]) }}" class="text-xs border rounded-full px-2.5 py-0.5 transition-colors {{ request('tag') === $tag->slug ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]' : 'border-gray-300 text-gray-500 hover:border-gray-400' }}">
                    {{ $tag->name }}
                </a>
            @endforeach
        </div>
    </div>
@endif
