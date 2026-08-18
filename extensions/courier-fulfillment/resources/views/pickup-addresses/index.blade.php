<x-courier-fulfillment::space title="Pickup Addresses" active="pickup-addresses">
    @push('head')
        @vite('extensions/courier-fulfillment/resources/css/fulfillment.css')
    @endpush

    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.fulfillment.settings') }}">Fulfillment</a>
                <span aria-hidden="true">/</span>
                <span>Pickup addresses</span>
            </div>
            <h1 class="v-head__title">Pickup addresses</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.fulfillment.pickup-addresses.create') }}" class="v-btn v-btn--primary">Add pickup address</a>
        </div>
    </div>


    <div class="v-settle">

        {{-- A state, not an instruction: without the country row nothing can
             be saved at all, and this names the one page that fixes it. --}}
        @if ($countryMissing)
            <x-admin.panel title="Nothing can be saved" role="status">
                <x-slot:acts>
                    <a href="{{ route('admin.localization.index') }}" class="v-btn v-btn--primary v-btn--sm">Localization</a>
                </x-slot:acts>
                <span class="v-badge v-badge--warn">{{ $countryMissingMessage }}</span>
            </x-admin.panel>
        @endif

        <x-admin.panel title="Pickup addresses" flush
            note="A booking leaves from the default unless another address is picked in the booking window.">
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr><th>Address</th><th>Contact</th><th class="v-cell-act"><span class="v-sr">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($addresses as $address)
                            <tr>
                                <td>
                                    <span class="v-namerow">
                                        <strong>{{ $address->label }}</strong>
                                        @if ($address->is_default)
                                            <span class="v-badge v-badge--good">Default</span>
                                        @endif
                                    </span>
                                    <span class="v-cell-quiet v-block">{{ $address->summary() }}</span>
                                </td>
                                <td>
                                    {{ $address->contact_name }}
                                    <span class="v-cell-quiet v-block v-mono">{{ $address->contact_phone }}</span>
                                </td>
                                <td class="v-cell-act">
                                    @unless ($address->is_default)
                                        <button type="submit" form="pickup-default-{{ $address->id }}" class="v-btn v-btn--ghost v-btn--sm">Make default</button>
                                    @endunless
                                    <a href="{{ route('admin.fulfillment.pickup-addresses.edit', $address) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    @if ($addresses->count() > 1)
                                        <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                            @click="$dispatch('v-confirm', 'delete-pickup-address-{{ $address->id }}')">Delete</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No pickup address yet</p>
                                        <p class="v-empty__line">Bookings refuse until parcels have somewhere to leave from.</p>
                                        <a href="{{ route('admin.fulfillment.pickup-addresses.create') }}" class="v-btn v-btn--primary v-btn--sm">Add pickup address</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        @foreach ($addresses as $address)
            @unless ($address->is_default)
                <form id="pickup-default-{{ $address->id }}" method="POST" action="{{ route('admin.fulfillment.pickup-addresses.default', $address) }}" hidden>@csrf</form>
            @endunless
            @if ($addresses->count() > 1)
                <x-admin.confirm name="delete-pickup-address-{{ $address->id }}" title="Delete pickup address"
                    message="Delete {{ $address->label }}?{{ $address->is_default ? ' The oldest remaining address becomes the default.' : '' }}"
                    confirm="Delete"
                    :action="route('admin.fulfillment.pickup-addresses.destroy', $address)" method="DELETE" />
            @endif
        @endforeach

    </div>
</x-courier-fulfillment::space>
