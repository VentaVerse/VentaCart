@php
    $config = $placement->config ?? [];
    $url = trim((string) ($config['url'] ?? ''));
    $height = max(130, min(1200, (int) ($config['height'] ?? 400)));
@endphp
@if (preg_match('#^https://(www\.)?facebook\.com/[A-Za-z0-9._/-]+$#', $url))
<section class="py-10 md:py-14">
    <div class="container-store">
        @if ($placement->title)
            <h2 class="section-title reveal">{{ $placement->title }}</h2>
        @endif
        <div class="wsocial reveal">
            <iframe class="wsocial__frame" src="https://www.facebook.com/plugins/page.php?href={{ rawurlencode($url) }}&tabs=timeline&small_header=false&adapt_container_width=true&hide_cover=false&show_facepile=true&height={{ $height }}"
                    height="{{ $height }}" style="height: {{ $height }}px" scrolling="no" frameborder="0" allowfullscreen="true" loading="lazy"
                    allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" title="Facebook page"></iframe>
        </div>
    </div>
</section>
@endif
