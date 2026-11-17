{{--
    The filter form: only the parameters core's controller reads (price,
    availability, on sale, brand, attributes, tags). It is a plain GET form
    that keeps the sort; the script sends it on each change on large screens
    and from the drawer's button on phones.
--}}
@php $filters = $data['filters']; @endphp
<form class="filters" id="{{ $section['id'] }}-filters" method="get" action="{{ request()->url() }}" data-filters>
    @foreach ($data['carried'] as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <div class="filters__head">
        <h2 class="filters__title">Filter</h2>
        @if ($filters['active'] > 0)
            <a class="filters__clear" href="{{ $filters['clear_url'] }}" data-filters-clear>Clear all</a>
        @endif
    </div>
    @if ($filters['price'])
        @php $p = $filters['price']; @endphp
        <details class="facet" open>
            <summary class="facet__summary">Price<svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg></summary>
            <fieldset class="facet__body">
                <legend class="visually-hidden">Price</legend>
                <div class="price-range">
                    <label class="field"><span class="field__label">From {{ $p['symbol'] }}</span><input class="input" type="number" inputmode="numeric" name="min_price" min="0" step="any" value="{{ $p['min'] !== null ? rtrim(rtrim(number_format($p['min'], 2, '.', ''), '0'), '.') : '' }}" placeholder="{{ $p['min_hint'] }}" data-price-min></label>
                    <label class="field"><span class="field__label">To {{ $p['symbol'] }}</span><input class="input" type="number" inputmode="numeric" name="max_price" min="0" step="any" value="{{ $p['max'] !== null ? rtrim(rtrim(number_format($p['max'], 2, '.', ''), '0'), '.') : '' }}" placeholder="{{ $p['max_hint'] }}" data-price-max></label>
                </div>
            </fieldset>
        </details>
    @endif
    @foreach ($filters['facets'] as $facet)
        <details class="facet" @if ($facet['open']) open @endif>
            <summary class="facet__summary">{{ $facet['label'] }}<svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg></summary>
            <fieldset class="facet__body">
                <legend class="visually-hidden">{{ $facet['label'] }}</legend>
                <ul class="facet__list">
                    @foreach ($facet['items'] as $item)
                        <li><label class="check"><input type="checkbox" name="{{ $item['name'] }}" value="{{ $item['value'] }}" @checked($item['checked'])>@if ($item['swatch'] !== null)<span class="swatch" data-swatch="{{ $item['swatch'] }}" aria-hidden="true"></span>@endif<span class="check__label">{{ $item['label'] }}</span>@if ($item['count'])<span class="check__count">{{ $item['count'] }}</span>@endif</label></li>
                    @endforeach
                </ul>
            </fieldset>
        </details>
    @endforeach
    <div class="filters__foot">
        <button type="submit" class="btn btn--block" data-filters-submit>Show products</button>
    </div>
</form>
