@php
    // Unescaped on purpose. Only staff signed in to the layout editor can
    // write a placement's settings; see content.html in
    // App\Storefront\Widgets\CoreWidgets for why that makes this safe.
    $html = trim((string) (($placement->config ?? [])['html'] ?? ''));
@endphp
@if ($html !== '')
<section class="py-10 md:py-14">
    <div class="container-store">
        @if ($placement->title)
            <h2 class="section-title reveal">{{ $placement->title }}</h2>
        @endif
        <div class="whtml">{!! $html !!}</div>
    </div>
</section>
@endif
