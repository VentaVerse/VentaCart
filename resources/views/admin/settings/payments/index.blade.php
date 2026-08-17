<x-admin3.shell title="Payments">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Payments</h1>
            <p class="v3-head__standing">How buyers pay. Where a method may appear lives in its page's Availability tab.</p>
        </div>
    </div>

    {{-- The standard row (owner ruling 2026-08-18): name, then
         Enable/Disable and Configure — no anonymous toggles, no side
         doors. These methods gain Install/Uninstall when wave 2 turns
         them into extensions. --}}
    <div class="v3-settle">
        @if ($noMethodEnabled)
            <div class="v3-notice">
                <p class="v3-notice__title">No payment method is enabled</p>
                <p class="v3-notice__text">The storefront cannot check out until a method below is turned back on.</p>
            </div>
        @endif

        <div class="v3-dir">
            @foreach ($cards as $card)
                <div class="v3-dir__card v3-dir__card--static">
                    <span class="v3-dir__ico"><x-admin3.icon :name="$card['family'] === 'cod' ? 'cart' : 'payments'" /></span>
                    <span class="v3-dir__body">
                        <span class="v3-dir__name">{{ $card['name'] }}</span>
                        <span class="v3-dir__line v3-block">{{ $card['description'] }}</span>
                    </span>
                    <span class="v3-actrow">
                        <form method="POST" action="{{ route($card['enabled'] ? 'admin.payment-settings.disable' : 'admin.payment-settings.enable', $card['family']) }}">
                            @csrf
                            <button type="submit" class="v3-btn v3-btn--secondary v3-btn--sm">{{ $card['enabled'] ? 'Disable' : 'Enable' }}</button>
                        </form>
                        <a href="{{ route('admin.payment-settings.show', $card['family']) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Configure</a>
                    </span>
                </div>
            @endforeach
        </div>
    </div>
</x-admin3.shell>
