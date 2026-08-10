<x-admin.settings-shell title="Localization">
        <div x-data="localizationPage()">
            <x-admin.tabs default="{{ $tab }}" param="tab" :names="['countries', 'zones', 'geo-zones']">
                <x-slot:nav>
                    <x-admin.tabs.tab name="countries">Countries</x-admin.tabs.tab>
                    <x-admin.tabs.tab name="zones">Zones</x-admin.tabs.tab>
                    <x-admin.tabs.tab name="geo-zones">Geo Zones</x-admin.tabs.tab>
                </x-slot:nav>

                {{-- ==================== COUNTRIES ==================== --}}
                <x-admin.tabs.panel name="countries">
                    <x-admin.card>
                        <div class="v-toolbar">
                            <div class="v-toolbar__row">
                                <span class="text-[13px] text-v-secondary">
                                    <span class="v-num" x-text="enabledCount">{{ $countries->where('is_active', true)->count() }}</span>
                                    of <span class="v-num">{{ $countries->count() }}</span> countries enabled
                                </span>
                                <div class="v-toolbar__actions">
                                    <input type="search" x-model="countrySearch" placeholder="Search countries&hellip;" aria-label="Search countries"
                                        class="v-control h-[var(--control-h)] w-64 rounded-v-md border bg-v-surface px-2.5 text-[13px] text-v-text placeholder:text-v-muted">
                                </div>
                            </div>
                        </div>

                        <x-admin.table>
                            <x-slot:head>
                                <x-admin.table.th>Country</x-admin.table.th>
                                <x-admin.table.th>ISO2</x-admin.table.th>
                                <x-admin.table.th>ISO3</x-admin.table.th>
                                <x-admin.table.th>Phone</x-admin.table.th>
                                <x-admin.table.th align="right">Sort</x-admin.table.th>
                                <x-admin.table.th>Zones</x-admin.table.th>
                                <x-admin.table.th align="right">Enabled</x-admin.table.th>
                                <x-admin.table.th align="right">Actions</x-admin.table.th>
                            </x-slot:head>
                            @foreach ($countries as $country)
                                <tr class="v-row group"
                                    x-show="!countrySearch || '{{ strtolower(addslashes($country->name)) }}'.includes(countrySearch.toLowerCase()) || '{{ $country->iso2 }}'.toLowerCase().includes(countrySearch.toLowerCase())"
                                    x-data="{ editing: false, active: {{ $country->is_active ? 'true' : 'false' }}, toggling: false }">

                                    {{-- View mode --}}
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-[13px] text-v-text">{{ $country->name }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-xs text-v-secondary v-num">{{ $country->iso2 }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-xs text-v-secondary v-num">{{ $country->iso3 }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-xs text-v-secondary">{{ $country->phone_code ? '+' . $country->phone_code : '-' }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-xs text-v-muted text-right v-num">{{ $country->sort_order }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-[13px] text-v-secondary">{{ $country->zones_count }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-right">
                                            <x-admin.switch :checked="null" label="{{ $country->name }} enabled"
                                                @click="toggleCountry({{ $country->id }}, $data)"
                                                x-bind:disabled="toggling" x-bind:aria-checked="active" />
                                        </td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-4 py-2.5 text-right">
                                            <div class="v-rowactions v-rowactions--end">
                                                <x-admin.button type="button" size="sm" variant="ghost" @click="editing = true">Edit</x-admin.button>
                                            </div>
                                        </td>
                                    </template>

                                    {{-- Edit mode --}}
                                    <template x-if="editing">
                                        <td colspan="8" class="px-4 py-2.5">
                                            <form method="POST" action="{{ route('admin.localization.update-country', $country) }}" data-v-form class="flex items-center gap-2">
                                                @csrf @method('PUT')
                                                <input type="text" name="name" value="{{ $country->name }}" required placeholder="Country name"
                                                    class="v-control h-[var(--control-h-sm)] flex-1 px-2 text-[13px] bg-v-surface text-v-text border rounded-v-md">
                                                <input type="text" name="iso2" value="{{ $country->iso2 }}" required maxlength="2" placeholder="US"
                                                    class="v-control h-[var(--control-h-sm)] w-16 px-2 text-[13px] text-center uppercase bg-v-surface text-v-text border rounded-v-md">
                                                <input type="text" name="iso3" value="{{ $country->iso3 }}" required maxlength="3" placeholder="USA"
                                                    class="v-control h-[var(--control-h-sm)] w-16 px-2 text-[13px] text-center uppercase bg-v-surface text-v-text border rounded-v-md">
                                                <div class="relative">
                                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-v-muted">+</span>
                                                    <input type="text" name="phone_code" value="{{ $country->phone_code }}" placeholder="1"
                                                        class="v-control h-[var(--control-h-sm)] w-20 pl-5 pr-2 text-[13px] bg-v-surface text-v-text border rounded-v-md">
                                                </div>
                                                <input type="number" name="sort_order" value="{{ $country->sort_order }}" title="Sort order"
                                                    class="v-control h-[var(--control-h-sm)] w-16 px-2 text-[13px] text-center bg-v-surface text-v-text border rounded-v-md">
                                                <x-admin.button type="submit" size="sm">Save</x-admin.button>
                                                <x-admin.button type="button" variant="secondary" size="sm" @click="editing = false">Cancel</x-admin.button>
                                            </form>
                                        </td>
                                    </template>
                                </tr>
                            @endforeach
                        </x-admin.table>
                    </x-admin.card>
                </x-admin.tabs.panel>

                {{-- ==================== ZONES ==================== --}}
                <x-admin.tabs.panel name="zones">
                    <div class="space-y-5">
                        <x-admin.card>
                            <div class="flex items-center justify-between">
                                <form method="GET" class="flex items-center gap-3">
                                    <input type="hidden" name="tab" value="zones">
                                    <select name="country" aria-label="Country whose zones are listed" onchange="this.form.submit()"
                                        class="v-control h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border rounded-v-md">
                                        @foreach ($countryList as $code => $name)
                                            <option value="{{ $code }}" {{ $selectedCountry === $code ? 'selected' : '' }}>{{ $name }} ({{ $code }})</option>
                                        @endforeach
                                    </select>
                                </form>
                                <span class="text-[13px] text-v-secondary">{{ $zones->count() }} zones</span>
                            </div>
                        </x-admin.card>

                        <x-admin.card>
                            <x-admin.table>
                                <x-slot:head>
                                    <x-admin.table.th>Zone Name</x-admin.table.th>
                                    <x-admin.table.th>Code</x-admin.table.th>
                                    <x-admin.table.th align="right">Enabled</x-admin.table.th>
                                    <x-admin.table.th align="right">Actions</x-admin.table.th>
                                </x-slot:head>
                                @forelse ($zones as $zone)
                                    <tr class="v-row group" x-data="{ editing: false, active: {{ $zone->is_active ? 'true' : 'false' }}, toggling: false }">
                                        {{-- View mode --}}
                                        <template x-if="!editing">
                                            <td class="px-4 py-2.5 text-[13px] text-v-text">{{ $zone->name }}</td>
                                        </template>
                                        <template x-if="!editing">
                                            <td class="px-4 py-2.5 text-xs text-v-secondary v-num">{{ $zone->code ?? '-' }}</td>
                                        </template>
                                        <template x-if="!editing">
                                            <td class="px-4 py-2.5 text-right">
                                                <x-admin.switch :checked="null" label="{{ $zone->name }} enabled"
                                                    @click="toggleZone({{ $zone->id }}, $data)"
                                                    x-bind:disabled="toggling" x-bind:aria-checked="active" />
                                            </td>
                                        </template>
                                        <template x-if="!editing">
                                            <td class="px-4 py-2.5 text-right text-[13px]">
                                                <div class="v-rowactions v-rowactions--end">
                                                    <x-admin.button type="button" size="sm" variant="ghost" @click="editing = true">Edit</x-admin.button>
                                                    <x-admin.button type="button" size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'delete-zone-{{ $zone->id }}')">Delete</x-admin.button>
                                                </div>
                                            </td>
                                        </template>

                                        {{-- Edit mode --}}
                                        <template x-if="editing">
                                            <td colspan="4" class="px-4 py-2.5">
                                                <form method="POST" action="{{ route('admin.localization.update-zone', $zone) }}" data-v-form class="flex items-center gap-2">
                                                    @csrf @method('PUT')
                                                    <input type="text" name="name" value="{{ $zone->name }}" required
                                                        class="v-control h-[var(--control-h-sm)] flex-1 px-2 text-[13px] bg-v-surface text-v-text border rounded-v-md">
                                                    <input type="text" name="code" value="{{ $zone->code }}" placeholder="Code"
                                                        class="v-control h-[var(--control-h-sm)] w-20 px-2 text-[13px] bg-v-surface text-v-text border rounded-v-md">
                                                    <input type="hidden" name="is_active" :value="active ? '1' : '0'">
                                                    <x-admin.button type="submit" size="sm">Save</x-admin.button>
                                                    <x-admin.button type="button" variant="secondary" size="sm" @click="editing = false">Cancel</x-admin.button>
                                                </form>
                                            </td>
                                        </template>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">
                                            <x-admin.empty-state title="No zones for this country" />
                                        </td>
                                    </tr>
                                @endforelse
                            </x-admin.table>
                        </x-admin.card>

                        <x-admin.card title="Add Zone">
                            <form method="POST" action="{{ route('admin.localization.store-zone') }}" data-v-form class="flex items-end gap-3">
                                @csrf
                                <input type="hidden" name="country_id" value="{{ $selectedCountry }}">
                                <div class="flex-1">
                                    <x-admin.field name="name" label="Name" required>
                                        <x-admin.input name="name" required placeholder="Zone name" />
                                    </x-admin.field>
                                </div>
                                <div class="w-28">
                                    <x-admin.field name="code" label="Code">
                                        <x-admin.input name="code" placeholder="e.g. NY" />
                                    </x-admin.field>
                                </div>
                                <x-admin.button type="submit">Add</x-admin.button>
                            </form>
                        </x-admin.card>
                    </div>
                </x-admin.tabs.panel>

                {{-- ==================== GEO ZONES ==================== --}}
                <x-admin.tabs.panel name="geo-zones">
                    <div class="space-y-5">
                        <x-admin.card>
                            <x-admin.table>
                                <x-slot:head>
                                    <x-admin.table.th>Name</x-admin.table.th>
                                    <x-admin.table.th>Description</x-admin.table.th>
                                    <x-admin.table.th>Entries</x-admin.table.th>
                                    <x-admin.table.th>Order</x-admin.table.th>
                                    <x-admin.table.th align="right">Enabled</x-admin.table.th>
                                    <x-admin.table.th align="right">Actions</x-admin.table.th>
                                </x-slot:head>
                                @forelse ($geoZones as $gz)
                                    <tr class="v-row group" x-data="{ active: {{ $gz->is_active ? 'true' : 'false' }}, toggling: false }">
                                        <td class="px-4 py-2.5 text-[13px] font-medium text-v-text">{{ $gz->name }}</td>
                                        <td class="px-4 py-2.5 text-xs text-v-secondary">{{ $gz->description ?? '-' }}</td>
                                        <td class="px-4 py-2.5 text-[13px] text-v-secondary">{{ $gz->entries_count }}</td>
                                        <td class="px-4 py-2.5 text-[13px] text-v-secondary v-num">{{ $gz->sort_order }}</td>
                                        <td class="px-4 py-2.5 text-right">
                                            <x-admin.switch :checked="null" label="{{ $gz->name }} enabled"
                                                @click="toggleGeoZone({{ $gz->id }}, $data)"
                                                x-bind:disabled="toggling" x-bind:aria-checked="active" />
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-[13px]">
                                            <div class="v-rowactions v-rowactions--end">
                                                <x-admin.button size="sm" variant="ghost" :href="route('admin.localization.edit-geo-zone', $gz)">Edit</x-admin.button>
                                                <x-admin.button type="button" size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'delete-geo-zone-{{ $gz->id }}')">Delete</x-admin.button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <x-admin.empty-state title="No geo zones yet" />
                                        </td>
                                    </tr>
                                @endforelse
                            </x-admin.table>
                        </x-admin.card>

                        <x-admin.card title="Add Geo Zone">
                            <form method="POST" action="{{ route('admin.localization.store-geo-zone') }}" data-v-form class="flex items-end gap-3">
                                @csrf
                                <div class="flex-1">
                                    <x-admin.field name="name" label="Name" required>
                                        <x-admin.input name="name" required placeholder="e.g. Domestic" />
                                    </x-admin.field>
                                </div>
                                <div class="flex-1">
                                    <x-admin.field name="description" label="Description">
                                        <x-admin.input name="description" placeholder="Optional description" />
                                    </x-admin.field>
                                </div>
                                <div class="w-24">
                                    <x-admin.field name="sort_order" label="Order">
                                        <x-admin.input name="sort_order" type="number" value="0" min="0" />
                                    </x-admin.field>
                                </div>
                                <x-admin.button type="submit">Add</x-admin.button>
                            </form>
                        </x-admin.card>
                    </div>
                </x-admin.tabs.panel>
            </x-admin.tabs>
        </div>

        @foreach ($zones as $zone)
            <x-admin.confirm-dialog name="delete-zone-{{ $zone->id }}" title="Delete “{{ $zone->name }}”?"
                message="This cannot be undone." confirm="Delete" :action="route('admin.localization.destroy-zone', $zone)" method="DELETE" />
        @endforeach

        @foreach ($geoZones as $gz)
            <x-admin.confirm-dialog name="delete-geo-zone-{{ $gz->id }}" title="Delete “{{ $gz->name }}”?"
                message="Shipping rates linked to it will lose their zone reference. This cannot be undone."
                confirm="Delete" :action="route('admin.localization.destroy-geo-zone', $gz)" method="DELETE" />
        @endforeach

        @push('scripts')
        <script>
        function localizationPage() {
            return {
                countrySearch: '',
                enabledCount: {{ $countries->where('is_active', true)->count() }},

                async toggleCountry(id, row) {
                    row.toggling = true;
                    try {
                        const res = await fetch(`{{ route('admin.localization.toggle-country', ['country' => '__ID__']) }}`.replace('__ID__', id), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        });
                        const data = await res.json();
                        row.active = data.is_active;
                        this.enabledCount += data.is_active ? 1 : -1;
                        this.$store.toasts.push(data.message, 'success');
                    } catch (e) {
                        this.$store.toasts.push('Failed to toggle.', 'danger');
                    }
                    row.toggling = false;
                },

                async toggleZone(id, row) {
                    row.toggling = true;
                    try {
                        const res = await fetch(`{{ route('admin.localization.toggle-zone', ['zone' => '__ID__']) }}`.replace('__ID__', id), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        });
                        const data = await res.json();
                        row.active = data.is_active;
                        this.$store.toasts.push(data.message, 'success');
                    } catch (e) {
                        this.$store.toasts.push('Failed to toggle.', 'danger');
                    }
                    row.toggling = false;
                },

                async toggleGeoZone(id, row) {
                    row.toggling = true;
                    try {
                        const res = await fetch(`{{ route('admin.localization.toggle-geo-zone', ['geoZone' => '__ID__']) }}`.replace('__ID__', id), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        });
                        const data = await res.json();
                        row.active = data.is_active;
                        this.$store.toasts.push(data.message, 'success');
                    } catch (e) {
                        this.$store.toasts.push('Failed to toggle.', 'danger');
                    }
                    row.toggling = false;
                },
            };
        }
        </script>
        @endpush
</x-admin.settings-shell>
