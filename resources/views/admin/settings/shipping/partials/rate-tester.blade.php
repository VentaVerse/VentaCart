@php
    $rateTest = session('rate_test');
@endphp

<x-admin.panel title="Rate Tester">
    <form action="{{ route('admin.shipping-settings.test') }}" method="POST">
        @csrf

        <div class="v-grid v-grid--3">
            <x-admin.field name="country_id" label="Country" required>
                <select name="country_id" class="v-control">
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}"
                            @selected(old('country_id', $rateTest['input']['country_id'] ?? $defaultCountryId) == $country->id)>
                            {{ $country->name }}
                        </option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="province" label="Province">
                <input name="province" type="text" class="v-control"
                    value="{{ old('province', $rateTest['input']['province'] ?? 'Metro Manila') }}">
            </x-admin.field>

            <x-admin.field name="city" label="City">
                <input name="city" type="text" class="v-control"
                    value="{{ old('city', $rateTest['input']['city'] ?? 'Quezon City') }}">
            </x-admin.field>

            <x-admin.field name="postcode" label="Postcode">
                <input name="postcode" type="text" class="v-control v-mono"
                    value="{{ old('postcode', $rateTest['input']['postcode'] ?? '') }}">
            </x-admin.field>

            <x-admin.field name="weight" label="Weight (kg)" required>
                <input name="weight" type="number" step="0.01" min="0.01" max="1000" required class="v-control v-mono"
                    value="{{ old('weight', $rateTest['input']['weight'] ?? '1.5') }}">
            </x-admin.field>

            <x-admin.field name="subtotal" label="Subtotal" required>
                <input name="subtotal" type="number" step="0.01" min="0" required class="v-control v-mono"
                    value="{{ old('subtotal', $rateTest['input']['subtotal'] ?? '2500') }}">
            </x-admin.field>

            <x-admin.field name="quantity" label="Quantity">
                <input name="quantity" type="number" min="1" max="999" class="v-control v-mono"
                    value="{{ old('quantity', $rateTest['input']['quantity'] ?? '') }}">
            </x-admin.field>
        </div>

        {{-- A plain action row, not the sticky save bar: quoting rates is
             a verb, and the page's own doctrine form already carries the
             one bar. --}}
        <div class="v-gap v-actrow">
            <button type="submit" class="v-btn v-btn--primary">Test rates</button>
        </div>
    </form>

    @if ($rateTest)
        <div class="v-gap">
            @if ($rateTest['quotes'])
                <p class="v-cell-quiet v-gap-sm">{{ $rateTest['explanation'] }}</p>
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Service type</th>
                                <th class="v-cell-num">Cost</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rateTest['quotes'] as $quote)
                                <tr>
                                    <td>{{ $quote['name'] }}</td>
                                    <td>
                                        @if ($quote['service_type'] === 'free_shipping')
                                            <span class="v-tone-act">Free Shipping</span>
                                        @else
                                            {{ $quote['service_type'] ?? '-' }}
                                        @endif
                                    </td>
                                    <td class="v-cell-num v-mono">
                                        @if ($quote['service_type'] === 'free_shipping')
                                            0.00
                                        @else
                                            {{ number_format($quote['cost'] ?? 0, 2) }}
                                        @endif
                                    </td>
                                    <td>
                                        @if ($quote['key'] === $rateTest['winner'])
                                            <span class="v-badge v-badge--good">Winner</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="v-cell-quiet">{{ $rateTest['explanation'] }}</p>
            @endif
        </div>
    @endif
</x-admin.panel>
