<x-admin-layout>
    <x-slot name="header">{{ $method ? 'Edit Weight-Based Method' : 'New Weight-Based Method' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.shipping-settings.index', ['tab' => 'weight-based']) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">Back to Shipping</a>
    </x-slot>

    <form method="POST"
          action="{{ $method ? route('admin.weight-based-methods.update', $method) : route('admin.weight-based-methods.store') }}"
          x-data="weightMethodForm()">
        @csrf
        @if ($method) @method('PUT') @endif

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-6">
            {{-- Left --}}
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <div>
                        <label for="name" class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Method Name</label>
                        <input type="text" name="name" id="name" required value="{{ old('name', $method?->name) }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="e.g. Metro Manila Standard, Provincial Courier">
                        <p class="text-[11px] text-gray-400 mt-1">This name appears in checkout exactly as you type it</p>
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Weight Tiers --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Weight Tiers</label>
                        <button type="button" @click="addRate()" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs bg-indigo-50 text-indigo-600 rounded-md hover:bg-indigo-100 font-medium">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Add Tier
                        </button>
                    </div>

                    <template x-if="rates.length === 0">
                        <p class="text-sm text-gray-400 text-center py-6">No weight tiers. Click "Add Tier" to define costs by weight range.</p>
                    </template>

                    <template x-if="rates.length > 0">
                        <div class="space-y-2">
                            <div class="grid grid-cols-[1fr_1fr_1fr_40px] gap-2 text-xs text-gray-500 font-medium px-1">
                                <span>From (kg)</span>
                                <span>To (kg)</span>
                                <span>Cost (₱)</span>
                                <span></span>
                            </div>
                            <template x-for="(rate, i) in rates" :key="i">
                                <div class="grid grid-cols-[1fr_1fr_1fr_40px] gap-2 items-center">
                                    <input type="number" :name="'rates['+i+'][weight_from]'" x-model="rate.weight_from" step="0.001" min="0"
                                        class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <input type="number" :name="'rates['+i+'][weight_to]'" x-model="rate.weight_to" step="0.001" min="0"
                                        class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <input type="number" :name="'rates['+i+'][cost]'" x-model="rate.cost" step="0.01" min="0"
                                        class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <button type="button" @click="rates.splice(i, 1)" class="p-1.5 text-gray-400 hover:text-red-600 transition-colors" title="Remove">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Right --}}
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Conditions</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="geo_zone_id" class="block text-sm text-gray-700 mb-1">Geo Zone</label>
                            <select name="geo_zone_id" id="geo_zone_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All zones</option>
                                @foreach ($geoZones as $zone)
                                    <option value="{{ $zone->id }}" {{ old('geo_zone_id', $method?->geo_zone_id) == $zone->id ? 'selected' : '' }}>{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="customer_group_id" class="block text-sm text-gray-700 mb-1">Customer Group</label>
                            <select name="customer_group_id" id="customer_group_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All customers</option>
                                @foreach ($customerGroups as $group)
                                    <option value="{{ $group->id }}" {{ old('customer_group_id', $method?->customer_group_id) == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Settings</h3>
                    <div class="space-y-4">
                        <label class="flex items-center justify-between cursor-pointer">
                            <span class="text-sm text-gray-700">Active</span>
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $method?->is_active ?? true) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                        </label>
                        <div>
                            <label for="sort_order" class="block text-sm text-gray-700 mb-1">Sort Order</label>
                            <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $method?->sort_order ?? 0) }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full px-4 py-3 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                    {{ $method ? 'Update Method' : 'Create Method' }}
                </button>
            </div>
        </div>
    </form>

    @push('scripts')
    <script>
    function weightMethodForm() {
        return {
            rates: @json(old('rates', $method?->rates?->map(fn($r) => ['weight_from' => $r->weight_from, 'weight_to' => $r->weight_to, 'cost' => $r->cost])->toArray() ?? [])),
            addRate() {
                const last = this.rates.length > 0 ? this.rates[this.rates.length - 1] : null;
                this.rates.push({
                    weight_from: last ? last.weight_to : '0.000',
                    weight_to: last ? (parseFloat(last.weight_to) + 5).toFixed(3) : '5.000',
                    cost: last ? last.cost : '0.00',
                });
            },
        };
    }
    </script>
    @endpush
</x-admin-layout>
