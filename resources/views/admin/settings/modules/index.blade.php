<x-admin3.settings-shell title="Modules" active="modules">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Modules</h1>
            <p class="v3-head__standing">Extensions and the core features that switch on and off.</p>
        </div>
    </div>

    <div class="v3-settle">
        <div>
            <div class="v3-label v3-gap-sm">Extensions</div>
            @if ($extensions->isEmpty())
                <div class="v3-empty">
                    <p class="v3-empty__title">No extensions found</p>
                    <p class="v3-empty__line">Drop an extension folder with an extension.json manifest into extensions/ to see it here.</p>
                </div>
            @else
                <div class="v3-dir">
                    @foreach ($extensions as $ext)
                        <div class="v3-dir__card v3-dir__card--static">
                            <span class="v3-dir__ico"><x-admin3.icon :name="in_array($ext['icon'] ?? '', ['fulfillment']) ? 'package' : 'modules'" /></span>
                            <span class="v3-dir__body">
                                <span class="v3-dir__name">{{ $ext['title'] }}
                                    @if ($ext['version'])<span class="v3-mono v3-cell-quiet"> v{{ $ext['version'] }}</span>@endif
                                    @if (!empty($ext['description']))<x-admin3.help :text="$ext['description']" />@endif
                                </span>
                            </span>
                            <span class="v3-dir__state">
                                <span class="v3-badge {{ ($ext['state_tone'] ?? '') === 'success' ? 'v3-badge--good' : (($ext['state_tone'] ?? '') === 'warning' ? 'v3-badge--warn' : 'v3-badge--quiet') }}">{{ $ext['state_label'] }}</span>
                            </span>
                            <span class="v3-actrow">
                                @if ($ext['configure'])
                                    <a href="{{ $ext['configure'] }}" class="v3-btn v3-btn--ghost v3-btn--sm">Configure</a>
                                @endif
                                @if (in_array('install', $ext['actions'], true))
                                    <form method="POST" action="{{ route('admin.modules.install', $ext['name']) }}">
                                        @csrf
                                        <button type="submit" class="v3-btn v3-btn--primary v3-btn--sm">{{ $ext['state'] === 'needs_migration' ? 'Update' : 'Install' }}</button>
                                    </form>
                                @endif
                                @if (in_array('enable', $ext['actions'], true))
                                    <form method="POST" action="{{ route('admin.modules.enable', $ext['name']) }}">
                                        @csrf
                                        <button type="submit" class="v3-btn v3-btn--primary v3-btn--sm">Enable</button>
                                    </form>
                                @endif
                                @if (in_array('disable', $ext['actions'], true))
                                    <form method="POST" action="{{ route('admin.modules.disable', $ext['name']) }}">
                                        @csrf
                                        <button type="submit" class="v3-btn v3-btn--secondary v3-btn--sm">Disable</button>
                                    </form>
                                @endif
                                @if (in_array('uninstall', $ext['actions'], true))
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'uninstall-{{ $ext['name'] }}')">Uninstall</button>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>

                @foreach ($extensions as $ext)
                    @if (in_array('uninstall', $ext['actions'], true))
                        <x-admin3.confirm name="uninstall-{{ $ext['name'] }}" title="Uninstall {{ $ext['title'] }}?"
                            message="This removes this extension's database tables and all of its data. This cannot be undone."
                            confirm="Uninstall" :action="route('admin.modules.uninstall', $ext['name'])" method="POST" />
                    @endif
                @endforeach
            @endif
        </div>

        <div class="v3-gap-lg">
            <div class="v3-label v3-gap-sm">Core features</div>
            <div class="v3-dir">
                @foreach ($coreFeatures as $module)
                    <div class="v3-dir__card v3-dir__card--static">
                        <span class="v3-dir__ico"><x-admin3.icon name="modules" /></span>
                        <span class="v3-dir__body">
                            <span class="v3-dir__name">{{ $module['name'] }}
                                @if (!empty($module['stats']))<span class="v3-mono v3-cell-quiet"> {{ $module['stats'] }}</span>@endif
                                @if (!empty($module['description']))<x-admin3.help :text="$module['description']" />@endif
                            </span>
                        </span>
                        <span class="v3-dir__state">
                            <span class="v3-badge {{ ($module['tone'] ?? ($module['enabled'] ? 'success' : 'neutral')) === 'success' ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $module['status_label'] }}</span>
                        </span>
                        <span class="v3-actrow">
                            @if ($module['configure'])
                                <a href="{{ $module['configure'] }}" class="v3-btn v3-btn--ghost v3-btn--sm">Configure</a>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-admin3.settings-shell>
