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

    {{-- One list (owner ruling 2026-08-17): a module shipped in the repo is
         a core feature, whether it lives in extensions/ (filesystem
         lifecycle: install runs its migrations) or in app/ (a master
         switch). The difference shows only in a row's own actions; there
         is no Uninstall here, because you do not uninstall your own
         platform. The uninstall lifecycle survives in ExtensionManager for
         the day third-party extensions arrive from outside the repo. --}}
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
                            @if ($module['configure'])
                                <a href="{{ $module['configure'] }}" class="v3-btn v3-btn--ghost v3-btn--sm">Configure</a>
                            @endif
                            @if (in_array('install', $module['actions'], true))
                                <form method="POST" action="{{ route('admin.extensions.install', $module['name']) }}">
                                    @csrf
                                    <button type="submit" class="v3-btn v3-btn--primary v3-btn--sm">{{ $module['state'] === 'needs_migration' ? 'Update' : 'Install' }}</button>
                                </form>
                            @endif
                            @if (in_array('enable', $module['actions'], true))
                                <form method="POST" action="{{ route('admin.extensions.enable', $module['name']) }}">
                                    @csrf
                                    <button type="submit" class="v3-btn v3-btn--primary v3-btn--sm">Enable</button>
                                </form>
                            @endif
                            @if (in_array('disable', $module['actions'], true))
                                <form method="POST" action="{{ route('admin.extensions.disable', $module['name']) }}">
                                    @csrf
                                    <button type="submit" class="v3-btn v3-btn--secondary v3-btn--sm">Disable</button>
                                </form>
                            @endif
                        </span>
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
