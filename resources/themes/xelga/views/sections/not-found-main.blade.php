{{-- Page not found: a message, a search of the shop and buttons back into the store. --}}
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs') aria-labelledby="{{ $section['id'] }}-title">
    <div class="s__inner nf">
        @unless (collect($blocks)->contains(fn ($b) => $b['type'] === 'heading' && ! $b['disabled']))
            <h1 class="visually-hidden" id="{{ $section['id'] }}-title">Page not found</h1>
        @endunless
        @foreach ($blocks as $block)
            @include('xelga::blocks.not-found-main.' . $block['type'])
        @endforeach
    </div>
</section>
