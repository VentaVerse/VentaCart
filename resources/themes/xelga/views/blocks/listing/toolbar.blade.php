{{--
    The row above a listing: the filter button (phones, or every screen
    when filters open in a drawer; a link when a Filters section holds
    them), the number of products, the sort and the grid or list switch. Sorting is a real form, so it works before
    the script loads; the script only sends it as soon as the choice changes.
--}}
<div class="toolbar">
    @if ($withFilters && ($filtersAt ?? null) !== null)
        {{-- The filters are in their own section on this page: the button leads there and opens them. --}}
        <a class="btn btn--secondary btn--sm toolbar__filter" href="#{{ $filtersAt }}" data-filter-jump>
            <svg class="icon" aria-hidden="true"><use href="#i-filter"></use></svg>Filter<span class="toolbar__badge" data-filter-badge @if ($data['filters']['active'] === 0) hidden @endif>{{ $data['filters']['active'] }}</span>
        </a>
    @elseif ($withFilters)
        <button type="button" class="btn btn--secondary btn--sm toolbar__filter" data-filter-open aria-haspopup="dialog" aria-controls="{{ $section['id'] }}-filter-drawer">
            <svg class="icon" aria-hidden="true"><use href="#i-filter"></use></svg>Filter<span class="toolbar__badge" data-filter-badge @if ($data['filters']['active'] === 0) hidden @endif>{{ $data['filters']['active'] }}</span>
        </button>
    @endif
    <p class="toolbar__count" data-result-count aria-live="polite" aria-atomic="true">{{ $data['list']['count_text'] }}</p>
    @if ($data['list']['total'] > 1)
        <form class="toolbar__sort" method="get" action="{{ request()->url() }}" data-sort-form>
            @foreach (\Themes\Xelga\Sections\Listing::hiddenFields(['sort']) as $field)
                <input type="hidden" name="{{ $field['name'] }}" value="{{ $field['value'] }}">
            @endforeach
            <label><span class="visually-hidden">Sort by</span>
                <select class="select select--sm" name="sort" data-sort>
                    @foreach ($data['sorts'] as $option)
                        <option value="{{ $option['value'] }}" @selected($option['selected'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="btn btn--secondary btn--sm toolbar__sort-go" data-sort-go>Sort</button>
        </form>
    @endif
    @if ($settings['switch_view'] && $data['list']['total'] > 0)
        <div class="view-toggle" role="group" aria-label="Show products as" data-view-toggle>
            <button type="button" class="icon-btn" data-view="grid" aria-pressed="{{ $settings['view'] === 'grid' ? 'true' : 'false' }}" aria-label="Grid"><svg class="icon" aria-hidden="true"><use href="#i-grid"></use></svg></button>
            <button type="button" class="icon-btn" data-view="list" aria-pressed="{{ $settings['view'] === 'list' ? 'true' : 'false' }}" aria-label="List"><svg class="icon" aria-hidden="true"><use href="#i-list"></use></svg></button>
        </div>
    @endif
</div>
