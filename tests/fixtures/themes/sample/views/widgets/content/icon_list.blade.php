@php
    $config = $placement->config ?? [];
    $columns = max(2, min(6, (int) ($config['columns'] ?? 4)));
    $items = collect(is_array($config['items'] ?? null) ? $config['items'] : [])->filter(fn ($i) => is_array($i) && (($i['title'] ?? '') !== '' || ($i['text'] ?? '') !== ''));
@endphp
@if ($items->isNotEmpty())
<section class="py-8 md:py-10">
    <div class="container-store">
        @if ($placement->title)
            <h2 class="section-title reveal">{{ $placement->title }}</h2>
        @endif
        <ul class="wicons wicons--{{ $columns }} reveal-stagger">
            @foreach ($items as $item)
                <li class="wicons__item">
                    <span class="wicons__icon" aria-hidden="true">@include('partials.icon', ['name' => $item['icon'] ?? ''])</span>
                    <span class="wicons__body">
                        @if (($item['title'] ?? '') !== '')<b class="wicons__title">{{ $item['title'] }}</b>@endif
                        @if (($item['text'] ?? '') !== '')<span class="wicons__text">{{ $item['text'] }}</span>@endif
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
</section>
@endif
