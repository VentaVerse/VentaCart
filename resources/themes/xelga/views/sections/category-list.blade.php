{{--
    Category list: the store's active categories, each a link, the one the
    shopper is on marked. A store with no categories draws nothing.
--}}
@if ($data['items'] !== [] || $context->preview)
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')>
    <div class="s__inner">
        <nav class="category-list" aria-label="{{ $settings['heading'] !== '' ? $settings['heading'] : 'Categories' }}">
            <h2 class="category-list__title" data-setting="heading" data-optional @if ($settings['heading'] === '') hidden @endif>{{ $settings['heading'] }}</h2>
            @if ($data['items'] === [])
                <p class="muted">The store has no active categories yet. Add them in VentaCart, Catalog, Categories.</p>
            @else
                <ul class="category-list__list">
                    @foreach ($data['items'] as $item)
                        <li class="category-list__item">
                            <a class="category-list__link" href="{{ $item['url'] }}"@if ($item['current']) aria-current="page"@endif>{{ $item['name'] }}</a>
                            @if ($item['children'] !== [])
                                <ul class="category-list__sub">
                                    @foreach ($item['children'] as $child)
                                        <li><a class="category-list__link" href="{{ $child['url'] }}"@if ($child['current']) aria-current="page"@endif>{{ $child['name'] }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </nav>
    </div>
</section>
@endif
