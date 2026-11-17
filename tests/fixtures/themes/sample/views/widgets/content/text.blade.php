<section class="py-10 md:py-14">
    <div class="container-store reveal">
@php
    $config = $placement->config ?? [];
    $alignment = $config['alignment'] ?? 'center';
    $alignClass = match($alignment) {
        'left' => 'text-left',
        'right' => 'text-right',
        default => 'text-center',
    };
@endphp

<div class="{{ $alignClass }}">
    @if ($placement->title)
        <h2 class="section-title">{{ $placement->title }}</h2>
    @endif
    @if (!empty($config['content']))
        <div class="prose prose-invert max-w-none text-[var(--color-text-muted)]">
            {!! nl2br(e($config['content'])) !!}
        </div>
    @endif
</div>
    </div>
</section>
