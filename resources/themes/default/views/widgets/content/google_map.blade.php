@php
    $config = $placement->config ?? [];
    $height = max(150, min(1000, (int) ($config['height'] ?? 400) ?: 400));
@endphp
@if (! empty($mapUrl))
<section class="py-10 md:py-14">
    <div class="container-store">
        @if ($placement->title)
            <h2 class="section-title reveal">{{ $placement->title }}</h2>
        @endif
        <div class="wmap reveal">
            {{-- loading="lazy": the map is fetched only as it nears the screen. --}}
            <iframe class="wmap__frame" src="{{ $mapUrl }}" height="{{ $height }}" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade" allowfullscreen
                    title="{{ $placement->title ?: 'Map of ' . trim((string) ($config['location'] ?? '')) }}"></iframe>
        </div>
    </div>
</section>
@endif
