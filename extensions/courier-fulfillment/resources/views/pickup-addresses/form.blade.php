@php
    $editing = $address->exists;
    $title = $editing ? 'Edit Pickup Address' : 'Add Pickup Address';
    $action = $editing
        ? route('admin.fulfillment.pickup-addresses.update', $address)
        : route('admin.fulfillment.pickup-addresses.store');

    // The cascade's starting state. Values come from old() first so a failed
    // validation round trip keeps every dropdown where the operator left it,
    // then from the record, then empty.
    $cascade = [
        'levels' => $levels,
        'values' => collect($levels)
            ->mapWithKeys(fn ($level) => [
                $level['key'] => (string) old($level['key'], $address->{$level['key']} ?? ''),
            ])
            ->all(),
    ];
@endphp
<x-admin.settings-shell :title="$title" active="fulfillment">
    @push('head')
        @vite('extensions/courier-fulfillment/resources/css/fulfillment.css')
    @endpush

    @if (!$country)
        <x-admin.empty-state variant="notice" role="status" :title="$countryMissingMessage">
            <a class="v-notice__btn" href="{{ route('admin.localization.index') }}">Localization</a>
        </x-admin.empty-state>
    @else
        <form method="POST" action="{{ $action }}" data-v-form class="space-y-6"
            x-data="pickupAddressCascade(@js($cascade))">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <x-admin.card title="Address">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-admin.field name="label" label="Name" required class="sm:col-span-2">
                        <x-admin.input name="label" :value="$address->label" required maxlength="100" placeholder="e.g. Main warehouse" />
                    </x-admin.field>

                    {{-- Fixed, and a real foreign key underneath: every courier
                         this module books with collects in one country, so the
                         field states the answer instead of asking. Nothing is
                         posted from here; the country is stamped server-side
                         from the ISO lookup. --}}
                    <x-admin.field name="country_display" label="Country" class="sm:col-span-2"
                        hint="Every courier this module books with collects in {{ $country->name }}.">
                        <p id="country_display" class="v-fixedvalue">{{ $country->name }}</p>
                    </x-admin.field>

                    @foreach ($levels as $level)
                        <x-admin.field :name="$level['key']" :label="$level['label']" :required="$level['required']"
                            x-show="isVisible('{{ $level['key'] }}')"
                            class="{{ $level['key'] === 'barangay' ? 'sm:col-span-2' : '' }}">
                            <x-admin.select :name="$level['key']" x-model="values.{{ $level['key'] }}"
                                x-on:change="onFieldChange('{{ $level['key'] }}')"
                                x-bind:disabled="loading['{{ $level['key'] }}']">
                                <option value="">{{ $level['required'] ? 'Choose' : 'None' }}</option>
                                <template x-for="option in options['{{ $level['key'] }}']" :key="option.id">
                                    <option :value="option.name" x-text="option.name"></option>
                                </template>
                            </x-admin.select>
                        </x-admin.field>
                    @endforeach

                    <x-admin.field name="address_1" label="Address" required class="sm:col-span-2">
                        <x-admin.input name="address_1" :value="$address->address_1" required maxlength="500" placeholder="Unit / street / building" />
                    </x-admin.field>

                    <x-admin.field name="postcode" label="Postal code" required>
                        <x-admin.input name="postcode" :value="$address->postcode" required maxlength="32" inputmode="numeric" />
                    </x-admin.field>
                </div>
            </x-admin.card>

            <x-admin.card title="Contact">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-admin.field name="contact_name" label="Contact name" required>
                        <x-admin.input name="contact_name" :value="$address->contact_name" required maxlength="150" />
                    </x-admin.field>

                    <x-admin.field name="contact_phone" label="Mobile number" required>
                        <x-admin.input name="contact_phone" :value="$address->contact_phone" required maxlength="30" inputmode="tel" placeholder="09171234567" />
                    </x-admin.field>
                </div>
            </x-admin.card>

            <x-admin.card title="Default">
                {{-- With one address on file there is no choice to offer: it
                     holds the flag either way, so the state is stated rather
                     than asked as a control that cannot change anything. --}}
                @if ($defaultLocked)
                    <x-admin.badge tone="success">Parcels leave from here</x-admin.badge>
                @else
                    <x-admin.field name="is_default">
                        <x-admin.toggle name="is_default" :checked="$address->is_default"
                            label="Parcels leave from here unless another address is picked at booking" />
                    </x-admin.field>
                @endif
            </x-admin.card>

            <x-admin.form-actions>
                <x-admin.button type="submit">{{ $editing ? 'Save' : 'Add pickup address' }}</x-admin.button>
                <x-admin.button variant="secondary" :href="route('admin.fulfillment.pickup-addresses.index')">Cancel</x-admin.button>
            </x-admin.form-actions>
        </form>
    @endif

    @push('scripts')
    <script>
        /**
         * The deep-address cascade, driven entirely by the level manifest the
         * controller built from config('localization.deep_address'). It is the
         * storefront addressForm's own logic (each theme's js/theme.js,
         * _loadField / onFieldChange / syncSelect), kept field-for-field:
         * same paramFrom contract, same "walk the parent list in reverse so
         * the finer parent wins" rule, same restore-on-load pass. Only the
         * endpoint differs, and only because the admin has its own
         * authenticated route over the same service.
         *
         * syncSelect is load-bearing, not decoration: Alpine's x-model on a
         * <select> does not re-apply itself when x-for adds the options
         * asynchronously, so a saved value would render as a blank dropdown.
         * Save, clear, wait a tick, restore.
         */
        function pickupAddressCascade(config) {
            return {
                levels: config.levels,
                values: config.values,
                options: {},
                loading: {},

                init() {
                    this.levels.forEach((level) => {
                        this.options[level.key] = [];
                        this.loading[level.key] = false;
                    });

                    const root = this.levels.find((level) => !level.paramFrom);
                    if (root) {
                        this.loadLevel(root, true);
                    }
                },

                // A conditional level (PH districts exist for Manila only)
                // shows only once it has something to offer.
                isVisible(key) {
                    const level = this.levels.find((l) => l.key === key);
                    if (!level || !level.conditional) return true;
                    return (this.options[key] || []).length > 0;
                },

                async syncSelect(key) {
                    const saved = this.values[key];
                    if (!saved) return;
                    this.values[key] = '';
                    await this.$nextTick();
                    this.values[key] = saved;
                },

                async onFieldChange(key, clear = true) {
                    const index = this.levels.findIndex((level) => level.key === key);

                    if (clear) {
                        for (let i = index + 1; i < this.levels.length; i++) {
                            const downstream = this.levels[i];
                            this.values[downstream.key] = '';
                            this.options[downstream.key] = [];
                        }
                    }

                    if (!this.values[key]) return;

                    for (const level of this.levels) {
                        if (!level.paramFrom) continue;
                        if (!level.paramFrom.split('|').includes(key)) continue;
                        await this.loadLevel(level, !clear);
                    }
                },

                async loadLevel(level, restore = false) {
                    let url = level.endpoint;

                    if (level.paramFrom) {
                        const parentId = this.parentIdFor(level);
                        if (!parentId) return;
                        url = `${level.endpoint}?${level.paramName}=${encodeURIComponent(parentId)}`;
                    }

                    this.loading[level.key] = true;
                    try {
                        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        this.options[level.key] = await response.json();
                        if (restore && this.values[level.key]) {
                            await this.$nextTick();
                            await this.syncSelect(level.key);
                            await this.onFieldChange(level.key, false);
                        }
                    } catch (e) {
                        this.options[level.key] = [];
                    } finally {
                        this.loading[level.key] = false;
                    }
                },

                // Reverse order so the finer parent wins: a PH barangay hangs
                // off its district when one was chosen, off the city otherwise.
                parentIdFor(level) {
                    const sources = level.paramFrom.split('|');
                    for (const source of [...sources].reverse()) {
                        const value = this.values[source];
                        if (!value) continue;
                        const match = (this.options[source] || []).find((option) => option.name === value);
                        if (match) return match.id;
                    }
                    return null;
                },
            };
        }
    </script>
    @endpush
</x-admin.settings-shell>
