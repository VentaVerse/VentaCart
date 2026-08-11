<x-admin.settings-shell title="Pickup Addresses" active="fulfillment">
    @push('head')
        @vite('extensions/courier-fulfillment/resources/css/fulfillment.css')
    @endpush

    <x-slot:actions>
        <x-admin.button :href="route('admin.fulfillment.pickup-addresses.create')">Add pickup address</x-admin.button>
    </x-slot:actions>

    <div class="space-y-6">

        {{-- A state, not an instruction: without the country row nothing can
             be saved at all, and this names the one page that fixes it. --}}
        @if ($countryMissing)
            <x-admin.empty-state variant="notice" role="status" :title="$countryMissingMessage">
                <a class="v-notice__btn" href="{{ route('admin.localization.index') }}">Localization</a>
            </x-admin.empty-state>
        @endif

        <x-admin.card title="Pickup addresses">
            <x-slot name="headerActions">
                <x-admin.hint label="What the default does" class="v-hint--end">A booking leaves from the default unless another address is picked in the booking window.</x-admin.hint>
            </x-slot>

            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.th class="v-col-pickup">Address</x-admin.table.th>
                    <x-admin.table.th class="v-col-contact">Contact</x-admin.table.th>
                    <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                </x-slot:head>
                @forelse ($addresses as $address)
                    <tr class="v-row">
                        <td class="v-col-pickup px-4 py-2 align-middle">
                            <div class="v-cellstack">
                                <span class="v-lead">
                                    {{ $address->label }}
                                    @if ($address->is_default)
                                        <x-admin.badge tone="success">Default</x-admin.badge>
                                    @endif
                                </span>
                                <span class="v-sub">{{ $address->summary() }}</span>
                            </div>
                        </td>
                        <td class="v-col-contact px-4 py-2 align-middle">
                            <div class="v-cellstack">
                                <span class="v-lead">{{ $address->contact_name }}</span>
                                <span class="v-sub v-num">{{ $address->contact_phone }}</span>
                            </div>
                        </td>
                        <td class="v-col-act">
                            <div class="v-rowactions v-rowactions--end">
                                @unless ($address->is_default)
                                    <form method="POST" action="{{ route('admin.fulfillment.pickup-addresses.default', $address) }}">
                                        @csrf
                                        <x-admin.button type="submit" size="sm" variant="ghost">Make default</x-admin.button>
                                    </form>
                                @endunless
                                <x-admin.button size="sm" variant="ghost" :href="route('admin.fulfillment.pickup-addresses.edit', $address)">Edit</x-admin.button>
                                @if ($addresses->count() > 1)
                                    <x-admin.button type="button" size="sm" variant="ghost" class="v-rowactions__danger"
                                        @click="$dispatch('open-modal', 'delete-pickup-address-{{ $address->id }}')">Delete</x-admin.button>
                                @endif
                            </div>
                            @if ($addresses->count() > 1)
                                <x-admin.confirm-dialog name="delete-pickup-address-{{ $address->id }}" title="Delete pickup address"
                                    message="Delete {{ $address->label }}?{{ $address->is_default ? ' The oldest remaining address becomes the default.' : '' }}"
                                    confirm="Delete"
                                    :action="route('admin.fulfillment.pickup-addresses.destroy', $address)" method="DELETE" />
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">
                            <x-admin.empty-state title="No pickup address yet"
                                description="Bookings refuse until parcels have somewhere to leave from.">
                                <x-admin.button :href="route('admin.fulfillment.pickup-addresses.create')">Add pickup address</x-admin.button>
                            </x-admin.empty-state>
                        </td>
                    </tr>
                @endforelse
            </x-admin.table>
        </x-admin.card>

    </div>
</x-admin.settings-shell>
