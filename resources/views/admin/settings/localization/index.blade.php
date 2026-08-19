<x-admin.settings-shell title="Localization" active="localization">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Localization</h1>
            <p class="v-head__standing">The countries and zones the store ships to, and the geo zones that scope rates and rules.</p>
        </div>
    </div>

    <div x-data="{ tab: @js($tab),
            pick(t) { this.tab = t; const u = new URL(window.location); u.searchParams.set('tab', t); history.replaceState(null, '', u); } }">
        <div class="v-tabs" role="tablist">
            <button type="button" class="v-tab" role="tab" :aria-selected="tab === 'countries' ? 'true' : 'false'" @click="pick('countries')">Countries</button>
            <button type="button" class="v-tab" role="tab" :aria-selected="tab === 'zones' ? 'true' : 'false'" @click="pick('zones')">Zones</button>
            <button type="button" class="v-tab" role="tab" :aria-selected="tab === 'geo-zones' ? 'true' : 'false'" @click="pick('geo-zones')">Geo zones</button>
        </div>

        {{-- 250 rows: rows stay plain HTML and one shared editor panel
             serves the whole table. Per-row x-if edit templates at this
             scale stall Alpine's boot (thousands of template clones). --}}
        <div x-show="tab === 'countries'" class="v-settle"
             x-data="{ q: '', editing: false, edit: { url: '', name: '', iso2: '', iso3: '', phone: '', sort: 0 },
                 enabled: {{ $countries->where('is_active', true)->count() }},
                 recount() { this.enabled = document.querySelectorAll('input[name^=\'rows\'][name$=\'[is_active]\'][type=checkbox]:checked').length; } }">
            <x-admin.panel x-show="editing" x-cloak title="Edit country">
                <form method="POST" :action="edit.url">
                    @csrf @method('PUT')
                    <div class="v-rowedit__line">
                        <input type="text" name="name" x-model="edit.name" required aria-label="Country name" class="v-control">
                        <input type="text" name="iso2" x-model="edit.iso2" required maxlength="2" placeholder="US" aria-label="ISO2" class="v-control v-mono v-w-sym">
                        <input type="text" name="iso3" x-model="edit.iso3" required maxlength="3" placeholder="USA" aria-label="ISO3" class="v-control v-mono v-w-sym">
                        <input type="text" name="phone_code" x-model="edit.phone" placeholder="+1" aria-label="Phone code" class="v-control v-mono v-w-sym">
                        <input type="number" name="sort_order" x-model="edit.sort" aria-label="Sort order" class="v-control v-mono v-w-sort">
                        <button type="submit" class="v-btn v-btn--primary v-btn--sm">Save</button>
                        <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="editing = false">Cancel</button>
                    </div>
                </form>
            </x-admin.panel>

            <x-admin.form :header-save="false" :action="route('admin.localization.update-all-countries')" method="PUT">
            <x-admin.panel title="Countries" flush>
                <x-slot:acts>
                    <span class="v-cell-quiet"><span class="v-mono" x-text="enabled"></span> of {{ $countries->count() }} enabled</span>
                    <input type="search" x-model="q" placeholder="Search countries…" aria-label="Search countries" class="v-control v-w-search">
                </x-slot:acts>
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th>Country</th><th>ISO2</th><th>ISO3</th><th>Phone</th><th>Sort</th><th>Zones</th><th>Enabled</th>
                                <th class="v-cell-act"><span class="v-sr">Actions</span></th>
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
                                    :class="{ 'v-row-dim': !active }"
                                    x-show="!q || @js(mb_strtolower($country->name)).includes(q.toLowerCase()) || @js(strtolower($country->iso2)).includes(q.toLowerCase())">
                                    <td>{{ $country->name }}</td>
                                    <td class="v-mono">{{ $country->iso2 }}</td>
                                    <td class="v-mono">{{ $country->iso3 }}</td>
                                    <td class="v-mono">{{ $country->phone_code ? '+' . $country->phone_code : '-' }}</td>
                                    <td class="v-mono">{{ $country->sort_order }}</td>
                                    <td>{{ $country->zones_count }}</td>
                                    <td>
                                        <label class="v-toggle">
                                            <input type="hidden" name="rows[{{ $country->id }}][is_active]" value="0">
                                            <input type="checkbox" name="rows[{{ $country->id }}][is_active]" value="1"
                                                   x-model="active" @change="recount()"
                                                   aria-label="{{ $country->name }} enabled">
                                            <span class="v-toggle__track" aria-hidden="true"></span>
                                        </label>
                                    </td>
                                    <td class="v-cell-act">
                                        <button type="button" class="v-btn v-btn--ghost v-btn--sm"
                                                @click="edit = @js($countryEdit); editing = true; window.scrollTo({top: 0, behavior: 'smooth'})">Edit</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.panel>
            </x-admin.form>
        </div>

        <div x-show="tab === 'zones'" x-cloak class="v-settle" x-data="{ editing: false, edit: { url: '', name: '', code: '', active: true } }">
            <x-admin.panel x-show="editing" x-cloak title="Edit zone">
                <form method="POST" :action="edit.url">
                    @csrf @method('PUT')
                    <div class="v-rowedit__line">
                        <input type="text" name="name" x-model="edit.name" required aria-label="Zone name" class="v-control">
                        <input type="text" name="code" x-model="edit.code" placeholder="Code" aria-label="Zone code" class="v-control v-mono v-w-name">
                        <input type="hidden" name="is_active" :value="edit.active ? '1' : '0'">
                        <button type="submit" class="v-btn v-btn--primary v-btn--sm">Save</button>
                        <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="editing = false">Cancel</button>
                    </div>
                </form>
            </x-admin.panel>

            <x-admin.form :header-save="false" :action="route('admin.localization.update-all-zones')" method="PUT">
            <input type="hidden" name="country" value="{{ $selectedCountry }}">
            <x-admin.panel :title="'Zones (' . $zones->count() . ')'" flush>
                <x-slot:acts>
                    <select aria-label="Country whose zones are listed"
                            onchange="window.location.href = '{{ route('admin.localization.index') }}?tab=zones&country=' + this.value"
                            class="v-control">
                        @foreach ($countryList as $code => $name)
                            <option value="{{ $code }}" @selected($selectedCountry === $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                </x-slot:acts>
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th>Zone</th><th>Code</th><th>Enabled</th>
                                <th class="v-cell-act"><span class="v-sr">Actions</span></th>
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
                                <tr x-data="{ active: @js((bool) $zone->is_active), busy: false }" :class="{ 'v-row-dim': !active }">
                                    <td>{{ $zone->name }}</td>
                                    <td class="v-mono">{{ $zone->code ?? '-' }}</td>
                                    <td>
                                        <label class="v-toggle">
                                            <input type="hidden" name="rows[{{ $zone->id }}][is_active]" value="0">
                                            <input type="checkbox" name="rows[{{ $zone->id }}][is_active]" value="1"
                                                   x-model="active" aria-label="{{ $zone->name }} enabled">
                                            <span class="v-toggle__track" aria-hidden="true"></span>
                                        </label>
                                    </td>
                                    <td class="v-cell-act">
                                        <button type="button" class="v-btn v-btn--ghost v-btn--sm"
                                                @click="edit = @js($zoneEdit); editing = true; window.scrollTo({top: 0, behavior: 'smooth'})">Edit</button>
                                        <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-zone-{{ $zone->id }}')">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="v-empty"><p class="v-empty__title">No zones for this country</p></div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.panel>
            </x-admin.form>

            <x-admin.panel title="Add zone">
                <form method="POST" action="{{ route('admin.localization.store-zone') }}" class="v-addrow v-addrow--3">
                    @csrf
                    <input type="hidden" name="country_id" value="{{ $selectedCountry }}">
                    <x-admin.field name="name" label="Name" required>
                        <input type="text" name="name" required class="v-control">
                    </x-admin.field>
                    <x-admin.field name="code" label="Code">
                        <input type="text" name="code" class="v-control v-mono">
                    </x-admin.field>
                    <button type="submit" class="v-btn v-btn--primary">Add zone</button>
                </form>
            </x-admin.panel>
        </div>

        <div x-show="tab === 'geo-zones'" x-cloak class="v-settle">
            <x-admin.form :header-save="false" :action="route('admin.localization.update-all-geo-zones')" method="PUT">
            <x-admin.panel title="Geo zones" flush>
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th>Name</th><th>Description</th><th>Entries</th><th>Sort</th><th>Enabled</th>
                                <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($geoZones as $gz)
                                <tr x-data="{ active: @js((bool) $gz->is_active), busy: false }" :class="{ 'v-row-dim': !active }">
                                    <td>{{ $gz->name }}</td>
                                    <td class="v-cell-quiet">{{ $gz->description ?? '-' }}</td>
                                    <td>{{ $gz->entries_count }}</td>
                                    <td class="v-mono">{{ $gz->sort_order }}</td>
                                    <td>
                                        <label class="v-toggle">
                                            <input type="hidden" name="rows[{{ $gz->id }}][is_active]" value="0">
                                            <input type="checkbox" name="rows[{{ $gz->id }}][is_active]" value="1"
                                                   x-model="active" aria-label="{{ $gz->name }} enabled">
                                            <span class="v-toggle__track" aria-hidden="true"></span>
                                        </label>
                                    </td>
                                    <td class="v-cell-act">
                                        <a href="{{ route('admin.localization.edit-geo-zone', $gz) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                        <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-geo-zone-{{ $gz->id }}')">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="v-empty">
                                            <p class="v-empty__title">No geo zones yet</p>
                                            <p class="v-empty__line">Shipping rates and payment rules scope themselves to geo zones.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.panel>
            </x-admin.form>

            <x-admin.panel title="Add geo zone">
                <form method="POST" action="{{ route('admin.localization.store-geo-zone') }}" class="v-addrow v-addrow--3">
                    @csrf
                    <x-admin.field name="name" label="Name" required>
                        <input type="text" name="name" required class="v-control">
                    </x-admin.field>
                    <x-admin.field name="description" label="Description">
                        <input type="text" name="description" class="v-control">
                    </x-admin.field>
                    <button type="submit" class="v-btn v-btn--primary">Add geo zone</button>
                </form>
            </x-admin.panel>
        </div>
    </div>

    @foreach ($zones as $zone)
        <x-admin.confirm name="delete-zone-{{ $zone->id }}" title="Delete {{ $zone->name }}?"
            message="Addresses already using it keep their text; new addresses lose the option. This cannot be undone."
            confirm="Delete" :action="route('admin.localization.destroy-zone', $zone)" method="DELETE" />
    @endforeach

    @foreach ($geoZones as $gz)
        <x-admin.confirm name="delete-geo-zone-{{ $gz->id }}" title="Delete {{ $gz->name }}?"
            message="Shipping rates linked to it lose their zone reference. This cannot be undone."
            confirm="Delete" :action="route('admin.localization.destroy-geo-zone', $gz)" method="DELETE" />
    @endforeach
</x-admin.settings-shell>
