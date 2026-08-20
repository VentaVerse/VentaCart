@php
    $addressData = $address ? [
        'countryCode' => (string) $address->country_id,
        'state' => $address->state ?? '',
        'city' => $address->city ?? '',
        'district' => $address->district ?? '',
        'barangay' => $address->barangay ?? '',
    ] : [
        'countryCode' => '',
        'state' => '',
        'city' => '',
        'district' => '',
        'barangay' => '',
    ];
    $addressData['phoneCodes'] = $countries->pluck('phone_code', 'id')->all();
@endphp

<x-store-layout :title="$address ? 'Edit Address' : 'Add Address'">
    <x-storefront.region name="content_top" />
    <div class="container-store py-10">
        <h1 class="section-title reveal">{{ $address ? 'EDIT ADDRESS' : 'ADD ADDRESS' }}</h1>

        <div class="account-layout">
            @include('account.partials.sidebar')

            <div class="account-card reveal" x-data="addressForm({{ json_encode($addressData) }})">
                @if ($errors->any())
                    <div class="account-flash-error mb-6">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST"
                      action="{{ $address ? route('account.addresses.update', $address) : route('account.addresses.store') }}">
                    @csrf
                    @if ($address)
                        @method('PUT')
                    @endif

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="account-label">First Name <span class="text-[var(--color-secondary)]">*</span></label>
                                <input type="text" name="first_name" value="{{ old('first_name', $address?->first_name ?? Auth::guard('customer')->user()->first_name) }}" required class="account-input w-full">
                            </div>
                            <div>
                                <label class="account-label">Last Name <span class="text-[var(--color-secondary)]">*</span></label>
                                <input type="text" name="last_name" value="{{ old('last_name', $address?->last_name ?? Auth::guard('customer')->user()->last_name) }}" required class="account-input w-full">
                            </div>
                        </div>


                        {{-- Country --}}
                        <div>
                            <label class="account-label">Country <span class="text-[var(--color-secondary)]">*</span></label>
                            <select name="country_id" x-model="countryCode" @change="onCountryChange()" class="account-input w-full">
                                <option value="">-- Select Country --</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- PH: Province --}}
                        <div x-show="hasProvider">
                            <label class="account-label">Province <span class="text-[var(--color-secondary)]">*</span></label>
                            <select name="state" x-model="state" @change="onProvinceChange()" class="account-input w-full" :class="loadingProvinces ? 'address-select-loading' : ''">
                                <option value="">-- Select Province --</option>
                                <template x-for="p in provinces" :key="p.id">
                                    <option :value="p.name" x-text="p.name"></option>
                                </template>
                            </select>
                        </div>

                        {{-- PH: City --}}
                        <div x-show="hasProvider && provinces.length > 0">
                            <label class="account-label">City / Municipality <span class="text-[var(--color-secondary)]">*</span></label>
                            <select name="city" x-model="city" @change="onCityChange()" class="account-input w-full" :class="loadingCities ? 'address-select-loading' : ''">
                                <option value="">-- Select City --</option>
                                <template x-for="c in cities" :key="c.id">
                                    <option :value="c.name" x-text="c.name"></option>
                                </template>
                            </select>
                        </div>

                        {{-- PH: District (Manila-style cities) --}}
                        <div x-show="hasProvider && districts.length > 0">
                            <label class="account-label">District</label>
                            <select name="district" x-model="district" @change="onDistrictChange()" class="account-input w-full">
                                <option value="">-- Select District --</option>
                                <template x-for="d in districts" :key="d.id">
                                    <option :value="d.name" x-text="d.name"></option>
                                </template>
                            </select>
                        </div>

                        {{-- PH: Barangay --}}
                        <div x-show="hasProvider && barangays.length > 0">
                            <label class="account-label">Barangay</label>
                            <select name="barangay" x-model="barangay" class="account-input w-full" :class="loadingBarangays ? 'address-select-loading' : ''">
                                <option value="">-- Select Barangay --</option>
                                <template x-for="b in barangays" :key="b.id">
                                    <option :value="b.name" x-text="b.name"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Non-PH: State/Zone --}}
                        <div x-show="!hasProvider && countryCode !== ''">
                            <label class="account-label">State / Region</label>
                            <template x-if="zones.length > 0">
                                <select name="state" x-model="state" class="account-input w-full">
                                    <option value="">-- Select State --</option>
                                    <template x-for="z in zones" :key="z.id">
                                        <option :value="z.name" x-text="z.name"></option>
                                    </template>
                                </select>
                            </template>
                            <template x-if="zones.length === 0">
                                <input type="text" name="state" x-model="state" class="account-input w-full" placeholder="State / Region">
                            </template>
                        </div>

                        {{-- Non-PH: City (text input) --}}
                        <div x-show="!hasProvider && countryCode !== ''">
                            <label class="account-label">City <span class="text-[var(--color-secondary)]">*</span></label>
                            <input type="text" name="city" x-model="city" class="account-input w-full">
                        </div>

                        {{-- Address (after location dropdowns) --}}
                        <div>
                            <label class="account-label">Address <span class="text-[var(--color-secondary)]">*</span></label>
                            <input type="text" name="address_1" value="{{ old('address_1', $address?->address_1) }}" required class="account-input w-full" placeholder="House/unit number, street, building">
                        </div>

                        {{-- Postcode --}}
                        <div>
                            <label class="account-label">Postcode / ZIP</label>
                            <input type="text" name="postcode" value="{{ old('postcode', $address?->postcode) }}" class="account-input w-full max-w-[200px]">
                        </div>

                        {{-- Phone --}}
                        <div>
                            <label class="account-label">Mobile Number <span class="text-[var(--color-secondary)]">*</span></label>
                            <div class="flex gap-2">
                                <span class="inline-flex items-center px-3 rounded-md bg-[var(--color-search-bg)] border border-[var(--color-input-border)] text-sm text-[var(--color-text-muted)]"
                                    x-text="window.__phoneCodes?.[countryCode] ? '+' + window.__phoneCodes[countryCode] : '-'"></span>
                                <input type="tel" name="phone" value="{{ old('phone', $address?->phone) }}" class="account-input flex-1" placeholder="Mobile number" minlength="7" maxlength="13" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                        </div>

                        {{-- Default checkbox --}}
                        <label class="flex items-center gap-2 text-sm text-[var(--color-text)] cursor-pointer">
                            <input type="checkbox" name="is_default" value="1"
                                   {{ old('is_default', $address?->is_default) ? 'checked' : '' }}
                                   class="accent-[var(--color-primary)]">
                            Set as default address
                        </label>

                        <div class="flex items-center gap-3 pt-4 border-t border-[var(--color-border)]">
                            <button type="submit" class="account-btn-primary">{{ $address ? 'UPDATE ADDRESS' : 'SAVE ADDRESS' }}</button>
                            <a href="{{ route('account.addresses') }}" class="account-btn-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <x-storefront.region name="content_bottom" />
</x-store-layout>
