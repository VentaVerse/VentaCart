<x-admin.settings-shell title="Settings">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Settings</h1>
            <p class="v-head__standing">Everything that configures {{ \App\Support\StoreIdentity::name() }}, grouped by what it governs.</p>
        </div>
    </div>

    @php
        $vDirGroups = collect(config('admin-settings-nav'))
            ->map(function ($group) {
                $group['items'] = array_values(array_filter($group['items'], fn ($i) => \Illuminate\Support\Facades\Route::has($i['route'])));
                return $group;
            })
            ->filter(fn ($group) => $group['items'] !== [])
            ->values();
        $vAllHays = $vDirGroups->flatMap(fn ($g) => collect($g['items'])->map(fn ($i) => strtolower($i['label'] . ' ' . $i['line'])))->values();
    @endphp

    <div class="v-settle" x-data="{ q: '', hit(h) { const t = this.q.trim().toLowerCase(); return !t || h.includes(t); } }">
        <x-admin.field name="q" label="Find a setting">
            <input type="search" class="v-control v-narrow" x-model="q" placeholder="Search settings…">
        </x-admin.field>

        @foreach ($vDirGroups as $group)
            @php $groupHays = collect($group['items'])->map(fn ($i) => strtolower($i['label'] . ' ' . $i['line']))->values(); @endphp
            <div class="v-gap-lg" x-show="@js($groupHays).some(h => hit(h))">
                <div class="v-label v-gap-sm">{{ $group['label'] }}</div>
                <div class="v-dir">
                    @foreach ($group['items'] as $item)
                        @php
                            $url = $item['section'] !== null ? route($item['route'], $item['section']) : route($item['route']);
                            $hay = strtolower($item['label'] . ' ' . $item['line']);
                        @endphp
                        <a href="{{ $url }}" class="v-dir__card" x-show="hit(@js($hay))">
                            <span class="v-dir__ico"><x-admin.icon :name="$item['icon']" /></span>
                            <span>
                                <span class="v-dir__name">{{ $item['label'] }}</span>
                                <span class="v-dir__line v-block">{{ $item['line'] }}</span>
                            </span>
                            <span class="v-dir__go" aria-hidden="true"><x-admin.icon name="chevron" /></span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="v-empty v-gap-lg" x-show="q.trim() && !@js($vAllHays).some(h => hit(h))" x-cloak>
            <p class="v-empty__title">No setting matches</p>
            <p class="v-empty__line">Nothing here is named that. Try the section names on the left.</p>
        </div>
    </div>
</x-admin.settings-shell>
