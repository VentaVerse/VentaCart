@props(['name', 'title' => null, 'maxWidth' => 'md'])
@php $widths = ['sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-2xl']; @endphp
<div x-data="{ open: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
     x-on:keydown.escape.window="open = false"
     x-show="open" x-cloak
     class="fixed inset-0 z-[90] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
    <div x-show="open" x-transition
         class="relative w-full {{ $widths[$maxWidth] }} bg-v-surface border border-v-border rounded-v-lg shadow-2xl whitespace-normal"
         role="dialog" aria-modal="true" @if($title) aria-label="{{ $title }}" @endif>
        @if ($title)
            <header class="flex items-center justify-between px-5 pt-4">
                <h2 class="text-[15px] font-semibold text-v-text">{{ $title }}</h2>
                <button type="button" class="text-v-muted hover:text-v-text" @click="open = false">
                    <x-admin.icon name="x" class="w-4 h-4" />
                </button>
            </header>
        @endif
        <div class="px-5 py-4">{{ $slot }}</div>
    </div>
</div>
