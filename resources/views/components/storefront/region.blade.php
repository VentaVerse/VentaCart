@props(['name'])
@php
    /**
     * Everything the merchant placed in this region of this page.
     *
     * The theme calls this wherever it wants a slot. Core resolves which
     * widgets belong there; the theme decides where the slot IS and what wraps
     * it. That division is what makes a layout portable between themes that
     * look nothing alike.
     */
    $vPageType = \App\Support\StorefrontPageType::current();
    $vPlacements = \App\Models\LayoutPlacement::forRegion($vPageType, $name);
@endphp

@foreach ($vPlacements as $placement)
    @php
        $vWidget = \App\Support\Widgets::get($placement->widget);
        $vView = \App\Support\Widgets::view($placement->widget);
        $vData = $vWidget ? \App\Support\Widgets::resolve($placement->widget, $placement->config ?? []) : [];
    @endphp

    {{-- A placement whose module is disabled or uninstalled renders NOTHING on
         the storefront, and is reported in the layout editor instead. A shopper
         must never meet a broken slot because an admin turned an extension off. --}}
    @if ($vWidget && $vView)
        @include($vView, array_merge(['placement' => $placement], $vData))
    @endif
@endforeach
