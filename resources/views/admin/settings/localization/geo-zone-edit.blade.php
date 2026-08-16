<x-admin.settings-shell :title="'Edit Geo Zone: ' . $geoZone->name">
        <div class="max-w-4xl space-y-6" x-data="geoZoneEditor()">
            {{-- General --}}
            <x-admin.card title="General">
                <form method="POST" action="{{ route('admin.localization.update-geo-zone', $geoZone) }}" data-v-form class="space-y-4">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <x-admin.field name="name" label="Name" required>
                                <x-admin.input name="name" required :value="$geoZone->name" />
                            </x-admin.field>
                        </div>
                        <x-admin.field name="sort_order" label="Sort Order">
                            <x-admin.input name="sort_order" type="number" min="0" :value="$geoZone->sort_order" />
                        </x-admin.field>
                    </div>
                    <x-admin.field name="description" label="Description">
                        <x-admin.input name="description" :value="$geoZone->description" />
                    </x-admin.field>
                    <x-admin.toggle name="is_active" label="Enabled" :checked="$geoZone->is_active" />
                    <x-admin.form-actions>
                        <x-admin.button variant="primary" type="submit">Save zone</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>

            {{-- Entries --}}
            <x-admin.card title="Zone Entries">
                <x-slot:headerActions>
                    <x-admin.button type="button" size="sm" @click="addEntry()">
                        <x-admin.icon name="plus" class="w-3.5 h-3.5" /> Add entry
                    </x-admin.button>
                </x-slot:headerActions>

                <form method="POST" action="{{ route('admin.localization.sync-geo-zone-entries', $geoZone) }}" data-v-form>
                    @csrf

                    {{-- Entries table --}}
                    <div x-show="entries.length > 0" class="mb-4">
                        <x-admin.table>
                            <x-slot:head>
                                <x-admin.table.th>Country</x-admin.table.th>
                                <x-admin.table.th>Zone / State</x-admin.table.th>
                                <x-admin.table.th class="v-col-act"><span class="v-sr">Remove</span></x-admin.table.th>
                            </x-slot:head>
                            <template x-for="(entry, idx) in entries" :key="idx">
                                <tr class="v-row">
                                    <td class="px-4 py-2.5">
                                        <input type="hidden" :name="'entries[' + idx + '][country_id]'" :value="entry.country_id || ''">
                                        <input type="hidden" :name="'entries[' + idx + '][zone_id]'" :value="entry.zone_id || ''">

                                        <select @change="onCountryChange(idx, $event.target.value)" aria-label="Country"
                                            class="v-control h-[var(--control-h-sm)] w-full rounded-v-md border bg-v-surface px-2 text-[13px] text-v-text">
                                            <option value="">Select country...</option>
                                            @foreach ($countries as $c)
                                                <option value="{{ $c->id }}" :selected="entry.country_id == {{ $c->id }}">{{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <select @change="entry.zone_id = $event.target.value || null" aria-label="Zone or state"
                                            :disabled="!entry.zones || entry.zones.length === 0"
                                            class="v-control h-[var(--control-h-sm)] w-full rounded-v-md border bg-v-surface px-2 text-[13px] text-v-text">
                                            <option value="">All zones</option>
                                            <template x-for="z in (entry.zones || [])" :key="z.id">
                                                <option :value="z.id" :selected="entry.zone_id == z.id" x-text="z.name + (z.code ? ' (' + z.code + ')' : '')"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="v-col-act">
                                        <div class="v-rowactions v-rowactions--end">
                                            <x-admin.button type="button" size="sm" variant="ghost" class="v-rowactions__danger"
                                                @click="removeEntry(idx)" aria-label="Remove entry">
                                                <x-admin.icon name="trash" class="w-3.5 h-3.5" />
                                            </x-admin.button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </x-admin.table>
                    </div>

                    {{-- Empty state --}}
                    <div x-show="entries.length === 0" class="mb-4">
                        <x-admin.empty-state title="No entries yet" description="An entry is one country, optionally narrowed to one of its zones." />
                    </div>

                    {{-- Always visible: removing the LAST entry must still be
                         saveable, and a hidden save bar made that impossible. --}}
                    <x-admin.form-actions>
                        <x-admin.button variant="primary" type="submit">Save entries</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>
        </div>

        @push('scripts')
        <script>
        function geoZoneEditor() {
            return {
                entries: @json($entries),

                init() {
                    // Load zones for entries that already have a country
                    this.entries.forEach(function(entry, idx) {
                        if (entry.country_id) {
                            this.loadZones(idx, entry.country_id, entry.zone_id);
                        }
                    }.bind(this));
                },

                addEntry() {
                    this.entries.push({
                        country_id: null,
                        zone_id: null,
                        zones: [],
                        label: '',
                    });
                },

                removeEntry(idx) {
                    this.entries.splice(idx, 1);
                },

                async onCountryChange(idx, countryId) {
                    this.entries[idx].country_id = countryId || null;
                    this.entries[idx].zone_id = null;
                    this.entries[idx].zones = [];

                    if (!countryId) return;

                    await this.loadZones(idx, countryId, null);
                },

                async loadZones(idx, countryId, preselectedZoneId) {
                    try {
                        if (!countryId) return;

                        const res = await fetch(`{{ route('admin.address.zones') }}?country_id=${countryId}`);
                        const zones = await res.json();
                        this.entries[idx].zones = zones;

                        // Restore preselected zone
                        if (preselectedZoneId) {
                            this.$nextTick(() => {
                                this.entries[idx].zone_id = preselectedZoneId;
                            });
                        }
                    } catch (e) {
                        // Silently fail — zone dropdown stays empty
                    }
                },
            };
        }
        </script>
        @endpush
</x-admin.settings-shell>
