<x-admin3.settings-shell title="Localization" active="localization">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Localization</h1>
            <p class="v3-head__standing">The countries and zones the store ships to, and the geo zones that scope rates and rules.</p>
        </div>
    </div>

    <div x-data="{ tab: @js($tab),
            pick(t) { this.tab = t; const u = new URL(window.location); u.searchParams.set('tab', t); history.replaceState(null, '', u); } }">
        <div class="v3-tabs" role="tablist">
            <button type="button" class="v3-tab" role="tab" :aria-selected="tab === 'countries' ? 'true' : 'false'" @click="pick('countries')">Countries</button>
            <button type="button" class="v3-tab" role="tab" :aria-selected="tab === 'zones' ? 'true' : 'false'" @click="pick('zones')">Zones</button>
            <button type="button" class="v3-tab" role="tab" :aria-selected="tab === 'geo-zones' ? 'true' : 'false'" @click="pick('geo-zones')">Geo zones</button>
        </div>

        {{-- 250 rows: rows stay plain HTML and one shared editor panel
             serves the whole table. Per-row x-if edit templates at this
             scale stall Alpine's boot (thousands of template clones). --}}
        <div x-show="tab === 'countries'" class="v3-settle"
             x-data="{ q: '', editing: false, edit: { url: '', name: '', iso2: '', iso3: '', phone: '', sort: 0 }, enabled: {{ $countries->where('is_active', true)->count() }},
                 async flip(url, row) {
                     if (row.busy) return; row.busy = true;
                     const r = await v3Post(url);
                     if (r.ok) { row.active = !row.active; this.enabled += row.active ? 1 : -1; }
                     row.busy = false;
                 } }">
            <x-admin3.panel x-show="editing" x-cloak title="Edit country">
                <form method="POST" :action="edit.url">
                    @csrf @method('PUT')
                    <div class="v3-rowedit__line">
                        <input type="text" name="name" x-model="edit.name" required aria-label="Country name" class="v3-control">
                        <input type="text" name="iso2" x-model="edit.iso2" required maxlength="2" placeholder="US" aria-label="ISO2" class="v3-control v3-mono v3-w-sym">
                        <input type="text" name="iso3" x-model="edit.iso3" required maxlength="3" placeholder="USA" aria-label="ISO3" class="v3-control v3-mono v3-w-sym">
                        <input type="text" name="phone_code" x-model="edit.phone" placeholder="+1" aria-label="Phone code" class="v3-control v3-mono v3-w-sym">
                        <input type="number" name="sort_order" x-model="edit.sort" aria-label="Sort order" class="v3-control v3-mono v3-w-sort">
                        <button type="submit" class="v3-btn v3-btn--primary v3-btn--sm">Save</button>
                        <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="editing = false">Cancel</button>
                    </div>
                </form>
            </x-admin3.panel>

            <x-admin3.panel title="Countries" flush>
                <x-slot:acts>
                    <span class="v3-cell-quiet"><span class="v3-mono" x-text="enabled"></span> of {{ $countries->count() }} enabled</span>
                    <input type="search" x-model="q" placeholder="Search countries…" aria-label="Search countries" class="v3-control v3-w-search">
                </x-slot:acts>
                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr>
                                <th>Country</th><th>ISO2</th><th>ISO3</th><th>Phone</th><th>Sort</th><th>Zones</th><th>Enabled</th>
                                <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($countries as $country)
                                @php
                                    $countryEdit = [
                                        'url' => route('admin.localization.update-country', $country),
                                        'name' => $country->name,
                                        'iso2' => $country->iso2,
                                        'iso3' => $country->iso3,
                                        'phone' => $country->phone_code,
                                        'sort' => $country->sort_order,
                                    ];
                                @endphp
                                <tr x-data="{ active: @js((bool) $country->is_active), busy: false }"
                                    :class="{ 'v3-row-dim': !active }"
                                    x-show="!q || @js(mb_strtolower($country->name)).includes(q.toLowerCase()) || @js(strtolower($country->iso2)).includes(q.toLowerCase())">
                                    <td>{{ $country->name }}</td>
                                    <td class="v3-mono">{{ $country->iso2 }}</td>
                                    <td class="v3-mono">{{ $country->iso3 }}</td>
                                    <td class="v3-mono">{{ $country->phone_code ? '+' . $country->phone_code : '-' }}</td>
                                    <td class="v3-mono">{{ $country->sort_order }}</td>
                                    <td>{{ $country->zones_count }}</td>
                                    <td>
                                        <button type="button" class="v3-toggle v3-toggle--btn" role="switch"
                                                :aria-checked="active ? 'true' : 'false'" :disabled="busy"
                                                aria-label="{{ $country->name }} enabled"
                                                @click="flip(@js(route('admin.localization.toggle-country', $country)), $data)">
                                            <span class="v3-toggle__track" aria-hidden="true"></span>
                                        </button>
                                    </td>
                                    <td class="v3-cell-act">
                                        <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm"
                                                @click="edit = @js($countryEdit); editing = true; window.scrollTo({top: 0, behavior: 'smooth'})">Edit</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin3.panel>
        </div>

        <div x-show="tab === 'zones'" x-cloak class="v3-settle" x-data="{ editing: false, edit: { url: '', name: '', code: '', active: true } }">
            <x-admin3.panel x-show="editing" x-cloak title="Edit zone">
                <form method="POST" :action="edit.url">
                    @csrf @method('PUT')
                    <div class="v3-rowedit__line">
                        <input type="text" name="name" x-model="edit.name" required aria-label="Zone name" class="v3-control">
                        <input type="text" name="code" x-model="edit.code" placeholder="Code" aria-label="Zone code" class="v3-control v3-mono v3-w-name">
                        <input type="hidden" name="is_active" :value="edit.active ? '1' : '0'">
                        <button type="submit" class="v3-btn v3-btn--primary v3-btn--sm">Save</button>
                        <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="editing = false">Cancel</button>
                    </div>
                </form>
            </x-admin3.panel>

            <x-admin3.panel :title="'Zones (' . $zones->count() . ')'" flush>
                <x-slot:acts>
                    <form method="GET">
                        <input type="hidden" name="tab" value="zones">
                        <select name="country" aria-label="Country whose zones are listed" onchange="this.form.submit()" class="v3-control">
                            @foreach ($countryList as $code => $name)
                                <option value="{{ $code }}" @selected($selectedCountry === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </form>
                </x-slot:acts>
                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr>
                                <th>Zone</th><th>Code</th><th>Enabled</th>
                                <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($zones as $zone)
                                @php
                                    $zoneEdit = [
                                        'url' => route('admin.localization.update-zone', $zone),
                                        'name' => $zone->name,
                                        'code' => $zone->code,
                                        'active' => (bool) $zone->is_active,
                                    ];
                                @endphp
                                <tr x-data="{ active: @js((bool) $zone->is_active), busy: false }" :class="{ 'v3-row-dim': !active }">
                                    <td>{{ $zone->name }}</td>
                                    <td class="v3-mono">{{ $zone->code ?? '-' }}</td>
                                    <td>
                                        <button type="button" class="v3-toggle v3-toggle--btn" role="switch"
                                                :aria-checked="active ? 'true' : 'false'" :disabled="busy"
                                                aria-label="{{ $zone->name }} enabled"
                                                @click="busy = true; v3Post(@js(route('admin.localization.toggle-zone', $zone))).then(r => { if (r.ok) active = !active; busy = false; })">
                                            <span class="v3-toggle__track" aria-hidden="true"></span>
                                        </button>
                                    </td>
                                    <td class="v3-cell-act">
                                        <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm"
                                                @click="edit = @js($zoneEdit); editing = true; window.scrollTo({top: 0, behavior: 'smooth'})">Edit</button>
                                        <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-zone-{{ $zone->id }}')">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="v3-empty"><p class="v3-empty__title">No zones for this country</p></div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin3.panel>

            <x-admin3.panel title="Add zone">
                <form method="POST" action="{{ route('admin.localization.store-zone') }}" class="v3-addrow v3-addrow--3">
                    @csrf
                    <input type="hidden" name="country_id" value="{{ $selectedCountry }}">
                    <x-admin3.field name="name" label="Name" required>
                        <input type="text" name="name" required class="v3-control">
                    </x-admin3.field>
                    <x-admin3.field name="code" label="Code">
                        <input type="text" name="code" class="v3-control v3-mono">
                    </x-admin3.field>
                    <button type="submit" class="v3-btn v3-btn--primary">Add zone</button>
                </form>
            </x-admin3.panel>
        </div>

        <div x-show="tab === 'geo-zones'" x-cloak class="v3-settle">
            <x-admin3.panel title="Geo zones" flush>
                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr>
                                <th>Name</th><th>Description</th><th>Entries</th><th>Sort</th><th>Enabled</th>
                                <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($geoZones as $gz)
                                <tr x-data="{ active: @js((bool) $gz->is_active), busy: false }" :class="{ 'v3-row-dim': !active }">
                                    <td>{{ $gz->name }}</td>
                                    <td class="v3-cell-quiet">{{ $gz->description ?? '-' }}</td>
                                    <td>{{ $gz->entries_count }}</td>
                                    <td class="v3-mono">{{ $gz->sort_order }}</td>
                                    <td>
                                        <button type="button" class="v3-toggle v3-toggle--btn" role="switch"
                                                :aria-checked="active ? 'true' : 'false'" :disabled="busy"
                                                aria-label="{{ $gz->name }} enabled"
                                                @click="busy = true; v3Post(@js(route('admin.localization.toggle-geo-zone', $gz))).then(r => { if (r.ok) active = !active; busy = false; })">
                                            <span class="v3-toggle__track" aria-hidden="true"></span>
                                        </button>
                                    </td>
                                    <td class="v3-cell-act">
                                        <a href="{{ route('admin.localization.edit-geo-zone', $gz) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                        <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-geo-zone-{{ $gz->id }}')">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="v3-empty">
                                            <p class="v3-empty__title">No geo zones yet</p>
                                            <p class="v3-empty__line">Shipping rates and payment rules scope themselves to geo zones.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin3.panel>

            <x-admin3.panel title="Add geo zone">
                <form method="POST" action="{{ route('admin.localization.store-geo-zone') }}" class="v3-addrow v3-addrow--3">
                    @csrf
                    <x-admin3.field name="name" label="Name" required>
                        <input type="text" name="name" required class="v3-control">
                    </x-admin3.field>
                    <x-admin3.field name="description" label="Description">
                        <input type="text" name="description" class="v3-control">
                    </x-admin3.field>
                    <button type="submit" class="v3-btn v3-btn--primary">Add geo zone</button>
                </form>
            </x-admin3.panel>
        </div>
    </div>

    @foreach ($zones as $zone)
        <x-admin3.confirm name="delete-zone-{{ $zone->id }}" title="Delete {{ $zone->name }}?"
            message="Addresses already using it keep their text; new addresses lose the option. This cannot be undone."
            confirm="Delete" :action="route('admin.localization.destroy-zone', $zone)" method="DELETE" />
    @endforeach

    @foreach ($geoZones as $gz)
        <x-admin3.confirm name="delete-geo-zone-{{ $gz->id }}" title="Delete {{ $gz->name }}?"
            message="Shipping rates linked to it lose their zone reference. This cannot be undone."
            confirm="Delete" :action="route('admin.localization.destroy-geo-zone', $gz)" method="DELETE" />
    @endforeach
</x-admin3.settings-shell>
