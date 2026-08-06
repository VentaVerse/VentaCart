<x-admin.shell title="Modules">
    <x-admin.page title="Modules" :breadcrumb="[['label' => 'Settings', 'url' => route('admin.settings.index')]]">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($modules as $module)
                <x-admin.card>
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-v-md bg-v-sunken text-v-secondary">
                            <x-admin.icon :name="$module['icon']" class="w-5 h-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-[14px] font-semibold text-v-text">{{ $module['name'] }}</h3>
                                <x-admin.badge :tone="$module['enabled'] ? 'success' : 'neutral'">{{ $module['status_label'] }}</x-admin.badge>
                            </div>
                            <p class="mt-1 text-[13px] leading-snug text-v-secondary">{{ $module['description'] }}</p>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="v-num text-xs text-v-muted">{{ $module['stats'] ?? '' }}</span>
                                @if ($module['configure'])
                                    <x-admin.button variant="ghost" size="sm" :href="$module['configure']">Configure →</x-admin.button>
                                @endif
                            </div>
                        </div>
                    </div>
                </x-admin.card>
            @endforeach
        </div>
    </x-admin.page>
</x-admin.shell>
