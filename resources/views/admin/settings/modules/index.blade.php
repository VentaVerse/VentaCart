<x-admin.settings-shell active="modules" title="Modules" :wide="true">
    <div class="space-y-8">
        <div>
            <p class="mb-3 text-[10px] font-semibold uppercase tracking-widest text-v-muted">Extensions</p>

            @if ($extensions->isEmpty())
                <x-admin.empty-state icon="categories" title="No extensions found"
                    description="Drop an extension folder with an extension.json manifest into extensions/ to see it here." />
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($extensions as $ext)
                        <x-admin.card>
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-v-md bg-v-sunken text-v-secondary">
                                    <x-admin.icon :name="$ext['icon']" class="w-5 h-5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <h3 class="text-[14px] font-semibold text-v-text">{{ $ext['title'] }}</h3>
                                        <x-admin.badge :tone="$ext['state_tone']">{{ $ext['state_label'] }}</x-admin.badge>
                                    </div>
                                    <p class="mt-1 text-[13px] leading-snug text-v-secondary">{{ $ext['description'] }}</p>
                                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                        <span class="v-num text-xs text-v-muted">{{ $ext['version'] ? 'v' . $ext['version'] : '' }}</span>
                                        <div class="flex items-center gap-2">
                                            @if ($ext['configure'])
                                                <x-admin.button variant="ghost" size="sm" :href="$ext['configure']">Configure →</x-admin.button>
                                            @endif

                                            @if (in_array('install', $ext['actions'], true))
                                                <form method="POST" action="{{ route('admin.modules.install', $ext['name']) }}" data-v-form>
                                                    @csrf
                                                    <x-admin.button type="submit" variant="primary" size="sm">
                                                        {{ $ext['state'] === 'needs_migration' ? 'Update' : 'Install' }}
                                                    </x-admin.button>
                                                </form>
                                            @endif

                                            @if (in_array('enable', $ext['actions'], true))
                                                <form method="POST" action="{{ route('admin.modules.enable', $ext['name']) }}" data-v-form>
                                                    @csrf
                                                    <x-admin.button type="submit" variant="primary" size="sm">Enable</x-admin.button>
                                                </form>
                                            @endif

                                            @if (in_array('disable', $ext['actions'], true))
                                                <form method="POST" action="{{ route('admin.modules.disable', $ext['name']) }}" data-v-form>
                                                    @csrf
                                                    <x-admin.button type="submit" variant="secondary" size="sm">Disable</x-admin.button>
                                                </form>
                                            @endif

                                            @if (in_array('uninstall', $ext['actions'], true))
                                                <button type="button" class="text-xs text-v-danger hover:opacity-80"
                                                    @click="$dispatch('open-modal', 'uninstall-{{ $ext['name'] }}')">
                                                    Uninstall
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </x-admin.card>
                    @endforeach
                </div>

                @foreach ($extensions as $ext)
                    @if (in_array('uninstall', $ext['actions'], true))
                        <x-admin.confirm-dialog name="uninstall-{{ $ext['name'] }}" title="Uninstall {{ $ext['title'] }}?"
                            message="This removes this extension's database tables and all of its data. This cannot be undone."
                            confirm="Uninstall" :action="route('admin.modules.uninstall', $ext['name'])" method="POST" />
                    @endif
                @endforeach
            @endif
        </div>

        <div>
            <p class="mb-3 text-[10px] font-semibold uppercase tracking-widest text-v-muted">Core Features</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($coreFeatures as $module)
                    <x-admin.card>
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-v-md bg-v-sunken text-v-secondary">
                                <x-admin.icon :name="$module['icon']" class="w-5 h-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="text-[14px] font-semibold text-v-text">{{ $module['name'] }}</h3>
                                    <x-admin.badge :tone="$module['tone'] ?? ($module['enabled'] ? 'success' : 'neutral')">{{ $module['status_label'] }}</x-admin.badge>
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
        </div>
    </div>
</x-admin.settings-shell>
