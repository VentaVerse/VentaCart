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
                        {{-- No state badge: the buttons say the state
                             (owner ruling 2026-08-18 — Install means not
                             installed, Disable means enabled). The one
                             exception is a broken state no button can
                             express. --}}
                        @if ($module['state'] === 'missing_files')
                            <span class="v3-dir__state">
                                <span class="v3-badge v3-badge--bad">{{ $module['state_label'] }}</span>
                            </span>
                        @endif
                        @include('admin.extensions.partials.lifecycle-actions')
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-admin3.shell>
