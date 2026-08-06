@props(['sortable' => false, 'field' => null, 'align' => 'left'])
@php
$alignClass = $align === 'right' ? 'text-right' : 'text-left';
$currentSort = request('sort');
$currentDir = request('dir', 'asc');
$nextDir = ($currentSort === $field && $currentDir === 'asc') ? 'desc' : 'asc';
@endphp
<th {{ $attributes->merge(['class' => "px-4 h-[var(--row-h)] {$alignClass} text-[10px] font-semibold uppercase tracking-widest text-v-muted whitespace-nowrap"]) }}>
    @if ($sortable && $field)
        <a href="{{ request()->fullUrlWithQuery(['sort' => $field, 'dir' => $nextDir]) }}" class="inline-flex items-center gap-1 hover:text-v-text">
            {{ $slot }}
            @if ($currentSort === $field)
                <x-admin.icon :name="$currentDir === 'asc' ? 'chevron-down' : 'chevron-right'" class="w-3 h-3" />
            @endif
        </a>
    @else
        {{ $slot }}
    @endif
</th>
