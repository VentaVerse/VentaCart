@php
    $config = $section->config ?? [];
    $alignment = $config['alignment'] ?? 'center';
    $alignClass = match($alignment) {
        'left' => 'text-left',
        'right' => 'text-right',
        default => 'text-center',
    };
@endphp

<div class="{{ $alignClass }}">
    @if ($section->title)
        <h2 class="section-title">{{ $section->title }}</h2>
    @endif
    @if (!empty($config['content']))
        <div class="prose prose-invert max-w-none text-[#888]">
            {!! nl2br(e($config['content'])) !!}
        </div>
    @endif
</div>
