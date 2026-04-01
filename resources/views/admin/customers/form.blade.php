<x-admin-layout>
    <x-slot name="header">{{ $customer ? 'Edit Customer' : 'Create Customer' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.customers.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back to Customers
        </a>
    </x-slot>

    <form method="POST"
          action="{{ $customer ? route('admin.customers.update', $customer) : route('admin.customers.store') }}"
          x-data="customerForm()"
          class="space-y-6">
        @csrf
        @if ($customer)
            @method('PUT')
        @endif

        {{-- Tab Navigation --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <nav class="flex border-b border-gray-200">
                <button type="button" @click="activeTab = 'general'"
                    :class="activeTab === 'general' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors">General</button>
                <button type="button" @click="activeTab = 'addresses'"
                    :class="activeTab === 'addresses' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors">
                    Addresses
                    <span class="ml-1 text-xs bg-gray-100 text-gray-600 rounded-full px-2 py-0.5" x-text="addresses.length"></span>
                </button>
                @if (isset($customer) && $customer->id)
                    <button type="button" @click="activeTab = 'activity'"
                        :class="activeTab === 'activity' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors">Activity</button>
                @endif
            </nav>

            {{-- ===================== GENERAL ===================== --}}
            <div x-show="activeTab === 'general'" class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="hidden" name="store_id" value="1">

                    <div>
                        <label for="customer_group_id" class="block text-sm font-medium text-gray-700 mb-1">Customer Group</label>
                        <select name="customer_group_id" id="customer_group_id"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">None</option>
                            @foreach ($customerGroups as $group)
                                <option value="{{ $group->id }}"
                                    {{ old('customer_group_id', $customer?->customer_group_id) == $group->id ? 'selected' : '' }}>
                                    {{ $group->name }}
                                    @if ($group->is_default) (default) @endif
                                </option>
                            @endforeach
                        </select>
                        @error('customer_group_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                        <input type="text" name="first_name" id="first_name" required value="{{ old('first_name', $customer?->first_name) }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                        <input type="text" name="last_name" id="last_name" required value="{{ old('last_name', $customer?->last_name) }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" id="email" required value="{{ old('email', $customer?->email) }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone', $customer?->phone) }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                            Password
                            @if ($customer)
                                <span class="text-xs text-gray-400 font-normal">(leave blank to keep current)</span>
                            @endif
                        </label>
                        <input type="password" name="password" id="password" {{ $customer ? '' : 'required' }} minlength="8"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Status --}}
                <div class="border-t border-gray-200 pt-6">
                    <div class="flex items-center space-x-8">
                        <label class="flex items-center">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $customer?->is_active ?? true) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                        <label class="flex items-center">
                            <input type="hidden" name="newsletter" value="0">
                            <input type="checkbox" name="newsletter" value="1" {{ old('newsletter', $customer?->newsletter) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-700">Newsletter</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- ===================== ADDRESSES ===================== --}}
            <div x-show="activeTab === 'addresses'" class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm text-gray-500">Manage customer shipping/billing addresses.</p>
                    <button type="button" @click="addAddress()" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Address
                    </button>
                </div>

                <div x-show="addresses.length === 0" class="text-sm text-gray-400 py-8 text-center">
                    No addresses. Click "Add Address" to create one.
                </div>

                <div class="space-y-4">
                    <template x-for="(addr, aIndex) in addresses" :key="aIndex">
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <h4 class="text-sm font-medium text-gray-700" x-text="'Address #' + (aIndex + 1)"></h4>
                                    <label class="flex items-center">
                                        <input type="radio" :name="'addresses['+aIndex+'][is_default]'" value="1"
                                            :checked="addr.is_default"
                                            @click="addresses.forEach((a, i) => a.is_default = (i === aIndex))"
                                            class="text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-1 text-xs text-gray-500">Default</span>
                                    </label>
                                </div>
                                <button type="button" @click="addresses.splice(aIndex, 1)" class="text-red-500 hover:text-red-700">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>

                            <input type="hidden" :name="'addresses['+aIndex+'][id]'" :value="addr.id || ''">
                            <input type="hidden" :name="'addresses['+aIndex+'][is_default]'" :value="addr.is_default ? '1' : '0'">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">First Name</label>
                                    <input type="text" x-model="addr.first_name" :name="'addresses['+aIndex+'][first_name]'"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Last Name</label>
                                    <input type="text" x-model="addr.last_name" :name="'addresses['+aIndex+'][last_name]'"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Country</label>
                                    <select x-model="addr.country_code" :name="'addresses['+aIndex+'][country_code]'"
                                        @change="onAddrCountryChange(aIndex)"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Select Country</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->iso2 }}">{{ $country->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">State / Province</label>
                                    <template x-if="(addrDropdowns[aIndex]?.zones || []).length > 0">
                                        <select x-model="addr.state" :name="'addresses['+aIndex+'][state]'"
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="">Select State / Province</option>
                                            <template x-for="z in (addrDropdowns[aIndex]?.zones || [])" :key="z.id">
                                                <option :value="z.name" x-text="z.name"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="(addrDropdowns[aIndex]?.zones || []).length === 0">
                                        <input type="text" x-model="addr.state" :name="'addresses['+aIndex+'][state]'"
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </template>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">City</label>
                                    <input type="text" x-model="addr.city" :name="'addresses['+aIndex+'][city]'" required
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Address Line 1</label>
                                    <input type="text" x-model="addr.address_1" :name="'addresses['+aIndex+'][address_1]'" required
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Postcode</label>
                                    <input type="text" x-model="addr.postcode" :name="'addresses['+aIndex+'][postcode]'"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ===================== ACTIVITY ===================== --}}
            @if (isset($customer) && $customer->id)
                <div x-show="activeTab === 'activity'" class="p-6">
                    <div class="space-y-3">
                        @forelse ($customer->activities->take(100) as $activity)
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 {{ $activity->color }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $activity->icon }}"/></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-gray-900">{{ $activity->description }}</p>
                                    <p class="text-xs text-gray-400">{{ $activity->created_at->format('M d, Y H:i') }} &middot; {{ $activity->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 text-center py-8">No activity recorded yet.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>

        {{-- Submit --}}
        <div class="flex justify-end space-x-3">
            @if (isset($customer) && $customer->id)
                <a href="{{ route('admin.customers.impersonate', $customer) }}"
                   class="px-6 py-2.5 bg-amber-500 text-white text-sm font-medium rounded-lg hover:bg-amber-600 transition-colors"
                   onclick="return confirm('You will be logged in as {{ $customer->first_name }} {{ $customer->last_name }} on the storefront.')">
                    Login as Customer
                </a>
            @endif
            <a href="{{ route('admin.customers.index') }}" class="px-6 py-2.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                {{ $customer ? 'Update Customer' : 'Create Customer' }}
            </button>
        </div>
    </form>

    @push('scripts')
    <script>
        function customerForm() {
            return {
                activeTab: 'general',
                addresses: @json($customer?->addresses ?? []),
                addrDropdowns: {},

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
                        country_code: 'PH',
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

                async onAddrCountryChange(idx, clear) {
                    if (clear !== false) {
                        this.addresses[idx].state = '';
                        this.addresses[idx].city = '';
                    }
                    this.addrDropdowns[idx] = { zones: [] };

                    const cc = this.addresses[idx].country_code;
                    if (!cc) return;

                    const res = await fetch(`{{ route('admin.address.zones') }}?country_code=${cc}`);
                    this.updateDropdown(idx, 'zones', await res.json());
                    if (clear === false && this.addresses[idx].state) {
                        await this.$nextTick();
                        await this.syncAddrSelect(idx, 'state');
                    }
                },

                init() {
                    // Load dropdown data for existing addresses
                    this.addresses.forEach((addr, idx) => {
                        if (addr.country_code) {
                            this.onAddrCountryChange(idx, false);
                        }
                    });
                }
            }
        }
    </script>
    @endpush
</x-admin-layout>
