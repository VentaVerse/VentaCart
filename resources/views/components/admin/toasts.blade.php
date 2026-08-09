<div x-data class="fixed top-4 right-4 z-[100] flex flex-col gap-2 w-72"
     aria-live="polite">
    {{-- Bridge session flash into the store exactly once per page load --}}
    @if (session('success'))
        <div x-init="$store.toasts.push(@js(session('success')), 'success')" hidden>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div x-init="$store.toasts.push(@js(session('error')), 'danger')" hidden>{{ session('error') }}</div>
    @endif
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div class="flex items-start gap-2.5 px-3.5 py-2.5 bg-v-surface border border-v-border rounded-v-md shadow-v-lift-2 text-[13px] text-v-text"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-x-4"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-end="opacity-0">
            <x-admin.icon name="check" x-show="toast.tone === 'success'" class="w-4 h-4 text-v-accent mt-0.5 shrink-0" />
            <x-admin.icon name="warning" x-show="toast.tone === 'danger'" class="w-4 h-4 text-v-danger mt-0.5 shrink-0" />
            <span class="flex-1" x-text="toast.message"></span>
            <button type="button" class="text-v-muted hover:text-v-text" @click="$store.toasts.dismiss(toast.id)"
                    aria-label="Dismiss notification">
                <x-admin.icon name="x" class="w-3.5 h-3.5" />
            </button>
        </div>
    </template>
</div>
