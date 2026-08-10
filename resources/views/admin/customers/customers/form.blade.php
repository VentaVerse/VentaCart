<x-admin.shell :title="$customer ? 'Edit Customer' : 'Create Customer'">
    <x-admin.page :title="$customer ? 'Edit Customer' : 'Create Customer'">
        <x-slot:actions>
            <x-admin.button variant="ghost" href="{{ route('admin.customers.index') }}">Back to Customers</x-admin.button>
        </x-slot:actions>

        <form method="POST"
              action="{{ $customer ? route('admin.customers.update', $customer) : route('admin.customers.store') }}"
              x-data="customerForm()"
              data-v-form>
            @csrf
            @if ($customer)
                @method('PUT')
            @endif

            {{-- Tab Navigation. The trough, keyed on aria-selected exactly as
                 x-admin.tabs is, so this set and the shared component are the
                 same control; the Alpine scope stays customerForm()'s own. --}}
            <div class="v-segbar">
                <div class="v-segment" role="tablist">
                    <button type="button" class="v-segment__item" @click="activeTab = 'general'" role="tab"
                        :aria-selected="activeTab === 'general' ? 'true' : 'false'">General</button>
                    <button type="button" class="v-segment__item" @click="activeTab = 'addresses'" role="tab"
                        :aria-selected="activeTab === 'addresses' ? 'true' : 'false'">
                        <span>Addresses</span>
                        <span class="v-segment__count" x-text="addresses.length"></span>
                    </button>
                    @if (isset($customer) && $customer->id)
                        <button type="button" class="v-segment__item" @click="activeTab = 'activity'" role="tab"
                            :aria-selected="activeTab === 'activity' ? 'true' : 'false'">Activity</button>
                    @endif
                </div>
            </div>

            <x-admin.panel flush>
                {{-- ===================== GENERAL ===================== --}}
                <div x-show="activeTab === 'general'" class="v-panel__body space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <input type="hidden" name="store_id" value="1">

                        <x-admin.field name="customer_group_id" label="Customer Group">
                            <x-admin.select name="customer_group_id">
                                <option value="">None</option>
                                @foreach ($customerGroups as $group)
                                    <option value="{{ $group->id }}"
                                        {{ old('customer_group_id', $customer?->customer_group_id) == $group->id ? 'selected' : '' }}>
                                        {{ $group->name }}
                                        @if ($group->is_default) (default) @endif
                                    </option>
                                @endforeach
                            </x-admin.select>
                        </x-admin.field>

                        <x-admin.field name="first_name" label="First Name" required>
                            <x-admin.input type="text" name="first_name" required :value="$customer?->first_name" />
                        </x-admin.field>

                        <x-admin.field name="last_name" label="Last Name" required>
                            <x-admin.input type="text" name="last_name" required :value="$customer?->last_name" />
                        </x-admin.field>

                        <x-admin.field name="email" label="Email" required>
                            <x-admin.input type="email" name="email" required :value="$customer?->email" />
                        </x-admin.field>

                        <x-admin.field name="phone" label="Phone">
                            <x-admin.input type="text" name="phone" :value="$customer?->phone" />
                        </x-admin.field>

                        <x-admin.field name="password" :label="$customer ? 'Password (leave blank to keep current)' : 'Password'" :required="! $customer">
                            <x-admin.input type="password" name="password" {{ $customer ? '' : 'required' }} minlength="8" />
                        </x-admin.field>
                    </div>

                    {{-- Status --}}
                    <div class="border-t border-v-border pt-6">
                        <div class="flex items-center gap-8">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <x-admin.toggle name="is_active" :checked="$customer?->is_active ?? true" />
                                <span class="text-[13px] text-v-text">Active</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <x-admin.toggle name="newsletter" :checked="$customer?->newsletter ?? false" />
                                <span class="text-[13px] text-v-text">Newsletter</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- ===================== ADDRESSES ===================== --}}
                <div x-show="activeTab === 'addresses'" class="v-panel__body">
                    <div class="flex items-center justify-end mb-4">
                        <x-admin.button type="button" variant="primary" size="sm" @click="addAddress()">
                            <x-admin.icon name="plus" class="w-4 h-4" /> Add Address
                        </x-admin.button>
                    </div>

                    <div x-show="addresses.length === 0">
                        <x-admin.empty-state title="No addresses" />
                    </div>

                    <div class="space-y-4">
                        <template x-for="(addr, aIndex) in addresses" :key="aIndex">
                            <div class="border border-v-border rounded-v-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-3">
                                        <h4 class="text-[13px] font-medium text-v-text" x-text="'Address #' + (aIndex + 1)"></h4>
                                        <label class="flex items-center cursor-pointer">
                                            <input type="radio" :name="'addresses['+aIndex+'][is_default]'" value="1"
                                                :checked="addr.is_default"
                                                @click="addresses.forEach((a, i) => a.is_default = (i === aIndex))"
                                                class="text-v-accent focus:ring-v-accent">
                                            <span class="ml-1 text-xs text-v-muted">Default</span>
                                        </label>
                                    </div>
                                    <x-admin.button type="button" size="sm" variant="ghost" class="v-rowactions__danger" @click="addresses.splice(aIndex, 1)" aria-label="Remove address">
                                        <x-admin.icon name="trash" class="w-4 h-4" />
                                    </x-admin.button>
                                </div>

                                <input type="hidden" :name="'addresses['+aIndex+'][id]'" :value="addr.id || ''">
                                <input type="hidden" :name="'addresses['+aIndex+'][is_default]'" :value="addr.is_default ? '1' : '0'">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="v-field">
                                        <label class="v-field__label v-label">First Name</label>
                                        <input type="text" x-model="addr.first_name" :name="'addresses['+aIndex+'][first_name]'"
                                            class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                    </div>
                                    <div class="v-field">
                                        <label class="v-field__label v-label">Last Name</label>
                                        <input type="text" x-model="addr.last_name" :name="'addresses['+aIndex+'][last_name]'"
                                            class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                    </div>
                                    <div class="v-field">
                                        <label class="v-field__label v-label">Country</label>
                                        <select x-model="addr.country_id" :name="'addresses['+aIndex+'][country_id]'"
                                            @change="onAddrCountryChange(aIndex)"
                                            class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                            <option value="">Select Country</option>
                                            @foreach ($countries as $country)
                                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- Provider: Province --}}
                                    <div class="v-field" x-show="addrDropdowns[aIndex]?.hasProvider">
                                        <label class="v-field__label v-label">Province</label>
                                        <select x-model="addr.state" :name="'addresses['+aIndex+'][state]'"
                                            @change="onAddrProvinceChange(aIndex)"
                                            class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                            <option value="">Select Province</option>
                                            <template x-for="p in (addrDropdowns[aIndex]?.provinces || [])" :key="p.id">
                                                <option :value="p.name" x-text="p.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                    {{-- Provider: City --}}
                                    <div class="v-field" x-show="addrDropdowns[aIndex]?.hasProvider && (addrDropdowns[aIndex]?.cities || []).length > 0">
                                        <label class="v-field__label v-label">City / Municipality</label>
                                        <select x-model="addr.city" :name="'addresses['+aIndex+'][city]'"
                                            @change="onAddrCityChange(aIndex)"
                                            class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                            <option value="">Select City</option>
                                            <template x-for="c in (addrDropdowns[aIndex]?.cities || [])" :key="c.id">
                                                <option :value="c.name" x-text="c.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                    {{-- Provider: District --}}
                                    <div class="v-field" x-show="addrDropdowns[aIndex]?.hasProvider && (addrDropdowns[aIndex]?.districts || []).length > 0">
                                        <label class="v-field__label v-label">District</label>
                                        <select x-model="addr.district" :name="'addresses['+aIndex+'][district]'"
                                            @change="onAddrDistrictChange(aIndex)"
                                            class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                            <option value="">Select District</option>
                                            <template x-for="d in (addrDropdowns[aIndex]?.districts || [])" :key="d.id">
                                                <option :value="d.name" x-text="d.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                    {{-- Provider: Barangay --}}
                                    <div class="v-field" x-show="addrDropdowns[aIndex]?.hasProvider && (addrDropdowns[aIndex]?.barangays || []).length > 0">
                                        <label class="v-field__label v-label">Barangay</label>
                                        <select x-model="addr.barangay" :name="'addresses['+aIndex+'][barangay]'"
                                            class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                            <option value="">Select Barangay</option>
                                            <template x-for="b in (addrDropdowns[aIndex]?.barangays || [])" :key="b.id">
                                                <option :value="b.name" x-text="b.name"></option>
                                            </template>
                                        </select>
                                    </div>

                                    {{-- Core: State / Zone --}}
                                    <div class="v-field" x-show="!addrDropdowns[aIndex]?.hasProvider && addr.country_id">
                                        <label class="v-field__label v-label">State / Province</label>
                                        <template x-if="(addrDropdowns[aIndex]?.zones || []).length > 0">
                                            <select x-model="addr.state" :name="'addresses['+aIndex+'][state]'"
                                                class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                <option value="">Select State / Province</option>
                                                <template x-for="z in (addrDropdowns[aIndex]?.zones || [])" :key="z.id">
                                                    <option :value="z.name" x-text="z.name"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="(addrDropdowns[aIndex]?.zones || []).length === 0">
                                            <input type="text" x-model="addr.state" :name="'addresses['+aIndex+'][state]'"
                                                class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                        </template>
                                    </div>
                                    {{-- Core: City --}}
                                    <div class="v-field" x-show="!addrDropdowns[aIndex]?.hasProvider && addr.country_id">
                                        <label class="v-field__label v-label">City</label>
                                        <input type="text" x-model="addr.city" :name="'addresses['+aIndex+'][city]'" required
                                            class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                    </div>
                                    <div class="v-field md:col-span-2">
                                        <label class="v-field__label v-label">Address Line 1</label>
                                        <input type="text" x-model="addr.address_1" :name="'addresses['+aIndex+'][address_1]'" required
                                            class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                    </div>
                                    <div class="v-field">
                                        <label class="v-field__label v-label">Postcode</label>
                                        <input type="text" x-model="addr.postcode" :name="'addresses['+aIndex+'][postcode]'"
                                            class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                    </div>
                                    <div class="v-field">
                                        <label class="v-field__label v-label">Phone</label>
                                        <div class="flex gap-2">
                                            <span class="inline-flex items-center px-3 rounded-v-md border border-v-border bg-v-sunken text-[13px] text-v-muted"
                                                x-text="phoneCodes[addr.country_id] ? '+' + phoneCodes[addr.country_id] : '-'"></span>
                                            <input type="text" x-model="addr.phone" :name="'addresses['+aIndex+'][phone]'"
                                                class="v-control flex-1 h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md"
                                                placeholder="Mobile number" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- ===================== ACTIVITY ===================== --}}
                @if (isset($customer) && $customer->id)
                    {{-- The same feed the day sheet's activity rail runs, at the
                         same density, so one customer's history and the store's
                         are the same list read at two scopes. --}}
                    <div x-show="activeTab === 'activity'" class="py-2">
                        @if ($customer->activities->isEmpty())
                            <x-admin.empty-state title="No activity recorded yet" />
                        @else
                            <ul class="v-feed">
                                @foreach ($customer->activities->take(100) as $activity)
                                    <x-admin.feed.entry :icon="$activity->icon" :tone="$activity->color"
                                        when="{{ $activity->created_at->format('M d, Y H:i') }} · {{ $activity->created_at->diffForHumans() }}">
                                        {{ $activity->description }}
                                    </x-admin.feed.entry>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </x-admin.panel>

            {{-- Submit --}}
            <x-admin.form-actions class="mt-6 rounded-v-lg border border-v-border">
                @if (isset($customer) && $customer->id)
                    <x-admin.button type="button" variant="secondary" class="text-v-warning"
                        @click="$dispatch('open-modal', 'impersonate-customer')">
                        Login as Customer
                    </x-admin.button>
                @endif
                <x-admin.button variant="secondary" href="{{ route('admin.customers.index') }}">Cancel</x-admin.button>
                <x-admin.button type="submit">{{ $customer ? 'Update Customer' : 'Create Customer' }}</x-admin.button>
            </x-admin.form-actions>
        </form>

        @if (isset($customer) && $customer->id)
            <x-admin.confirm-dialog name="impersonate-customer" tone="primary"
                title="Sign in as {{ $customer->first_name }} {{ $customer->last_name }}?"
                message="The storefront opens as this customer, with their cart and their prices. Your admin session stays open, and Stop impersonating returns you to it."
                confirm="Sign in as customer" method="GET"
                :action="route('admin.customers.impersonate', $customer)" />
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

                        // No districts — load barangays directly
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

                    init() {
                        this.addresses.forEach((addr, idx) => {
                            if (addr.country_id) {
                                this.onAddrCountryChange(idx, false);
                            }
                        });
                    }
                }
            }
        </script>
        @endpush
    </x-admin.page>
</x-admin.shell>
