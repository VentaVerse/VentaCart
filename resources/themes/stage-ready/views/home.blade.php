<x-store-layout>
    @foreach ($sectionData as $data)
        @php $section = $data['section']; @endphp

        @if ($section->type === 'banner')
            <section>
                @include('sections.banner', ['section' => $section])
            </section>


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

    {{-- Recently Viewed --}}
    <div x-data="recentlyViewed()" x-show="products.length > 0" x-cloak class="container-store py-10">
        <h2 class="section-title reveal">RECENTLY VIEWED</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-5">
            <template x-for="item in products" :key="item.id">
                <div class="product-card">
                    <div class="product-image-wrap">
                        {{-- Badges --}}
                        <template x-for="(badge, bi) in (item.badges || []).filter(b => b.type !== 'free_shipping')" :key="bi">
                            <span class="badge"
                                  :class="badge.type === 'new' ? 'badge-new' : (badge.type === 'hot' ? 'badge-hot' : 'badge-sale')"
                                  :style="badge.type !== 'sale' ? 'top: ' + (12 + bi * 28) + 'px' : ''"
                                  x-text="badge.label"></span>
                        </template>
                        <a :href="item.url">
                            <template x-if="item.image">
                                <img :src="item.image" :alt="item.name" loading="lazy">
                            </template>
                            <template x-if="!item.image">
                                <div class="w-full h-full flex items-center justify-center text-[#333]">
                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            </template>
                        </a>
                    </div>
                    <div class="product-info">
                        <div class="product-name">
                            <a :href="item.url" x-text="item.name"></a>
                        </div>
                        <template x-if="item.reviews_count > 0">
                            <div class="product-rating">
                                <template x-for="s in 5">
                                    <svg class="star-icon" :class="s <= Math.round(item.reviews_avg) ? 'star-full' : 'star-empty'" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.37 2.448a1 1 0 00-.364 1.118l1.287 3.957c.3.921-.755 1.688-1.54 1.118l-3.37-2.448a1 1 0 00-1.176 0l-3.37 2.448c-.784.57-1.838-.197-1.539-1.118l1.287-3.957a1 1 0 00-.364-1.118L2.065 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.957z"/></svg>
                                </template>
                                <span class="review-count" x-text="'(' + item.reviews_count + ')'"></span>
                            </div>
                        </template>
                        <div class="product-price">
                            <template x-if="item.formatted_sale_price">
                                <span>
                                    <span class="product-price-old" x-text="item.formatted_price"></span>
                                    <span class="product-price-sale" x-text="item.formatted_sale_price"></span>
                                </span>
                            </template>
                            <template x-if="!item.formatted_sale_price">
                                <span x-text="item.formatted_price"></span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

</x-store-layout>
