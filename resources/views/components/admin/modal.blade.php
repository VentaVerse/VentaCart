{{-- The one dialog shell in the admin.

     `role` is a prop because a confirmation is not a plain dialog: it
     interrupts to ask a question, so x-admin.confirm-dialog passes
     `alertdialog` and gets the announcement that goes with it.

     The panel names itself from the heading it already renders
     (aria-labelledby), takes focus on open through x-dialog, holds it
     until it closes, and hands it back to whatever opened it. --}}
@props([
    'name',
    'title' => null,
    'maxWidth' => 'md',
    'role' => 'dialog',
    'describedby' => null,
])
@php
    $widths = ['sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-2xl'];
    $titleId = $name . '-title';
@endphp
<div x-data="{ open: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
     x-on:keydown.escape.window="open = false"
     x-show="open" x-cloak
     {{ $attributes->merge(['class' => 'fixed inset-0 z-[90] flex items-center justify-center p-4']) }}>
    <div class="absolute inset-0 bg-black/50" @click="open = false" aria-hidden="true"></div>
    <div x-show="open" x-transition x-dialog="open" tabindex="-1"
         class="v-modal relative w-full {{ $widths[$maxWidth] }} bg-v-surface border border-v-border rounded-v-lg whitespace-normal"
         role="{{ $role }}" aria-modal="true"
         @if ($title) aria-labelledby="{{ $titleId }}" @else aria-label="{{ \Illuminate\Support\Str::headline($name) }}" @endif
         @if ($describedby) aria-describedby="{{ $describedby }}" @endif>
        @if ($title)
            <header class="flex items-center justify-between px-5 pt-4">
                <h2 id="{{ $titleId }}" class="text-[15px] font-semibold text-v-text">{{ $title }}</h2>
                <button type="button" class="text-v-muted hover:text-v-text" @click="open = false" aria-label="Close">
                    <x-admin.icon name="x" class="w-4 h-4" />
                </button>
            </header>
        @endif
        <div class="px-5 py-4">{{ $slot }}</div>
    </div>
</div>
