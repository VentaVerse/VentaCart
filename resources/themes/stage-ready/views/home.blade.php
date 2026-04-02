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

        @elseif ($section->type === 'recently_viewed')
            @include('sections.recently_viewed', ['section' => $section])
        @endif
    @endforeach

</x-store-layout>
