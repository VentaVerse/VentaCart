{{--
    Search results: the words searched, the products core's search found,
    and, when nothing matches, suggested searches and links. Core's search
    takes words and a sort only, so there are no filters here.
--}}
@php
    $list = $data['list'];
    $query = $data['query'];
    $found = $list['total'] > 0;
    // Cards a line from 990px: the setting, or fewer where sides narrow the content area (the styles cap it too).
    $fitCols = $context->fitColumns($settings['cols'], 3);
@endphp
<section class="s {{ $section['class'] }} s-search {{ $settings['scheme'] }}" id="{{ $section['id'] }}" @include('xelga::snippets.section-attrs')
         data-variant="{{ $settings['view'] }}" data-default-view="{{ $settings['view'] }}" data-switch-view="{{ $settings['switch_view'] ? 'true' : 'false' }}"
         data-cols="{{ $settings['cols'] }}" data-mobile-cols="{{ $settings['mobile_cols'] }}" data-filters="none"
         data-state="{{ $found ? 'results' : 'empty' }}" data-show-search="{{ $settings['show_search'] ? 'true' : 'false' }}"
         data-pagination="{{ $settings['pagination'] }}" aria-labelledby="{{ $section['id'] }}-title">
    <div class="s__inner">
        <header class="listing-head search-head">
            <h1 class="listing-head__title" id="{{ $section['id'] }}-title">
                @if ($query === '')
                    Search
                @else
                    Results for <q>{{ $query }}</q>
                @endif
            </h1>
            @if ($settings['show_search'] || $context->preview)
                <search class="search-head__form">
                    <form class="search-field search-field--lg" action="{{ route('search.results') }}" method="get" role="search">
                        <label class="visually-hidden" for="{{ $section['id'] }}-q">Search products</label>
                        <input class="search-field__input" id="{{ $section['id'] }}-q" type="search" name="q" value="{{ $query }}" maxlength="120" autocomplete="off">
                        <button class="search-field__submit" type="submit" aria-label="Search"><svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg></button>
                    </form>
                </search>
            @endif
        </header>
        @if ($found)
            <div class="listing listing--nofilters" data-listing>
                <div class="listing__main">
                    @include('xelga::blocks.listing.toolbar', ['withFilters' => false])
                    @include('xelga::blocks.listing.grid', ['label' => 'Search results', 'cols' => $fitCols])
                    @include('xelga::blocks.listing.pagination')
                </div>
            </div>
        @endif
        @if (! $found || $context->preview)
            <div class="no-results" @if ($found) hidden @endif>
                <div class="no-results__text">
                    <h2 class="no-results__title">
                        @if ($query === '')
                            Type a word to search the store
                        @else
                            Nothing found for <q>{{ $query }}</q>
                        @endif
                    </h2>
                    <p class="muted">Check the spelling, try a shorter word, or start from one of these.</p>
                </div>
                @if ($data['suggestions'] !== [])
                    <ul class="chips" aria-label="Suggested searches">
                        @foreach ($data['suggestions'] as $suggestion)
                            <li><a class="chip" href="{{ $suggestion['url'] }}">{{ $suggestion['label'] }}</a></li>
                        @endforeach
                    </ul>
                @elseif ($context->preview)
                    @include('xelga::snippets.editor-note', ['note' => 'Add suggested searches in this section\'s settings, one per line.'])
                @endif
                @if ($data['links'] !== [])
                    <ul class="no-results__links">
                        @foreach ($data['links'] as $link)
                            <li data-block-id="{{ $link['block']['id'] }}" data-block-type="link" @if ($link['block']['disabled']) hidden @endif>
                                <a class="link-arrow" href="{{ $link['url'] ?? '#' }}"><span data-setting="label">{{ $link['label'] }}</span><svg class="icon icon--sm" aria-hidden="true"><use href="#i-arrow-right"></use></svg></a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>
</section>
