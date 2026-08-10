<x-admin.settings-shell title="Modules" :wide="true">
    <div class="space-y-8">
        <div>
            <h2 class="mb-3 v-label">Extensions</h2>

            @if ($extensions->isEmpty())
                <x-admin.empty-state title="No extensions found"
                    description="Drop an extension folder with an extension.json manifest into extensions/ to see it here." />
            @else
                <div class="v-slates">
                    @foreach ($extensions as $ext)
                        <x-admin.slate :title="$ext['title']" :icon="$ext['icon']" :note="$ext['description']">
                            <x-slot:state>
                                <x-admin.badge :tone="$ext['state_tone']">{{ $ext['state_label'] }}</x-admin.badge>
                            </x-slot:state>
                            <x-slot:meta>{{ $ext['version'] ? 'v' . $ext['version'] : '' }}</x-slot:meta>
                            <x-slot:actions>
                                @if ($ext['configure'])
                                    <x-admin.button variant="ghost" size="sm" :href="$ext['configure']">Configure</x-admin.button>
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
                                    <x-admin.button type="button" variant="ghost" size="sm" class="v-rowactions__danger"
                                        @click="$dispatch('open-modal', 'uninstall-{{ $ext['name'] }}')">Uninstall</x-admin.button>
                                @endif
                            </x-slot:actions>
                        </x-admin.slate>
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
            <h2 class="mb-3 v-label">Core Features</h2>
            <div class="v-slates">
                @foreach ($coreFeatures as $module)
                    <x-admin.slate :title="$module['name']" :icon="$module['icon']" :note="$module['description']">
                        <x-slot:state>
                            <x-admin.badge :tone="$module['tone'] ?? ($module['enabled'] ? 'success' : 'neutral')">{{ $module['status_label'] }}</x-admin.badge>
                        </x-slot:state>
                        <x-slot:meta>{{ $module['stats'] ?? '' }}</x-slot:meta>
                        @if ($module['configure'])
                            <x-slot:actions>
                                <x-admin.button variant="ghost" size="sm" :href="$module['configure']">Configure</x-admin.button>
                            </x-slot:actions>
                        @endif
                    </x-admin.slate>
                @endforeach
            </div>
        </div>
    </div>
</x-admin.settings-shell>
