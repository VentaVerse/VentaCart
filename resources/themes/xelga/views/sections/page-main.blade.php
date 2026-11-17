{{--
    An information page: its title, a menu of other pages beside it and its
    text. The text is core's rich text, printed as core prints it.
--}}
@php $page = $data['page']; @endphp
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs') aria-labelledby="{{ $section['id'] }}-title">
    <div class="s__inner">
        @include('xelga::snippets.page-crumbs', ['crumbs' => $data['crumbs']])
        @if ($page === null)
            <h1 class="visually-hidden" id="{{ $section['id'] }}-title">Page</h1>
            @include('xelga::snippets.editor-note', ['note' => 'Open one of the store\'s pages to see this section with its text. Pages are written in VentaCart, Pages.'])
        @else
            @unless (collect($blocks)->contains(fn ($b) => $b['type'] === 'title' && ! $b['disabled']))
                <h1 class="visually-hidden" id="{{ $section['id'] }}-title">{{ $page['title'] }}</h1>
            @endunless
            <div class="info">
                @foreach ($blocks as $block)
                    @include('xelga::blocks.page-main.' . $block['type'])
                @endforeach
            </div>
        @endif
    </div>
</section>
