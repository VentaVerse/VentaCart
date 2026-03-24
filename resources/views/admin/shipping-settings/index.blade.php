<x-admin-layout>
    <x-slot name="header">Shipping</x-slot>

    @php
        $extensions = [
            ['id' => 'flat-rate', 'label' => 'Flat Rate', 'enabled' => ($settings['shipping']['flat_rate_enabled'] ?? '0') === '1'],
            ['id' => 'weight-based', 'label' => 'Weight-Based', 'enabled' => ($settings['shipping']['weight_based_enabled'] ?? '0') === '1'],
            ['id' => 'free-shipping', 'label' => 'Free Shipping', 'enabled' => true],
            ['id' => 'dhl', 'label' => 'DHL Express', 'enabled' => ($settings['shipping']['dhl_enabled'] ?? '0') === '1'],
            ['id' => 'dph', 'label' => 'Deliveries PH', 'enabled' => ($settings['shipping']['dph_enabled'] ?? '0') === '1'],
        ];
    @endphp

    <div x-data="{ active: '{{ request('tab', session('tab', 'flat-rate')) }}' }" class="flex gap-6">
        {{-- Left sidebar --}}
        <nav class="w-52 shrink-0">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Extensions</h3>
                </div>
                @foreach ($extensions as $ext)
                    <button type="button" @click="active = '{{ $ext['id'] }}'"
                        :class="active === '{{ $ext['id'] }}' ? 'bg-indigo-50 text-indigo-700 border-l-2 border-indigo-500' : 'text-gray-600 hover:bg-gray-50 border-l-2 border-transparent'"
                        class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-medium transition-colors text-left">
                        <span>{{ $ext['label'] }}</span>
                        <span class="w-2 h-2 rounded-full shrink-0 {{ $ext['enabled'] ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                    </button>
                @endforeach
            </div>
        </nav>

        {{-- Right content --}}
        <div class="flex-1 min-w-0">

            {{-- ==================== FLAT RATE ==================== --}}
            <div x-show="active === 'flat-rate'" class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between p-5 border-b border-gray-100">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Flat Rate Shipping</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Fixed-cost shipping methods with custom names</p>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.update') }}" class="flex items-center gap-3">
                        @csrf
                        <input type="hidden" name="group" value="shipping">
                        <input type="hidden" name="_tab" value="flat-rate">
                        <input type="hidden" name="settings[flat_rate_enabled]" value="0">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="settings[flat_rate_enabled]" value="1" {{ ($settings['shipping']['flat_rate_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600" onchange="this.form.submit()">
                            Enabled
                        </label>
                    </form>
                </div>
                <div class="p-5">
                    <div class="overflow-hidden rounded-lg border border-gray-200 mb-4">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Geo Zone</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Group</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase w-24">Cost</th>
                                    <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500 uppercase w-16">Status</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 uppercase w-20"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($flatRates as $fr)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $fr->name ?: 'Unnamed' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-500">{{ $fr->geoZone?->name ?? 'All' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-500">{{ $fr->customerGroup?->name ?? 'All' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-700 font-medium">₱{{ number_format($fr->cost, 2) }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="w-2 h-2 rounded-full inline-block {{ $fr->is_active ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <a href="{{ route('admin.flat-rates.edit', $fr) }}" class="text-indigo-600 hover:text-indigo-900 text-xs mr-2">Edit</a>
                                            <form method="POST" action="{{ route('admin.flat-rates.destroy', $fr) }}" class="inline" onsubmit="return confirm('Delete?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">No flat rate methods configured</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <a href="{{ route('admin.flat-rates.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Add Flat Rate
                    </a>
                </div>
            </div>

            {{-- ==================== WEIGHT-BASED ==================== --}}
            <div x-show="active === 'weight-based'" x-cloak class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between p-5 border-b border-gray-100">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Weight-Based Shipping</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Cost tiers based on cart weight. Click a method to expand and edit.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <form method="POST" action="{{ route('admin.settings.update') }}" class="flex items-center gap-3">
                            @csrf
                            <input type="hidden" name="group" value="shipping">
                            <input type="hidden" name="_tab" value="weight-based">
                            <input type="hidden" name="settings[weight_based_enabled]" value="0">
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" name="settings[weight_based_enabled]" value="1" {{ ($settings['shipping']['weight_based_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600" onchange="this.form.submit()">
                                Enabled
                            </label>
                        </form>
                        <a href="{{ route('admin.weight-based-methods.create') }}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Add
                        </a>
                    </div>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse ($weightMethods as $wm)
                        <div x-data="{ open: false }">
                            {{-- Method header row --}}
                            <div class="flex items-center gap-3 px-5 py-3 cursor-pointer hover:bg-gray-50 transition-colors" @click="open = !open">
                                <svg class="w-4 h-4 text-gray-400 transition-transform shrink-0" :class="open && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                <span class="w-2 h-2 rounded-full shrink-0 {{ $wm->is_active ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                <span class="text-sm font-medium text-gray-900 flex-1">{{ $wm->name }}</span>
                                <span class="text-xs text-gray-400">{{ $wm->geoZone?->name ?? 'All zones' }}</span>
                                <span class="text-xs text-gray-400">{{ $wm->customerGroup?->name ?? 'All groups' }}</span>
                                <span class="text-xs text-gray-400">{{ $wm->rates_count }} {{ Str::plural('tier', $wm->rates_count) }}</span>
                            </div>

                            {{-- Expanded edit form --}}
                            <div x-show="open" x-transition class="px-5 pb-5 bg-gray-50/50 border-t border-gray-100">
                                @php
                                    $wmRatesData = $wm->rates->map(function($r) {
                                        return ['weight_from' => $r->weight_from, 'weight_to' => $r->weight_to, 'cost' => $r->cost];
                                    })->toArray();
                                @endphp
                                <form method="POST" action="{{ route('admin.weight-based-methods.update', $wm) }}"
                                      x-data="{ rates: {{ json_encode($wmRatesData) }}, addRate() { const last = this.rates.length > 0 ? this.rates[this.rates.length - 1] : null; this.rates.push({ weight_from: last ? last.weight_to : '0.000', weight_to: last ? (parseFloat(last.weight_to) + 5).toFixed(3) : '5.000', cost: last ? last.cost : '0.00' }); } }">
                                    @csrf @method('PUT')
                                    <div class="pt-4 space-y-4">
                                        {{-- Name + settings row --}}
                                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                                                <input type="text" name="name" value="{{ $wm->name }}" required class="w-full rounded-md border-gray-300 text-sm py-1.5 focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Geo Zone</label>
                                                <select name="geo_zone_id" class="w-full rounded-md border-gray-300 text-sm py-1.5 focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">All zones</option>
                                                    @foreach ($geoZones as $zone)
                                                        <option value="{{ $zone->id }}" {{ $wm->geo_zone_id == $zone->id ? 'selected' : '' }}>{{ $zone->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Customer Group</label>
                                                <select name="customer_group_id" class="w-full rounded-md border-gray-300 text-sm py-1.5 focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">All customers</option>
                                                    @foreach ($customerGroups as $group)
                                                        <option value="{{ $group->id }}" {{ $wm->customer_group_id == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="flex items-end gap-3">
                                                <label class="flex items-center gap-2 text-sm pb-1.5">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input type="checkbox" name="is_active" value="1" {{ $wm->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 w-3.5 h-3.5">
                                                    Active
                                                </label>
                                                <div class="flex items-center gap-1 pb-1.5">
                                                    <label class="text-xs text-gray-500">Sort</label>
                                                    <input type="number" name="sort_order" value="{{ $wm->sort_order }}" class="w-14 rounded-md border-gray-300 text-sm py-1 text-center focus:border-indigo-500 focus:ring-indigo-500">
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Weight tiers table --}}
                                        <div>
                                            <div class="flex items-center justify-between mb-2">
                                                <label class="text-xs font-medium text-gray-600">Weight Tiers</label>
                                                <button type="button" @click="addRate()" class="inline-flex items-center gap-1 px-2 py-1 text-[11px] bg-indigo-50 text-indigo-600 rounded-md hover:bg-indigo-100 font-medium">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                                    Add Tier
                                                </button>
                                            </div>
                                            <template x-if="rates.length > 0">
                                                <div class="overflow-hidden rounded-lg border border-gray-200">
                                                    <table class="min-w-full divide-y divide-gray-200">
                                                        <thead class="bg-gray-50">
                                                            <tr>
                                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">From (kg)</th>
                                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">To (kg)</th>
                                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cost (₱)</th>
                                                                <th class="px-3 py-2 w-8"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-100">
                                                            <template x-for="(rate, i) in rates" :key="i">
                                                                <tr>
                                                                    <td class="px-3 py-1.5">
                                                                        <input type="number" :name="'rates['+i+'][weight_from]'" x-model="rate.weight_from" step="0.001" min="0" class="w-full rounded-md border-gray-300 text-sm py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                                                    </td>
                                                                    <td class="px-3 py-1.5">
                                                                        <input type="number" :name="'rates['+i+'][weight_to]'" x-model="rate.weight_to" step="0.001" min="0" class="w-full rounded-md border-gray-300 text-sm py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                                                    </td>
                                                                    <td class="px-3 py-1.5">
                                                                        <input type="number" :name="'rates['+i+'][cost]'" x-model="rate.cost" step="0.01" min="0" class="w-full rounded-md border-gray-300 text-sm py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                                                    </td>
                                                                    <td class="px-1 py-1.5 text-center">
                                                                        <button type="button" @click="rates.splice(i, 1)" class="p-0.5 text-gray-400 hover:text-red-600" title="Remove">
                                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            </template>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </template>
                                            <template x-if="rates.length === 0">
                                                <p class="text-sm text-gray-400 text-center py-4">No tiers. Click "Add Tier" above.</p>
                                            </template>
                                        </div>

                                        {{-- Save --}}
                                        <div class="flex items-center justify-end pt-2">
                                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save</button>
                                        </div>
                                    </div>
                                </form>
                                {{-- Delete (outside the update form to avoid nested form issue) --}}
                                <div class="px-5 pb-4 flex justify-start">
                                    <form method="POST" action="{{ route('admin.weight-based-methods.destroy', $wm) }}" onsubmit="return confirm('Delete {{ addslashes($wm->name) }} and all its tiers?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Delete Method</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-12 text-center text-gray-400 text-sm">
                            No weight-based methods configured.
                            <a href="{{ route('admin.weight-based-methods.create') }}" class="text-indigo-600 hover:underline">Create your first one</a>.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- ==================== FREE SHIPPING ==================== --}}
            <div x-show="active === 'free-shipping'" x-cloak class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Free Shipping Rules</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Automatically offer free shipping when conditions are met. First matching rule applies.</p>
                </div>
                <div class="p-5">
                    <a href="{{ route('admin.free-shipping-rules.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/></svg>
                        Manage Free Shipping Rules
                    </a>
                </div>
            </div>

            {{-- ==================== DHL EXPRESS ==================== --}}
            <div x-show="active === 'dhl'" x-cloak class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">DHL Express</h3>
                    <p class="text-xs text-gray-500 mt-0.5">International shipments via DHL Express API</p>
                </div>
                <div class="p-5">
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        <input type="hidden" name="group" value="shipping">
                        <input type="hidden" name="_tab" value="dhl">
                        <div class="space-y-4 max-w-lg">
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="hidden" name="settings[dhl_enabled]" value="0">
                                <input type="checkbox" name="settings[dhl_enabled]" value="1" {{ ($settings['shipping']['dhl_enabled'] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                Enable DHL Express
                            </label>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Site ID</label>
                                @if (!empty($maskedKeys['dhl_site_id'] ?? ''))
                                    <p class="text-xs text-gray-500 mb-1">Current: {{ $maskedKeys['dhl_site_id'] }}</p>
                                @endif
                                <input type="text" name="settings[dhl_site_id]" autocomplete="off" placeholder="{{ !empty($maskedKeys['dhl_site_id'] ?? '') ? 'Leave blank to keep current' : 'Enter Site ID' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Password</label>
                                @if (!empty($maskedKeys['dhl_password'] ?? ''))
                                    <p class="text-xs text-gray-500 mb-1">Current: {{ $maskedKeys['dhl_password'] }}</p>
                                @endif
                                <input type="password" name="settings[dhl_password]" autocomplete="new-password" placeholder="{{ !empty($maskedKeys['dhl_password'] ?? '') ? 'Leave blank to keep current' : 'Enter Password' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Account Number</label>
                                <input type="text" name="settings[dhl_account_number]" value="{{ $settings['shipping']['dhl_account_number'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="e.g. 642344995">
                            </div>
                        </div>
                        <div class="mt-6">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save</button>
                        </div>
                    </form>
                    <div class="border-t border-gray-200 mt-6 pt-4">
                        <form method="POST" action="{{ route('admin.settings.test-dhl') }}" class="flex items-center gap-3">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 border border-gray-300">Test Connection</button>
                            <span class="text-xs text-gray-400">Tests a rate quote from Manila to Singapore.</span>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ==================== DPH ==================== --}}
            <div x-show="active === 'dph'" x-cloak class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Deliveries PH</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Domestic shipping via Deliveries PH courier aggregator</p>
                </div>
                <div class="p-5">
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        <input type="hidden" name="group" value="shipping">
                        <input type="hidden" name="_tab" value="dph">
                        <div class="space-y-4 max-w-lg">
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="hidden" name="settings[dph_enabled]" value="0">
                                <input type="checkbox" name="settings[dph_enabled]" value="1" {{ ($settings['shipping']['dph_enabled'] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                Enable Deliveries PH
                            </label>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Mode</label>
                                <select name="settings[dph_mode]" class="mt-1 block w-48 rounded-md border-gray-300 text-sm">
                                    <option value="sandbox" {{ ($settings['shipping']['dph_mode'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                                    <option value="production" {{ ($settings['shipping']['dph_mode'] ?? '') === 'production' ? 'selected' : '' }}>Production</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email</label>
                                @if (!empty($maskedKeys['dph_email'] ?? ''))
                                    <p class="text-xs text-gray-500 mb-1">Current: {{ $maskedKeys['dph_email'] }}</p>
                                @endif
                                <input type="text" name="settings[dph_email]" autocomplete="off" placeholder="{{ !empty($maskedKeys['dph_email'] ?? '') ? 'Leave blank to keep current' : 'Enter email' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Password</label>
                                @if (!empty($maskedKeys['dph_password'] ?? ''))
                                    <p class="text-xs text-gray-500 mb-1">Current: {{ $maskedKeys['dph_password'] }}</p>
                                @endif
                                <input type="password" name="settings[dph_password]" autocomplete="new-password" placeholder="{{ !empty($maskedKeys['dph_password'] ?? '') ? 'Leave blank to keep current' : 'Enter password' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Production URL</label>
                                <p class="text-xs text-gray-500 mb-1">Leave blank to use sandbox.</p>
                                <input type="text" name="settings[dph_production_url]" value="{{ $settings['shipping']['dph_production_url'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            </div>
                        </div>
                        <div class="mt-6">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save</button>
                        </div>
                    </form>
                    <div class="border-t border-gray-200 mt-6 pt-4">
                        <form method="POST" action="{{ route('admin.settings.test-dph') }}" class="flex items-center gap-3">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 border border-gray-300">Test Connection</button>
                            <span class="text-xs text-gray-400">Save credentials first.</span>
                        </form>
                    </div>

                    {{-- Webhook --}}
                    <div class="border-t border-gray-200 mt-6 pt-6">
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Webhook</h4>
                        <div class="max-w-lg">
                            @if ($settings['shipping']['dph_webhook_secret'] ?? '')
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Secret</label>
                                    <div class="flex gap-2">
                                        <input type="text" value="{{ $settings['shipping']['dph_webhook_secret'] }}" readonly class="block w-full rounded-md border-gray-300 bg-gray-50 text-sm font-mono">
                                        <button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value).then(() => this.textContent = 'Copied!')" class="px-3 py-2 bg-gray-100 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-200 border border-gray-300 whitespace-nowrap">Copy</button>
                                    </div>
                                </div>
                            @endif
                            <form method="POST" action="{{ route('admin.settings.generate-dph-secret') }}">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 border border-gray-300">{{ ($settings['shipping']['dph_webhook_secret'] ?? '') ? 'Regenerate' : 'Generate' }} Secret</button>
                            </form>
                        </div>
                    </div>

                    {{-- Pickup Address --}}
                    <div class="border-t border-gray-200 mt-6 pt-6">
                        <form method="POST" action="{{ route('admin.settings.update') }}">
                            @csrf
                            <input type="hidden" name="group" value="shipping">
                            <input type="hidden" name="_tab" value="dph">
                            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Store Pickup Address</h4>
                            <p class="text-xs text-gray-500 mb-4">Used as sender address for DPH and DHL shipments.</p>
                            <div class="space-y-4 max-w-lg">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Contact Name</label>
                                        <input type="text" name="settings[dph_pickup_contact_name]" value="{{ $settings['shipping']['dph_pickup_contact_name'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Contact Number</label>
                                        <input type="text" name="settings[dph_pickup_contact_number]" value="{{ $settings['shipping']['dph_pickup_contact_number'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Contact Email</label>
                                    <input type="email" name="settings[dph_pickup_contact_email]" value="{{ $settings['shipping']['dph_pickup_contact_email'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Street Address</label>
                                    <input type="text" name="settings[dph_pickup_address]" value="{{ $settings['shipping']['dph_pickup_address'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Province</label>
                                        <input type="text" name="settings[dph_pickup_province]" value="{{ $settings['shipping']['dph_pickup_province'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">City</label>
                                        <input type="text" name="settings[dph_pickup_city]" value="{{ $settings['shipping']['dph_pickup_city'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Barangay</label>
                                        <input type="text" name="settings[dph_pickup_barangay]" value="{{ $settings['shipping']['dph_pickup_barangay'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Postal Code</label>
                                        <input type="text" name="settings[dph_pickup_postal_code]" value="{{ $settings['shipping']['dph_pickup_postal_code'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    </div>
                                </div>
                            </div>
                            <div class="mt-6">
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Pickup Address</button>
                            </div>
                        </form>
                    </div>

                    {{-- DPH Explorer --}}
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <a href="{{ route('admin.dph-explorer.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 border border-gray-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z"/></svg>
                            DPH API Explorer
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-admin-layout>
