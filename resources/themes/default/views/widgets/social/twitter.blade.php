@php
    $config = $placement->config ?? [];
    $user = ltrim(trim((string) ($config['username'] ?? '')), '@');
    $height = max(200, min(1200, (int) ($config['height'] ?? 400)));
@endphp
@if (preg_match('/^[A-Za-z0-9_]{1,15}$/', $user))
<section class="py-10 md:py-14">
    <div class="container-store">
        @if ($placement->title)
            <h2 class="section-title reveal">{{ $placement->title }}</h2>
        @endif
        <div class="wsocial reveal">
            <a class="twitter-timeline" data-height="{{ $height }}" data-dnt="true" href="https://twitter.com/{{ $user }}?ref_src=twsrc%5Etfw">Posts by @{{ $user }}</a>
            <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
        </div>
    </div>
</section>
@endif
