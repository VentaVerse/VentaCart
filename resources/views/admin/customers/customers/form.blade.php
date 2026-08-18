<x-admin3.shell :title="$customer ? 'Edit Customer' : 'Create Customer'" active="customers">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.customers.index') }}">Customers</a>
                <span aria-hidden="true">/</span>
                <span>{{ $customer?->full_name ?? 'New' }}</span>
            </div>
            <h1 class="v3-head__title">{{ $customer ? $customer->full_name : 'Create customer' }}</h1>
        </div>
        @if (isset($customer) && $customer->id)
            <div class="v3-head__actions">
                <button type="button" class="v3-btn v3-btn--secondary" @click="$dispatch('v3-impersonate')">Login as customer</button>
            </div>
        @endif
    </div>

    <x-admin3.form :action="$customer ? route('admin.customers.update', $customer) : route('admin.customers.store')"
                   :method="$customer ? 'PUT' : 'POST'" class="v3-settle">
        <input type="hidden" name="store_id" value="1">

        <div x-data="customerForm()">
            <div class="v3-tabs" role="tablist">
                <button type="button" class="v3-tab" role="tab" :aria-selected="activeTab === 'general' ? 'true' : 'false'" @click="activeTab = 'general'">General</button>
                <button type="button" class="v3-tab" role="tab" :aria-selected="activeTab === 'addresses' ? 'true' : 'false'" @click="activeTab = 'addresses'">
                    Addresses <span class="v3-tab__count" x-text="addresses.length"></span>
                </button>
                @if (isset($customer) && $customer->id)
                    <button type="button" class="v3-tab" role="tab" :aria-selected="activeTab === 'activity' ? 'true' : 'false'" @click="activeTab = 'activity'">Activity</button>
                @endif
            </div>

            {{-- ===================== GENERAL ===================== --}}
            <div x-show="activeTab === 'general'" class="v3-settle">
                <x-admin3.panel title="Account">
                    <div class="v3-grid v3-grid--2">
                        <x-admin3.field name="customer_group_id" label="Customer group">
                            <select name="customer_group_id" class="v3-control">
                                <option value="">None</option>
                                @foreach ($customerGroups as $group)
                                    <option value="{{ $group->id }}" @selected(old('customer_group_id', $customer?->customer_group_id) == $group->id)>
                                        {{ $group->name }}@if ($group->is_default) (default)@endif
                                    </option>
                                @endforeach
                            </select>
                        </x-admin3.field>
                        <x-admin3.field name="first_name" label="First name" required>
                            <input type="text" id="first_name" name="first_name" required class="v3-control" value="{{ old('first_name', $customer?->first_name) }}">
                        </x-admin3.field>
                        <x-admin3.field name="last_name" label="Last name" required>
                            <input type="text" id="last_name" name="last_name" required class="v3-control" value="{{ old('last_name', $customer?->last_name) }}">
                        </x-admin3.field>
                        <x-admin3.field name="email" label="Email" required>
                            <input type="email" name="email" required class="v3-control" value="{{ old('email', $customer?->email) }}">
                        </x-admin3.field>
                        <x-admin3.field name="phone" label="Phone">
                            <input type="text" name="phone" class="v3-control v3-mono" value="{{ old('phone', $customer?->phone) }}">
                        </x-admin3.field>
                        <x-admin3.field name="password" label="Password" :required="! $customer"
                            :hint="$customer ? 'Leave blank to keep the current password.' : null">
                            <input type="password" name="password" autocomplete="new-password" minlength="8" class="v3-control" @required(! $customer)>
                        </x-admin3.field>
                    </div>
                    <div class="v3-gap v3-actrow">
                        <label class="v3-toggle">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer?->is_active ?? true))>
                            <span class="v3-toggle__track" aria-hidden="true"></span>
                            <span>Active</span>
                        </label>
                        <label class="v3-toggle">
                            <input type="hidden" name="newsletter" value="0">
                            <input type="checkbox" name="newsletter" value="1" @checked(old('newsletter', $customer?->newsletter ?? false))>
                            <span class="v3-toggle__track" aria-hidden="true"></span>
                            <span>Newsletter</span>
                        </label>
                    </div>
                </x-admin3.panel>
            </div>

            {{-- ===================== ADDRESSES ===================== --}}
            <div x-show="activeTab === 'addresses'" class="v3-settle">
                <x-admin3.panel title="Addresses">
                    <x-slot:acts>
                        <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="addAddress(); touch()">Add address</button>
                    </x-slot:acts>

                    <div x-show="addresses.length === 0">
                        <div class="v3-empty">
                            <p class="v3-empty__title">No addresses</p>
                        </div>
                    </div>

                    <template x-for="(addr, aIndex) in addresses" :key="aIndex">
                        <div class="v3-well">
                            <div class="v3-between v3-gap-sm">
                                <span class="v3-actrow">
                                    <span class="v3-field__label" x-text="'Address ' + (aIndex + 1)"></span>
                                    <label class="v3-check">
                                        <input type="radio" :name="'address_default_pick'" :checked="addr.is_default"
                                               @click="addresses.forEach((a, i) => a.is_default = (i === aIndex))">
                                        <span>Default</span>
                                    </label>
                                </span>
                                <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="addresses.splice(aIndex, 1); touch()">Remove</button>
                            </div>

                            <input type="hidden" :name="'addresses['+aIndex+'][id]'" :value="addr.id || ''">
                            <input type="hidden" :name="'addresses['+aIndex+'][is_default]'" :value="addr.is_default ? '1' : '0'">

                            <div class="v3-grid v3-grid--2 v3-gap">
                                <label class="v3-field">
                                    <span class="v3-field__label">First name</span>
                                    <input type="text" x-model="addr.first_name" :name="'addresses['+aIndex+'][first_name]'" class="v3-control">
                                </label>
                                <label class="v3-field">
                                    <span class="v3-field__label">Last name</span>
                                    <input type="text" x-model="addr.last_name" :name="'addresses['+aIndex+'][last_name]'" class="v3-control">
                                </label>
                                <label class="v3-field">
                                    <span class="v3-field__label">Country</span>
                                    <select x-model="addr.country_id" :name="'addresses['+aIndex+'][country_id]'"
                                            @change="onAddrCountryChange(aIndex)" class="v3-control">
                                        <option value="">Select country</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                {{-- Provider hierarchy (PH-style cascades) --}}
                                <label class="v3-field" x-show="addrDropdowns[aIndex]?.hasProvider">
                                    <span class="v3-field__label">Province</span>
                                    <select x-model="addr.state" :name="'addresses['+aIndex+'][state]'"
                                            @change="onAddrProvinceChange(aIndex)" class="v3-control">
                                        <option value="">Select province</option>
                                        <template x-for="p in (addrDropdowns[aIndex]?.provinces || [])" :key="p.id">
                                            <option :value="p.name" x-text="p.name"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="v3-field" x-show="addrDropdowns[aIndex]?.hasProvider && (addrDropdowns[aIndex]?.cities || []).length > 0">
                                    <span class="v3-field__label">City / Municipality</span>
                                    <select x-model="addr.city" :name="'addresses['+aIndex+'][city]'"
                                            @change="onAddrCityChange(aIndex)" class="v3-control">
                                        <option value="">Select city</option>
                                        <template x-for="c in (addrDropdowns[aIndex]?.cities || [])" :key="c.id">
                                            <option :value="c.name" x-text="c.name"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="v3-field" x-show="addrDropdowns[aIndex]?.hasProvider && (addrDropdowns[aIndex]?.districts || []).length > 0">
                                    <span class="v3-field__label">District</span>
                                    <select x-model="addr.district" :name="'addresses['+aIndex+'][district]'"
                                            @change="onAddrDistrictChange(aIndex)" class="v3-control">
                                        <option value="">Select district</option>
                                        <template x-for="d in (addrDropdowns[aIndex]?.districts || [])" :key="d.id">
                                            <option :value="d.name" x-text="d.name"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="v3-field" x-show="addrDropdowns[aIndex]?.hasProvider && (addrDropdowns[aIndex]?.barangays || []).length > 0">
                                    <span class="v3-field__label">Barangay</span>
                                    <select x-model="addr.barangay" :name="'addresses['+aIndex+'][barangay]'" class="v3-control">
                                        <option value="">Select barangay</option>
                                        <template x-for="b in (addrDropdowns[aIndex]?.barangays || [])" :key="b.id">
                                            <option :value="b.name" x-text="b.name"></option>
                                        </template>
                                    </select>
                                </label>

                                {{-- Core hierarchy (zones or free text) --}}
                                <label class="v3-field" x-show="!addrDropdowns[aIndex]?.hasProvider && addr.country_id">
                                    <span class="v3-field__label">State / Province</span>
                                    <template x-if="(addrDropdowns[aIndex]?.zones || []).length > 0">
                                        <select x-model="addr.state" :name="'addresses['+aIndex+'][state]'" class="v3-control">
                                            <option value="">Select state or province</option>
                                            <template x-for="z in (addrDropdowns[aIndex]?.zones || [])" :key="z.id">
                                                <option :value="z.name" x-text="z.name"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="(addrDropdowns[aIndex]?.zones || []).length === 0">
                                        <input type="text" x-model="addr.state" :name="'addresses['+aIndex+'][state]'" class="v3-control">
                                    </template>
                                </label>
                                <label class="v3-field" x-show="!addrDropdowns[aIndex]?.hasProvider && addr.country_id">
                                    <span class="v3-field__label">City</span>
                                    <input type="text" x-model="addr.city" :name="'addresses['+aIndex+'][city]'" class="v3-control">
                                </label>

                                <label class="v3-field v3-span2">
                                    <span class="v3-field__label">Address line 1</span>
                                    <input type="text" x-model="addr.address_1" :name="'addresses['+aIndex+'][address_1]'" class="v3-control">
                                </label>
                                <label class="v3-field">
                                    <span class="v3-field__label">Postcode</span>
                                    <input type="text" x-model="addr.postcode" :name="'addresses['+aIndex+'][postcode]'" class="v3-control v3-mono">
                                </label>
                                <label class="v3-field">
                                    <span class="v3-field__label">Phone</span>
                                    <span class="v3-namerow">
                                        <span class="v3-mono v3-cell-quiet" x-text="phoneCodes[addr.country_id] ? '+' + phoneCodes[addr.country_id] : '-'"></span>
                                        <input type="text" x-model="addr.phone" :name="'addresses['+aIndex+'][phone]'"
                                               class="v3-control v3-mono" inputmode="numeric" pattern="[0-9]*"
                                               oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                    </span>
                                </label>
                            </div>
                        </div>
                    </template>
                </x-admin3.panel>
            </div>

            {{-- ===================== ACTIVITY ===================== --}}
            @if (isset($customer) && $customer->id)
                {{-- The same feed the day sheet's activity rail runs, at the
                     same density, so one customer's history and the store's
                     are the same list read at two scopes. --}}
                <div x-show="activeTab === 'activity'" class="v3-settle">
                    <x-admin3.panel title="Activity">
                        @if ($customer->activities->isEmpty())
                            <div class="v3-empty">
                                <p class="v3-empty__title">No activity recorded yet</p>
                            </div>
                        @else
                            <ul class="v3-feed">
                                @foreach ($customer->activities->take(100) as $activity)
                                    <li class="v3-feed__entry">
                                        <div class="v3-feed__row">
                                            <span>{{ $activity->description }}</span>
                                        </div>
                                        <p class="v3-feed__when">{{ $activity->created_at->format('M d, Y H:i') }} · {{ $activity->created_at->diffForHumans() }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-admin3.panel>
                </div>
            @endif
        </div>
    </x-admin3.form>

    {{-- Store credit panel: a guarded seam into the store-credit
         extension; the route only exists while it is enabled. --}}
    @if (isset($customer) && $customer->id && Route::has('admin.store-credit.adjust') && View::exists('store-credit::customer-panel'))
        <div class="v3-settle">
            @include('store-credit::customer-panel', ['customer' => $customer])
        </div>
    @endif

    @if (isset($customer) && $customer->id)
        {{-- Impersonation is a GET crossing, so it hand-rolls the confirm
             (x-admin3.confirm only wraps POST forms). --}}
        <div class="v3-passthru" x-data="{ open: false }"
             x-on:v3-impersonate.window="open = true; $nextTick(() => $refs.cancel.focus());"
             x-on:keydown.escape.window="open = false">
            <template x-if="open">
                <div class="v3-overlay" @click.self="open = false">
                    <div class="v3-modal" role="dialog" aria-modal="true" aria-labelledby="v3c-impersonate">
                        <h2 class="v3-modal__title" id="v3c-impersonate">Sign in as {{ $customer->first_name }} {{ $customer->last_name }}?</h2>
                        <p class="v3-modal__body">The storefront opens as this customer, with their cart and their prices. Your admin session stays open, and Stop impersonating returns you to it.</p>
                        <div class="v3-modal__acts">
                            <button type="button" class="v3-btn v3-btn--ghost" x-ref="cancel" @click="open = false">Cancel</button>
                            <a href="{{ route('admin.customers.impersonate', $customer) }}" class="v3-btn v3-btn--primary">Sign in as customer</a>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    @endif

    @push('scripts')
    <script>
        function customerForm() {
            return {
                activeTab: 'general',
                addresses: @json($customer?->addresses ?? []),
                addrDropdowns: {},
                phoneCodes: @json(\App\Models\Country::where('is_active', true)->pluck('phone_code', 'id')),

                addAddress() {
                    this.addresses.push({
                        id: '',
                        first_name: document.getElementById('first_name')?.value || '',
                        last_name: document.getElementById('last_name')?.value || '',
                        address_1: '',
                        city: '',
                        district: '',
                        barangay: '',
                        state: '',
                        postcode: '',
                        phone: '',
                        country_id: '',
                        is_default: this.addresses.length === 0,
                    });
                    const idx = this.addresses.length - 1;
                    this.onAddrCountryChange(idx, false);
                },

                updateDropdown(idx, field, value) {
                    const dd = { ...(this.addrDropdowns[idx] || { provinces: [], cities: [], districts: [], barangays: [], zones: [] }) };
                    dd[field] = value;
                    this.addrDropdowns[idx] = dd;
                },

                resetDropdowns(idx, fields) {
                    const dd = { ...(this.addrDropdowns[idx] || { provinces: [], cities: [], districts: [], barangays: [], zones: [] }) };
                    fields.forEach(f => dd[f] = []);
                    this.addrDropdowns[idx] = dd;
                },

                // Force x-model re-sync on selects after async options load
                async syncAddrSelect(idx, prop) {
                    const saved = this.addresses[idx][prop];
                    if (!saved) return;
                    this.addresses[idx][prop] = '';
                    await this.$nextTick();
                    this.addresses[idx][prop] = saved;
                },

                providers: @json(app(\App\Services\AddressProviderRegistry::class)->toFrontendManifest()),

                async onAddrCountryChange(idx, clear) {
                    if (clear !== false) {
                        this.addresses[idx].state = '';
                        this.addresses[idx].city = '';
                        this.addresses[idx].district = '';
                        this.addresses[idx].barangay = '';
                    }
                    this.addrDropdowns[idx] = { zones: [], provinces: [], cities: [], districts: [], barangays: [], hasProvider: false };

                    const countryId = this.addresses[idx].country_id;
                    if (!countryId) return;

                    const provider = this.providers[countryId];
                    if (provider) {
                        this.addrDropdowns[idx].hasProvider = true;
                        // Load provinces from provider
                        const rootField = provider.fields.find(f => !f.paramFrom);
                        if (rootField) {
                            const res = await fetch(rootField.endpoint);
                            this.updateDropdown(idx, 'provinces', await res.json());
                            if (clear === false && this.addresses[idx].state) {
                                await this.$nextTick();
                                await this.syncAddrSelect(idx, 'state');
                                await this.onAddrProvinceChange(idx, false);
                            }
                        }
                    } else {
                        const res = await fetch(`{{ route('admin.address.zones') }}?country_id=${countryId}`);
                        this.updateDropdown(idx, 'zones', await res.json());
                        if (clear === false && this.addresses[idx].state) {
                            await this.$nextTick();
                            await this.syncAddrSelect(idx, 'state');
                        }
                    }
                },

                async onAddrProvinceChange(idx, clear) {
                    if (clear !== false) {
                        this.addresses[idx].city = '';
                        this.addresses[idx].district = '';
                        this.addresses[idx].barangay = '';
                    }
                    this.resetDropdowns(idx, ['cities', 'districts', 'barangays']);

                    const province = (this.addrDropdowns[idx]?.provinces || []).find(p => p.name === this.addresses[idx].state);
                    if (!province) return;

                    const provider = this.providers[this.addresses[idx].country_id];
                    const cityField = provider?.fields.find(f => f.key === 'city');
                    if (!cityField) return;

                    const res = await fetch(`${cityField.endpoint}?${cityField.paramName}=${province.id}`);
                    this.updateDropdown(idx, 'cities', await res.json());
                    if (clear === false && this.addresses[idx].city) {
                        await this.$nextTick();
                        await this.syncAddrSelect(idx, 'city');
                        await this.onAddrCityChange(idx, false);
                    }
                },

                async onAddrCityChange(idx, clear) {
                    if (clear !== false) {
                        this.addresses[idx].district = '';
                        this.addresses[idx].barangay = '';
                    }
                    this.resetDropdowns(idx, ['districts', 'barangays']);

                    const city = (this.addrDropdowns[idx]?.cities || []).find(c => c.name === this.addresses[idx].city);
                    if (!city) return;

                    const provider = this.providers[this.addresses[idx].country_id];

                    // Try districts first
                    const distField = provider?.fields.find(f => f.key === 'district');
                    if (distField) {
                        const distRes = await fetch(`${distField.endpoint}?${distField.paramName}=${city.id}`);
                        const districts = await distRes.json();
                        this.updateDropdown(idx, 'districts', districts);

                        if (districts.length > 0) {
                            if (clear === false && this.addresses[idx].district) {
                                await this.$nextTick();
                                await this.syncAddrSelect(idx, 'district');
                                await this.onAddrDistrictChange(idx, false);
                            }
                            return;
                        }
                    }

                    // No districts, so load barangays directly
                    const brgyField = provider?.fields.find(f => f.key === 'barangay');
                    if (brgyField) {
                        const brgyRes = await fetch(`${brgyField.endpoint}?${brgyField.paramName}=${city.id}`);
                        this.updateDropdown(idx, 'barangays', await brgyRes.json());
                        if (clear === false && this.addresses[idx].barangay) {
                            await this.$nextTick();
                            await this.syncAddrSelect(idx, 'barangay');
                        }
                    }
                },

                async onAddrDistrictChange(idx, clear) {
                    if (clear !== false) {
                        this.addresses[idx].barangay = '';
                    }
                    this.resetDropdowns(idx, ['barangays']);

                    const district = (this.addrDropdowns[idx]?.districts || []).find(d => d.name === this.addresses[idx].district);
                    if (!district) return;

                    const provider = this.providers[this.addresses[idx].country_id];
                    const brgyField = provider?.fields.find(f => f.key === 'barangay');
                    if (!brgyField) return;

                    const res = await fetch(`${brgyField.endpoint}?${brgyField.paramName}=${district.id}`);
                    this.updateDropdown(idx, 'barangays', await res.json());
                    if (clear === false && this.addresses[idx].barangay) {
                        await this.$nextTick();
                        await this.syncAddrSelect(idx, 'barangay');
                    }
                },

                async init() {
                    // Hydrate every saved address cascade, then hand the
                    // doctrine the settled state as the clean baseline
                    // (v3-rebaseline), so a restored form never shows a
                    // phantom Unsaved changes bar.
                    await Promise.all(this.addresses.map((addr, idx) =>
                        addr.country_id ? this.onAddrCountryChange(idx, false) : Promise.resolve()
                    ));
                    await this.$nextTick();
                    this.$el.closest('form')?.dispatchEvent(new Event('v3-rebaseline'));
                }
            }
        }
    </script>
    @endpush
</x-admin3.shell>
