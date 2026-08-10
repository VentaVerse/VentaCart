<x-admin.settings-shell title="Shipping" :wide="true">
    <div class="space-y-8">
        {{--
            The chain, numbered in the real evaluation order from
            ShippingQuoteService::getProviders(): Free Shipping,
            Weight Based, Flat Rate, DHL. Free shipping has no enable
            switch of its own because its "on" state is simply whether
            an active rule exists.
        --}}
        <div class="v-slates">
            @foreach ($chain as $i => $provider)
                <x-admin.slate :title="$provider['label']" :number="$i + 1" :note="$provider['description']">
                    <x-slot:state>
                        @if ($provider['settingKey'])
                            <form method="POST" action="{{ route('admin.settings.update') }}">
                                @csrf
                                <input type="hidden" name="group" value="shipping">
                                <input type="hidden" name="_redirect" value="{{ route('admin.shipping-settings.index') }}">
                                <x-admin.toggle name="settings[{{ $provider['settingKey'] }}]" :checked="$provider['active']"
                                    aria-label="{{ $provider['label'] }} enabled"
                                    x-on:change="$el.closest('form').submit()" />
                            </form>
                        @else
                            <x-admin.badge :tone="$provider['active'] ? 'success' : 'neutral'">
                                {{ $provider['active'] ? 'Active' : 'No active rules' }}
                            </x-admin.badge>
                        @endif
                    </x-slot:state>

                    <x-slot:meta>{{ $provider['summary'] }}</x-slot:meta>
                    <x-slot:actions>
                        @if ($provider['id'] === 'free-shipping')
                            {{-- The one consequence its own position in the
                                 chain cannot state: matching does not add a
                                 free option beside the paid ones, it removes
                                 them. --}}
                            <x-admin.hint label="What a match does">A matching free shipping rule replaces every paid method below it, rather than sitting alongside them.</x-admin.hint>
                        @endif
                        <x-admin.button variant="ghost" size="sm" :href="$provider['route']">Configure</x-admin.button>
                    </x-slot:actions>
                </x-admin.slate>
            @endforeach
        </div>

        @include('admin.settings.shipping.partials.rate-tester')

        <x-admin.card id="origin" title="Origin">
            <x-slot name="headerActions">
                <x-admin.hint label="Where the origin is used" class="v-hint--end">Courier rate quotes and fulfillment bookings are both measured from this address.</x-admin.hint>
            </x-slot>
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
