<x-admin.settings-shell title="Shipping" :wide="true">

        {{--
            Chain strip — numbered in the REAL evaluation order from
            ShippingQuoteService::getProviders(): Free Shipping, Weight Based,
            Flat Rate, DHL. If a free-shipping rule matches, it overrides every
            paid method below it, which is why it has no enable toggle of its
            own — its "on" state is simply "does an active rule exist". Each
            card owns a one-line summary and a "Configure →" link to that
            provider's own dedicated settings page.
        --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-8">
            @foreach ($chain as $i => $provider)
                <x-admin.card>
                    <div class="flex items-start gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-v-sunken text-[13px] font-semibold text-v-secondary">{{ $i + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-[14px] font-semibold text-v-text">{{ $provider['label'] }}</h3>
                                @if ($provider['settingKey'])
                                    <form method="POST" action="{{ route('admin.settings.update') }}">
                                        @csrf
                                        <input type="hidden" name="group" value="shipping">
                                        <input type="hidden" name="_redirect" value="{{ route('admin.shipping-settings.index') }}">
                                        <x-admin.toggle name="settings[{{ $provider['settingKey'] }}]" :checked="$provider['active']"
                                            x-on:change="$el.closest('form').submit()" />
                                    </form>
                                @else
                                    <x-admin.badge :tone="$provider['active'] ? 'success' : 'neutral'">
                                        {{ $provider['active'] ? 'Active' : 'No active rules' }}
                                    </x-admin.badge>
                                @endif
                            </div>
                            <p class="mt-1 text-[13px] leading-snug text-v-secondary">{{ $provider['description'] }}</p>
                            <p class="mt-1.5 text-[11px] font-medium text-v-muted">{{ $provider['summary'] }}</p>
                            @if ($provider['id'] === 'free-shipping')
                                <p class="mt-1 text-[11px] text-v-muted">Overrides every paid method below when a rule matches.</p>
                            @endif
                            <div class="mt-3 flex items-center justify-end">
                                <x-admin.button variant="ghost" size="sm" :href="$provider['route']">Configure →</x-admin.button>
                            </div>
                        </div>
                    </div>
                </x-admin.card>
            @endforeach
        </div>

        <div class="space-y-8">

            {{-- ==================== RATE TESTER ==================== --}}
            @include('admin.settings.shipping.partials.rate-tester')

            {{-- ==================== ORIGIN ==================== --}}
            <x-admin.card id="origin" title="Origin"
                description="Used as the origin for courier rate quotes (DHL) and future fulfillment bookings.">
                <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5">
                    @csrf
                    <input type="hidden" name="group" value="shipping">
                    <input type="hidden" name="_redirect" value="{{ route('admin.shipping-settings.index') }}">

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-admin.field name="settings[pickup_address]" label="Address" class="sm:col-span-2">
                            <x-admin.input name="settings[pickup_address]" :value="$settings['shipping']['pickup_address'] ?? ''" placeholder="Unit / street / building" />
                        </x-admin.field>
                        <x-admin.field name="settings[pickup_barangay]" label="Barangay">
                            <x-admin.input name="settings[pickup_barangay]" :value="$settings['shipping']['pickup_barangay'] ?? ''" />
                        </x-admin.field>
                        <x-admin.field name="settings[pickup_city]" label="City">
                            <x-admin.input name="settings[pickup_city]" :value="$settings['shipping']['pickup_city'] ?? ''" />
                        </x-admin.field>
                        <x-admin.field name="settings[pickup_province]" label="Province">
                            <x-admin.input name="settings[pickup_province]" :value="$settings['shipping']['pickup_province'] ?? ''" />
                        </x-admin.field>
                        <x-admin.field name="settings[pickup_postal_code]" label="Postal code">
                            <x-admin.input name="settings[pickup_postal_code]" :value="$settings['shipping']['pickup_postal_code'] ?? ''" />
                        </x-admin.field>
                        <x-admin.field name="settings[pickup_contact_name]" label="Contact name">
                            <x-admin.input name="settings[pickup_contact_name]" :value="$settings['shipping']['pickup_contact_name'] ?? ''" />
                        </x-admin.field>
                        <x-admin.field name="settings[pickup_contact_number]" label="Contact number">
                            <x-admin.input name="settings[pickup_contact_number]" :value="$settings['shipping']['pickup_contact_number'] ?? ''" placeholder="e.g. 09171234567" />
                        </x-admin.field>
                        <x-admin.field name="settings[pickup_contact_email]" label="Contact email" class="sm:col-span-2">
                            <x-admin.input type="email" name="settings[pickup_contact_email]" :value="$settings['shipping']['pickup_contact_email'] ?? ''" />
                        </x-admin.field>
                    </div>

                    <x-admin.form-actions>
                        <x-admin.button variant="primary" type="submit">Save origin</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>

        </div>
</x-admin.settings-shell>
