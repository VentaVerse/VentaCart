<x-admin3.settings-shell title="Payments" active="payments">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Payments</h1>
            <p class="v3-head__standing">How buyers pay, and where each method is allowed to appear.</p>
        </div>
    </div>

    <div class="v3-settle">
        <div class="v3-dir">
            @foreach ($cards as $card)
                @php $availabilityUrl = route('admin.payment-settings.show', [$card['family'], 'tab' => 'availability']); @endphp
                <div class="v3-dir__card v3-dir__card--static">
                    <span class="v3-dir__ico"><x-admin3.icon :name="$card['family'] === 'cod' ? 'cart' : 'payments'" /></span>
                    <span class="v3-dir__body">
                        <span class="v3-dir__name">{{ $card['name'] }}</span>
                        <span class="v3-dir__line v3-block">{{ $card['description'] }}</span>
                        <a href="{{ $availabilityUrl }}" class="v3-dir__line v3-block v3-dir__sub">
                            @if ($card['restricted'])
                                <span class="v3-badge v3-badge--warn">Restricted</span>
                                <span title="{{ $card['restriction_summary'] }}">{{ Str::limit($card['restriction_summary'], 80) }}</span>
                            @else
                                <span class="v3-badge v3-badge--good">Available everywhere</span>
                            @endif
                        </a>
                    </span>
                    <span class="v3-dir__state">
                        <form method="POST" action="{{ route('admin.settings.update') }}">
                            @csrf
                            <input type="hidden" name="group" value="payment">
                            <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.index') }}">
                            <input type="hidden" name="settings[{{ $card['family'] }}_enabled]" value="{{ $card['enabled'] ? 0 : 1 }}">
                            <button type="submit" class="v3-toggle v3-toggle--btn" role="switch"
                                    aria-checked="{{ $card['enabled'] ? 'true' : 'false' }}"
                                    aria-label="{{ $card['name'] }} enabled">
                                <span class="v3-toggle__track" aria-hidden="true"></span>
                            </button>
                        </form>
                    </span>
                    <span class="v3-actrow">
                        <a href="{{ $availabilityUrl }}" class="v3-btn v3-btn--ghost v3-btn--sm">Availability</a>
                        <a href="{{ route('admin.payment-settings.show', $card['family']) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Manage</a>
                    </span>
                </div>
            @endforeach
        </div>
    </div>
</x-admin3.settings-shell>
