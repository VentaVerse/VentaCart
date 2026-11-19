{{--
    The row above a listing: the filter button (phones, or every screen
    when filters open in a drawer; below 990px it opens a Filters section
    when one holds them), the number of products, the sort and the grid or list switch. Sorting is a real form, so it works before
    the script loads; the script only sends it as soon as the choice changes.
    A page with no filter column (search) passes $data['on_sale']: the
    "On sale" switch then sits in the sort form, so each keeps the other.
--}}
@php
    $onSale = $data['on_sale'] ?? null;
@endphp
<div class="toolbar">
    @if ($withFilters && ($filtersAt ?? null) !== null)
        {{-- The filters are in their own section on this page: below 990px the button opens it as a drawer. --}}
        <button type="button" class="btn btn--secondary btn--sm toolbar__filter" data-filter-open aria-controls="{{ $filtersAt }}" aria-expanded="false">
            <svg class="icon" aria-hidden="true"><use href="#i-filter"></use></svg>Filter<span class="toolbar__badge" data-filter-badge @if ($data['filters']['active'] === 0) hidden @endif>{{ $data['filters']['active'] }}</span>
        </button>
    @elseif ($withFilters)
        <button type="button" class="btn btn--secondary btn--sm toolbar__filter" data-filter-open aria-haspopup="dialog" aria-controls="{{ $section['id'] }}-filter-drawer">
            <svg class="icon" aria-hidden="true"><use href="#i-filter"></use></svg>Filter<span class="toolbar__badge" data-filter-badge @if ($data['filters']['active'] === 0) hidden @endif>{{ $data['filters']['active'] }}</span>
        </button>
    @endif
    <p class="toolbar__count" data-result-count aria-live="polite" aria-atomic="true">{{ $data['list']['count_text'] }}</p>
    @if ($data['list']['total'] > 1 || $onSale !== null)
        <form class="toolbar__sort" method="get" action="{{ request()->url() }}" data-sort-form>
            @foreach (\Themes\Xelga\Sections\Listing::hiddenFields($onSale !== null ? ['sort', 'on_sale'] : ['sort']) as $field)
                <input type="hidden" name="{{ $field['name'] }}" value="{{ $field['value'] }}">
            @endforeach
            <label><span class="visually-hidden">Sort by</span>
                <select class="select select--sm" name="sort" data-sort>
                    @foreach ($data['sorts'] as $option)
                        <option value="{{ $option['value'] }}" @selected($option['selected'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>
            @if ($onSale !== null)
                <label class="check toolbar__on-sale"><input type="checkbox" name="on_sale" value="1" data-sort @checked($onSale['on'])><span class="check__label">On sale</span></label>
            @endif
            <button type="submit" class="btn btn--secondary btn--sm toolbar__sort-go" data-sort-go>{{ $onSale !== null ? 'Apply' : 'Sort' }}</button>
        </form>
    @endif
    @if ($settings['switch_view'] && $data['list']['total'] > 0)
        <div class="view-toggle" role="group" aria-label="Show products as" data-view-toggle>
            <button type="button" class="icon-btn" data-view="grid" aria-pressed="{{ $settings['view'] === 'grid' ? 'true' : 'false' }}" aria-label="Grid"><svg class="icon" aria-hidden="true"><use href="#i-grid"></use></svg></button>
            <button type="button" class="icon-btn" data-view="list" aria-pressed="{{ $settings['view'] === 'list' ? 'true' : 'false' }}" aria-label="List"><svg class="icon" aria-hidden="true"><use href="#i-list"></use></svg></button>
        </div>
    @endif
</div>
