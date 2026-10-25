<x-admin.shell :title="$heading">
    <div class="v-head">
        <div class="v-head__body">
            @if ($heading !== 'Extensions')
                <div class="v-head__trail">
                    <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                    <span aria-hidden="true">/</span>
                    <span>{{ $heading }}</span>
                </div>
            @endif
            <h1 class="v-head__title">{{ $heading }}</h1>
            <p class="v-head__standing">{{ $standing }}</p>
        </div>
        @if (auth()->user()?->isSuperAdmin())
            <div class="v-head__actions">
                <a href="{{ route('admin.extensions.upload') }}" class="v-btn">Upload</a>
            </div>
        @endif
    </div>

    {{-- One list (owner ruling 2026-08-17), one standard button set per
         row (owner ruling 2026-08-18): not installed shows Install and
         nothing else; installed shows Uninstall, Enable/Disable, and
         Configure. Configure is a dead button while disabled because the
         extension's pages only exist while it is enabled. Uninstall rolls
         back the extension's migrations, so its confirm says the data
         goes with it. --}}
    <div class="v-settle">
        <div class="v-dir">
            @foreach ($modules as $module)
                <div class="v-dir__card v-dir__card--static">
                    <span class="v-dir__ico"><x-admin.icon name="modules" /></span>
                    <span class="v-dir__body">
                        <span class="v-dir__name">{{ $module['title'] }}
                            @if (!empty($module['version']))<span class="v-mono v-cell-quiet"> v{{ $module['version'] }}</span>@endif
                            @if (!empty($module['stats']))<span class="v-mono v-cell-quiet"> {{ $module['stats'] }}</span>@endif
                            @if (!empty($module['description']))<x-admin.help :text="$module['description']" />@endif
                        </span>
                    </span>
                    @if ($showCategory)
                        <span class="v-dir__cat">{{ $module['category'] }}</span>
                    @endif
                    @if ($module['kind'] === 'extension')
                        {{-- No state badge: the buttons say the state
                             (Install means not installed, Disable means
                             enabled). The exceptions are a broken state no
                             button can express, and a pending update, whose
                             badge names the version waiting on disk. --}}
                        @if ($module['state'] === 'missing_files')
                            <span class="v-dir__state">
                                <span class="v-badge v-badge--bad">{{ $module['state_label'] }}</span>
                            </span>
                        @elseif ($module['state'] === 'needs_migration')
                            <span class="v-dir__state">
                                <span class="v-badge v-badge--warn">{{ $module['state_label'] }}@if (! empty($module['update_to'])): v{{ $module['update_to'] }}@endif</span>
                            </span>
                        @endif
                        @include('admin.extensions.partials.lifecycle-actions')
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-admin.shell>
