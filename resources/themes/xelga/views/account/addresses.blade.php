{{--
    The address book: core's saved addresses, with edit, make default and
    delete (a POST and a DELETE with the CSRF token, as core requires).
    Delete asks once more before it goes; that works without the script.
--}}
@php app(\Themes\Xelga\Rendering\Storefront::class)->page('account'); @endphp
<x-store-layout title="Addresses">
    @include('xelga::partials.fixed.page-assets')
    <div class="s fixed-page scheme-1" data-fixed-page="addresses">
        <div class="s__inner">
            @include('xelga::partials.fixed.crumbs', ['trail' => [['label' => 'Your account', 'url' => route('account.dashboard')], ['label' => 'Addresses']]])
            <div class="acct">
                @include('xelga::partials.fixed.account-nav')
                <div class="acct__main">
                    <header class="acct__head acct__head--row">
                        <h1 class="acct__title">Addresses</h1>
                        <a class="btn btn--sm" href="{{ route('account.addresses.create') }}"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-plus"></use></svg>Add an address</a>
                    </header>
                    @include('xelga::partials.fixed.flash')
                    @if ($addresses->isEmpty())
                        <div class="empty-note">
                            <p class="empty-note__title">No addresses yet</p>
                            <p class="muted">Save a delivery address and checkout fills it in for you.</p>
                            <p><a class="btn" href="{{ route('account.addresses.create') }}">Add your first address</a></p>
                        </div>
                    @else
                        <ul class="addr-cards">
                            @foreach ($addresses as $address)
                                <li class="addr-card{{ $address->is_default ? ' is-default' : '' }}">
                                    @if ($address->is_default)
                                        <span class="pill">Default</span>
                                    @endif
                                    @include('xelga::partials.fixed.address', ['a' => [
                                        'first_name' => $address->first_name, 'last_name' => $address->last_name,
                                        'address_1' => $address->address_1, 'barangay' => $address->barangay,
                                        'district' => $address->district, 'city' => $address->city,
                                        'state' => $address->state, 'postcode' => $address->postcode,
                                        'country' => $address->country?->name, 'phone' => $address->phone_display,
                                    ]])
                                    <div class="addr-card__acts">
                                        <a class="btn btn--secondary btn--sm" href="{{ route('account.addresses.edit', $address) }}" aria-label="Edit the address of {{ $address->first_name }} {{ $address->last_name }}, {{ $address->address_1 }}">Edit</a>
                                        @unless ($address->is_default)
                                            <form method="POST" action="{{ route('account.addresses.default', $address) }}">
                                                @csrf
                                                <button type="submit" class="btn btn--secondary btn--sm">Make default</button>
                                            </form>
                                        @endunless
                                        <details class="cancel addr-card__delete">
                                            <summary class="text-btn">Delete</summary>
                                            <form class="cancel__form" method="POST" action="{{ route('account.addresses.destroy', $address) }}">
                                                @csrf
                                                @method('DELETE')
                                                <p class="muted">Delete this address?</p>
                                                <div class="cancel__acts">
                                                    <button type="submit" class="btn btn--danger btn--sm">Yes, delete it</button>
                                                    <button type="button" class="text-btn" data-close-details>Keep it</button>
                                                </div>
                                            </form>
                                        </details>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-store-layout>
