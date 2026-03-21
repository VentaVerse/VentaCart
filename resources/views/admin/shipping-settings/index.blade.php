<x-admin-layout>
    <x-slot name="header">Shipping</x-slot>

    <div x-data="{ activeTab: '{{ request('tab', session('tab', 'flat-rate')) }}' }">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <nav class="flex border-b border-gray-200 overflow-x-auto">
                @foreach ([['id' => 'flat-rate', 'label' => 'Flat Rate'], ['id' => 'weight-based', 'label' => 'Weight-Based'], ['id' => 'free-shipping', 'label' => 'Free Shipping'], ['id' => 'dhl', 'label' => 'DHL Express'], ['id' => 'dph', 'label' => 'Deliveries PH']] as $t)
                    <button type="button" @click="activeTab = '{{ $t['id'] }}'"
                        :class="activeTab === '{{ $t['id'] }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors">{{ $t['label'] }}</button>
                @endforeach
            </nav>

            {{-- ==================== FLAT RATE ==================== --}}
            <div x-show="activeTab === 'flat-rate'" class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Flat Rate Shipping</h3>
                        <p class="text-xs text-gray-500 mt-1">Set a fixed shipping cost. Optionally assign to a specific geo zone for zone-based flat rates.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        <input type="hidden" name="group" value="shipping">
                        <input type="hidden" name="_tab" value="flat-rate">
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="settings[flat_rate_enabled]" value="0">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="settings[flat_rate_enabled]" value="1" {{ ($settings['shipping']['flat_rate_enabled'] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                Enabled
                            </label>
                            <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-xs rounded-lg hover:bg-indigo-700">Save</button>
                        </div>
                    </form>
                </div>

                {{-- Flat Rate entries table --}}
                <div class="overflow-hidden rounded-lg border border-gray-200 mb-4">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Geo Zone</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-32">Cost (₱)</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-24">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-24">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($flatRates as $fr)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 text-sm text-gray-900">{{ $fr->geoZone?->name ?? 'All Zones (Global)' }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-700 font-medium">₱{{ number_format($fr->cost, 2) }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $fr->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $fr->is_active ? 'Active' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <form method="POST" action="{{ route('admin.flat-rates.destroy', $fr) }}" class="inline" onsubmit="return confirm('Delete this rate?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-400">No flat rates configured. Add one below.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Add flat rate --}}
                <div class="border-t border-gray-200 pt-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Add Flat Rate</h4>
                    <form method="POST" action="{{ route('admin.flat-rates.store') }}" class="flex items-end gap-3">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Geo Zone</label>
                            <select name="geo_zone_id" class="rounded-md border-gray-300 text-sm">
                                <option value="">All Zones (Global)</option>
                                @foreach ($geoZones as $gz)
                                    <option value="{{ $gz->id }}">{{ $gz->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Cost (₱)</label>
                            <input type="number" name="cost" required min="0" step="0.01" class="w-32 rounded-md border-gray-300 text-sm" placeholder="150">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Add</button>
                    </form>
                </div>
            </div>

            {{-- ==================== WEIGHT-BASED ==================== --}}
            <div x-show="activeTab === 'weight-based'" x-cloak class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Weight-Based Shipping</h3>
                        <p class="text-xs text-gray-500 mt-1">Set shipping rates based on cart weight per geo zone. Geo zones are managed in <a href="{{ route('admin.localization.index', ['tab' => 'geo-zones']) }}" class="text-indigo-600 hover:underline">Localization</a>.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        <input type="hidden" name="group" value="shipping">
                        <input type="hidden" name="_tab" value="weight-based">
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="settings[weight_based_enabled]" value="0">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="settings[weight_based_enabled]" value="1" {{ ($settings['shipping']['weight_based_enabled'] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                Enabled
                            </label>
                            <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-xs rounded-lg hover:bg-indigo-700">Save</button>
                        </div>
                    </form>
                </div>

                {{-- Per-zone rate tables --}}
                @foreach ($geoZones as $gz)
                    @php $zoneRates = $weightRates->where('geo_zone_id', $gz->id); @endphp
                    <div class="mb-6 border border-gray-200 rounded-lg overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center justify-between">
                            <div>
                                <span class="text-sm font-semibold text-gray-800">{{ $gz->name }}</span>
                                <span class="text-xs text-gray-500 ml-2">({{ $zoneRates->count() }} rate{{ $zoneRates->count() !== 1 ? 's' : '' }})</span>
                            </div>
                            <a href="{{ route('admin.shipping-zones.edit', $gz) }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Edit Rates</a>
                        </div>
                        @if ($zoneRates->isNotEmpty())
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50/50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs text-gray-500">Weight From</th>
                                        <th class="px-4 py-2 text-left text-xs text-gray-500">Weight To</th>
                                        <th class="px-4 py-2 text-left text-xs text-gray-500">Cost</th>
                                        <th class="px-4 py-2 text-left text-xs text-gray-500">Free Threshold</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($zoneRates as $rate)
                                        <tr>
                                            <td class="px-4 py-1.5 text-sm text-gray-700">{{ $rate->weight_from }} kg</td>
                                            <td class="px-4 py-1.5 text-sm text-gray-700">{{ $rate->weight_to }} kg</td>
                                            <td class="px-4 py-1.5 text-sm text-gray-700 font-medium">₱{{ number_format($rate->cost, 2) }}</td>
                                            <td class="px-4 py-1.5 text-sm text-gray-400">{{ $rate->free_shipping_threshold ? '₱' . number_format($rate->free_shipping_threshold, 2) : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="px-4 py-4 text-sm text-gray-400 text-center">No rates configured. <a href="{{ route('admin.shipping-zones.edit', $gz) }}" class="text-indigo-600 hover:underline">Add rates</a></div>
                        @endif
                    </div>
                @endforeach

                @if ($geoZones->isEmpty())
                    <div class="text-center py-8 text-gray-400">
                        No geo zones configured. <a href="{{ route('admin.localization.index', ['tab' => 'geo-zones']) }}" class="text-indigo-600 hover:underline">Create zones in Localization</a> first.
                    </div>
                @endif
            </div>

            {{-- ==================== FREE SHIPPING ==================== --}}
            <div x-show="activeTab === 'free-shipping'" x-cloak class="p-6">
                <p class="text-sm text-gray-500 mb-4">Create rules to automatically offer free shipping when conditions are met. Multiple rules can exist — the first matching rule applies.</p>
                <a href="{{ route('admin.free-shipping-rules.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/></svg>
                    Manage Free Shipping Rules
                </a>
            </div>

            {{-- ==================== DHL EXPRESS ==================== --}}
            <div x-show="activeTab === 'dhl'" x-cloak class="p-6">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="shipping">
                    <input type="hidden" name="_tab" value="dhl">
                    <div class="space-y-4 max-w-lg">
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="settings[dhl_enabled]" value="0">
                            <input type="checkbox" name="settings[dhl_enabled]" value="1"
                                {{ ($settings['shipping']['dhl_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600">
                            <label class="text-sm text-gray-700">Enable DHL Express</label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Site ID</label>
                            @if (!empty($maskedKeys['dhl_site_id'] ?? ''))
                                <p class="text-xs text-gray-500 mb-1">Current: {{ $maskedKeys['dhl_site_id'] }}</p>
                            @endif
                            <input type="text" name="settings[dhl_site_id]" autocomplete="off"
                                placeholder="{{ !empty($maskedKeys['dhl_site_id'] ?? '') ? 'Leave blank to keep current' : 'Enter Site ID' }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Password</label>
                            @if (!empty($maskedKeys['dhl_password'] ?? ''))
                                <p class="text-xs text-gray-500 mb-1">Current: {{ $maskedKeys['dhl_password'] }}</p>
                            @endif
                            <input type="password" name="settings[dhl_password]" autocomplete="new-password"
                                placeholder="{{ !empty($maskedKeys['dhl_password'] ?? '') ? 'Leave blank to keep current' : 'Enter Password' }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Account Number</label>
                            <input type="text" name="settings[dhl_account_number]"
                                value="{{ $settings['shipping']['dhl_account_number'] ?? '' }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="e.g. 642344995">
                        </div>
                        <p class="text-xs text-gray-500">DHL Express is for international shipments only. Origin address is taken from the DPH Pickup Address.</p>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save DHL Settings</button>
                    </div>
                </form>
                <div class="border-t border-gray-200 mt-6 pt-6">
                    <form method="POST" action="{{ route('admin.settings.test-dhl') }}" class="flex items-center gap-3">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 border border-gray-300">Test Connection</button>
                        <span class="text-xs text-gray-400">Tests a rate quote from Manila to Singapore.</span>
                    </form>
                </div>
            </div>

            {{-- ==================== DPH ==================== --}}
            <div x-show="activeTab === 'dph'" x-cloak class="p-6">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="shipping">
                    <input type="hidden" name="_tab" value="dph">
                    <div class="space-y-4 max-w-lg">
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="settings[dph_enabled]" value="0">
                            <input type="checkbox" name="settings[dph_enabled]" value="1" {{ ($settings['shipping']['dph_enabled'] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            <label class="text-sm text-gray-700">Enable Deliveries PH</label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Mode</label>
                            <select name="settings[dph_mode]" class="mt-1 block w-48 rounded-md border-gray-300 shadow-sm sm:text-sm">
                                <option value="sandbox" {{ ($settings['shipping']['dph_mode'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                                <option value="production" {{ ($settings['shipping']['dph_mode'] ?? '') === 'production' ? 'selected' : '' }}>Production</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            @if (!empty($maskedKeys['dph_email'] ?? ''))
                                <p class="text-xs text-gray-500 mb-1">Current: {{ $maskedKeys['dph_email'] }}</p>
                            @endif
                            <input type="text" name="settings[dph_email]" autocomplete="off" placeholder="{{ !empty($maskedKeys['dph_email'] ?? '') ? 'Leave blank to keep current' : 'Enter email' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Password</label>
                            @if (!empty($maskedKeys['dph_password'] ?? ''))
                                <p class="text-xs text-gray-500 mb-1">Current: {{ $maskedKeys['dph_password'] }}</p>
                            @endif
                            <input type="password" name="settings[dph_password]" autocomplete="new-password" placeholder="{{ !empty($maskedKeys['dph_password'] ?? '') ? 'Leave blank to keep current' : 'Enter password' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Production URL</label>
                            <p class="text-xs text-gray-500 mb-1">Leave blank to use sandbox URL.</p>
                            <input type="text" name="settings[dph_production_url]" value="{{ $settings['shipping']['dph_production_url'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save DPH Settings</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.settings.test-dph') }}" class="mt-3 flex items-center gap-3">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 border border-gray-300">Test Connection</button>
                    <span class="text-xs text-gray-400">Save credentials first, then test.</span>
                </form>

                {{-- Webhook --}}
                <div class="border-t border-gray-200 mt-8 pt-6">
                    <h4 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Webhook</h4>
                    <div class="max-w-lg">
                        @if ($settings['shipping']['dph_webhook_secret'] ?? '')
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Webhook Secret</label>
                                <div class="flex gap-2">
                                    <input type="text" value="{{ $settings['shipping']['dph_webhook_secret'] }}" readonly class="block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm sm:text-sm font-mono">
                                    <button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value).then(() => this.textContent = 'Copied!')" class="px-3 py-2 bg-gray-100 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-200 border border-gray-300 whitespace-nowrap">Copy</button>
                                </div>
                            </div>
                        @endif
                        <form method="POST" action="{{ route('admin.settings.generate-dph-secret') }}">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 border border-gray-300">{{ ($settings['shipping']['dph_webhook_secret'] ?? '') ? 'Regenerate' : 'Generate' }} Webhook Secret</button>
                        </form>
                    </div>
                </div>

                {{-- Pickup Address --}}
                <div class="border-t border-gray-200 mt-8 pt-6">
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        <input type="hidden" name="group" value="shipping">
                        <input type="hidden" name="_tab" value="dph">
                        <h4 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Store Pickup Address</h4>
                        <p class="text-xs text-gray-500 mb-4">Used as the pickup/sender address when booking DPH shipments.</p>
                        <div class="space-y-4 max-w-lg">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Contact Name</label>
                                    <input type="text" name="settings[dph_pickup_contact_name]" value="{{ $settings['shipping']['dph_pickup_contact_name'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Contact Number</label>
                                    <input type="text" name="settings[dph_pickup_contact_number]" value="{{ $settings['shipping']['dph_pickup_contact_number'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Contact Email</label>
                                <input type="email" name="settings[dph_pickup_contact_email]" value="{{ $settings['shipping']['dph_pickup_contact_email'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Street Address</label>
                                <input type="text" name="settings[dph_pickup_address]" value="{{ $settings['shipping']['dph_pickup_address'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Province</label>
                                    <input type="text" name="settings[dph_pickup_province]" value="{{ $settings['shipping']['dph_pickup_province'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">City</label>
                                    <input type="text" name="settings[dph_pickup_city]" value="{{ $settings['shipping']['dph_pickup_city'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Barangay</label>
                                    <input type="text" name="settings[dph_pickup_barangay]" value="{{ $settings['shipping']['dph_pickup_barangay'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Postal Code</label>
                                    <input type="text" name="settings[dph_pickup_postal_code]" value="{{ $settings['shipping']['dph_pickup_postal_code'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                </div>
                            </div>
                        </div>
                        <div class="mt-6">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Pickup Address</button>
                        </div>
                    </form>
                </div>

                {{-- DPH Explorer link --}}
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.dph-explorer.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 border border-gray-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z"/></svg>
                        DPH API Explorer
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
