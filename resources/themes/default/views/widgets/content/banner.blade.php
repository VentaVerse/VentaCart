@php
    $config = $placement->config ?? [];
    $image = is_string($config['image'] ?? null) && $config['image'] !== '' && ! str_contains($config['image'], '..') ? $config['image'] : '';
    $pick = fn (string $key, array $allowed, string $default) => in_array((string) ($config[$key] ?? ''), $allowed, true) ? (string) $config[$key] : $default;
    $align = $pick('alignment', ['left', 'center', 'right'], 'center');
    $position = $pick('position', ['top', 'middle', 'bottom'], 'middle');
    $height = $pick('height', ['image', 'sm', 'md', 'lg'], 'image');
    $overlay = $pick('overlay', ['0', '15', '30', '50', '70'], '30');
    $zoom = ($config['hover'] ?? 'zoom') !== 'none';
    $url = trim((string) ($config['url'] ?? ''));
    $safeUrl = $url !== '' && (str_starts_with($url, '/') || str_starts_with($url, 'https://') || str_starts_with($url, 'http://')) ? $url : '';
    $newTab = ($config['target'] ?? 'same') === 'new';
    $heading = trim((string) ($config['heading'] ?? ''));
    $text = trim((string) ($config['text'] ?? ''));
    $button = trim((string) ($config['button'] ?? ''));
    $tag = $safeUrl !== '' ? 'a' : 'div';
@endphp
@if ($image !== '' || $heading !== '' || $text !== '')
<section class="py-6 md:py-8">
    <div class="container-store">
        @if ($placement->title)
            <h2 class="section-title reveal">{{ $placement->title }}</h2>
        @endif
        <{{ $tag }} class="wbanner wbanner--{{ $height }} wbanner--{{ $position }} wbanner--{{ $align }} wbanner--shade-{{ $overlay }}{{ $zoom ? ' wbanner--zoom' : '' }}{{ $image === '' ? ' wbanner--plain' : '' }} reveal"
            @if ($safeUrl !== '') href="{{ $safeUrl }}" @if ($newTab) target="_blank" rel="noopener" @endif @endif>
            @if ($image !== '')
                <img class="wbanner__img" src="{{ storage_url($image) }}" alt="{{ $heading }}" loading="lazy" decoding="async">
            @endif
            @if ($heading !== '' || $text !== '' || $button !== '')
                <span class="wbanner__copy">
                    @if ($heading !== '')
                        <span class="wbanner__heading">{{ $heading }}</span>
                    @endif
                    @if ($text !== '')
                        <span class="wbanner__text">{!! nl2br(e($text)) !!}</span>
                    @endif
                    @if ($button !== '' && $safeUrl !== '')
                        <span class="wbanner__btn">{{ $button }}</span>
                    @endif
                </span>
            @endif
        </{{ $tag }}>
    </div>
</section>
@endif
