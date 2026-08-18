<x-admin.settings-shell :title="'Edit geo zone: ' . $geoZone->name" active="localization">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.localization.index') }}?tab=geo-zones">Localization</a>
                <span aria-hidden="true">/</span>
                <span>Geo zones</span>
            </div>
            <h1 class="v-head__title">{{ $geoZone->name }}</h1>
            <p class="v-head__standing">One save commits the whole page: the zone and its entries together.</p>
        </div>
    </div>

    {{-- ONE form. Editing the name and adding entries are the same piece
         of work; splitting them across two saves is how this page lost
         merchants' edits in v2. --}}
    <x-admin.form :action="route('admin.localization.update-geo-zone', $geoZone)" method="PUT" class="v-settle-host">
      <div x-data="vGeoZone(@js(old('entries') !== null ? collect(old('entries'))->values() : $entries))" class="v-settle">

        <x-admin.panel title="Zone">
            <div class="v-grid v-grid--3">
                <div class="v-span2">
                    <x-admin.field name="name" label="Name" required>
                        <input type="text" name="name" required class="v-control" value="{{ old('name', $geoZone->name) }}">
                    </x-admin.field>
                </div>
                <x-admin.field name="sort_order" label="Sort order">
                    <input type="number" name="sort_order" min="0" class="v-control" value="{{ old('sort_order', $geoZone->sort_order) }}">
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.field name="description" label="Description">
                    <input type="text" name="description" class="v-control" value="{{ old('description', $geoZone->description) }}">
                </x-admin.field>
            </div>
            <div class="v-gap">
                <label class="v-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $geoZone->is_active))>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    Enabled
                </label>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Entries" note="An entry is one country, optionally narrowed to one of its zones.">
            <x-slot:acts>
                <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="add(); touch()">
                    <x-admin.icon name="plus" /> Add entry
                </button>
            </x-slot:acts>

            <div class="v-rows" x-show="entries.length > 0">
                <template x-for="(entry, idx) in entries" :key="entry.uid">
                    <div class="v-row">
                        <input type="hidden" :name="'entries[' + idx + '][country_id]'" :value="entry.country_id || ''">
                        <input type="hidden" :name="'entries[' + idx + '][zone_id]'" :value="entry.zone_id || ''">

                        <select class="v-control" @change="setCountry(idx, $event.target.value); touch()" aria-label="Country">
                            <option value="">Select country…</option>
                            @foreach ($countries as $c)
                                <option value="{{ $c->id }}" :selected="entry.country_id == {{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>

                        <select class="v-control" aria-label="Zone or state"
                                :disabled="!entry.zones || entry.zones.length === 0"
                                @change="entry.zone_id = $event.target.value || null; touch()">
                            <option value="">All zones</option>
                            <template x-for="z in (entry.zones || [])" :key="z.id">
                                <option :value="z.id" :selected="entry.zone_id == z.id" x-text="z.name"></option>
                            </template>
                        </select>

                        <button type="button" class="v-row__x" @click="remove(idx); touch()" aria-label="Remove entry">
                            <x-admin.icon name="trash" />
                        </button>
                    </div>
                </template>
            </div>

            <div class="v-empty" x-show="entries.length === 0">
                <p class="v-empty__title">No entries yet</p>
                <p class="v-empty__line">This zone matches no address until an entry names a country.</p>
            </div>
        </x-admin.panel>
      </div>
    </x-admin.form>

    @push('scripts')
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('vGeoZone', (initial) => ({
            entries: initial.map((e, i) => ({ ...e, uid: 'e' + i, zones: [] })),
            _uid: initial.length,

            init() {
                this.entries.forEach((entry, idx) => {
                    if (entry.country_id) this.loadZones(idx, entry.country_id, entry.zone_id);
                });
            },

            add() {
                this.entries.push({ uid: 'e' + (++this._uid), country_id: null, zone_id: null, zones: [] });
            },

            remove(idx) {
                this.entries.splice(idx, 1);
            },

            async setCountry(idx, countryId) {
                this.entries[idx].country_id = countryId || null;
                this.entries[idx].zone_id = null;
                this.entries[idx].zones = [];
                if (countryId) await this.loadZones(idx, countryId, null);
            },

            async loadZones(idx, countryId, preselect) {
                try {
                    const res = await fetch(`{{ route('admin.address.zones') }}?country_id=${countryId}`);
                    if (!res.ok) throw new Error(res.status);
                    this.entries[idx].zones = await res.json();
                    if (preselect) this.$nextTick(() => { this.entries[idx].zone_id = preselect; });
                } catch (e) {
                    // Say so instead of silently leaving an empty select.
                    Alpine.store('toasts').push('Could not load zones for that country. Check the connection and retry.', 'bad');
                }
            },
        }));
    });
    </script>
    @endpush
</x-admin.settings-shell>
