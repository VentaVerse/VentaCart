@php
    $rateTest = session('rate_test');
@endphp

<div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Rate Tester</h3>

    <!-- Form -->
    <form action="{{ route('admin.shipping-settings.test') }}" method="POST" class="space-y-4 mb-6">
        @csrf

        <div class="grid grid-cols-2 gap-4">
            <!-- Country -->
            <div>
                <label for="country_id" class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                @php
                    $phCountryId = \App\Models\Country::where('iso2', 'PH')->value('id');
                    $defaultCountryId = old('country_id', $rateTest['input']['country_id'] ?? $phCountryId ?? 1);
                @endphp
                <select
                    id="country_id"
                    name="country_id"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                >
                    @foreach (\App\Models\Country::orderBy('name')->get() as $country)
                        <option value="{{ $country->id }}" @selected($defaultCountryId == $country->id)>
                            {{ $country->name }}
                        </option>
                    @endforeach
                </select>
                @error('country_id')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Province -->
            <div>
                <label for="province" class="block text-sm font-medium text-gray-700 mb-1">Province</label>
                <input
                    type="text"
                    id="province"
                    name="province"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    value="{{ old('province', $rateTest['input']['province'] ?? 'Metro Manila') }}"
                    placeholder="e.g., Metro Manila"
                />
                @error('province')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- City -->
            <div>
                <label for="city" class="block text-sm font-medium text-gray-700 mb-1">City</label>
                <input
                    type="text"
                    id="city"
                    name="city"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    value="{{ old('city', $rateTest['input']['city'] ?? 'Quezon City') }}"
                    placeholder="e.g., Quezon City"
                />
                @error('city')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Postcode -->
            <div>
                <label for="postcode" class="block text-sm font-medium text-gray-700 mb-1">Postcode (optional)</label>
                <input
                    type="text"
                    id="postcode"
                    name="postcode"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    value="{{ old('postcode', $rateTest['input']['postcode'] ?? '') }}"
                    placeholder="e.g., 1108"
                />
                @error('postcode')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Weight -->
            <div>
                <label for="weight" class="block text-sm font-medium text-gray-700 mb-1">Weight (kg)</label>
                <input
                    type="number"
                    id="weight"
                    name="weight"
                    step="0.01"
                    min="0.01"
                    max="1000"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    value="{{ old('weight', $rateTest['input']['weight'] ?? '1.5') }}"
                    required
                />
                @error('weight')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Subtotal -->
            <div>
                <label for="subtotal" class="block text-sm font-medium text-gray-700 mb-1">Subtotal (₱)</label>
                <input
                    type="number"
                    id="subtotal"
                    name="subtotal"
                    step="0.01"
                    min="0"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    value="{{ old('subtotal', $rateTest['input']['subtotal'] ?? '2500') }}"
                    required
                />
                @error('subtotal')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Quantity -->
            <div>
                <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Quantity (optional)</label>
                <input
                    type="number"
                    id="quantity"
                    name="quantity"
                    min="1"
                    max="999"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    value="{{ old('quantity', $rateTest['input']['quantity'] ?? '') }}"
                />
                @error('quantity')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex justify-end">
            <button
                type="submit"
                class="px-4 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
                Test Rates
            </button>
        </div>
    </form>

    <!-- Results Table -->
    @if ($rateTest)
        <div class="mt-6 pt-6 border-t border-gray-200">
            @if ($rateTest['quotes'])
                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-3">{{ $rateTest['explanation'] }}</p>
                    <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Method</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700">Service Type</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-700">Cost</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-700">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($rateTest['quotes'] as $quote)
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900">{{ $quote['name'] }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">
                                        @if ($quote['service_type'] === 'free_shipping')
                                            <span class="text-green-600 font-medium">Free Shipping</span>
                                        @else
                                            {{ $quote['service_type'] ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900 font-mono">
                                        @if ($quote['service_type'] === 'free_shipping')
                                            ₱0.00
                                        @else
                                            ₱{{ number_format($quote['cost'] ?? 0, 2) }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        @if ($quote['key'] === $rateTest['winner'])
                                            <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Winner</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-600 mb-2">{{ $rateTest['explanation'] }}</p>
            @endif
        </div>
    @endif
</div>
