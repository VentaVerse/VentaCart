<x-store-layout title="Sitemap">
    <div class="container-store py-10">
        <h1 class="text-3xl font-bold text-[#DDDDDD] mb-8">Sitemap</h1>

        {{-- Pages & Account --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-10">
            <div>
                <h2 class="text-sm font-semibold text-[var(--color-primary)] uppercase tracking-wider mb-3">Pages</h2>
                <ul class="space-y-1.5">
                    <li><a href="{{ route('home') }}" class="text-sm text-[#8899AA] hover:text-[var(--color-primary)]">Home</a></li>
                    <li><a href="{{ route('contact') }}" class="text-sm text-[#8899AA] hover:text-[var(--color-primary)]">Contact Us</a></li>
                    <li><a href="{{ route('sitemap') }}" class="text-sm text-[#8899AA] hover:text-[var(--color-primary)]">Sitemap</a></li>
                    @foreach ($pages as $page)
                        <li><a href="/{{ $page->slug }}" class="text-sm text-[#8899AA] hover:text-[var(--color-primary)]">{{ $page->title }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-[var(--color-primary)] uppercase tracking-wider mb-3">Account</h2>
                <ul class="space-y-1.5">
                    <li><a href="{{ route('customer.login') }}" class="text-sm text-[#8899AA] hover:text-[var(--color-primary)]">Sign In</a></li>
                    <li><a href="{{ route('customer.register') }}" class="text-sm text-[#8899AA] hover:text-[var(--color-primary)]">Create Account</a></li>
                </ul>
            </div>
            @if ($blogPosts->isNotEmpty())
                <div>
                    <h2 class="text-sm font-semibold text-[var(--color-primary)] uppercase tracking-wider mb-3">Blog</h2>
                    <ul class="space-y-1.5">
                        <li><a href="{{ route('blog.index') }}" class="text-sm text-[#8899AA] hover:text-[var(--color-primary)]">All Posts</a></li>
                        @foreach ($blogPosts as $post)
                            <li><a href="{{ route('blog.show', $post->slug) }}" class="text-sm text-[#8899AA] hover:text-[var(--color-primary)]">{{ $post->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Categories --}}
        <div class="mb-10">
            <h2 class="text-sm font-semibold text-[var(--color-primary)] uppercase tracking-wider mb-3">Categories ({{ $categories->count() }})</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-8 gap-y-1">
                @foreach ($categories as $category)
                    <div class="mb-2">
                        <a href="{{ route('category.show', $category->slug) }}" class="text-sm text-[#8899AA] hover:text-[var(--color-primary)]">{{ $category->name }}</a>
                        @if ($category->children->isNotEmpty())
                            @foreach ($category->children as $child)
                                <div class="ml-3"><a href="{{ route('category.show', $child->slug) }}" class="text-xs text-[#666] hover:text-[var(--color-primary)]">{{ $child->name }}</a></div>
                            @endforeach
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Brands --}}
        <div class="mb-10">
            <h2 class="text-sm font-semibold text-[var(--color-primary)] uppercase tracking-wider mb-3">Brands ({{ $brands->count() }})</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-x-6 gap-y-1">
                @foreach ($brands as $brand)
                    <a href="{{ route('brand.show', $brand->slug) }}" class="text-sm text-[#8899AA] hover:text-[var(--color-primary)] truncate">{{ $brand->name }}</a>
                @endforeach
            </div>
        </div>

        <p class="text-xs text-[#4F6070]">{{ $productCount }} products across {{ $categories->count() }} categories and {{ $brands->count() }} brands.</p>
    </div>
</x-store-layout>
