<x-store-layout title="Address Book">
    <div class="container-store py-10">
        <h1 class="section-title reveal">ADDRESS BOOK</h1>

        <div class="account-layout">
            @include('account.partials.sidebar')

            <div>
                @if (session('success'))
                    <div class="account-flash-success mb-6 reveal">{{ session('success') }}</div>
                @endif

                <div class="flex items-center justify-between mb-6 reveal">
                    <h2 class="account-section-title mb-0">Your Addresses</h2>
                    <a href="{{ route('account.addresses.create') }}" class="account-btn-primary text-sm">ADD NEW</a>
                </div>

                @if ($addresses->isEmpty())
                    <div class="account-card text-center py-10 reveal">
                        <p class="text-[#555] mb-4">You haven't added any addresses yet.</p>
                        <a href="{{ route('account.addresses.create') }}" class="account-btn-primary inline-block">ADD YOUR FIRST ADDRESS</a>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 reveal">
                        @foreach ($addresses as $address)
                            <div class="account-address-card {{ $address->is_default ? 'default' : '' }}" x-data="{ confirmDelete: false }">
                                @if ($address->is_default)
                                    <span class="account-badge-default">DEFAULT</span>
                                @endif

                                <div class="text-sm space-y-0.5 mb-4">
                                    <p class="text-[#E8E8E8] font-medium">{{ $address->first_name }} {{ $address->last_name }}</p>
                                    <p class="text-[#999]">{{ $address->address_1 }}</p>
                                    @if ($address->barangay)
                                        <p class="text-[#999]">{{ $address->barangay }}</p>
                                    @endif
                                    @if ($address->district)
                                        <p class="text-[#999]">{{ $address->district }}</p>
                                    @endif
                                    <p class="text-[#999]">
                                        {{ $address->city }}@if ($address->state), {{ $address->state }}@endif
                                        @if ($address->postcode) {{ $address->postcode }}@endif
                                    </p>
                                    <p class="text-[#777]">{{ $address->country_code }}</p>
                                </div>

                                <div class="flex items-center gap-3 pt-3 border-t border-[#2A2A2A]">
                                    <a href="{{ route('account.addresses.edit', $address) }}" class="account-btn-secondary text-xs">Edit</a>

                                    @if (!$address->is_default)
                                        <form method="POST" action="{{ route('account.addresses.default', $address) }}">
                                            @csrf
                                            <button type="submit" class="account-btn-secondary text-xs">Set Default</button>
                                        </form>
                                    @endif

                                    <div class="ml-auto">
                                        <button @click="confirmDelete = true" x-show="!confirmDelete" class="text-xs text-[#555] hover:text-[var(--color-secondary)] transition-colors">Delete</button>
                                        <div x-show="confirmDelete" class="flex items-center gap-2">
                                            <span class="text-xs text-[#777]">Sure?</span>
                                            <form method="POST" action="{{ route('account.addresses.destroy', $address) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="account-btn-danger text-xs">Yes</button>
                                            </form>
                                            <button @click="confirmDelete = false" class="text-xs text-[#555]">No</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-store-layout>
