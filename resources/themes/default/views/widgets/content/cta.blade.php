@php
    $config = $placement->config ?? [];
    $align = $config['alignment'] ?? 'center';
    $align = in_array($align, ['left', 'center', 'right'], true) ? $align : 'center';
    $url = trim((string) ($config['url'] ?? ''));
    $safeUrl = $url !== '' && (str_starts_with($url, '/') || str_starts_with($url, 'https://') || str_starts_with($url, 'http://')) ? $url : '';
@endphp
<section class="py-10 md:py-14">
    <div class="container-store">
        <div class="wcta wcta--{{ $align }} reveal">
            @if ($placement->title)
                <h2 class="wcta__title">{{ $placement->title }}</h2>
            @endif
            @if (!empty($config['text']))
                <p class="wcta__text">{!! nl2br(e($config['text'])) !!}</p>
            @endif
            @if (!empty($config['button']) && $safeUrl !== '')
                <a href="{{ $safeUrl }}" class="wcta__btn">{{ $config['button'] }}</a>
            @endif
        </div>
    </div>
</section>
