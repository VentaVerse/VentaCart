<x-admin3.settings-shell title="Settings">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Settings</h1>
            <p class="v3-head__standing">Everything that configures {{ \App\Support\StoreIdentity::name() }}, grouped by what it governs.</p>
        </div>
    </div>

    <div class="v3-settle" x-data="{ q: '' }">
        <x-admin3.field name="q" label="Find a setting">
            <input type="search" class="v3-control" x-model="q" placeholder="Search settings…" style="max-width: 380px">
        </x-admin3.field>

        @foreach (collect(config('admin3-settings-nav')) as $group)
            @php
                $items = array_values(array_filter($group['items'], fn ($i) => \Illuminate\Support\Facades\Route::has($i['route'])));
                if ($items === []) continue;
            @endphp
            <div style="margin-top: 26px">
                <div class="v3-label" style="margin-bottom: 10px">{{ $group['label'] }}</div>
                <div class="v3-dir">
                    @foreach ($items as $item)
                        @php
                            $url = $item['section'] !== null ? route($item['route'], $item['section']) : route($item['route']);
                            $hay = strtolower($item['label'] . ' ' . $item['line']);
                        @endphp
                        <a href="{{ $url }}" class="v3-dir__card" x-show="!q || @js($hay).includes(q.toLowerCase())">
                            <span class="v3-dir__ico"><x-admin3.icon :name="$item['icon']" /></span>
                            <span>
                                <span class="v3-dir__name">{{ $item['label'] }}</span>
                                <span class="v3-dir__line" style="display:block">{{ $item['line'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-admin3.settings-shell>
