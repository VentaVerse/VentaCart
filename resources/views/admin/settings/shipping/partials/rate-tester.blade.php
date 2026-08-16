@php
    $rateTest = session('rate_test');
@endphp

<x-admin.card title="Rate Tester">
    <form action="{{ route('admin.shipping-settings.test') }}" method="POST" class="space-y-4">
        @csrf

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-admin.field name="country_id" label="Country" required>
                <x-admin.select name="country_id"
                    value="{{ old('country_id', $rateTest['input']['country_id'] ?? $defaultCountryId) }}">
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}"
                            @selected(old('country_id', $rateTest['input']['country_id'] ?? $defaultCountryId) == $country->id)>
                            {{ $country->name }}
                        </option>
                    @endforeach
                </x-admin.select>
            </x-admin.field>

            <x-admin.field name="province" label="Province">
                <x-admin.input name="province" type="text"
                    value="{{ old('province', $rateTest['input']['province'] ?? 'Metro Manila') }}"
                    />
            </x-admin.field>

            <x-admin.field name="city" label="City">
                <x-admin.input name="city" type="text"
                    value="{{ old('city', $rateTest['input']['city'] ?? 'Quezon City') }}"
                    />
            </x-admin.field>

            <x-admin.field name="postcode" label="Postcode">
                <x-admin.input name="postcode" type="text"
                    value="{{ old('postcode', $rateTest['input']['postcode'] ?? '') }}"
                    />
            </x-admin.field>

            <x-admin.field name="weight" label="Weight (kg)" required>
                <x-admin.input name="weight" type="number" step="0.01" min="0.01" max="1000"
                    value="{{ old('weight', $rateTest['input']['weight'] ?? '1.5') }}" required />
            </x-admin.field>

            <x-admin.field name="subtotal" label="Subtotal (₱)" required>
                <x-admin.input name="subtotal" type="number" step="0.01" min="0"
                    value="{{ old('subtotal', $rateTest['input']['subtotal'] ?? '2500') }}" required />
            </x-admin.field>

            <x-admin.field name="quantity" label="Quantity">
                <x-admin.input name="quantity" type="number" min="1" max="999"
                    value="{{ old('quantity', $rateTest['input']['quantity'] ?? '') }}" />
            </x-admin.field>
        </div>

        {{-- A plain action row, not the sticky form band: this card
             sits above a form that already carries one, and two bars
             pinned to the same edge read as one control that moved. --}}
        <div class="flex justify-end pt-1">
            <x-admin.button variant="primary" type="submit">Test rates</x-admin.button>
        </div>
    </form>

    @if ($rateTest)
        <div class="mt-6 border-t border-v-border pt-6">
            @if ($rateTest['quotes'])
                <p class="text-[13px] text-v-secondary mb-3">{{ $rateTest['explanation'] }}</p>
                <x-admin.table>
                    <x-slot name="head">
                        <x-admin.table.th>Method</x-admin.table.th>
                        <x-admin.table.th>Service Type</x-admin.table.th>
                        <x-admin.table.th align="right">Cost</x-admin.table.th>
                        <x-admin.table.th align="center">Status</x-admin.table.th>
                    </x-slot>
                    @foreach ($rateTest['quotes'] as $quote)
                        <x-admin.table.row>
                            <x-admin.table.cell>{{ $quote['name'] }}</x-admin.table.cell>
                            <x-admin.table.cell>
                                @if ($quote['service_type'] === 'free_shipping')
                                    <span class="text-v-accent font-medium">Free Shipping</span>
                                @else
                                    {{ $quote['service_type'] ?? '-' }}
                                @endif
                            </x-admin.table.cell>
                            <x-admin.table.cell align="right" mono>
                                @if ($quote['service_type'] === 'free_shipping')
                                    ₱0.00
                                @else
                                    ₱{{ number_format($quote['cost'] ?? 0, 2) }}
                                @endif
                            </x-admin.table.cell>
                            <x-admin.table.cell align="center">
                                @if ($quote['key'] === $rateTest['winner'])
                                    <x-admin.badge tone="success">Winner</x-admin.badge>
                                @endif
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @endforeach
                </x-admin.table>
            @else
                <p class="text-[13px] text-v-secondary">{{ $rateTest['explanation'] }}</p>
            @endif
        </div>
    @endif
</x-admin.card>
