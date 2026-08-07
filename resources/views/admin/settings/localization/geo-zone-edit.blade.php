<x-admin.settings-shell :title="'Edit Geo Zone: ' . $geoZone->name" :wide="true">
        <div class="max-w-4xl space-y-6" x-data="geoZoneEditor()">
            {{-- General --}}
            <x-admin.card title="General">
                <form method="POST" action="{{ route('admin.localization.update-geo-zone', $geoZone) }}" data-v-form class="space-y-4">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <x-admin.field name="name" label="Name">
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
                    <div class="flex items-center gap-3">
                        <x-admin.toggle name="is_active" :checked="$geoZone->is_active" />
                        <label for="is_active" class="text-[13px] text-v-text cursor-pointer">Enabled</label>
                        <x-admin.button type="submit" class="ml-auto">Update</x-admin.button>
                    </div>
                </form>
            </x-admin.card>

            {{-- Entries --}}
            <x-admin.card title="Zone Entries" description="Define which geographic areas belong to this zone. Pick a country, then optionally narrow to a specific state/zone.">
                <x-slot:headerActions>
                    <x-admin.button type="button" size="sm" @click="addEntry()">
                        <x-admin.icon name="plus" class="w-3.5 h-3.5" /> Add Entry
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
                                <x-admin.table.th align="right"></x-admin.table.th>
                            </x-slot:head>
                            <template x-for="(entry, idx) in entries" :key="idx">
                                <tr class="v-row">
                                    <td class="px-4 py-2.5">
                                        <input type="hidden" :name="'entries[' + idx + '][country_id]'" :value="entry.country_id || ''">
                                        <input type="hidden" :name="'entries[' + idx + '][zone_id]'" :value="entry.zone_id || ''">

                                        <select @change="onCountryChange(idx, $event.target.value)"
                                            class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                            <option value="">Select country...</option>
                                            @foreach ($countries as $c)
                                                <option value="{{ $c->id }}" :selected="entry.country_id == {{ $c->id }}">{{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <select @change="entry.zone_id = $event.target.value || null"
                                            :disabled="!entry.zones || entry.zones.length === 0"
                                            class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md disabled:bg-v-sunken disabled:text-v-muted">
                                            <option value="">All zones</option>
                                            <template x-for="z in (entry.zones || [])" :key="z.id">
                                                <option :value="z.id" :selected="entry.zone_id == z.id" x-text="z.name + (z.code ? ' (' + z.code + ')' : '')"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <button type="button" @click="removeEntry(idx)"
                                            class="text-v-muted hover:text-v-danger transition-colors" title="Remove">
                                            <x-admin.icon name="trash" class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </x-admin.table>
                    </div>

                    {{-- Empty state --}}
                    <div x-show="entries.length === 0" class="mb-4">
                        <x-admin.empty-state icon="media" title="No entries yet" description="Click &ldquo;Add Entry&rdquo; to define which areas belong to this zone" />
                    </div>

                    <x-admin.button type="submit" x-show="entries.length > 0">Save Entries</x-admin.button>
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
