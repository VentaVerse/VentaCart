<x-admin3.shell title="Shipping">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Shipping</h1>
            <p class="v3-head__standing">The rate chain in its real evaluation order. A matching free shipping rule replaces every paid method below it.</p>
        </div>
    </div>

    <div class="v3-settle">
        @if ($noMethodEnabled)
            <div class="v3-notice">
                <p class="v3-notice__title">No shipping method is enabled</p>
                <p class="v3-notice__text">The storefront cannot check out until a method below is turned back on or a free shipping rule is active.</p>
            </div>
        @endif

        {{-- Every row is an extension wearing the standard button set
             (wave 2): the four rate methods in chain order, then
             Fulfillment as the courier hub. --}}
        <div class="v3-dir">
            @foreach ($chain as $i => $module)
                <div class="v3-dir__card v3-dir__card--static">
                    @if ($module['name'] === 'courier-fulfillment')
                        <span class="v3-dir__ico"><x-admin3.icon name="package" /></span>
                    @else
                        <span class="v3-dir__ico v3-mono">{{ $i + 1 }}</span>
                    @endif
                    <span class="v3-dir__body">
                        <span class="v3-dir__name">{{ $module['title'] }}</span>
                        <span class="v3-dir__line v3-block">{{ $module['description'] }} {{ $module['summary'] ? '· ' . $module['summary'] : '' }}</span>
                    </span>
                    @include('admin.extensions.partials.lifecycle-actions')
                </div>
            @endforeach
        </div>

        <div class="v3-gap-lg">
            <x-admin3.panel title="Rate tester" note="Runs the whole chain for an address and cart, exactly as checkout would.">
                @php $rateTest = session('rate_test'); @endphp
                <form action="{{ route('admin.shipping-settings.test') }}" method="POST"
                      x-data="rateTester({
                          providers: @js($addressProviders),
                          zonesUrl: @js(route('admin.address.zones')),
                          initial: {
                              country_id: '{{ old('country_id', $rateTest['input']['country_id'] ?? $defaultCountryId) }}',
                              state: @js(old('province', $rateTest['input']['province'] ?? 'Metro Manila')),
                              city: @js(old('city', $rateTest['input']['city'] ?? 'Quezon City')),
                              district: @js(old('district', $rateTest['input']['district'] ?? '')),
                              barangay: @js(old('barangay', $rateTest['input']['barangay'] ?? '')),
                          },
                      })" x-init="init()">
                    @csrf
                    <div class="v3-grid v3-grid--3">
                        <x-admin3.field name="country_id" label="Country" required>
                            <select name="country_id" x-model="addr.country_id" @change="onCountry()" class="v3-control">
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                                @endforeach
                            </select>
                        </x-admin3.field>
                        {{-- Provider tree (the storefront's own deep-address cascade) --}}
                        <div x-show="dd.hasProvider">
                            <x-admin3.field name="province" label="Province">
                                <select name="province" x-model="addr.state" @change="onProvince()" class="v3-control">
                                    <option value="">Select province</option>
                                    <template x-for="p in (dd.provinces || [])" :key="p.id">
                                        <option :value="p.name" x-text="p.name"></option>
                                    </template>
                                </select>
                            </x-admin3.field>
                        </div>
                        <div x-show="dd.hasProvider && (dd.cities || []).length > 0">
                            <x-admin3.field name="city" label="City / Municipality">
                                <select name="city" x-model="addr.city" @change="onCity()" class="v3-control">
                                    <option value="">Select city</option>
                                    <template x-for="c in (dd.cities || [])" :key="c.id">
                                        <option :value="c.name" x-text="c.name"></option>
                                    </template>
                                </select>
                            </x-admin3.field>
                        </div>
                        <div x-show="dd.hasProvider && (dd.districts || []).length > 0">
                            <x-admin3.field name="district" label="District">
                                <select name="district" x-model="addr.district" @change="onDistrict()" class="v3-control">
                                    <option value="">Select district</option>
                                    <template x-for="d in (dd.districts || [])" :key="d.id">
                                        <option :value="d.name" x-text="d.name"></option>
                                    </template>
                                </select>
                            </x-admin3.field>
                        </div>
                        <div x-show="dd.hasProvider && (dd.barangays || []).length > 0">
                            <x-admin3.field name="barangay" label="Barangay">
                                <select name="barangay" x-model="addr.barangay" class="v3-control">
                                    <option value="">Select barangay</option>
                                    <template x-for="b in (dd.barangays || [])" :key="b.id">
                                        <option :value="b.name" x-text="b.name"></option>
                                    </template>
                                </select>
                            </x-admin3.field>
                        </div>
                        {{-- Core countries: zone select or free text, city text --}}
                        <div x-show="!dd.hasProvider && addr.country_id">
                            <x-admin3.field name="province" label="State / Province">
                                <template x-if="(dd.zones || []).length > 0">
                                    <select name="province" x-model="addr.state" class="v3-control">
                                        <option value="">Select state / province</option>
                                        <template x-for="z in (dd.zones || [])" :key="z.id">
                                            <option :value="z.name" x-text="z.name"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="(dd.zones || []).length === 0">
                                    <input type="text" name="province" x-model="addr.state" class="v3-control">
                                </template>
                            </x-admin3.field>
                        </div>
                        <div x-show="!dd.hasProvider && addr.country_id">
                            <x-admin3.field name="city" label="City">
                                <input type="text" name="city" x-model="addr.city" class="v3-control">
                            </x-admin3.field>
                        </div>
                        <x-admin3.field name="postcode" label="Postcode">
                            <input type="text" name="postcode" class="v3-control v3-mono"
                                   value="{{ old('postcode', $rateTest['input']['postcode'] ?? '') }}">
                        </x-admin3.field>
                        {{-- Guests ARE the default group (effectiveId), so
                             only real groups are offered, Default first. --}}
                        <x-admin3.field name="customer_group_id" label="Customer group">
                            <select name="customer_group_id" class="v3-control">
                                @foreach ($customerGroups as $group)
                                    <option value="{{ $group->id }}" @selected(old('customer_group_id', $rateTest['input']['customer_group_id'] ?? $defaultGroupId) == $group->id)>{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </x-admin3.field>
                        <x-admin3.field name="weight" label="Weight (kg)" required>
                            <input type="number" name="weight" step="0.01" min="0.01" max="1000" required class="v3-control v3-mono"
                                   value="{{ old('weight', $rateTest['input']['weight'] ?? '1.5') }}">
                        </x-admin3.field>
                        <x-admin3.field name="subtotal" label="Subtotal (₱)" required>
                            <input type="number" name="subtotal" step="0.01" min="0" required class="v3-control v3-mono"
                                   value="{{ old('subtotal', $rateTest['input']['subtotal'] ?? '2500') }}">
                        </x-admin3.field>
                    </div>
                    <div class="v3-gap v3-inline">
                        <x-admin3.field name="quantity" label="Quantity">
                            <input type="number" name="quantity" min="1" max="999" class="v3-control v3-mono v3-w-rate"
                                   value="{{ old('quantity', $rateTest['input']['quantity'] ?? '') }}">
                        </x-admin3.field>
                        <button type="submit" class="v3-btn v3-btn--primary">Test rates</button>
                    </div>
                </form>

                @if ($rateTest)
                    <div class="v3-gap-lg">
                        <p class="v3-cell-quiet v3-gap-sm">{{ $rateTest['explanation'] }}</p>
                        @if ($rateTest['quotes'])
                            <div class="v3-tablewrap">
                                <table class="v3-table">
                                    <thead>
                                        <tr><th>Method</th><th>Service type</th><th>Cost</th><th><span class="v3-sr">Result</span></th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rateTest['quotes'] as $quote)
                                            <tr>
                                                <td>{{ $quote['name'] }}</td>
                                                <td class="v3-cell-quiet">{{ $quote['service_type'] === 'free_shipping' ? 'Free shipping' : ($quote['service_type'] ?? '-') }}</td>
                                                <td class="v3-mono">₱{{ number_format($quote['service_type'] === 'free_shipping' ? 0 : ($quote['cost'] ?? 0), 2) }}</td>
                                                <td>
                                                    @if ($quote['key'] === $rateTest['winner'])
                                                        <span class="v3-badge v3-badge--good">Winner</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif
            </x-admin3.panel>
        </div>

    </div>
@push('scripts')
<script>
    // The storefront's own deep-address cascade (the same provider manifest
    // checkout uses), simplified to one address for the rate tester.
    function rateTester(cfg) {
        return {
            providers: cfg.providers,
            addr: { country_id: cfg.initial.country_id, state: cfg.initial.state, city: cfg.initial.city, district: cfg.initial.district, barangay: cfg.initial.barangay },
            dd: { zones: [], provinces: [], cities: [], districts: [], barangays: [], hasProvider: false },

            async syncSelect(prop) {
                const saved = this.addr[prop];
                if (!saved) return;
                this.addr[prop] = '';
                await this.$nextTick();
                this.addr[prop] = saved;
            },

            async onCountry(preserve) {
                if (!preserve) { this.addr.state = ''; this.addr.city = ''; this.addr.district = ''; this.addr.barangay = ''; }
                Object.assign(this.dd, { zones: [], provinces: [], cities: [], districts: [], barangays: [], hasProvider: false });
                if (!this.addr.country_id) return;

                const provider = this.providers[this.addr.country_id];
                if (provider) {
                    this.dd.hasProvider = true;
                    const rootField = provider.fields.find(f => !f.paramFrom);
                    if (rootField) {
                        this.dd.provinces = await (await fetch(rootField.endpoint)).json();
                        if (preserve && this.addr.state) {
                            await this.$nextTick();
                            await this.syncSelect('state');
                            await this.onProvince(true);
                        }
                    }
                } else {
                    this.dd.zones = await (await fetch(cfg.zonesUrl + '?country_id=' + this.addr.country_id)).json();
                    if (preserve && this.addr.state) { await this.$nextTick(); await this.syncSelect('state'); }
                }
            },

            async onProvince(preserve) {
                if (!preserve) { this.addr.city = ''; this.addr.district = ''; this.addr.barangay = ''; }
                Object.assign(this.dd, { cities: [], districts: [], barangays: [] });
                const province = (this.dd.provinces || []).find(p => p.name === this.addr.state);
                const provider = this.providers[this.addr.country_id];
                const cityField = provider?.fields.find(f => f.key === 'city');
                if (!province || !cityField) return;
                this.dd.cities = await (await fetch(cityField.endpoint + '?' + cityField.paramName + '=' + province.id)).json();
                if (preserve && this.addr.city) { await this.$nextTick(); await this.syncSelect('city'); await this.onCity(true); }
            },

            async onCity(preserve) {
                if (!preserve) { this.addr.district = ''; this.addr.barangay = ''; }
                Object.assign(this.dd, { districts: [], barangays: [] });
                const city = (this.dd.cities || []).find(c => c.name === this.addr.city);
                const provider = this.providers[this.addr.country_id];
                if (!city || !provider) return;
                const distField = provider.fields.find(f => f.key === 'district');
                if (distField) {
                    this.dd.districts = await (await fetch(distField.endpoint + '?' + distField.paramName + '=' + city.id)).json();
                    if (this.dd.districts.length > 0) {
                        if (preserve && this.addr.district) { await this.$nextTick(); await this.syncSelect('district'); await this.onDistrict(true); }
                        return;
                    }
                }
                const brgyField = provider.fields.find(f => f.key === 'barangay');
                if (brgyField) {
                    this.dd.barangays = await (await fetch(brgyField.endpoint + '?' + brgyField.paramName + '=' + city.id)).json();
                    if (preserve && this.addr.barangay) { await this.$nextTick(); await this.syncSelect('barangay'); }
                }
            },

            async onDistrict(preserve) {
                if (!preserve) { this.addr.barangay = ''; }
                this.dd.barangays = [];
                const district = (this.dd.districts || []).find(d => d.name === this.addr.district);
                const provider = this.providers[this.addr.country_id];
                const brgyField = provider?.fields.find(f => f.key === 'barangay');
                if (!district || !brgyField) return;
                this.dd.barangays = await (await fetch(brgyField.endpoint + '?' + brgyField.paramName + '=' + district.id)).json();
                if (preserve && this.addr.barangay) { await this.$nextTick(); await this.syncSelect('barangay'); }
            },

            init() {
                if (this.addr.country_id) { this.onCountry(true); }
            },
        };
    }
</script>
@endpush
</x-admin3.shell>

