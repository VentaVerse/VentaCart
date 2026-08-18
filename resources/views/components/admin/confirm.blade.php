@props(['name', 'title', 'message', 'confirm' => 'Confirm', 'action', 'method' => 'POST', 'danger' => true])
{{-- A blocking confirm for a destructive or high-stakes action. Opened by
     $dispatch('v-confirm', '{{ $name }}'). Escape and the overlay close it;
     focus lands on Cancel so Enter never destroys by default. --}}
<div class="v-passthru" x-data="{ open: false }"
     x-on:v-confirm.window="if ($event.detail === @js($name)) { open = true; $nextTick(() => $refs.cancel.focus()); }"
     x-on:keydown.escape.window="open = false">
    <template x-if="open">
        <div class="v-overlay" @click.self="open = false">
            <div class="v-modal" role="dialog" aria-modal="true" aria-labelledby="vc-{{ $name }}">
                <h2 class="v-modal__title" id="vc-{{ $name }}">{{ $title }}</h2>
                <p class="v-modal__body">{{ $message }}</p>
                <form method="POST" action="{{ $action }}" class="v-modal__acts">
                    @csrf
                    @if (strtoupper($method) !== 'POST') @method($method) @endif
                    {{ $slot ?? '' }}
                    <button type="button" class="v-btn v-btn--ghost" x-ref="cancel" @click="open = false">Cancel</button>
                    <button type="submit" class="v-btn {{ $danger ? 'v-btn--danger-fill' : 'v-btn--primary' }}">{{ $confirm }}</button>
                </form>
            </div>
        </div>
    </template>
</div>
