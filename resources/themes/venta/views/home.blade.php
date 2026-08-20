<x-store-layout>
    {{-- The home page is now the same shape as every other page: regions the
         merchant fills, not a hardcoded chain of section types. What used to be
         an if/elseif over eight known types lives in the widget registry, so a
         module can add a ninth without touching this file or the controller. --}}
    <x-storefront.region name="header_top" />
    <x-storefront.region name="content_top" />
    <x-storefront.region name="content_bottom" />
    <x-storefront.region name="footer_top" />
</x-store-layout>
