<x-admin3.settings-shell title="Settings">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Settings</h1>
            <p class="v3-head__standing">Everything that configures {{ \App\Support\StoreIdentity::name() }}, grouped by what it governs.</p>
        </div>
    </div>

    @php
        $v3DirGroups = collect(config('admin3-settings-nav'))
            ->map(function ($group) {
                $group['items'] = array_values(array_filter($group['items'], fn ($i) => \Illuminate\Support\Facades\Route::has($i['route'])));
                return $group;
            })
            ->filter(fn ($group) => $group['items'] !== [])
            ->values();
        $v3AllHays = $v3DirGroups->flatMap(fn ($g) => collect($g['items'])->map(fn ($i) => strtolower($i['label'] . ' ' . $i['line'])))->values();
    @endphp

    <div class="v3-settle" x-data="{ q: '', hit(h) { return !this.q || h.includes(this.q.toLowerCase()); } }">
        <x-admin3.field name="q" label="Find a setting">
            <input type="search" class="v3-control v3-narrow" x-model="q" placeholder="Search settings…">
        </x-admin3.field>

        @foreach ($v3DirGroups as $group)
            @php $groupHays = collect($group['items'])->map(fn ($i) => strtolower($i['label'] . ' ' . $i['line']))->values(); @endphp
            <div class="v3-gap-lg" x-show="@js($groupHays).some(h => hit(h))">
                <div class="v3-label v3-gap-sm">{{ $group['label'] }}</div>
                <div class="v3-dir">
                    @foreach ($group['items'] as $item)
                        @php
                            $url = $item['section'] !== null ? route($item['route'], $item['section']) : route($item['route']);
                            $hay = strtolower($item['label'] . ' ' . $item['line']);
                        @endphp
                        <a href="{{ $url }}" class="v3-dir__card" x-show="hit(@js($hay))">
                            <span class="v3-dir__ico"><x-admin3.icon :name="$item['icon']" /></span>
                            <span>
                                <span class="v3-dir__name">{{ $item['label'] }}</span>
                                <span class="v3-dir__line v3-block">{{ $item['line'] }}</span>
                            </span>
                            <span class="v3-dir__go" aria-hidden="true"><x-admin3.icon name="chevron" /></span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="v3-empty v3-gap-lg" x-show="q && !@js($v3AllHays).some(h => hit(h))" x-cloak>
            <p class="v3-empty__title">No setting matches</p>
            <p class="v3-empty__line">Nothing here is named that. Try the section names on the left.</p>
        </div>
    </div>
</x-admin3.settings-shell>
