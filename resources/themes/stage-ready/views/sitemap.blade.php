<x-store-layout title="Sitemap">
    <div class="container-store py-10">
        <h1 class="text-3xl font-bold text-[#E8E8E8] mb-8">Sitemap</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {{-- Pages --}}
            <div>
                <h2 class="text-sm font-semibold text-[var(--color-primary)] uppercase tracking-wider mb-3">Pages</h2>
                <ul class="space-y-1.5">
                    <li><a href="{{ route('home') }}" class="text-sm text-[#999] hover:text-[var(--color-primary)] transition-colors">Home</a></li>
                    <li><a href="{{ route('contact') }}" class="text-sm text-[#999] hover:text-[var(--color-primary)] transition-colors">Contact Us</a></li>
                    @foreach ($pages as $page)
                        <li><a href="/{{ $page->slug }}" class="text-sm text-[#999] hover:text-[var(--color-primary)] transition-colors">{{ $page->title }}</a></li>
                    @endforeach
                </ul>
            </div>

            {{-- Categories --}}
            <div>
                <h2 class="text-sm font-semibold text-[var(--color-primary)] uppercase tracking-wider mb-3">Categories</h2>
                <ul class="space-y-1.5">
                    @foreach ($categories as $category)
                        <li>
                            <a href="{{ route('category.show', $category->slug) }}" class="text-sm text-[#999] hover:text-[var(--color-primary)] transition-colors">{{ $category->name }}</a>
                            @if ($category->children->isNotEmpty())
                                <ul class="ml-4 mt-1 space-y-1">
                                    @foreach ($category->children as $child)
                                        <li><a href="{{ route('category.show', $child->slug) }}" class="text-xs text-[#666] hover:text-[var(--color-primary)] transition-colors">{{ $child->name }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Brands --}}
            <div>
                <h2 class="text-sm font-semibold text-[var(--color-primary)] uppercase tracking-wider mb-3">Brands</h2>
                <ul class="space-y-1.5">
                    @foreach ($brands as $brand)
                        <li><a href="{{ route('brand.show', $brand->slug) }}" class="text-sm text-[#999] hover:text-[var(--color-primary)] transition-colors">{{ $brand->name }}</a></li>
                    @endforeach
                </ul>
            </div>

            {{-- Blog --}}
            @if ($blogPosts->isNotEmpty())
                <div>
                    <h2 class="text-sm font-semibold text-[var(--color-primary)] uppercase tracking-wider mb-3">Blog</h2>
                    <ul class="space-y-1.5">
                        <li><a href="{{ route('blog.index') }}" class="text-sm text-[#999] hover:text-[var(--color-primary)] transition-colors">All Posts</a></li>
                        @foreach ($blogPosts as $post)
                            <li><a href="{{ route('blog.show', $post->slug) }}" class="text-sm text-[#999] hover:text-[var(--color-primary)] transition-colors">{{ $post->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Account --}}
            <div>
                <h2 class="text-sm font-semibold text-[var(--color-primary)] uppercase tracking-wider mb-3">Account</h2>
                <ul class="space-y-1.5">
                    <li><a href="{{ route('customer.login') }}" class="text-sm text-[#999] hover:text-[var(--color-primary)] transition-colors">Sign In</a></li>
                    <li><a href="{{ route('customer.register') }}" class="text-sm text-[#999] hover:text-[var(--color-primary)] transition-colors">Create Account</a></li>
                </ul>
            </div>
        </div>

        <p class="text-xs text-[#555] mt-8">{{ $productCount }} products across {{ $categories->count() }} categories and {{ $brands->count() }} brands.</p>
    </div>
</x-store-layout>
