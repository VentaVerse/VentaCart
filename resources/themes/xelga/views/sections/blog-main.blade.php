{{--
    Blog posts: the posts the blog extension found for this page (category,
    tag and search filters, page size), with its heading, category links,
    search box and pager. The blog page answers 404 while the extension is
    off, so this section only draws an editor note then.
--}}
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
         data-variant="{{ $data['variant'] }}" data-cols="{{ $context->fitColumns($settings['cols'], 3) }}"
         data-show-date="{{ $settings['show_date'] ? 'true' : 'false' }}" data-show-excerpt="{{ $settings['show_excerpt'] ? 'true' : 'false' }}"
         data-show-category="{{ $settings['show_category'] ? 'true' : 'false' }}"
         aria-labelledby="{{ $section['id'] }}-title">
    <div class="s__inner">
        @include('xelga::snippets.page-crumbs', ['crumbs' => $data['crumbs']])
        @if (! $data['enabled'])
            <h1 class="visually-hidden" id="{{ $section['id'] }}-title">Blog</h1>
            @include('xelga::snippets.editor-note', ['note' => 'The blog is switched off, so this page is not shown to shoppers. Switch it on in VentaCart, Extensions.'])
        @else
            @php
                $tools = array_values(array_filter($blocks, fn ($b) => in_array($b['type'], ['categories', 'search'], true)));
                $hasHeading = collect($blocks)->contains(fn ($b) => $b['type'] === 'heading' && ! $b['disabled']);
            @endphp
            @unless ($hasHeading)
                <h1 class="visually-hidden" id="{{ $section['id'] }}-title">Blog</h1>
            @endunless
            @foreach ($blocks as $block)
                @if ($block['type'] === 'heading')
                    @include('xelga::blocks.blog-main.heading')
                @elseif ($block['type'] === 'posts')
                    @include('xelga::blocks.blog-main.posts')
                @elseif ($block === ($tools[0] ?? null))
                    {{-- Category links and search share one row, in the order the merchant set. --}}
                    <div class="blog-tools">
                        @foreach ($tools as $tool)
                            @include('xelga::blocks.blog-main.' . $tool['type'], ['block' => $tool])
                        @endforeach
                    </div>
                @endif
            @endforeach
        @endif
    </div>
</section>
