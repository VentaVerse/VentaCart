@props(['name', 'title', 'message', 'confirm' => 'Confirm', 'action', 'method' => 'POST', 'danger' => true])
{{-- A blocking confirm for a destructive or high-stakes action. Opened by
     $dispatch('v3-confirm', '{{ $name }}'). Escape and the overlay close it;
     focus lands on Cancel so Enter never destroys by default. --}}
<div x-data="{ open: false }"
     x-on:v3-confirm.window="if ($event.detail === @js($name)) { open = true; $nextTick(() => $refs.cancel.focus()); }"
     x-on:keydown.escape.window="open = false">
    <template x-if="open">
        <div class="v3-overlay" @click.self="open = false">
            <div class="v3-modal" role="dialog" aria-modal="true" aria-labelledby="v3c-{{ $name }}">
                <h2 class="v3-modal__title" id="v3c-{{ $name }}">{{ $title }}</h2>
                <p class="v3-modal__body">{{ $message }}</p>
                <form method="POST" action="{{ $action }}" class="v3-modal__acts">
                    @csrf
                    @if (strtoupper($method) !== 'POST') @method($method) @endif
                    <button type="button" class="v3-btn v3-btn--ghost" x-ref="cancel" @click="open = false">Cancel</button>
                    <button type="submit" class="v3-btn {{ $danger ? 'v3-btn--danger-fill' : 'v3-btn--primary' }}">{{ $confirm }}</button>
                </form>
            </div>
        </div>
    </template>
</div>
