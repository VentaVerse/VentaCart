<x-admin3.shell :title="$heading">
    <div class="v3-head">
        <div class="v3-head__body">
            @if ($heading !== 'Extensions')
                <div class="v3-head__trail">
                    <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                    <span aria-hidden="true">/</span>
                    <span>{{ $heading }}</span>
                </div>
            @endif
            <h1 class="v3-head__title">{{ $heading }}</h1>
            <p class="v3-head__standing">{{ $standing }}</p>
        </div>
    </div>

    {{-- One list (owner ruling 2026-08-17), one standard button set per
         row (owner ruling 2026-08-18): not installed shows Install and
         nothing else; installed shows Uninstall, Enable/Disable, and
         Configure. Configure is a dead button while disabled because the
         extension's pages only exist while it is enabled. Uninstall rolls
         back the extension's migrations, so its confirm says the data
         goes with it. --}}
    <div class="v3-settle">
        <div class="v3-dir">
            @foreach ($modules as $module)
                <div class="v3-dir__card v3-dir__card--static">
                    <span class="v3-dir__ico"><x-admin3.icon name="modules" /></span>
                    <span class="v3-dir__body">
                        <span class="v3-dir__name">{{ $module['title'] }}
                            @if (!empty($module['version']))<span class="v3-mono v3-cell-quiet"> v{{ $module['version'] }}</span>@endif
                            @if (!empty($module['stats']))<span class="v3-mono v3-cell-quiet"> {{ $module['stats'] }}</span>@endif
                            @if (!empty($module['description']))<x-admin3.help :text="$module['description']" />@endif
                        </span>
                    </span>
                    @if ($showCategory)
                        <span class="v3-dir__cat">{{ $module['category'] }}</span>
                    @endif
                    @if ($module['kind'] === 'extension')
                        <span class="v3-dir__state">
                            <span class="v3-badge {{ ($module['state_tone'] ?? '') === 'success' ? 'v3-badge--good' : (($module['state_tone'] ?? '') === 'warning' ? 'v3-badge--warn' : (($module['state_tone'] ?? '') === 'danger' ? 'v3-badge--bad' : 'v3-badge--quiet')) }}">{{ $module['state_label'] }}</span>
                        </span>
                        <span class="v3-actrow">
                            @if (in_array('uninstall', $module['actions'], true))
                                <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'uninstall-{{ $module['name'] }}')">Uninstall</button>
                            @endif
                            @if (in_array('enable', $module['actions'], true))
                                <form method="POST" action="{{ route('admin.extensions.enable', $module['name']) }}">
                                    @csrf
                                    <button type="submit" class="v3-btn v3-btn--secondary v3-btn--sm">Enable</button>
                                </form>
                            @endif
                            @if (in_array('disable', $module['actions'], true))
                                <form method="POST" action="{{ route('admin.extensions.disable', $module['name']) }}">
                                    @csrf
                                    <button type="submit" class="v3-btn v3-btn--secondary v3-btn--sm">Disable</button>
                                </form>
                            @endif
                            @if (in_array('install', $module['actions'], true))
                                <form method="POST" action="{{ route('admin.extensions.install', $module['name']) }}">
                                    @csrf
                                    <button type="submit" class="v3-btn v3-btn--primary v3-btn--sm">{{ $module['state'] === 'needs_migration' ? 'Update' : 'Install' }}</button>
                                </form>
                            @endif
                            @if ($module['configure'])
                                <a href="{{ $module['configure'] }}" class="v3-btn v3-btn--ghost v3-btn--sm">Configure</a>
                            @elseif ($module['state'] === 'disabled')
                                <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" disabled>Configure</button>
                            @endif
                        </span>
                        @if (in_array('uninstall', $module['actions'], true))
                            <x-admin3.confirm name="uninstall-{{ $module['name'] }}" title="Uninstall {{ $module['title'] }}?"
                                message="Uninstalling removes its tables and everything in them. Its files stay, so it can be installed again, but the data does not come back."
                                confirm="Uninstall"
                                :action="route('admin.extensions.uninstall', $module['name'])" method="POST" />
                        @endif
                    @else
                        <span class="v3-dir__state">
                            <span class="v3-badge {{ ($module['tone'] ?? ($module['enabled'] ? 'success' : 'neutral')) === 'success' ? 'v3-badge--good' : (($module['tone'] ?? '') === 'warning' ? 'v3-badge--warn' : 'v3-badge--quiet') }}">{{ $module['status_label'] }}</span>
                        </span>
                        <span class="v3-actrow">
                            @if ($module['configure'])
                                <a href="{{ $module['configure'] }}" class="v3-btn v3-btn--ghost v3-btn--sm">Configure</a>
                            @endif
                            {{-- The master switch. Off hides the feature's nav
                                 entry and pauses its scheduled jobs; its own
                                 settings stay reachable through Configure. --}}
                            <form method="POST" action="{{ route($module['enabled'] ? 'admin.extensions.feature-disable' : 'admin.extensions.feature-enable', $module['key']) }}">
                                @csrf
                                <button type="submit" class="v3-btn v3-btn--secondary v3-btn--sm">{{ $module['enabled'] ? 'Disable' : 'Enable' }}</button>
                            </form>
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-admin3.shell>
