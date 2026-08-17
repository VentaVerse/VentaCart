<x-admin3.shell title="Pickup Addresses">
    @push('head')
        @vite('extensions/courier-fulfillment/resources/css/fulfillment.css')
    @endpush

    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.fulfillment.settings') }}">Courier Fulfillment</a>
                <span aria-hidden="true">/</span>
                <span>Pickup addresses</span>
            </div>
            <h1 class="v3-head__title">Pickup addresses</h1>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.fulfillment.pickup-addresses.create') }}" class="v3-btn v3-btn--primary">Add pickup address</a>
        </div>
    </div>

    <div class="v3-settle">

        {{-- A state, not an instruction: without the country row nothing can
             be saved at all, and this names the one page that fixes it. --}}
        @if ($countryMissing)
            <x-admin3.panel title="Nothing can be saved" role="status">
                <x-slot:acts>
                    <a href="{{ route('admin.localization.index') }}" class="v3-btn v3-btn--primary v3-btn--sm">Localization</a>
                </x-slot:acts>
                <span class="v3-badge v3-badge--warn">{{ $countryMissingMessage }}</span>
            </x-admin3.panel>
        @endif

        <x-admin3.panel title="Pickup addresses" flush
            note="A booking leaves from the default unless another address is picked in the booking window.">
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr><th>Address</th><th>Contact</th><th class="v3-cell-act"><span class="v3-sr">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($addresses as $address)
                            <tr>
                                <td>
                                    <span class="v3-namerow">
                                        <strong>{{ $address->label }}</strong>
                                        @if ($address->is_default)
                                            <span class="v3-badge v3-badge--good">Default</span>
                                        @endif
                                    </span>
                                    <span class="v3-cell-quiet v3-block">{{ $address->summary() }}</span>
                                </td>
                                <td>
                                    {{ $address->contact_name }}
                                    <span class="v3-cell-quiet v3-block v3-mono">{{ $address->contact_phone }}</span>
                                </td>
                                <td class="v3-cell-act">
                                    @unless ($address->is_default)
                                        <button type="submit" form="pickup-default-{{ $address->id }}" class="v3-btn v3-btn--ghost v3-btn--sm">Make default</button>
                                    @endunless
                                    <a href="{{ route('admin.fulfillment.pickup-addresses.edit', $address) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    @if ($addresses->count() > 1)
                                        <button type="button" class="v3-btn v3-btn--danger v3-btn--sm"
                                            @click="$dispatch('v3-confirm', 'delete-pickup-address-{{ $address->id }}')">Delete</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No pickup address yet</p>
                                        <p class="v3-empty__line">Bookings refuse until parcels have somewhere to leave from.</p>
                                        <a href="{{ route('admin.fulfillment.pickup-addresses.create') }}" class="v3-btn v3-btn--primary v3-btn--sm">Add pickup address</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        @foreach ($addresses as $address)
            @unless ($address->is_default)
                <form id="pickup-default-{{ $address->id }}" method="POST" action="{{ route('admin.fulfillment.pickup-addresses.default', $address) }}" hidden>@csrf</form>
            @endunless
            @if ($addresses->count() > 1)
                <x-admin3.confirm name="delete-pickup-address-{{ $address->id }}" title="Delete pickup address"
                    message="Delete {{ $address->label }}?{{ $address->is_default ? ' The oldest remaining address becomes the default.' : '' }}"
                    confirm="Delete"
                    :action="route('admin.fulfillment.pickup-addresses.destroy', $address)" method="DELETE" />
            @endif
        @endforeach

    </div>
</x-admin3.shell>
