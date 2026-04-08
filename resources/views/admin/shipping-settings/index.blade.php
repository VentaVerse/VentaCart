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

    <div x-data="{ active: '{{ request('tab', session('tab', 'flat-rate')) }}' }" class="flex flex-col lg:flex-row gap-6">
        {{-- Left sidebar --}}
        <nav class="w-full lg:w-52 lg:shrink-0">
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
                        <input type="hidden" name="_redirect" value="{{ route('admin.shipping-settings.index') }}">
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
                            <input type="hidden" name="_redirect" value="{{ route('admin.shipping-settings.index') }}">
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

                <form method="POST" action="{{ route('admin.weight-based-methods.bulk-update') }}">
                    @csrf @method('PUT')
                    <div class="divide-y divide-gray-100">
                        @forelse ($weightMethods as $wm)
                            <div x-data="{ open: false, rates: {{ json_encode($wm->rates->map(fn($r) => ['weight_from' => $r->weight_from, 'weight_to' => $r->weight_to, 'cost' => $r->cost])) }}, addRate() { const last = this.rates.length > 0 ? this.rates[this.rates.length - 1] : null; this.rates.push({ weight_from: last ? last.weight_to : '0.000', weight_to: last ? (parseFloat(last.weight_to) + 5).toFixed(3) : '5.000', cost: last ? last.cost : '0.00' }); } }">
                                {{-- Method header row --}}
                                <div class="flex items-center gap-3 px-5 py-3 cursor-pointer hover:bg-gray-50 transition-colors" @click="open = !open">
                                    <svg class="w-4 h-4 text-gray-400 transition-transform shrink-0" :class="open && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                    <span class="w-2 h-2 rounded-full shrink-0 {{ $wm->is_active ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                    <span class="text-sm font-medium text-gray-900 flex-1">{{ $wm->admin_name ?: $wm->name }}</span>
                                    <span class="text-xs text-gray-400">{{ $wm->geoZones->isNotEmpty() ? $wm->geoZones->pluck('name')->implode(', ') : 'All zones' }}</span>
                                    <span class="text-xs text-gray-400">{{ $wm->customerGroups->isNotEmpty() ? $wm->customerGroups->pluck('name')->implode(', ') : 'All groups' }}</span>
                                    <span class="text-xs text-gray-400">{{ $wm->rates_count }} {{ Str::plural('tier', $wm->rates_count) }}</span>
                                </div>

                                {{-- Expanded edit --}}
                                <div x-show="open" x-transition class="px-5 pb-5 bg-gray-50/50 border-t border-gray-100">
                                    <div class="pt-4 space-y-4">
                                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Admin Name</label>
                                                <input type="text" name="methods[{{ $wm->id }}][admin_name]" value="{{ $wm->admin_name }}" class="w-full rounded-md border-gray-300 text-sm py-1.5 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Internal reference">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Checkout Name</label>
                                                <input type="text" name="methods[{{ $wm->id }}][name]" value="{{ $wm->name }}" required class="w-full rounded-md border-gray-300 text-sm py-1.5 focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                            <div class="flex items-end gap-3">
                                                <label class="flex items-center gap-2 text-sm pb-1.5">
                                                    <input type="checkbox" name="methods[{{ $wm->id }}][is_active]" value="1" {{ $wm->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 w-3.5 h-3.5">
                                                    Active
                                                </label>
                                                <div class="flex items-center gap-1 pb-1.5">
                                                    <label class="text-xs text-gray-500">Sort</label>
                                                    <input type="number" name="methods[{{ $wm->id }}][sort_order]" value="{{ $wm->sort_order }}" class="w-14 rounded-md border-gray-300 text-sm py-1 text-center focus:border-indigo-500 focus:ring-indigo-500">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Geo Zones</label>
                                                <div class="space-y-1 max-h-32 overflow-y-auto border border-gray-200 rounded-md p-2">
                                                    @foreach ($geoZones as $zone)
                                                        <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                                            <input type="checkbox" name="methods[{{ $wm->id }}][geo_zone_ids][]" value="{{ $zone->id }}"
                                                                {{ $wm->geoZones->contains('id', $zone->id) ? 'checked' : '' }}
                                                                class="rounded border-gray-300 text-indigo-600 w-3.5 h-3.5">
                                                            {{ $zone->name }}
                                                        </label>
                                                    @endforeach
                                                    @if ($geoZones->isEmpty())
                                                        <p class="text-xs text-gray-400">No geo zones</p>
                                                    @endif
                                                </div>
                                                <p class="text-[10px] text-gray-400 mt-1">None = all zones</p>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Customer Groups</label>
                                                <div class="space-y-1 max-h-32 overflow-y-auto border border-gray-200 rounded-md p-2">
                                                    @foreach ($customerGroups as $group)
                                                        <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                                            <input type="checkbox" name="methods[{{ $wm->id }}][customer_group_ids][]" value="{{ $group->id }}"
                                                                {{ $wm->customerGroups->contains('id', $group->id) ? 'checked' : '' }}
                                                                class="rounded border-gray-300 text-indigo-600 w-3.5 h-3.5">
                                                            {{ $group->name }}
                                                        </label>
                                                    @endforeach
                                                    @if ($customerGroups->isEmpty())
                                                        <p class="text-xs text-gray-400">No groups</p>
                                                    @endif
                                                </div>
                                                <p class="text-[10px] text-gray-400 mt-1">None = all customers</p>
                                            </div>
                                        </div>

                                        {{-- Weight tiers --}}
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
                                                                        <input type="number" :name="'methods[{{ $wm->id }}][rates]['+i+'][weight_from]'" x-model="rate.weight_from" step="0.001" min="0" class="w-full rounded-md border-gray-300 text-sm py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                                                    </td>
                                                                    <td class="px-3 py-1.5">
                                                                        <input type="number" :name="'methods[{{ $wm->id }}][rates]['+i+'][weight_to]'" x-model="rate.weight_to" step="0.001" min="0" class="w-full rounded-md border-gray-300 text-sm py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                                                    </td>
                                                                    <td class="px-3 py-1.5">
                                                                        <input type="number" :name="'methods[{{ $wm->id }}][rates]['+i+'][cost]'" x-model="rate.cost" step="0.01" min="0" class="w-full rounded-md border-gray-300 text-sm py-1 focus:border-indigo-500 focus:ring-indigo-500">
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

                                        {{-- Actions --}}
                                        <div class="flex items-center justify-between pt-2 border-t border-gray-200">
                                            <button type="button" onclick="if(confirm('Delete {{ addslashes($wm->admin_name ?: $wm->name) }} and all its tiers?')) document.getElementById('delete-wm-{{ $wm->id }}').submit()"
                                                class="text-red-500 hover:text-red-700 text-xs font-medium">Delete Method</button>
                                            <button type="button" onclick="document.getElementById('duplicate-wm-{{ $wm->id }}').submit()"
                                                class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2"/></svg>
                                                Duplicate
                                            </button>
                                        </div>
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

                    @if ($weightMethods->isNotEmpty())
                        <div class="p-5 border-t border-gray-200">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save All Methods</button>
                        </div>
                    @endif
                </form>

                {{-- Delete & Duplicate forms (outside the bulk form) --}}
                @foreach ($weightMethods as $wm)
                    <form id="delete-wm-{{ $wm->id }}" method="POST" action="{{ route('admin.weight-based-methods.destroy', $wm) }}" class="hidden">
                        @csrf @method('DELETE')
                    </form>
                    <form id="duplicate-wm-{{ $wm->id }}" method="POST" action="{{ route('admin.weight-based-methods.duplicate', $wm) }}" class="hidden">
                        @csrf
                    </form>
                @endforeach
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
                        <input type="hidden" name="_redirect" value="{{ route('admin.shipping-settings.index') }}">
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
            <div x-show="active === 'dph'" x-cloak class="bg-white rounded-lg shadow-sm border border-gray-200"
                 x-data="{ dphTab: '{{ request('dph_tab', 'settings') }}' }">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Deliveries PH</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Domestic shipping via Deliveries PH courier aggregator</p>
                </div>

                {{-- DPH Sub-tabs --}}
                <nav class="flex border-b border-gray-200 px-5">
                    @foreach ([['id' => 'settings', 'label' => 'Settings'], ['id' => 'pickup', 'label' => 'Pickup Address'], ['id' => 'mapping', 'label' => 'Status Mapping'], ['id' => 'explorer', 'label' => 'API Explorer'], ['id' => 'api-logs', 'label' => 'API Logs']] as $dt)
                        <button type="button" @click="dphTab = '{{ $dt['id'] }}'"
                            :class="dphTab === '{{ $dt['id'] }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap px-4 py-2.5 border-b-2 text-xs font-medium transition-colors">{{ $dt['label'] }}</button>
                    @endforeach
                </nav>

                {{-- DPH > Settings --}}
                <div x-show="dphTab === 'settings'" x-cloak class="p-5">
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        <input type="hidden" name="_redirect" value="{{ route('admin.shipping-settings.index') }}">
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

                        {{-- Time Slot Settings --}}
                        <div class="border-t border-gray-200 mt-6 pt-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Default Time Slots</h4>
                            <p class="text-xs text-gray-500 mb-3">Required by some couriers (e.g. NinjaVan). Leave empty if not needed — couriers that don't require it will ignore this.</p>
                            <div class="max-w-xs">
                                <label class="block text-sm font-medium text-gray-700">Pickup Time Slot</label>
                                <select name="settings[dph_pickup_time_slot]" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                    <option value="">Not set</option>
                                    @foreach (['09:00-12:00', '09:00-18:00', '09:00-22:00', '12:00-15:00', '15:00-18:00', '18:00-22:00'] as $slot)
                                        <option value="{{ $slot }}" {{ ($settings['shipping']['dph_pickup_time_slot'] ?? '') === $slot ? 'selected' : '' }}>{{ $slot }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-400 mt-1">Delivery time slot is always 09:00-22:00 (full day).</p>
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
                        <div class="max-w-lg" x-data="{ copied: false }">
                            @if ($settings['shipping']['dph_webhook_secret'] ?? '')
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Secret</label>
                                    <div class="flex gap-2">
                                        <input type="text" x-ref="dphWebhookSecret" value="{{ $settings['shipping']['dph_webhook_secret'] }}" readonly class="block w-full rounded-md border-gray-300 bg-gray-50 text-sm font-mono">
                                        <button type="button" @click="
                                            $refs.dphWebhookSecret.select();
                                            document.execCommand('copy');
                                            window.getSelection().removeAllRanges();
                                            if (navigator.clipboard) { navigator.clipboard.writeText($refs.dphWebhookSecret.value); }
                                            copied = true;
                                            setTimeout(() => copied = false, 2000);
                                        " class="px-3 py-2 bg-gray-100 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-200 border border-gray-300 whitespace-nowrap"
                                            x-text="copied ? 'Copied!' : 'Copy'">Copy</button>
                                    </div>
                                </div>
                            @endif
                            <form method="POST" action="{{ route('admin.settings.generate-dph-secret') }}">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 border border-gray-300">{{ ($settings['shipping']['dph_webhook_secret'] ?? '') ? 'Regenerate' : 'Generate' }} Secret</button>
                            </form>
                        </div>

                        {{-- Cron Job --}}
                        <div class="border-t border-gray-200 mt-6 pt-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Status Polling (Cron Job)</h4>
                            <p class="text-xs text-gray-500 mb-3">Polls DPH every minute for shipment status updates. Runs as a fallback alongside webhooks. Add this to your server's crontab:</p>
                            <div class="relative" x-data="{ cronCopied: false }">
                                <pre class="text-xs bg-gray-900 text-green-400 rounded-lg p-3 font-mono overflow-x-auto">* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</pre>
                                <button type="button" @click="
                                    navigator.clipboard.writeText('* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1');
                                    cronCopied = true; setTimeout(() => cronCopied = false, 2000);
                                " class="absolute top-2 right-2 px-2 py-1 bg-gray-700 text-gray-300 text-xs rounded hover:bg-gray-600"
                                   x-text="cronCopied ? 'Copied!' : 'Copy'"></button>
                            </div>
                            <p class="text-xs text-gray-400 mt-2">Command: <code class="bg-gray-100 px-1.5 py-0.5 rounded text-gray-600">php artisan dph:fetch-statuses</code> — checks all active shipments (excludes DONE, CANCEL, RETURNED).</p>
                        </div>
                    </div>
                </div>

                {{-- DPH > Pickup Address --}}
                <div x-show="dphTab === 'pickup'" x-cloak class="p-5">
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        <input type="hidden" name="_redirect" value="{{ route('admin.shipping-settings.index') }}">
                        <input type="hidden" name="group" value="shipping">
                        <input type="hidden" name="_tab" value="dph">
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Store Pickup Address</h4>
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

                {{-- DPH > Status Mapping --}}
                <div x-show="dphTab === 'mapping'" x-cloak class="p-5">
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Status Mapping</h4>
                    <p class="text-xs text-gray-500 mb-4">Map each DPH shipment status to your order status. When DPH sends a webhook update, the order transitions to the mapped status.</p>

                    <form method="POST" action="{{ route('admin.dph.update-mapping') }}">
                        @csrf
                        <div class="overflow-hidden rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DPH Status</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach ($dphStatuses as $dph)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-5 py-3 text-sm font-medium text-gray-900">{{ $dph->name }}</td>
                                            <td class="px-5 py-3 text-xs font-mono text-gray-500">{{ $dph->code }}</td>
                                            <td class="px-5 py-3">
                                                <select name="mappings[{{ $dph->id }}]" class="w-full rounded-md border-gray-300 text-sm">
                                                    <option value="">— Not mapped —</option>
                                                    @foreach ($orderStatuses as $os)
                                                        <option value="{{ $os->id }}" {{ $dph->order_status_id == $os->id ? 'selected' : '' }}>{{ $os->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Mapping</button>
                        </div>
                    </form>
                </div>

                {{-- DPH > API Explorer --}}
                <div x-show="dphTab === 'explorer'" x-cloak class="p-5" x-data="dphExplorer()">
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">API Explorer</h4>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Endpoint</label>
                            <select x-model="endpoint" @change="loadTemplate()" class="w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                <option value="login">loginV2 (authenticate)</option>
                                <option value="getPartners">getPartners</option>
                                <option value="getScheduledRates">v3/getAvailablePartners (scheduled rates)</option>
                                <option value="getOnDemandRates">v3/getOnDemandQuotation (on-demand rates)</option>
                                <option value="getPost">getPost</option>
                                <option value="createPost">v3/createPost</option>
                                <option value="cancelPost">v3/cancelPost</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Query Params (JSON)</label>
                            <input type="text" x-model="query" class="w-full rounded-md border-gray-300 shadow-sm sm:text-sm font-mono" placeholder="{}">
                        </div>
                        <div class="flex items-end">
                            <button @click="send()" :disabled="loading"
                                class="w-full px-4 py-2 rounded-md text-white text-sm font-medium transition-colors"
                                :class="loading ? 'bg-gray-400' : 'bg-indigo-600 hover:bg-indigo-700'">
                                <span x-show="!loading">Send Request</span>
                                <span x-show="loading">Sending...</span>
                            </button>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Request Body (JSON)</label>
                        <textarea x-model="body" rows="10" class="w-full rounded-md border-gray-300 shadow-sm sm:text-sm font-mono" placeholder="{}"></textarea>
                    </div>

                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-2">
                            <h4 class="text-sm font-medium text-gray-700">Response</h4>
                            <template x-if="lastDuration">
                                <span class="text-xs text-gray-500" x-text="lastDuration + 'ms'"></span>
                            </template>
                        </div>
                        <div x-show="!response && !error" class="text-sm text-gray-400 italic">No response yet.</div>
                        <template x-if="error">
                            <pre class="dph-error-output" x-text="error"></pre>
                        </template>
                        <template x-if="response">
                            <div>
                                <p class="text-xs text-gray-500 mb-2">
                                    <span class="font-medium" x-text="'Endpoint: ' + response.endpoint"></span>
                                    <span x-text="' | ' + response.duration_ms + 'ms'"></span>
                                </p>
                                <h4 class="text-xs font-medium text-gray-600 mb-1">Request Sent:</h4>
                                <pre class="dph-log-output mb-4" x-text="JSON.stringify(response.request, null, 2)"></pre>
                                <h4 class="text-xs font-medium text-gray-600 mb-1">API Response:</h4>
                                <pre class="dph-response-output" x-text="JSON.stringify(response.response, null, 2)"></pre>
                            </div>
                        </template>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <h4 class="text-sm font-medium text-gray-700">API Log (today)</h4>
                            <button @click="refreshLogs()" class="px-3 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 text-gray-700">Refresh</button>
                        </div>
                        <pre class="dph-log-output" x-text="logContent"></pre>
                    </div>
                </div>

                {{-- DPH > API Logs --}}
                <div x-show="dphTab === 'api-logs'" x-cloak class="p-5"
                     x-data="{
                        logs: [], loading: false, pagination: null,
                        filters: { endpoint: '', order_id: '', success: '' },
                        async fetchLogs(page = 1) {
                            this.loading = true;
                            const params = new URLSearchParams({ page });
                            if (this.filters.endpoint) params.set('endpoint', this.filters.endpoint);
                            if (this.filters.order_id) params.set('order_id', this.filters.order_id);
                            if (this.filters.success !== '') params.set('success', this.filters.success);
                            const res = await fetch('{{ route('admin.dph.api-logs') }}?' + params);
                            const data = await res.json();
                            this.logs = data.data;
                            this.pagination = { current: data.current_page, last: data.last_page, total: data.total };
                            this.loading = false;
                        },
                        init() { this.$watch('dphTab', (v) => { if (v === 'api-logs' && !this.logs.length) this.fetchLogs(); }); }
                     }"
                     x-init="if (dphTab === 'api-logs') fetchLogs()">

                    {{-- Filters --}}
                    <div class="flex flex-wrap items-end gap-3 mb-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Endpoint</label>
                            <input type="text" x-model="filters.endpoint" placeholder="e.g. createPost"
                                class="w-40 rounded-lg border-gray-300 text-sm py-1.5">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Order #</label>
                            <input type="text" x-model="filters.order_id" placeholder="e.g. 29802"
                                class="w-28 rounded-lg border-gray-300 text-sm py-1.5">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                            <select x-model="filters.success" class="rounded-lg border-gray-300 text-sm py-1.5">
                                <option value="">All</option>
                                <option value="1">Success</option>
                                <option value="0">Failed</option>
                            </select>
                        </div>
                        <button @click="fetchLogs(1)" class="px-4 py-1.5 bg-gray-800 text-white text-sm font-medium rounded-lg hover:bg-gray-700">Filter</button>
                        <button @click="filters = { endpoint: '', order_id: '', success: '' }; fetchLogs(1)" class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700">Clear</button>
                        <div class="ml-auto text-xs text-gray-400" x-show="pagination" x-text="pagination ? pagination.total + ' entries' : ''"></div>
                    </div>

                    {{-- Loading --}}
                    <div x-show="loading" class="flex items-center justify-center py-12">
                        <svg class="w-5 h-5 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </div>

                    {{-- Log entries --}}
                    <div x-show="!loading" class="space-y-0 border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-200">
                        <template x-for="log in logs" :key="log.id">
                            <div>
                                {{-- Summary row --}}
                                <div class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-sm" @click="log._open = !log._open">
                                    <span class="text-xs text-gray-400 w-36 shrink-0" x-text="new Date(log.created_at).toLocaleString()"></span>
                                    <span class="px-1.5 py-0.5 rounded text-xs font-mono font-medium shrink-0"
                                          :class="log.method === 'POST' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'"
                                          x-text="log.method"></span>
                                    <span class="font-mono text-xs text-gray-700 truncate" x-text="log.endpoint"></span>
                                    <span class="text-xs text-gray-400 shrink-0" x-show="log.order_id" x-text="'#' + log.order_id"></span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium shrink-0 ml-auto"
                                          :class="log.success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                          x-text="log.success ? 'OK' : (log.error_message ? log.error_message.substring(0, 40) : 'Failed')"></span>
                                    <span class="text-xs text-gray-400 shrink-0 w-14 text-right" x-text="log.response_time_ms + 'ms'"></span>
                                    <svg class="w-4 h-4 text-gray-400 transition-transform shrink-0" :class="log._open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                                {{-- Detail panel --}}
                                <div x-show="log._open" x-transition class="bg-gray-50 px-4 py-3 border-t border-gray-100">
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                        <div>
                                            <h5 class="text-xs font-semibold text-gray-500 uppercase mb-1">Request Payload</h5>
                                            <pre class="text-xs bg-white border border-gray-200 rounded-lg p-3 overflow-auto max-h-64 whitespace-pre-wrap break-words" x-text="JSON.stringify(log.request_body, null, 2)"></pre>
                                        </div>
                                        <div>
                                            <h5 class="text-xs font-semibold text-gray-500 uppercase mb-1">Response</h5>
                                            <pre class="text-xs bg-white border border-gray-200 rounded-lg p-3 overflow-auto max-h-64 whitespace-pre-wrap break-words" x-text="JSON.stringify(log.response_body, null, 2)"></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                        {{-- Empty --}}
                        <div x-show="!loading && logs.length === 0" class="text-center py-12 text-gray-400 text-sm">
                            No API logs found. Logs are created when DPH API calls are made (bookings, rate lookups, etc).
                        </div>
                    </div>

                    {{-- Pagination --}}
                    <div x-show="pagination && pagination.last > 1" class="flex items-center justify-between mt-4 pt-3 border-t border-gray-200">
                        <button @click="fetchLogs(pagination.current - 1)" :disabled="pagination.current <= 1"
                            class="px-3 py-1.5 text-xs rounded-lg border border-gray-300 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed">Prev</button>
                        <span class="text-xs text-gray-500" x-text="'Page ' + pagination.current + ' of ' + pagination.last"></span>
                        <button @click="fetchLogs(pagination.current + 1)" :disabled="pagination.current >= pagination.last"
                            class="px-3 py-1.5 text-xs rounded-lg border border-gray-300 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed">Next</button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
    function dphExplorer() {
        const templates = {
            login: { body: {}, query: {} },
            getPartners: { body: {}, query: {} },
            getScheduledRates: {
                body: {
                    pickup: { province: "metro manila", city: "quezon city", barangay: "katipunan" },
                    delivery: { province: "rizal", city: "antipolo city", barangay: "san juan" },
                    dimensions: { length: 10, width: 10, height: 10 },
                    weight: 2
                },
                query: {}
            },
            getOnDemandRates: {
                body: {
                    pickup: { name: "Store", phone: "09123456789", address: "Katipunan, Quezon City", province: "Metro Manila", lat: 14.6308, lng: 121.0753 },
                    delivery: { name: "Customer", phone: "09987654321", address: "BGC, Taguig", lat: 14.5528, lng: 121.0519 },
                    dimensions: { length: 10, width: 10, height: 10 },
                    weight: 2
                },
                query: {}
            },
            getPost: { body: {}, query: { postId: "" } },
            createPost: {
                body: {
                    refNo: "TEST-" + Date.now(),
                    isPSGC: false,
                    pickupDetails: {
                        senderName: "Test Store", contactNumber: "09123456789", emailAddress: "test@example.com",
                        pickupDateTime: new Date(Date.now() + 3600000).toISOString().slice(0, 16),
                        pickupAddress: "Katipunan, Quezon City", pickupCity: "Quezon City",
                        province: "Metro Manila", barangay: "Katipunan", postalCode: "1105"
                    },
                    deliveryDetails: {
                        recipientName: "Test Customer", contactNumber: "09987654321", emailAddress: "customer@example.com",
                        deliveryDateTime: new Date(Date.now() + 86400000).toISOString().slice(0, 16),
                        deliveryAddress: "San Juan, Antipolo City", deliveryCity: "Antipolo City",
                        province: "Rizal", barangay: "San Juan", postalCode: "1870",
                        productSize: "L", itemDescription: "Guitar effects pedal",
                        weight: 2, length: 10, width: 8, height: 4
                    }
                },
                query: { partnerId: "SANDBOX_QA" }
            },
            cancelPost: {
                body: { postId: "", trackingNumber: "", courierId: "" },
                query: {}
            }
        };

        return {
            endpoint: 'login',
            body: '{}',
            query: '{}',
            response: null,
            error: null,
            loading: false,
            lastDuration: null,
            logContent: @json($dphLogs),

            init() { this.loadTemplate(); },

            loadTemplate() {
                this.response = null;
                this.error = null;
                const t = templates[this.endpoint] || { body: {}, query: {} };
                this.body = JSON.stringify(t.body, null, 2);
                this.query = JSON.stringify(t.query, null, 2);
            },

            async send() {
                this.loading = true;
                this.response = null;
                this.error = null;
                this.lastDuration = null;

                try {
                    const res = await fetch('{{ route("admin.dph.execute") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            endpoint: this.endpoint,
                            body: this.body,
                            query: this.query
                        })
                    });

                    const data = await res.json();

                    if (data.success) {
                        this.response = data;
                        this.lastDuration = data.duration_ms;
                    } else {
                        this.error = data.error || JSON.stringify(data, null, 2);
                    }
                } catch (e) {
                    this.error = e.message;
                }

                this.loading = false;
                this.refreshLogs();
            },

            async refreshLogs() {
                try {
                    const res = await fetch('{{ route("admin.dph.logs") }}', {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    this.logContent = data.logs;
                } catch (e) {}
            }
        };
    }
    </script>
    @endpush
</x-admin-layout>
