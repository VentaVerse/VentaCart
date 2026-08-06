<x-admin.shell title="Payments">
    <x-admin.page title="Payments" :breadcrumb="[['label' => 'Settings', 'url' => route('admin.settings.index')]]">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($cards as $card)
                @php
                    $icon = match ($card['family']) {
                        'bank_transfer' => 'coupons',
                        'hitpay' => 'monitor',
                        'cod' => 'cart',
                        default => 'settings',
                    };
                @endphp
                <x-admin.card>
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-v-md bg-v-sunken text-v-secondary">
                            <x-admin.icon :name="$icon" class="w-5 h-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-[14px] font-semibold text-v-text">{{ $card['name'] }}</h3>
                                <form method="POST" action="{{ route('admin.settings.update') }}">
                                    @csrf
                                    <input type="hidden" name="group" value="payment">
                                    <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.index') }}">
                                    <label class="flex items-center gap-2 text-[13px] text-v-secondary cursor-pointer">
                                        <x-admin.toggle name="settings[{{ $card['family'] }}_enabled]" :checked="$card['enabled']"
                                            x-on:change="$el.closest('form').submit()" />
                                    </label>
                                </form>
                            </div>
                            <p class="mt-1 text-[13px] leading-snug text-v-secondary">{{ $card['description'] }}</p>
                            <div class="mt-3">
                                @if ($card['restricted'])
                                    <div class="flex items-center gap-1.5">
                                        <x-admin.badge tone="warning">Restricted</x-admin.badge>
                                        <span class="truncate text-xs text-v-muted" title="{{ $card['restriction_summary'] }}">{{ $card['restriction_summary'] }}</span>
                                    </div>
                                @else
                                    <x-admin.badge tone="success">Available everywhere</x-admin.badge>
                                @endif
                                <div class="mt-3 flex items-center justify-end">
                                    <x-admin.button variant="ghost" size="sm" :href="route('admin.payment-settings.show', $card['family'])">Manage →</x-admin.button>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-admin.card>
            @endforeach
        </div>
    </x-admin.page>
</x-admin.shell>
