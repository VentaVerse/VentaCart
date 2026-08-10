<x-admin.settings-shell title="Payments" :wide="true">
    <div class="v-slates">
        @foreach ($cards as $card)
            @php
                $icon = match ($card['family']) {
                    'bank_transfer' => 'coupons',
                    'hitpay' => 'monitor',
                    'cod' => 'cart',
                    default => 'settings',
                };
                $availabilityUrl = route('admin.payment-settings.show', [$card['family'], 'tab' => 'availability']);
            @endphp
            <x-admin.slate :title="$card['name']" :icon="$icon" :note="$card['description']">
                <x-slot:state>
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        <input type="hidden" name="group" value="payment">
                        <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.index') }}">
                        <x-admin.toggle name="settings[{{ $card['family'] }}_enabled]" :checked="$card['enabled']"
                            aria-label="{{ $card['name'] }} enabled"
                            x-on:change="$el.closest('form').submit()" />
                    </form>
                </x-slot:state>

                <a href="{{ $availabilityUrl }}" class="mt-1 flex w-fit max-w-full items-center gap-1.5">
                    @if ($card['restricted'])
                        <x-admin.badge tone="warning">Restricted</x-admin.badge>
                        <span class="truncate text-xs text-v-muted" title="{{ $card['restriction_summary'] }}">{{ $card['restriction_summary'] }}</span>
                    @else
                        <x-admin.badge tone="success">Available everywhere</x-admin.badge>
                    @endif
                </a>

                <x-slot:actions>
                    <x-admin.button variant="ghost" size="sm" :href="$availabilityUrl">Availability rules</x-admin.button>
                    <x-admin.button variant="ghost" size="sm" :href="route('admin.payment-settings.show', $card['family'])">Manage</x-admin.button>
                </x-slot:actions>
            </x-admin.slate>
        @endforeach
    </div>
</x-admin.settings-shell>
