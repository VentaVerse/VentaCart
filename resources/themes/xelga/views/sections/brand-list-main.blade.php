{{-- All brands: every brand with products in the store, as a grid of logos with names and counts. --}}
@php $items = $data['items']; @endphp
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
         data-cols="{{ $settings['cols'] }}" data-show-logo="{{ $settings['show_logo'] ? 'true' : 'false' }}"
         data-show-count="{{ $settings['show_count'] ? 'true' : 'false' }}" data-grayscale="{{ $settings['grayscale'] ? 'true' : 'false' }}"
         aria-labelledby="{{ $section['id'] }}-title">
    <div class="s__inner">
        <header class="listing-head">
            <h1 class="listing-head__title" id="{{ $section['id'] }}-title" data-setting="heading">{{ $settings['heading'] !== '' ? $settings['heading'] : 'Brands' }}</h1>
            <p class="listing-head__text" data-setting="subheading" data-optional @if (trim($settings['subheading']) === '') hidden @endif>{{ $settings['subheading'] }}</p>
        </header>
        @if ($items === [])
            <div class="no-match">
                <h2 class="no-match__title">No brands yet</h2>
                <p class="muted">Brands appear here once they have products in the store.</p>
                <a class="btn btn--secondary" href="{{ route('home') }}">Back to the home page</a>
            </div>
        @else
            <ul class="brand-index">
                @foreach ($items as $item)
                    @php $tag = $item['url'] ? 'a' : 'span'; @endphp
                    <li>
                        <{{ $tag }} class="brand-tile" @if ($item['url']) href="{{ $item['url'] }}" @endif>
                            <span class="brand-tile__logo">
                                @if ($item['picture'])
                                    <img src="{{ $item['picture']['src'] }}" alt="" loading="lazy" decoding="async"
                                         @if ($item['picture']['width']) width="{{ $item['picture']['width'] }}" height="{{ $item['picture']['height'] }}" @endif>
                                @else
                                    <span class="brand-tile__initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($item['name'], 0, 1)) }}</span>
                                @endif
                            </span>
                            <span class="brand-tile__name">{{ $item['name'] }}</span>
                            <span class="brand-tile__count muted">{{ $item['count'] }}</span>
                        </{{ $tag }}>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
