@props(['kind', 'name', 'options' => [], 'value' => ''])
{{-- A layout choice picked by seeing it: radio tiles, each carrying a
     small diagram of the arrangement. Schema type "layout" routes here;
     an option with no diagram on file falls back to a text tile, so a
     third-party theme's unknown layout value still renders a control. --}}
@php
    // Diagram library, keyed "kind:option". Each entry is the inner SVG of
    // a 64x44 canvas: quiet structure in currentColor, the option's
    // distinguishing region slightly stronger.
    $d = [
        'header_layout:marketplace' => '<rect x="2" y="2" width="60" height="3" rx="1" opacity=".35"/><rect x="2" y="9" width="12" height="6" rx="1" opacity=".55"/><rect x="18" y="9" width="32" height="6" rx="1" opacity=".9"/><rect x="54" y="9" width="8" height="6" rx="1" opacity=".55"/><rect x="2" y="19" width="60" height="3" rx="1" opacity=".55"/><rect x="2" y="26" width="60" height="16" rx="1" opacity=".12"/>',
        'header_layout:centered' => '<rect x="2" y="2" width="60" height="3" rx="1" opacity=".35"/><rect x="4" y="9" width="6" height="6" rx="1" opacity=".55"/><rect x="26" y="9" width="12" height="6" rx="1" opacity=".9"/><rect x="54" y="9" width="6" height="6" rx="1" opacity=".55"/><rect x="16" y="19" width="32" height="3" rx="1" opacity=".55"/><rect x="2" y="26" width="60" height="16" rx="1" opacity=".12"/>',
        'header_layout:inline' => '<rect x="2" y="5" width="10" height="6" rx="1" opacity=".9"/><rect x="16" y="7" width="4" height="2" rx="1" opacity=".55"/><rect x="22" y="7" width="6" height="2" rx="1" opacity=".55"/><rect x="30" y="7" width="5" height="2" rx="1" opacity=".55"/><rect x="46" y="5" width="6" height="6" rx="1" opacity=".55"/><rect x="54" y="5" width="8" height="6" rx="1" opacity=".55"/><rect x="2" y="16" width="60" height="26" rx="1" opacity=".12"/>',
        'header_layout:search-under' => '<rect x="2" y="3" width="10" height="5" rx="1" opacity=".55"/><rect x="54" y="3" width="8" height="5" rx="1" opacity=".55"/><rect x="2" y="11" width="60" height="6" rx="1" opacity=".9"/><rect x="2" y="21" width="60" height="3" rx="1" opacity=".55"/><rect x="2" y="28" width="60" height="14" rx="1" opacity=".12"/>',

        'category_layout:sidebar-left' => '<rect x="2" y="2" width="14" height="40" rx="1" opacity=".9"/><rect x="20" y="2" width="12" height="12" rx="1" opacity=".3"/><rect x="35" y="2" width="12" height="12" rx="1" opacity=".3"/><rect x="50" y="2" width="12" height="12" rx="1" opacity=".3"/><rect x="20" y="17" width="12" height="12" rx="1" opacity=".3"/><rect x="35" y="17" width="12" height="12" rx="1" opacity=".3"/><rect x="50" y="17" width="12" height="12" rx="1" opacity=".3"/>',
        'category_layout:sidebar-right' => '<rect x="48" y="2" width="14" height="40" rx="1" opacity=".9"/><rect x="2" y="2" width="12" height="12" rx="1" opacity=".3"/><rect x="17" y="2" width="12" height="12" rx="1" opacity=".3"/><rect x="32" y="2" width="12" height="12" rx="1" opacity=".3"/><rect x="2" y="17" width="12" height="12" rx="1" opacity=".3"/><rect x="17" y="17" width="12" height="12" rx="1" opacity=".3"/><rect x="32" y="17" width="12" height="12" rx="1" opacity=".3"/>',
        'category_layout:offcanvas' => '<rect x="2" y="2" width="16" height="6" rx="1" opacity=".9"/><rect x="2" y="12" width="13" height="13" rx="1" opacity=".3"/><rect x="18" y="12" width="13" height="13" rx="1" opacity=".3"/><rect x="34" y="12" width="13" height="13" rx="1" opacity=".3"/><rect x="50" y="12" width="12" height="13" rx="1" opacity=".3"/><rect x="2" y="28" width="13" height="13" rx="1" opacity=".3"/><rect x="18" y="28" width="13" height="13" rx="1" opacity=".3"/><rect x="34" y="28" width="13" height="13" rx="1" opacity=".3"/><rect x="50" y="28" width="12" height="13" rx="1" opacity=".3"/>',

        'product_gallery_side:left' => '<rect x="2" y="2" width="30" height="40" rx="1" opacity=".9"/><rect x="36" y="4" width="26" height="4" rx="1" opacity=".55"/><rect x="36" y="11" width="18" height="3" rx="1" opacity=".3"/><rect x="36" y="17" width="24" height="3" rx="1" opacity=".3"/><rect x="36" y="30" width="20" height="7" rx="1" opacity=".55"/>',
        'product_gallery_side:right' => '<rect x="32" y="2" width="30" height="40" rx="1" opacity=".9"/><rect x="2" y="4" width="26" height="4" rx="1" opacity=".55"/><rect x="2" y="11" width="18" height="3" rx="1" opacity=".3"/><rect x="2" y="17" width="24" height="3" rx="1" opacity=".3"/><rect x="2" y="30" width="20" height="7" rx="1" opacity=".55"/>',

        'product_info_style:tabs' => '<rect x="2" y="2" width="16" height="6" rx="1" opacity=".9"/><rect x="20" y="2" width="16" height="6" rx="1" opacity=".35"/><rect x="2" y="10" width="60" height="1" opacity=".55"/><rect x="2" y="15" width="56" height="3" rx="1" opacity=".3"/><rect x="2" y="21" width="48" height="3" rx="1" opacity=".3"/><rect x="2" y="27" width="52" height="3" rx="1" opacity=".3"/>',
        'product_info_style:stacked' => '<rect x="2" y="2" width="20" height="4" rx="1" opacity=".9"/><rect x="2" y="9" width="56" height="3" rx="1" opacity=".3"/><rect x="2" y="14" width="48" height="3" rx="1" opacity=".3"/><rect x="2" y="24" width="20" height="4" rx="1" opacity=".9"/><rect x="2" y="31" width="52" height="3" rx="1" opacity=".3"/><rect x="2" y="36" width="44" height="3" rx="1" opacity=".3"/>',

        'footer_layout:columns' => '<rect x="2" y="4" width="16" height="4" rx="1" opacity=".9"/><rect x="2" y="11" width="12" height="2" rx="1" opacity=".3"/><rect x="2" y="15" width="14" height="2" rx="1" opacity=".3"/><rect x="24" y="4" width="16" height="4" rx="1" opacity=".9"/><rect x="24" y="11" width="12" height="2" rx="1" opacity=".3"/><rect x="24" y="15" width="14" height="2" rx="1" opacity=".3"/><rect x="46" y="4" width="16" height="4" rx="1" opacity=".9"/><rect x="46" y="11" width="12" height="2" rx="1" opacity=".3"/><rect x="46" y="15" width="14" height="2" rx="1" opacity=".3"/><rect x="2" y="36" width="60" height="2" rx="1" opacity=".2"/>',
        'footer_layout:centered' => '<rect x="24" y="4" width="16" height="5" rx="1" opacity=".9"/><rect x="8" y="14" width="48" height="3" rx="1" opacity=".3"/><rect x="26" y="22" width="12" height="4" rx="1" opacity=".55"/><rect x="2" y="36" width="60" height="2" rx="1" opacity=".2"/>',
        'footer_layout:split' => '<rect x="2" y="4" width="18" height="6" rx="1" opacity=".9"/><rect x="2" y="13" width="14" height="2" rx="1" opacity=".3"/><rect x="2" y="17" width="16" height="2" rx="1" opacity=".3"/><rect x="28" y="4" width="14" height="4" rx="1" opacity=".55"/><rect x="28" y="11" width="10" height="2" rx="1" opacity=".3"/><rect x="48" y="4" width="14" height="4" rx="1" opacity=".55"/><rect x="48" y="11" width="10" height="2" rx="1" opacity=".3"/><rect x="2" y="36" width="60" height="2" rx="1" opacity=".2"/>',
    ];
@endphp
<div class="v3-laypick" role="radiogroup">
    @foreach ($options as $option)
        @php $svg = $d["{$kind}:{$option}"] ?? null; @endphp
        <label class="v3-laypick__tile">
            <input type="radio" name="{{ $name }}" value="{{ $option }}" @checked((string) $value === (string) $option)>
            <span class="v3-laypick__card">
                @if ($svg)
                    <svg viewBox="0 0 64 44" aria-hidden="true" class="v3-laypick__art">{!! $svg !!}</svg>
                @else
                    <span class="v3-laypick__art v3-laypick__art--blank" aria-hidden="true"></span>
                @endif
                <span class="v3-laypick__name">{{ ucfirst(str_replace('-', ' ', $option)) }}</span>
            </span>
        </label>
    @endforeach
</div>
