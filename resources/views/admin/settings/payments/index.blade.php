<x-admin3.shell title="Payments">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Payments</h1>
            <p class="v3-head__standing">How buyers pay, and where each method is allowed to appear.</p>
        </div>
    </div>

    <x-admin3.form :action="route('admin.settings.update')" class="v3-settle">
        <input type="hidden" name="group" value="payment">
        <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.index') }}">
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
                        <label class="v3-toggle">
                            <input type="hidden" name="settings[{{ $card['family'] }}_enabled]" value="0">
                            <input type="checkbox" name="settings[{{ $card['family'] }}_enabled]" value="1"
                                   @checked($card['enabled']) aria-label="{{ $card['name'] }} enabled">
                            <span class="v3-toggle__track" aria-hidden="true"></span>
                        </label>
                    </span>
                    <span class="v3-actrow">
                        <a href="{{ $availabilityUrl }}" class="v3-btn v3-btn--ghost v3-btn--sm">Availability</a>
                        <a href="{{ route('admin.payment-settings.show', $card['family']) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Manage</a>
                    </span>
                </div>
            @endforeach
        </div>
    </x-admin3.form>
</x-admin3.shell>
