<div x-data class="v3-toasts" aria-live="polite">
    @if (session('success'))
        <div x-init="$store.toasts.push(@js(session('success')), 'good')" hidden></div>
    @endif
    @if (session('error'))
        <div x-init="$store.toasts.push(@js(session('error')), 'bad')" hidden></div>
    @endif
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div class="v3-toast" :class="'v3-toast--' + toast.tone"
             x-transition:enter="v3-toast-in" x-transition.opacity.duration.150ms>
            <span class="v3-toast__mark" aria-hidden="true"></span>
            <span x-text="toast.message"></span>
            <button type="button" class="v3-toast__x" @click="$store.toasts.dismiss(toast.id)" aria-label="Dismiss notification">
                <x-admin3.icon name="x" />
            </button>
        </div>
    </template>
</div>
