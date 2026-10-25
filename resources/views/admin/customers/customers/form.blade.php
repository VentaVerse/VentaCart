<x-admin.shell :title="$customer ? 'Edit Customer' : 'Create Customer'" active="customers">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.customers.index') }}">Customers</a>
                <span aria-hidden="true">/</span>
                <span>{{ $customer?->full_name ?? 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $customer ? $customer->full_name : 'Create customer' }}</h1>
        </div>
        @if (isset($customer) && $customer->id)
            <div class="v-head__actions">
                <button type="button" class="v-btn v-btn--secondary" @click="$dispatch('v-impersonate')">Login as customer</button>
            </div>
        @endif
    </div>

    {{-- Tab state wraps the doctrine form AND the store-credit block:
         the credit panel carries its own record-scoped form, so it must
         stay a sibling of the doctrine form, never nest inside it. The
         ?tab= sync keeps the credit tab in place across its Apply POST. --}}
    @php
        $hasCreditTab = isset($customer) && $customer->id
            && Route::has('admin.store-credit.adjust')
            && View::exists('store-credit::customer-panel');
        $customerTabs = array_merge(
            ['general', 'addresses'],
            isset($customer) && $customer->id ? ['activity'] : [],
            $hasCreditTab ? ['credit'] : [],
        );
        $initialTab = in_array(request('tab'), $customerTabs, true) ? request('tab') : 'general';
    @endphp
    <div x-data="customerForm()">
    <x-admin.form :action="$customer ? route('admin.customers.update', $customer) : route('admin.customers.store')"
                   :method="$customer ? 'PUT' : 'POST'" :guard="false" class="v-settle">
        <input type="hidden" name="store_id" value="1">

            @if ($errors->any())
                <div class="v-notice v-notice--bad">
                    <p class="v-notice__title">Please fix the following</p>
                    <ul class="v-notice__list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="v-tabs" role="tablist">
                <button type="button" class="v-tab" role="tab" :aria-selected="activeTab === 'general' ? 'true' : 'false'" @click="pick('general')">General</button>
                <button type="button" class="v-tab" role="tab" :aria-selected="activeTab === 'addresses' ? 'true' : 'false'" @click="pick('addresses')">
                    Addresses <span class="v-tab__count" x-text="addresses.length"></span>
                </button>
                @if (isset($customer) && $customer->id)
                    <button type="button" class="v-tab" role="tab" :aria-selected="activeTab === 'activity' ? 'true' : 'false'" @click="pick('activity')">Activity</button>
                @endif
                @if ($hasCreditTab)
                    <button type="button" class="v-tab" role="tab" :aria-selected="activeTab === 'credit' ? 'true' : 'false'" @click="pick('credit')">Store credit</button>
                @endif
            </div>

            {{-- ===================== GENERAL ===================== --}}
            <div x-show="activeTab === 'general'" class="v-settle">
                <x-admin.panel title="Account">
                    <div class="v-grid v-grid--2">
                        <x-admin.field name="customer_group_id" label="Customer group">
                            <select name="customer_group_id" class="v-control">
                                <option value="">None</option>
                                @foreach ($customerGroups as $group)
                                    <option value="{{ $group->id }}" @selected(old('customer_group_id', $customer?->customer_group_id) == $group->id)>
                                        {{ $group->name }}@if ($group->is_default) (default)@endif
                                    </option>
                                @endforeach
                            </select>
                        </x-admin.field>
                        <x-admin.field name="first_name" label="First name" required>
                            <input type="text" id="first_name" name="first_name" required class="v-control" value="{{ old('first_name', $customer?->first_name) }}">
                        </x-admin.field>
                        <x-admin.field name="last_name" label="Last name" required>
                            <input type="text" id="last_name" name="last_name" required class="v-control" value="{{ old('last_name', $customer?->last_name) }}">
                        </x-admin.field>
                        <x-admin.field name="email" label="Email" required>
                            <input type="email" name="email" required class="v-control" value="{{ old('email', $customer?->email) }}">
                        </x-admin.field>
                        <x-admin.field name="phone" label="Phone">
                            <input type="text" name="phone" class="v-control v-mono" value="{{ old('phone', $customer?->phone) }}">
                        </x-admin.field>
                        @if ($customer)
                            {{-- Not rendered until asked. An email field beside a password
                                 field is a login form to Chrome, which then autofills the
                                 ADMIN's saved credentials into the customer's record and
                                 raises the Unsaved changes bar on a page nobody touched. --}}
                            <div x-data="{ changing: {{ old('password') ? 'true' : 'false' }} }">
                                <div class="v-label v-gap-sm">Password</div>
                                <button type="button" class="v-btn v-btn--ghost v-btn--sm" x-show="!changing" @click="changing = true">Change password</button>
                                <template x-if="changing">
                                    <x-admin.field name="password" label="New password">
                                        <input type="password" name="password" autocomplete="new-password" minlength="8" class="v-control" x-init="$nextTick(() => { $el.focus(); $el.closest('form')?.dispatchEvent(new Event('v-rebaseline')); })">
                                    </x-admin.field>
                                </template>
                            </div>
                        @else
                            <x-admin.field name="password" label="Password" required>
                                <input type="password" name="password" autocomplete="new-password" minlength="8" class="v-control" required>
                            </x-admin.field>
                        @endif
                    </div>
                    <div class="v-gap v-actrow">
                        <label class="v-toggle">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer?->is_active ?? true))>
                            <span class="v-toggle__track" aria-hidden="true"></span>
                            <span>Active</span>
                        </label>
                        <label class="v-toggle">
                            <input type="hidden" name="newsletter" value="0">
                            <input type="checkbox" name="newsletter" value="1" @checked(old('newsletter', $customer?->newsletter ?? false))>
                            <span class="v-toggle__track" aria-hidden="true"></span>
                            <span>Newsletter</span>
                        </label>
                    </div>
                </x-admin.panel>
            </div>

            {{-- ===================== ADDRESSES ===================== --}}
            <div x-show="activeTab === 'addresses'" class="v-settle">
                <x-admin.panel title="Addresses">
                    <x-slot:acts>
                        <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="addAddress(); touch()">Add address</button>
                    </x-slot:acts>

                    <div x-show="addresses.length === 0">
                        <div class="v-empty">
                            <p class="v-empty__title">No addresses</p>
                        </div>
                    </div>

                    <template x-for="(addr, aIndex) in addresses" :key="aIndex">
                        <div class="v-well">
                            <div class="v-between v-gap-sm">
                                <span class="v-actrow">
                                    <span class="v-field__label" x-text="'Address ' + (aIndex + 1)"></span>
                                    <label class="v-check">
                                        <input type="radio" :name="'address_default_pick'" :checked="addr.is_default"
                                               @click="addresses.forEach((a, i) => a.is_default = (i === aIndex))">
                                        <span>Default</span>
                                    </label>
                                </span>
                                <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="addresses.splice(aIndex, 1); touch()">Remove</button>
                            </div>

                            <input type="hidden" :name="'addresses['+aIndex+'][id]'" :value="addr.id || ''">
                            <input type="hidden" :name="'addresses['+aIndex+'][is_default]'" :value="addr.is_default ? '1' : '0'">

                            <div class="v-grid v-grid--2 v-gap">
                                <label class="v-field">
                                    <span class="v-field__label">First name</span>
                                    <input type="text" x-model="addr.first_name" :name="'addresses['+aIndex+'][first_name]'" class="v-control">
                                </label>
                                <label class="v-field">
                                    <span class="v-field__label">Last name</span>
                                    <input type="text" x-model="addr.last_name" :name="'addresses['+aIndex+'][last_name]'" class="v-control">
                                </label>
                                <label class="v-field">
                                    <span class="v-field__label">Country</span>
                                    <select x-model="addr.country_id" :name="'addresses['+aIndex+'][country_id]'"
                                            @change="onAddrCountryChange(aIndex)" class="v-control">
                                        <option value="">Select country</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                {{-- Provider hierarchy (PH-style cascades) --}}
                                <label class="v-field" x-show="addrDropdowns[aIndex]?.hasProvider">
                                    <span class="v-field__label">Province</span>
                                    <select x-model="addr.state" :name="'addresses['+aIndex+'][state]'"
                                            @change="onAddrProvinceChange(aIndex)" class="v-control">
                                        <option value="">Select province</option>
                                        <template x-for="p in (addrDropdowns[aIndex]?.provinces || [])" :key="p.id">
                                            <option :value="p.name" x-text="p.name"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="v-field" x-show="addrDropdowns[aIndex]?.hasProvider && (addrDropdowns[aIndex]?.cities || []).length > 0">
                                    <span class="v-field__label">City / Municipality</span>
                                    <select x-model="addr.city" :name="'addresses['+aIndex+'][city]'"
                                            @change="onAddrCityChange(aIndex)" class="v-control">
                                        <option value="">Select city</option>
                                        <template x-for="c in (addrDropdowns[aIndex]?.cities || [])" :key="c.id">
                                            <option :value="c.name" x-text="c.name"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="v-field" x-show="addrDropdowns[aIndex]?.hasProvider && (addrDropdowns[aIndex]?.districts || []).length > 0">
                                    <span class="v-field__label">District <span class="v-req">Required</span></span>
                                    <select x-model="addr.district" :name="'addresses['+aIndex+'][district]'" :required="addrDropdowns[aIndex]?.hasProvider && (addrDropdowns[aIndex]?.districts || []).length > 0"
                                            @change="onAddrDistrictChange(aIndex)" class="v-control">
                                        <option value="">Select district</option>
                                        <template x-for="d in (addrDropdowns[aIndex]?.districts || [])" :key="d.id">
                                            <option :value="d.name" x-text="d.name"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="v-field" x-show="addrDropdowns[aIndex]?.hasProvider && (addrDropdowns[aIndex]?.barangays || []).length > 0">
                                    <span class="v-field__label">Barangay <span class="v-req">Required</span></span>
                                    <select x-model="addr.barangay" :name="'addresses['+aIndex+'][barangay]'" :required="addrDropdowns[aIndex]?.hasProvider && (addrDropdowns[aIndex]?.barangays || []).length > 0" class="v-control">
                                        <option value="">Select barangay</option>
                                        <template x-for="b in (addrDropdowns[aIndex]?.barangays || [])" :key="b.id">
                                            <option :value="b.name" x-text="b.name"></option>
                                        </template>
                                    </select>
                                </label>

                                {{-- Core hierarchy (zones or free text) --}}
                                <label class="v-field" x-show="!addrDropdowns[aIndex]?.hasProvider && addr.country_id">
                                    <span class="v-field__label">State / Province</span>
                                    <template x-if="(addrDropdowns[aIndex]?.zones || []).length > 0">
                                        <select x-model="addr.state" :name="'addresses['+aIndex+'][state]'" class="v-control">
                                            <option value="">Select state or province</option>
                                            <template x-for="z in (addrDropdowns[aIndex]?.zones || [])" :key="z.id">
                                                <option :value="z.name" x-text="z.name"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="(addrDropdowns[aIndex]?.zones || []).length === 0">
                                        <input type="text" x-model="addr.state" :name="'addresses['+aIndex+'][state]'" class="v-control">
                                    </template>
                                </label>
                                <label class="v-field" x-show="!addrDropdowns[aIndex]?.hasProvider && addr.country_id">
                                    <span class="v-field__label">City</span>
                                    <input type="text" x-model="addr.city" :name="'addresses['+aIndex+'][city]'" class="v-control">
                                </label>

                                <label class="v-field v-span2">
                                    <span class="v-field__label">Address line 1</span>
                                    <input type="text" x-model="addr.address_1" :name="'addresses['+aIndex+'][address_1]'" class="v-control">
                                </label>
                                <label class="v-field">
                                    <span class="v-field__label">Postcode</span>
                                    <input type="text" x-model="addr.postcode" :name="'addresses['+aIndex+'][postcode]'" class="v-control v-mono">
                                </label>
                                <label class="v-field">
                                    <span class="v-field__label">Phone</span>
                                    <span class="v-namerow">
                                        <span class="v-mono v-cell-quiet" x-text="phoneCodes[addr.country_id] ? '+' + phoneCodes[addr.country_id] : '-'"></span>
                                        <input type="text" x-model="addr.phone" :name="'addresses['+aIndex+'][phone]'"
                                               class="v-control v-mono" inputmode="numeric" pattern="[0-9]*"
                                               oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                    </span>
                                </label>
                            </div>
                        </div>
                    </template>
                </x-admin.panel>
            </div>

            {{-- ===================== ACTIVITY ===================== --}}
            @if (isset($customer) && $customer->id)
                {{-- The same feed the day sheet's activity rail runs, at the
                     same density, so one customer's history and the store's
                     are the same list read at two scopes. --}}
                <div x-show="activeTab === 'activity'" class="v-settle">
                    <x-admin.panel title="Activity">
                        @if ($customer->activities->isEmpty())
                            <div class="v-empty">
                                <p class="v-empty__title">No activity recorded yet</p>
                            </div>
                        @else
                            <ul class="v-feed">
                                @foreach ($customer->activities->take(100) as $activity)
                                    <li class="v-feed__entry">
                                        <div class="v-feed__row">
                                            <span>{{ $activity->description }}</span>
                                        </div>
                                        <p class="v-feed__when">{{ $activity->created_at->format('M d, Y H:i') }} · {{ $activity->created_at->diffForHumans() }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-admin.panel>
                </div>
            @endif
    </x-admin.form>

    {{-- Store credit tab pane: a guarded seam into the store-credit
         extension; the route only exists while it is enabled. --}}
    @if ($hasCreditTab)
        <div x-show="activeTab === 'credit'" x-cloak class="v-settle">
            @include('store-credit::customer-panel', ['customer' => $customer])
        </div>
    @endif
    </div>

    @if (isset($customer) && $customer->id)
        {{-- Impersonation is a GET crossing, so it hand-rolls the confirm
             (x-admin.confirm only wraps POST forms). --}}
        <div class="v-passthru" x-data="{ open: false }"
             x-on:v-impersonate.window="open = true; $nextTick(() => $refs.cancel.focus());"
             x-on:keydown.escape.window="open = false">
            <template x-if="open">
                <div class="v-overlay" @click.self="open = false">
                    <div class="v-modal" role="dialog" aria-modal="true" aria-labelledby="vc-impersonate">
                        <h2 class="v-modal__title" id="vc-impersonate">Sign in as {{ $customer->first_name }} {{ $customer->last_name }}?</h2>
                        <p class="v-modal__body">The storefront opens as this customer, with their cart and their prices. Your admin session stays open, and Stop impersonating returns you to it.</p>
                        <div class="v-modal__acts">
                            <button type="button" class="v-btn v-btn--ghost" x-ref="cancel" @click="open = false">Cancel</button>
                            <a href="{{ route('admin.customers.impersonate', $customer) }}" class="v-btn v-btn--primary">Sign in as customer</a>
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
                activeTab: @json($initialTab),
                pick(t) {
                    this.activeTab = t;
                    const u = new URL(window.location);
                    u.searchParams.set('tab', t);
                    history.replaceState(null, '', u);
                },
                addresses: @json(array_values(old('addresses', $customer?->addresses?->toArray() ?? []))),
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
                    // (v-rebaseline), so a restored form never shows a
                    // phantom Unsaved changes bar.
                    await Promise.all(this.addresses.map((addr, idx) =>
                        addr.country_id ? this.onAddrCountryChange(idx, false) : Promise.resolve()
                    ));
                    await this.$nextTick();
                    // The tab scope now wraps the doctrine form (the credit
                    // pane's own form comes later in the tree), so the form
                    // is found downward, not upward.
                    (this.$el.closest('form') ?? this.$el.querySelector('form'))
                        ?.dispatchEvent(new Event('v-rebaseline'));
                }
            }
        }
    </script>
    @endpush
</x-admin.shell>
