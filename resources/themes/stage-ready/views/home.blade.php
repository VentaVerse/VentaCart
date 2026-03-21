<x-store-layout>
    @foreach ($sectionData as $data)
        @php $section = $data['section']; @endphp

        @if ($section->type === 'banner')
            <section>
                @include('sections.banner', ['section' => $section])
            </section>
            @include('partials.trust-bar')

        @elseif ($section->type === 'products')
            @if (($data['products'] ?? collect())->isNotEmpty())
            <section class="py-10 md:py-14">
                <div class="container-store">
                    <h2 class="section-title reveal">{{ $section->title }}</h2>
                    @include('sections.products', [
                        'section' => $section,
                        'products' => $data['products'],
                    ])
                </div>
            </section>
            @endif

        @elseif ($section->type === 'brands')
            <section class="py-10 md:py-14">
                <div class="container-store">
                    <h2 class="section-title reveal">{{ $section->title }}</h2>
                    <div class="reveal">
                        @include('sections.brands', [
                            'section' => $section,
                            'brands' => $data['brands'] ?? collect(),
                        ])
                    </div>
                </div>
            </section>

            {{-- Recently Viewed (after brands) --}}
            <div x-data="recentlyViewed()" x-show="products.length > 0" x-cloak class="container-store py-10">
                <h2 class="section-title reveal">RECENTLY VIEWED</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <template x-for="item in products" :key="item.id">
                        <a :href="item.url" class="product-card">
                            <div class="product-image-wrap">
                                <template x-if="item.image">
                                    <img :src="item.image" :alt="item.name" loading="lazy" class="w-full h-full object-contain p-3">
                                </template>
                                <template x-if="!item.image">
                                    <div class="w-full h-full flex items-center justify-center text-[#333]">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                </template>
                            </div>
                            <div class="product-info">
                                <h3 class="product-name" x-text="item.name"></h3>
                                <div class="product-price">
                                    <template x-if="item.sale_price">
                                        <span>
                                            <span class="product-price-old" x-text="'₱' + item.price.toFixed(2)"></span>
                                            <span class="product-price-sale" x-text="'₱' + item.sale_price.toFixed(2)"></span>
                                        </span>
                                    </template>
                                    <template x-if="!item.sale_price">
                                        <span x-text="'₱' + item.price.toFixed(2)"></span>
                                    </template>
                                </div>
                            </div>
                        </a>
                    </template>
                </div>
            </div>

        @elseif ($section->type === 'category_tabs')
            <section class="py-10 md:py-14">
                <div class="container-store">
                    <h2 class="section-title reveal">{{ $section->title }}</h2>
                    @include('sections.category_tabs', ['section' => $section])
                </div>
            </section>

        @elseif ($section->type === 'text_block')
            <section class="py-10 md:py-14">
                <div class="container-store reveal">
                    @include('sections.text_block', ['section' => $section])
                </div>
            </section>

        @elseif ($section->type === 'blog_posts')
            <section class="py-10 md:py-14">
                <div class="container-store">
                    <h2 class="section-title reveal">{{ $section->title }}</h2>
                    @include('sections.blog_posts', ['section' => $section])
                </div>
            </section>
        @endif
    @endforeach

</x-store-layout>
