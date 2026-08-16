<x-admin3.settings-shell title="Shipping" active="shipping">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Shipping</h1>
            <p class="v3-head__standing">The rate chain in its real evaluation order. A matching free shipping rule replaces every paid method below it.</p>
        </div>
    </div>

    <div class="v3-settle">
        <div class="v3-dir">
            @foreach ($chain as $i => $provider)
                <div class="v3-dir__card v3-dir__card--static">
                    <span class="v3-dir__ico v3-mono">{{ $i + 1 }}</span>
                    <span class="v3-dir__body">
                        <span class="v3-dir__name">{{ $provider['label'] }}</span>
                        <span class="v3-dir__line v3-block">{{ $provider['description'] }} {{ $provider['summary'] ? '· ' . $provider['summary'] : '' }}</span>
                    </span>
                    <span class="v3-dir__state">
                        @if ($provider['settingKey'])
                            <form method="POST" action="{{ route('admin.settings.update') }}">
                                @csrf
                                <input type="hidden" name="group" value="shipping">
                                <input type="hidden" name="_redirect" value="{{ route('admin.shipping-settings.index') }}">
                                <input type="hidden" name="settings[{{ $provider['settingKey'] }}]" value="{{ $provider['active'] ? 0 : 1 }}">
                                <button type="submit" class="v3-toggle v3-toggle--btn" role="switch"
                                        aria-checked="{{ $provider['active'] ? 'true' : 'false' }}"
                                        aria-label="{{ $provider['label'] }} enabled">
                                    <span class="v3-toggle__track" aria-hidden="true"></span>
                                </button>
                            </form>
                        @else
                            <span class="v3-badge {{ $provider['active'] ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $provider['active'] ? 'Active' : 'No active rules' }}</span>
                        @endif
                    </span>
                    <span class="v3-actrow">
                        <a href="{{ $provider['route'] }}" class="v3-btn v3-btn--ghost v3-btn--sm">Configure</a>
                    </span>
                </div>
            @endforeach
        </div>

        <div class="v3-gap-lg">
            <x-admin3.panel title="Rate tester" note="Runs the whole chain for an address and cart, exactly as checkout would.">
                @php $rateTest = session('rate_test'); @endphp
                <form action="{{ route('admin.shipping-settings.test') }}" method="POST">
                    @csrf
                    <div class="v3-grid v3-grid--3">
                        <x-admin3.field name="country_id" label="Country" required>
                            <select name="country_id" class="v3-control">
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}" @selected(old('country_id', $rateTest['input']['country_id'] ?? $defaultCountryId) == $country->id)>{{ $country->name }}</option>
                                @endforeach
                            </select>
                        </x-admin3.field>
                        <x-admin3.field name="province" label="Province">
                            <input type="text" name="province" class="v3-control"
                                   value="{{ old('province', $rateTest['input']['province'] ?? 'Metro Manila') }}">
                        </x-admin3.field>
                        <x-admin3.field name="city" label="City">
                            <input type="text" name="city" class="v3-control"
                                   value="{{ old('city', $rateTest['input']['city'] ?? 'Quezon City') }}">
                        </x-admin3.field>
                        <x-admin3.field name="postcode" label="Postcode">
                            <input type="text" name="postcode" class="v3-control v3-mono"
                                   value="{{ old('postcode', $rateTest['input']['postcode'] ?? '') }}">
                        </x-admin3.field>
                        <x-admin3.field name="weight" label="Weight (kg)" required>
                            <input type="number" name="weight" step="0.01" min="0.01" max="1000" required class="v3-control v3-mono"
                                   value="{{ old('weight', $rateTest['input']['weight'] ?? '1.5') }}">
                        </x-admin3.field>
                        <x-admin3.field name="subtotal" label="Subtotal (₱)" required>
                            <input type="number" name="subtotal" step="0.01" min="0" required class="v3-control v3-mono"
                                   value="{{ old('subtotal', $rateTest['input']['subtotal'] ?? '2500') }}">
                        </x-admin3.field>
                    </div>
                    <div class="v3-gap v3-inline">
                        <x-admin3.field name="quantity" label="Quantity">
                            <input type="number" name="quantity" min="1" max="999" class="v3-control v3-mono v3-w-rate"
                                   value="{{ old('quantity', $rateTest['input']['quantity'] ?? '') }}">
                        </x-admin3.field>
                        <button type="submit" class="v3-btn v3-btn--primary">Test rates</button>
                    </div>
                </form>

                @if ($rateTest)
                    <div class="v3-gap-lg">
                        <p class="v3-cell-quiet v3-gap-sm">{{ $rateTest['explanation'] }}</p>
                        @if ($rateTest['quotes'])
                            <div class="v3-tablewrap">
                                <table class="v3-table">
                                    <thead>
                                        <tr><th>Method</th><th>Service type</th><th>Cost</th><th><span class="v3-sr">Result</span></th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rateTest['quotes'] as $quote)
                                            <tr>
                                                <td>{{ $quote['name'] }}</td>
                                                <td class="v3-cell-quiet">{{ $quote['service_type'] === 'free_shipping' ? 'Free shipping' : ($quote['service_type'] ?? '-') }}</td>
                                                <td class="v3-mono">₱{{ number_format($quote['service_type'] === 'free_shipping' ? 0 : ($quote['cost'] ?? 0), 2) }}</td>
                                                <td>
                                                    @if ($quote['key'] === $rateTest['winner'])
                                                        <span class="v3-badge v3-badge--good">Winner</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif
            </x-admin3.panel>
        </div>

        @if (Route::has('admin.fulfillment.pickup-addresses.index'))
            <x-admin3.panel title="Pickup addresses" note="DHL rate quotes and courier bookings are both measured from the default pickup address.">
                <x-slot:acts>
                    <a href="{{ route('admin.fulfillment.pickup-addresses.index') }}" class="v3-btn v3-btn--secondary v3-btn--sm">Configure</a>
                </x-slot:acts>
                @if ($pickupOrigin)
                    <p class="v3-cell-quiet">{{ $pickupOrigin }}</p>
                @else
                    <span class="v3-badge v3-badge--warn">No default pickup address</span>
                @endif
            </x-admin3.panel>
        @endif
    </div>
</x-admin3.settings-shell>
