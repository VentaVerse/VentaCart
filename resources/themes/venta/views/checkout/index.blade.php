@php
    $addressesJson = $addresses->toJson();
    $defaultAddressId = $defaultAddress?->id;
    $itemsJson = $items->toJson();
    $shippingMethodsJson = json_encode($shippingMethods);
    $paymentMethodsData = collect($paymentMethods)->map(function($m) {
        return [
            'key' => $m->getKey(),
            'name' => $m->getName(),
            'description' => $m->getDescription(),
            'image' => $m->getImage(),
        ];
    })->values()->toJson();
    $countriesJson = $countries->toJson();
    $phoneCodesJson = $countries->pluck('phone_code', 'id')->toJson();
    $countryNamesJson = $countries->pluck('name', 'id')->toJson();
    $currencySymbol = $defaultCurrency?->symbol ?? '₱';
    $couponCodeJson = json_encode($couponCode ?? '');
    $couponDiscountVal = $couponDiscount ?? 0;
    $couponFreeShippingJson = json_encode($couponFreeShipping ?? false);
@endphp

<x-store-layout title="Checkout">
    <x-storefront.columns>
    <x-storefront.region name="content_top" />
    <div class="container-store checkout-page py-10"
         x-data="checkoutWizard(
            {{ $addressesJson }},
            {{ $defaultAddressId ?? 'null' }},
            {{ $itemsJson }},
            {{ $subtotal }},
            {{ $shippingMethodsJson }},
            {{ $paymentMethodsData }},
            {{ $countriesJson }},
            { firstName: {{ json_encode($customer->first_name) }}, lastName: {{ json_encode($customer->last_name) }} }
         )"
         x-init="couponCode = {{ $couponCodeJson }}; couponDiscount = {{ $couponDiscountVal }}; couponFreeShipping = {{ $couponFreeShippingJson }}; creditBalance = {{ (float) ($creditBalance ?? 0) }}">

        <h1 class="section-title reveal">CHECKOUT</h1>

        {{-- Errors --}}
        @if ($errors->any())
            <div class="account-flash-error mb-6 reveal">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Step Indicators --}}
        <div class="checkout-steps reveal">
            <button class="checkout-step"
                    :class="{ 'active': step === 1, 'completed': step > 1 }"
                    @click="step > 1 && goToStep(1)">
                <span class="checkout-step-number">1</span>
                <span class="checkout-step-label">DELIVERY ADDRESS</span>
            </button>
            <div class="checkout-step-line" :class="{ 'completed': step > 1 }"></div>
            <button class="checkout-step"
                    :class="{ 'active': step === 2 }">
                <span class="checkout-step-number">2</span>
                <span class="checkout-step-label">PAYMENT</span>
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Main Content --}}
            <div class="lg:col-span-2">

                {{-- ========================
                     STEP 1: Delivery Address
                     ======================== --}}
                <div x-show="step === 1" x-transition:enter.opacity.duration.300ms>

                    <h2 class="checkout-section-title">SELECT DELIVERY ADDRESS</h2>

                    {{-- Address Cards Grid --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6" x-show="!showNewAddressForm">
                        <template x-for="addr in addresses" :key="addr.id">
                            <div class="checkout-address-card"
                                 :class="{ 'selected': selectedAddressId === addr.id }"
                                 @click="selectAddress(addr.id)">

                                <template x-if="addr.is_default">
                                    <span class="account-badge-default">DEFAULT</span>
                                </template>

                                <div class="text-sm space-y-0.5 mb-6">
                                    <p class="text-[var(--color-text-heading)] font-medium" x-text="addr.first_name + ' ' + addr.last_name"></p>
                                    <p class="text-[var(--color-text-muted)]" x-text="addr.address_1"></p>
                                    <template x-if="addr.barangay">
                                        <p class="text-[var(--color-text-muted)]" x-text="addr.barangay"></p>
                                    </template>
                                    <template x-if="addr.district">
                                        <p class="text-[var(--color-text-muted)]" x-text="addr.district"></p>
                                    </template>
                                    <p class="text-[var(--color-text-muted)]" x-text="[addr.city, addr.state, addr.postcode].filter(Boolean).join(', ')"></p>
                                    <p class="text-[var(--color-text)]" x-text="({{ $countryNamesJson }})[addr.country_id] || ''"></p>
                                    <template x-if="addr.phone">
                                        <p class="text-[var(--color-text-muted)]" x-text="(({{ $phoneCodesJson }})[addr.country_id] ? '+' + ({{ $phoneCodesJson }})[addr.country_id] + ' ' : '') + addr.phone"></p>
                                    </template>
                                </div>

                                <button class="checkout-card-edit" @click.stop="editAddress(addr)">EDIT</button>
                            </div>
                        </template>

                        {{-- Add New Address Card --}}
                        <div class="checkout-address-card add-new" @click="openNewAddressForm()">
                            <div class="text-center">
                                <svg class="w-8 h-8 mx-auto mb-2 text-[var(--color-text)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span class="text-sm text-[var(--color-text)]" class="font-heading-wide">ADD NEW ADDRESS</span>
                            </div>
                        </div>
                    </div>

                    {{-- Inline Address Form (new or edit) — x-if destroys/recreates addressForm on toggle so edit values load fresh --}}
                    <template x-if="showNewAddressForm">
                    <div class="account-card mb-6"
                         x-data="addressForm({
                            countryCode: String(newAddr.country_id || ''),
                            state: newAddr.state,
                            city: newAddr.city,
                            district: newAddr.district,
                            barangay: newAddr.barangay,
                            phoneCodes: {{ $phoneCodesJson }},
                         })"
                         x-effect="newAddr.country_id = countryCode; newAddr.state = state; newAddr.city = city; newAddr.district = district; newAddr.barangay = barangay;">

                        <h3 class="checkout-section-title" x-text="editingAddress ? 'EDIT ADDRESS' : 'NEW ADDRESS'"></h3>

                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="account-label">First Name <span class="text-[var(--color-secondary)]">*</span></label>
                                    <input type="text" x-model="newAddr.first_name" required class="account-input w-full">
                                </div>
                                <div>
                                    <label class="account-label">Last Name <span class="text-[var(--color-secondary)]">*</span></label>
                                    <input type="text" x-model="newAddr.last_name" required class="account-input w-full">
                                </div>
                            </div>

                            {{-- Country --}}
                            <div>
                                <label class="account-label">Country <span class="text-[var(--color-secondary)]">*</span></label>
                                <select x-model="countryCode" @change="onCountryChange()" class="account-input w-full">
                                    <option value="">-- Select Country --</option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- PH: Province --}}
                            <div x-show="hasProvider">
                                <label class="account-label">Province <span class="text-[var(--color-secondary)]">*</span></label>
                                <select x-model="state" @change="onProvinceChange()" class="account-input w-full" :class="loadingProvinces ? 'address-select-loading' : ''">
                                    <option value="">-- Select Province --</option>
                                    <template x-for="p in provinces" :key="p.id">
                                        <option :value="p.name" x-text="p.name"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- PH: City --}}
                            <div x-show="hasProvider && provinces.length > 0">
                                <label class="account-label">City / Municipality <span class="text-[var(--color-secondary)]">*</span></label>
                                <select x-model="city" @change="onCityChange()" class="account-input w-full" :class="loadingCities ? 'address-select-loading' : ''">
                                    <option value="">-- Select City --</option>
                                    <template x-for="c in cities" :key="c.id">
                                        <option :value="c.name" x-text="c.name"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- PH: District --}}
                            <div x-show="hasProvider && districts.length > 0">
                                <label class="account-label">District</label>
                                <select x-model="district" @change="onDistrictChange()" class="account-input w-full">
                                    <option value="">-- Select District --</option>
                                    <template x-for="d in districts" :key="d.id">
                                        <option :value="d.name" x-text="d.name"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- PH: Barangay --}}
                            <div x-show="hasProvider && barangays.length > 0">
                                <label class="account-label">Barangay</label>
                                <select x-model="barangay" class="account-input w-full" :class="loadingBarangays ? 'address-select-loading' : ''">
                                    <option value="">-- Select Barangay --</option>
                                    <template x-for="b in barangays" :key="b.id">
                                        <option :value="b.name" x-text="b.name"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- Non-PH: State --}}
                            <div x-show="!hasProvider && countryCode !== ''">
                                <label class="account-label">State / Region</label>
                                <template x-if="zones.length > 0">
                                    <select x-model="state" class="account-input w-full">
                                        <option value="">-- Select State --</option>
                                        <template x-for="z in zones" :key="z.id">
                                            <option :value="z.name" x-text="z.name"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="zones.length === 0">
                                    <input type="text" x-model="state" class="account-input w-full" placeholder="State / Region">
                                </template>
                            </div>

                            {{-- Non-PH: City --}}
                            <div x-show="!hasProvider && countryCode !== ''">
                                <label class="account-label">City <span class="text-[var(--color-secondary)]">*</span></label>
                                <input type="text" x-model="city" class="account-input w-full">
                            </div>

                            {{-- Address (after location dropdowns) --}}
                            <div>
                                <label class="account-label">Address <span class="text-[var(--color-secondary)]">*</span></label>
                                <input type="text" x-model="newAddr.address_1" required class="account-input w-full" placeholder="House/unit number, street, building">
                            </div>

                            {{-- Postcode --}}
                            <div>
                                <label class="account-label">Postcode / ZIP</label>
                                <input type="text" x-model="newAddr.postcode" class="account-input w-full max-w-[200px]">
                            </div>

                            {{-- Phone --}}
                            <div>
                                <label class="account-label">Mobile Number <span class="text-[var(--color-secondary)]">*</span></label>
                                <div class="flex gap-2">
                                    <span class="inline-flex items-center px-3 rounded-md bg-[var(--color-search-bg)] border border-[var(--color-input-border)] text-sm text-[var(--color-text-muted)]" x-text="window.__phoneCodes?.[countryCode] ? '+' + window.__phoneCodes[countryCode] : '-'"></span>
                                    <input type="tel" x-model="newAddr.phone" class="account-input flex-1" placeholder="Mobile number" minlength="7" maxlength="13" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                </div>
                            </div>

                            {{-- Validation Errors --}}
                            <div x-show="addressErrors.length > 0" class="rounded-lg bg-[var(--color-danger-bg)] border border-[var(--color-danger-border)] p-3 text-sm text-[var(--color-danger-text)]">
                                <div class="flex items-start gap-2">
                                    <svg class="w-4 h-4 mt-0.5 shrink-0 text-[var(--color-danger-accent)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <ul class="space-y-0.5">
                                        <template x-for="err in addressErrors" :key="err">
                                            <li x-text="err"></li>
                                        </template>
                                    </ul>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3 pt-4 border-t border-[var(--color-border)]">
                                <a href="{{ route('cart.index') }}" class="account-btn-secondary" x-show="!editingAddress && addresses.length === 0">Cancel</a>
                                <button @click="cancelAddressForm()" class="account-btn-secondary" x-show="editingAddress || addresses.length > 0">Cancel</button>
                                <button @click="saveNewAddress()" class="account-btn-primary" :disabled="savingAddress">
                                    <span x-show="!savingAddress" x-text="editingAddress ? 'UPDATE ADDRESS' : 'NEXT'"></span>
                                    <span x-show="savingAddress">SAVING...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    </template>

                    {{-- Different billing address --}}
                    <div class="mb-6" x-show="!showNewAddressForm">
                        <label class="flex items-center gap-2 text-sm text-[var(--color-text)] cursor-pointer">
                            <input type="checkbox" x-model="differentBilling" class="accent-[var(--color-primary)]">
                            Use a different billing address
                        </label>
                    </div>

                    {{-- Billing Address Form --}}
                    <div x-show="differentBilling && !showNewAddressForm" x-transition:enter.opacity.duration.300ms class="account-card mb-6"
                         x-data="addressForm({
                            countryCode: String(billing.country_id || ''),
                            state: billing.state,
                            city: billing.city,
                            district: billing.district,
                            barangay: billing.barangay,
                            phoneCodes: {{ $phoneCodesJson }},
                         })"
                         x-effect="billing.country_id = countryCode; billing.state = state; billing.city = city; billing.district = district; billing.barangay = barangay;">

                        <h3 class="checkout-section-title">BILLING ADDRESS</h3>

                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="account-label">First Name <span class="text-[var(--color-secondary)]">*</span></label>
                                    <input type="text" x-model="$data.billing.first_name" required class="account-input w-full">
                                </div>
                                <div>
                                    <label class="account-label">Last Name <span class="text-[var(--color-secondary)]">*</span></label>
                                    <input type="text" x-model="$data.billing.last_name" required class="account-input w-full">
                                </div>
                            </div>

                            <div>
                                <label class="account-label">Address <span class="text-[var(--color-secondary)]">*</span></label>
                                <input type="text" x-model="$data.billing.address_1" required class="account-input w-full" placeholder="Street address, building, unit">
                            </div>

                            {{-- Country --}}
                            <div>
                                <label class="account-label">Country <span class="text-[var(--color-secondary)]">*</span></label>
                                <select x-model="countryCode" @change="onCountryChange()" class="account-input w-full">
                                    <option value="">-- Select Country --</option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- PH: Province --}}
                            <div x-show="hasProvider">
                                <label class="account-label">Province <span class="text-[var(--color-secondary)]">*</span></label>
                                <select x-model="state" @change="onProvinceChange()" class="account-input w-full" :class="loadingProvinces ? 'address-select-loading' : ''">
                                    <option value="">-- Select Province --</option>
                                    <template x-for="p in provinces" :key="p.id">
                                        <option :value="p.name" x-text="p.name"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- PH: City --}}
                            <div x-show="hasProvider && provinces.length > 0">
                                <label class="account-label">City / Municipality <span class="text-[var(--color-secondary)]">*</span></label>
                                <select x-model="city" @change="onCityChange()" class="account-input w-full" :class="loadingCities ? 'address-select-loading' : ''">
                                    <option value="">-- Select City --</option>
                                    <template x-for="c in cities" :key="c.id">
                                        <option :value="c.name" x-text="c.name"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- PH: District --}}
                            <div x-show="hasProvider && districts.length > 0">
                                <label class="account-label">District</label>
                                <select x-model="district" @change="onDistrictChange()" class="account-input w-full">
                                    <option value="">-- Select District --</option>
                                    <template x-for="d in districts" :key="d.id">
                                        <option :value="d.name" x-text="d.name"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- PH: Barangay --}}
                            <div x-show="hasProvider && barangays.length > 0">
                                <label class="account-label">Barangay</label>
                                <select x-model="barangay" class="account-input w-full" :class="loadingBarangays ? 'address-select-loading' : ''">
                                    <option value="">-- Select Barangay --</option>
                                    <template x-for="b in barangays" :key="b.id">
                                        <option :value="b.name" x-text="b.name"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- Non-PH: State --}}
                            <div x-show="!hasProvider && countryCode !== ''">
                                <label class="account-label">State / Region</label>
                                <template x-if="zones.length > 0">
                                    <select x-model="state" class="account-input w-full">
                                        <option value="">-- Select State --</option>
                                        <template x-for="z in zones" :key="z.id">
                                            <option :value="z.name" x-text="z.name"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="zones.length === 0">
                                    <input type="text" x-model="state" class="account-input w-full" placeholder="State / Region">
                                </template>
                            </div>

                            {{-- Non-PH: City --}}
                            <div x-show="!hasProvider && countryCode !== ''">
                                <label class="account-label">City <span class="text-[var(--color-secondary)]">*</span></label>
                                <input type="text" x-model="city" class="account-input w-full">
                            </div>

                            {{-- Postcode --}}
                            <div>
                                <label class="account-label">Postcode / ZIP</label>
                                <input type="text" x-model="$data.billing.postcode" class="account-input w-full max-w-[200px]">
                            </div>

                            {{-- Phone --}}
                            <div>
                                <label class="account-label">Mobile Number</label>
                                <div class="flex gap-2">
                                    <span class="inline-flex items-center px-3 rounded-md bg-[var(--color-search-bg)] border border-[var(--color-input-border)] text-sm text-[var(--color-text-muted)]" x-text="window.__phoneCodes?.[countryCode] ? '+' + window.__phoneCodes[countryCode] : '-'"></span>
                                    <input type="tel" x-model="$data.billing.phone" class="account-input flex-1" placeholder="Mobile number" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- ========================
                     STEP 2: Payment
                     ======================== --}}
                <div x-show="step === 2" x-transition:enter.opacity.duration.300ms>

                    <h2 class="checkout-section-title">PAYMENT METHOD</h2>

                    <div class="space-y-3 mb-8">
                        <template x-for="method in paymentMethods" :key="method.key">
                            <label class="checkout-payment-option"
                                   :class="{ 'selected': selectedPayment === method.key }"
                                   @click="selectedPayment = method.key">
                                <input type="radio" :value="method.key" x-model="selectedPayment"
                                       class="mt-0.5 accent-[var(--color-primary)]">
                                <div class="flex-1">
                                    <div class="text-sm text-[var(--color-text-heading)]" x-text="method.name"></div>
                                    <div class="text-xs text-[var(--color-text-muted)] mt-1" x-text="method.description"></div>
                                    <template x-if="method.image && selectedPayment === method.key">
                                        <div class="mt-3" @click.prevent.stop="qrSrc = method.image; qrName = method.name; qrOpen = true">
                                            <img :src="method.image" alt="QR Code" class="w-48 h-48 rounded-lg border border-[var(--color-border)] object-contain bg-white p-2 cursor-pointer hover:ring-2 hover:ring-[var(--color-primary)] transition-all">
                                            <p class="text-[10px] text-[var(--color-text-muted)] mt-1 cursor-pointer">Tap to enlarge</p>
                                        </div>
                                    </template>
                                </div>
                            </label>
                        </template>
                    </div>

                    {{-- Order messages: two purpose-named fields, each behind
                         its own core setting. `comment` is the message to the
                         store; `courier_comment` is the message the fulfillment
                         side hands to the courier at booking. --}}
                    @php
                        $vCheckout = app(\App\Services\ThemeService::class);
                        $vMsgStoreOn = ($vCheckout->get('checkout.comment_store_enabled', '1')) === '1';
                        $vMsgCourierOn = ($vCheckout->get('checkout.comment_courier_enabled', '0')) === '1';
                        $vMsgStoreLabel = trim((string) $vCheckout->get('checkout.comment_store_label', '')) ?: 'Message to Store';
                        $vMsgCourierLabel = trim((string) $vCheckout->get('checkout.comment_courier_label', '')) ?: 'Message to Courier';
                    @endphp
                    @if ($vMsgStoreOn)
                        <div class="mb-6">
                            <label class="account-label" for="checkout-msg-store">{{ $vMsgStoreLabel }} <span class="text-[var(--color-text)]">(optional)</span></label>
                            <textarea id="checkout-msg-store" x-model="comment" class="account-input w-full h-24 resize-none" placeholder="Notes for the shop packing your order"></textarea>
                        </div>
                    @endif
                    @if ($vMsgCourierOn)
                        <div class="mb-8">
                            <label class="account-label" for="checkout-msg-courier">{{ $vMsgCourierLabel }} <span class="text-[var(--color-text)]">(optional)</span></label>
                            <textarea id="checkout-msg-courier" x-model="courierComment" class="account-input w-full h-24 resize-none" placeholder="Notes for the rider delivering your parcel"></textarea>
                        </div>
                    @endif

                </div>
            </div>

            {{-- ========================
                 ORDER SUMMARY SIDEBAR
                 ======================== --}}
            <div>
                <div class="order-summary-box">
                    <h3 class="text-lg tracking-[0.15em] mb-6 text-[var(--color-text-heading)]" class="font-heading">ORDER SUMMARY</h3>

                    {{-- Stock Warning --}}
                    <div x-show="stockWarning" x-transition.opacity class="bg-[var(--color-danger-bg)] border border-[var(--color-danger-border)] rounded-lg p-3 mb-4 text-xs text-[var(--color-danger-text)]" x-text="stockWarning"></div>

                    {{-- Items --}}
                    <div class="checkout-items-scroll space-y-4 mb-4" style="max-height: 360px; overflow-y: auto;">
                        <template x-for="item in items" :key="item.id">
                            <div class="flex items-start gap-3">
                                <div class="w-16 h-16 flex-shrink-0 rounded-lg overflow-hidden bg-[var(--color-search-bg)]">
                                    <template x-if="item.product && (item.product.image || (item.product.images && item.product.images.length))">
                                        <img :src="storageUrl(item.product.image ? item.product.image : item.product.images[0].path)" :alt="item.name" class="w-full h-full object-contain p-1" loading="lazy">
                                    </template>
                                    <template x-if="!item.product || (!item.product.image && (!item.product.images || !item.product.images.length))">
                                        <div class="w-full h-full flex items-center justify-center text-[var(--color-input-border)]">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    </template>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm text-[var(--color-text-heading)] leading-snug truncate" x-text="item.name"></div>
                                    <div class="text-xs text-[var(--color-text-muted)]" x-show="item.variant_label" x-text="item.variant_label"></div>
                                    <div class="flex items-center gap-2 mt-2">
                                        <button type="button" @click="updateItemQty(item, item.quantity - 1)"
                                            class="w-7 h-7 flex items-center justify-center rounded-md bg-[var(--color-search-bg)] hover:bg-[var(--color-border)] text-[var(--color-text)] text-sm font-medium transition-colors">−</button>
                                        <input type="number" :value="item.quantity" min="1"
                                            @change="updateItemQty(item, $event.target.value)"
                                            class="w-12 h-7 text-center text-sm text-[var(--color-text-heading)] bg-[var(--color-input-bg)] border border-[var(--color-input-border)] rounded-md focus:border-[var(--color-primary)] focus:ring-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                        <button type="button" @click="updateItemQty(item, item.quantity + 1)"
                                            class="w-7 h-7 flex items-center justify-center rounded-md bg-[var(--color-search-bg)] hover:bg-[var(--color-border)] text-[var(--color-text)] text-sm font-medium transition-colors">+</button>
                                    </div>
                                </div>
                                <div class="flex-shrink-0 text-right">
                                    <div class="text-sm text-[var(--color-text-heading)] font-medium">
                                        <span x-text="money((item.price * item.quantity))"></span>
                                    </div>
                                    <button type="button" @click="removeCheckoutItem(item)"
                                        class="text-[10px] text-[var(--color-text-muted)] hover:text-[var(--color-danger-accent)] mt-1 transition-colors">Remove</button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <hr class="divider-glow my-4">

                    {{-- Subtotal --}}
                    <div class="flex justify-between text-sm mb-3">
                        <span class="text-[var(--color-text-muted)]">Subtotal</span>
                        <span class="text-[var(--color-text-heading)]" class="font-heading"><span x-text="money(subtotal)"></span></span>
                    </div>

                    {{-- Shipping Methods --}}
                    <div class="mb-3">
                        <span class="text-sm text-[var(--color-text-muted)] block mb-2">Shipping</span>
                        <template x-if="loadingShipping">
                            <div class="flex items-center gap-2 py-3 text-sm text-[var(--color-text-muted)]">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                Calculating shipping...
                            </div>
                        </template>
                        <template x-if="!loadingShipping && shippingMethods.length > 0">
                            <div class="space-y-2">
                                <template x-for="sm in shippingMethods" :key="sm.key">
                                    <label class="flex items-center justify-between cursor-pointer text-sm group">
                                        <span class="flex items-center gap-2">
                                            <input type="radio" :value="sm.key" x-model="selectedShipping"
                                                   class="accent-[var(--color-primary)]">
                                            <span class="text-[var(--color-text-muted)] group-hover:text-[var(--color-text-muted)] transition-colors" x-text="sm.name"></span>
                                            <template x-if="sm.estimated_days">
                                                <span class="text-[var(--color-text)] text-xs" x-text="'~' + sm.estimated_days + ' days'"></span>
                                            </template>
                                        </span>
                                        <span class="text-[var(--color-text-heading)]" class="font-heading">
                                            <template x-if="sm.cost === 0">
                                                <span class="text-emerald-400">FREE</span>
                                            </template>
                                            <template x-if="sm.cost > 0">
                                                <span><span x-text="money(sm.cost)"></span></span>
                                            </template>
                                        </span>
                                    </label>
                                </template>
                            </div>
                        </template>
                        <template x-if="!loadingShipping && shippingMethods.length === 0">
                            <div class="bg-[var(--color-danger-bg)] border border-[var(--color-danger-border)] rounded-lg px-4 py-3">
                                <p class="text-sm text-[var(--color-danger-text)]">No shipping options available for this location.</p>
                                <p class="text-xs text-[var(--color-danger-accent)] mt-1">Please update your delivery address or contact us for assistance.</p>
                            </div>
                        </template>
                    </div>

                    {{-- Coupon (renders only while the coupons extension is enabled) --}}
                    @if (Illuminate\Support\Facades\Route::has('checkout.coupon.apply'))
                    <div class="mb-3">
                        @if (!empty($couponError))
                            <div class="text-xs text-[var(--color-danger-accent)] mb-2">{{ $couponError }}</div>
                        @endif
                        <template x-if="!couponCode">
                            <div>
                                <div class="flex gap-2">
                                    <input type="text" x-model="couponInput" placeholder="Coupon code"
                                           class="account-input flex-1 text-sm py-1.5"
                                           @keydown.enter.prevent="applyCoupon()">
                                    <button @click="applyCoupon()"
                                            :disabled="couponLoading || !couponInput.trim()"
                                            class="px-3 py-1.5 text-xs tracking-wider bg-[var(--color-primary)] text-[var(--color-btn-text)] rounded-md hover:opacity-90 transition-opacity disabled:opacity-40">
                                        <span x-show="!couponLoading">APPLY</span>
                                        <span x-show="couponLoading">...</span>
                                    </button>
                                </div>
                                <div x-show="couponError" class="text-xs text-[var(--color-danger-accent)] mt-1" x-text="couponError"></div>
                            </div>
                        </template>
                        <template x-if="couponCode">
                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-[var(--color-text-muted)]">Coupon <span class="font-medium text-[var(--color-text-heading)]" x-text="couponCode"></span></span>
                                    <span class="text-emerald-500">-<span x-text="money(couponDiscount)"></span></span>
                                </div>
                                <template x-if="couponFreeShipping">
                                    <div class="text-xs text-emerald-500 mt-0.5">+ Free shipping</div>
                                </template>
                                <button @click="removeCoupon()" class="text-[10px] text-[var(--color-text-muted)] hover:text-[var(--color-danger-accent)] mt-1 transition-colors">Remove coupon</button>
                            </div>
                        </template>
                    </div>
                    @endif

                    <hr class="divider-glow my-4">

                    <template x-if="creditApplied > 0">
                        <div class="flex justify-between text-sm text-emerald-500 mb-2">
                            <span>Store Credit</span>
                            <span>-<span x-text="money(creditApplied)"></span></span>
                        </div>
                    </template>

                    {{-- Total --}}
                    <div class="flex justify-between">
                        <span class="text-[var(--color-text-heading)]" class="font-heading-wide">TOTAL</span>
                        <span class="text-xl text-[var(--color-text-heading)]" class="font-heading"><span x-text="money(payableTotal)"></span></span>
                    </div>

                    @if ($activeCurrency && !$activeCurrency->is_default)
                        <p class="checkout-currency-note">
                            Prices shown in {{ $activeCurrency->code }}. Your order is charged in {{ $defaultCurrency->code }}.
                        </p>
                    @endif

                    {{-- Step Actions --}}
                    <div class="checkout-summary-actions" x-show="step === 2 || !showNewAddressForm">
                        <div class="checkout-sticky-total" aria-hidden="true">
                            <span>TOTAL</span>
                            <span x-text="money(payableTotal)"></span>
                        </div>
                        <div class="checkout-actions-row" x-show="step === 1">
                            <a href="{{ route('cart.index') }}" class="account-btn-secondary">Cancel</a>
                            <button @click="goToStep(2)"
                                    class="btn-checkout checkout-action-main"
                                    :disabled="!selectedAddressId"
                                    :class="{ 'opacity-50 cursor-not-allowed': !selectedAddressId }">
                                CONTINUE TO PAYMENT
                            </button>
                        </div>
                        <div class="checkout-actions-row" x-show="step === 2">
                            <button @click="goToStep(1)" class="account-btn-secondary">Back</button>
                            <button @click="submitOrder()"
                                    class="btn-checkout checkout-action-main"
                                    :disabled="!selectedPayment || !selectedShipping || placing"
                                    :class="{ 'opacity-50 cursor-not-allowed': !selectedPayment || !selectedShipping || placing }">
                                <span x-show="!placing">PLACE ORDER</span>
                                <span x-show="placing">PROCESSING...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hidden Order Form --}}
        <form x-ref="orderForm" method="POST" action="{{ route('checkout.place') }}" class="hidden">
            @csrf
            <input type="hidden" name="shipping_address_id" :value="selectedAddressId">
            <input type="hidden" name="payment_method" :value="selectedPayment">
            <input type="hidden" name="shipping_method" :value="selectedShipping">
            <input type="hidden" name="shipping_method_name" :value="selectedShippingMethod ? selectedShippingMethod.name : ''">
            <input type="hidden" name="shipping_cost" :value="selectedShippingMethod ? selectedShippingMethod.cost : ''">
            <input type="hidden" name="comment" :value="comment">
            <input type="hidden" name="courier_comment" :value="courierComment">
            <input type="hidden" name="different_billing" :value="differentBilling ? '1' : '0'">
            <input type="hidden" name="billing_first_name" :value="billing.first_name">
            <input type="hidden" name="billing_last_name" :value="billing.last_name">
            <input type="hidden" name="billing_address_1" :value="billing.address_1">
            <input type="hidden" name="billing_city" :value="billing.city">
            <input type="hidden" name="billing_district" :value="billing.district">
            <input type="hidden" name="billing_barangay" :value="billing.barangay">
            <input type="hidden" name="billing_state" :value="billing.state">
            <input type="hidden" name="billing_postcode" :value="billing.postcode">
            <input type="hidden" name="billing_country_id" :value="billing.country_id">
        </form>

        {{-- Checkout Confirmation Modal --}}
        <div x-show="showConfirmModal" x-cloak class="checkout-modal-overlay" @click.self="closeConfirmModal()">
            <div class="checkout-modal" @click.stop>
                <h2 class="checkout-modal-title">CONFIRM YOUR ORDER</h2>

                {{-- Products --}}
                <div class="checkout-modal-section">
                    <h4 class="checkout-modal-label">Products</h4>
                    <div class="checkout-modal-items">
                        <template x-for="item in items" :key="item.id">
                            <div class="checkout-modal-item">
                                <div class="checkout-modal-item-img">
                                    <template x-if="item.product && (item.product.image || (item.product.images && item.product.images.length))">
                                        <img :src="storageUrl(item.product.image ? item.product.image : item.product.images[0].path)" :alt="item.name" loading="lazy">
                                    </template>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm truncate" x-text="item.name"></div>
                                    <div class="text-xs opacity-60" x-show="item.variant_label" x-text="item.variant_label"></div>
                                    <div class="text-xs opacity-50" x-text="'Qty: ' + item.quantity"></div>
                                </div>
                                <div class="text-sm font-medium flex-shrink-0">
                                    <span x-text="money((item.price * item.quantity))"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Shipping Address --}}
                <div class="checkout-modal-section">
                    <h4 class="checkout-modal-label">Shipping Address</h4>
                    <div class="text-sm">
                        <span x-text="selectedAddress.first_name + ' ' + selectedAddress.last_name"></span><br>
                        <span x-text="selectedAddress.address_1"></span><br>
                        <template x-if="selectedAddress.barangay">
                            <span><span x-text="selectedAddress.barangay"></span><br></span>
                        </template>
                        <span x-text="selectedAddress.city"></span><template x-if="selectedAddress.state">, <span x-text="selectedAddress.state"></span></template>
                        <template x-if="selectedAddress.postcode"> <span x-text="selectedAddress.postcode"></span></template>
                    </div>
                </div>

                {{-- Shipping Method --}}
                <div class="checkout-modal-section">
                    <h4 class="checkout-modal-label">Shipping</h4>
                    <div class="flex justify-between text-sm">
                        <span x-text="selectedShippingMethod ? selectedShippingMethod.name : 'Not selected'"></span>
                        <span>
                            <template x-if="selectedShippingMethod && selectedShippingMethod.cost === 0">
                                <span class="text-emerald-500">FREE</span>
                            </template>
                            <template x-if="selectedShippingMethod && selectedShippingMethod.cost > 0">
                                <span><span x-text="money(selectedShippingMethod.cost)"></span></span>
                            </template>
                        </span>
                    </div>
                </div>

                {{-- Payment Method --}}
                <div class="checkout-modal-section">
                    <h4 class="checkout-modal-label">Payment</h4>
                    <div class="text-sm" x-text="selectedPaymentMethod.name || 'Not selected'"></div>
                </div>

                {{-- Order messages --}}
                <template x-if="comment && comment.trim()">
                    <div class="checkout-modal-section">
                        <h4 class="checkout-modal-label">{{ $vMsgStoreLabel ?? 'Message to Store' }}</h4>
                        <div class="text-sm opacity-70" x-text="comment"></div>
                    </div>
                </template>
                <template x-if="courierComment && courierComment.trim()">
                    <div class="checkout-modal-section">
                        <h4 class="checkout-modal-label">{{ $vMsgCourierLabel ?? 'Message to Courier' }}</h4>
                        <div class="text-sm opacity-70" x-text="courierComment"></div>
                    </div>
                </template>

                {{-- Totals --}}
                <div class="checkout-modal-totals">
                    <div class="flex justify-between text-sm">
                        <span>Subtotal</span>
                        <span><span x-text="money(subtotal)"></span></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span>Shipping</span>
                        <span>
                            <template x-if="effectiveShippingCost === 0">
                                <span class="text-emerald-500">FREE</span>
                            </template>
                            <template x-if="effectiveShippingCost > 0">
                                <span><span x-text="money(effectiveShippingCost)"></span></span>
                            </template>
                        </span>
                    </div>
                    <template x-if="couponCode">
                        <div class="flex justify-between text-sm text-emerald-500">
                            <span>Coupon (<span x-text="couponCode"></span>)</span>
                            <span>-<span x-text="money(couponDiscount)"></span></span>
                        </div>
                    </template>
                    <template x-if="creditApplied > 0">
                        <div class="flex justify-between text-sm text-emerald-500">
                            <span>Store Credit</span>
                            <span>-<span x-text="money(creditApplied)"></span></span>
                        </div>
                    </template>
                    <div class="checkout-modal-total-line">
                        <span>Total</span>
                        <span class="text-lg font-bold"><span x-text="money(payableTotal)"></span></span>
                    </div>
                </div>

                {{-- Buttons --}}
                <div class="checkout-modal-actions">
                    <button @click="closeConfirmModal()" class="checkout-modal-btn-back">Go Back</button>
                    <button @click="confirmOrder()" class="checkout-modal-btn-confirm" :disabled="placing">
                        <span x-show="!placing">Confirm & Pay</span>
                        <span x-show="placing">Processing...</span>
                    </button>
                </div>
            </div>
        </div>
        {{-- QR Enlarge Modal --}}
        <div x-show="qrOpen" x-transition.opacity class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
             style="background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);" @click="qrOpen = false" @keydown.escape.window="qrOpen = false" x-cloak>
            <div class="bg-white rounded-2xl p-6 shadow-2xl max-w-sm w-full" @click.stop>
                <h3 class="text-center text-sm font-semibold text-[var(--color-text-heading)] mb-3" x-text="qrName"></h3>
                <img :src="qrSrc" alt="QR Code" class="w-full rounded-lg">
                <p class="text-center text-xs text-[var(--color-text-muted)] mt-3">Scan with your banking app</p>
                <button @click="qrOpen = false" class="mt-4 w-full py-2 bg-[var(--color-search-bg)] hover:bg-[var(--color-border)] text-[var(--color-text)] text-sm font-medium rounded-lg transition-colors">Close</button>
            </div>
        </div>
    </div>
    <x-storefront.region name="content_bottom" />
    </x-storefront.columns>
</x-store-layout>
