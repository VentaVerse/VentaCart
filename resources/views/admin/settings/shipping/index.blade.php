<x-admin.settings-shell title="Shipping">
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

        {{-- The Origin card that stood here is gone. The store's pickup
             origin is no longer eight flat settings rows edited in two
             places: it is a record the courier-fulfillment module owns
             (Settings > Modules > Courier Fulfillment > Pickup addresses),
             where a store can keep more than one and pick which a parcel
             leaves from at booking time. DHL rate quotes read the default
             one. Linked only while that module is installed, so this page
             stays coherent without it. --}}
        @if (Route::has('admin.fulfillment.pickup-addresses.index'))
            <x-admin.card title="Pickup addresses">
                <x-slot name="headerActions">
                    <x-admin.hint label="Where the origin is used" class="v-hint--end">DHL rate quotes and courier bookings are both measured from the default pickup address.</x-admin.hint>
                    <x-admin.button variant="secondary" size="sm" :href="route('admin.fulfillment.pickup-addresses.index')">Configure</x-admin.button>
                </x-slot>
                @if ($pickupOrigin)
                    <p class="text-[13px] text-v-secondary">{{ $pickupOrigin }}</p>
                @else
                    <x-admin.badge tone="warning">No default pickup address</x-admin.badge>
                @endif
            </x-admin.card>
        @endif

    </div>
</x-admin.settings-shell>
