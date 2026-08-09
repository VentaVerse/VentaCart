{{-- A column label, in sentence case at normal width. The old
     10px/uppercase/tracking-widest micro-cap is retired: it was the
     single most dating detail in the build, and at 10px it was also
     the hardest thing on the page to read. --}}
@props(['sortable' => false, 'field' => null, 'align' => 'left'])
@php
$alignClass = $align === 'right' ? 'text-right' : 'text-left';
$currentSort = request('sort');
$currentDir = request('dir', 'asc');
$nextDir = ($currentSort === $field && $currentDir === 'asc') ? 'desc' : 'asc';
@endphp
<th {{ $attributes->merge(['class' => "v-label px-4 h-[var(--row-h)] {$alignClass} whitespace-nowrap"]) }}>
    @if ($sortable && $field)
        <a href="{{ request()->fullUrlWithQuery(['sort' => $field, 'dir' => $nextDir]) }}" class="inline-flex items-center gap-1 hover:text-v-text">
            {{ $slot }}
            @if ($currentSort === $field)
                <x-admin.icon name="chevron-down" class="{{ $currentDir === 'desc' ? 'rotate-180 ' : '' }}w-3 h-3" />
            @endif
        </a>
    @else
        {{ $slot }}
    @endif
</th>
