@php
    $vInput = 'v-control w-full h-[var(--control-h)] px-3 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md placeholder:text-v-muted';
    $vSelect = 'v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md';
    $vInputSm = 'v-control h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md';
    $vLabel = 'block text-[13px] font-medium text-v-text mb-1';
    $vCheck = 'w-4 h-4 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface';
@endphp
<x-admin.shell title="{{ $order ? 'Order #' . $order->id : 'Create Order' }}">
    <x-admin.page :title="$order ? 'Order #' . $order->id : 'Create Order'" :breadcrumb="[['label' => 'Orders', 'url' => route('admin.orders.index')]]">
        <x-slot:actions>
            <x-admin.button variant="ghost" href="{{ route('admin.orders.index') }}">Back to Orders</x-admin.button>
        </x-slot:actions>

        <div x-data="orderForm()" x-init="init()">
            <form method="POST"
                  action="{{ $order ? route('admin.orders.update', $order) : route('admin.orders.store') }}"
                  data-v-form class="space-y-6">
                @csrf
                @if ($order)
                    @method('PUT')
                @endif

                {{-- Validation Errors --}}
                @if ($errors->any())
                    <div class="rounded-v-lg border border-v-danger bg-[color-mix(in_srgb,var(--danger)_10%,transparent)] p-4">
                        <h4 class="mb-2 text-[13px] font-medium text-v-danger">Please fix the following errors:</h4>
                        <ul class="list-inside list-disc space-y-1 text-[13px] text-v-danger">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <input type="hidden" name="store_id" value="1">

                <x-admin.tabs default="details" param="tab" :names="['details', 'products']">
                    <x-slot:nav>
                        <x-admin.tabs.tab name="details">Details</x-admin.tabs.tab>
                        <x-admin.tabs.tab name="products">Products <span x-show="items.length > 0" x-text="'(' + items.length + ')'"></span></x-admin.tabs.tab>
                    </x-slot:nav>

                    {{-- ===================== DETAILS TAB ===================== --}}
                    <x-admin.tabs.panel name="details" class="space-y-6">

                        {{-- Customer --}}
                        <x-admin.card title="Customer">
                            <div class="max-w-xl">
                                <input type="hidden" name="customer_id" :value="customerId">
                                <input type="hidden" name="is_new_customer" :value="isNewCustomer && !customerId ? '1' : '0'">
                                <input type="hidden" name="phone" :value="customerPhone">

                                {{-- Search or New — unified input area --}}
                                <div x-show="!customerId" class="mb-3">
                                    <div class="mb-2 flex items-center gap-2">
                                        <div class="inline-flex rounded-v-md border border-v-border text-xs overflow-hidden">
                                            <button type="button" @click="isNewCustomer = false"
                                                :class="!isNewCustomer ? 'bg-v-accent text-v-accent-text' : 'bg-v-surface text-v-secondary hover:bg-v-hover'"
                                                class="px-3 py-1.5 font-medium transition-colors">Existing</button>
                                            <button type="button" @click="isNewCustomer = true"
                                                :class="isNewCustomer ? 'bg-v-accent text-v-accent-text' : 'bg-v-surface text-v-secondary hover:bg-v-hover'"
                                                class="border-l border-v-border px-3 py-1.5 font-medium transition-colors">New Customer</button>
                                        </div>
                                    </div>
                                    <div x-show="!isNewCustomer" class="relative">
                                        <input type="text" x-model="customerSearch" @input.debounce.300ms="searchCustomers()" @focus="customerDropdownOpen = true" @click.away="customerDropdownOpen = false"
                                            placeholder="Search customer by name or email..." autocomplete="off" class="{{ $vInput }}">
                                        <div x-show="customerDropdownOpen && customerResults.length > 0" x-cloak class="absolute z-50 mt-1 max-h-48 w-full overflow-y-auto rounded-v-md border border-v-border bg-v-surface shadow-lg">
                                            <template x-for="c in customerResults" :key="c.id">
                                                <button type="button" @click="selectCustomer(c)" class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-[13px] transition-colors hover:bg-v-hover">
                                                    <span class="min-w-0 truncate">
                                                        <span class="font-medium" x-text="c.name"></span>
                                                        <span class="ml-1 text-v-muted" x-text="c.email"></span>
                                                    </span>
                                                    <span x-show="c.customer_group_name" x-text="c.customer_group_name"
                                                          class="shrink-0 rounded-full bg-v-sunken px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-v-secondary ring-1 ring-inset ring-v-border"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                {{-- Selected Customer or New Customer Fields --}}
                                <div x-show="customerId || isNewCustomer" class="grid grid-cols-2 gap-3">
                                    <div x-show="customerId" class="col-span-2 mb-1 flex items-center justify-between gap-2 rounded-v-md bg-v-accent-weak px-3 py-2">
                                        <div class="flex min-w-0 flex-1 items-center gap-2">
                                            <span class="truncate text-[13px] font-medium text-v-accent" x-text="customerFirstName + ' ' + customerLastName + ' (' + customerEmail + ')'"></span>
                                            <span x-show="customerGroupName" x-text="customerGroupName"
                                                  class="shrink-0 rounded-full bg-v-surface px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-v-accent ring-1 ring-inset ring-v-border"></span>
                                        </div>
                                        <button type="button" @click="clearCustomer()" class="shrink-0 text-xs font-medium text-v-danger hover:opacity-80">Change</button>
                                    </div>
                                    <div x-show="isNewCustomer && !customerId" class="col-span-2 mb-1 flex items-center gap-2 rounded-v-md bg-[color-mix(in_srgb,var(--success,var(--accent))_10%,transparent)] px-3 py-2">
                                        <span class="text-[13px] font-medium text-v-accent">New customer — account will be created on save</span>
                                    </div>
                                    <div>
                                        <label class="{{ $vLabel }}">First Name</label>
                                        <input type="text" name="first_name" required x-model="customerFirstName" class="{{ $vInput }}">
                                    </div>
                                    <div>
                                        <label class="{{ $vLabel }}">Last Name</label>
                                        <input type="text" name="last_name" required x-model="customerLastName" class="{{ $vInput }}">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="{{ $vLabel }}">Email</label>
                                        <input type="email" name="email" required x-model="customerEmail" class="{{ $vInput }}">
                                    </div>
                                    <div class="col-span-2" x-show="customerAddresses.length > 0">
                                        <label class="{{ $vLabel }}">Delivery Address</label>
                                        <select @change="loadCustomerAddress($event.target.value)" class="{{ $vSelect }}">
                                            <option value="">Select address...</option>
                                            <template x-for="a in customerAddresses" :key="a.id">
                                                <option :value="a.id" x-text="a.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </x-admin.card>

                        {{-- Shipping Address --}}
                        <x-admin.card title="Shipping Address">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="{{ $vLabel }}">First Name</label>
                                    <input type="text" name="shipping_first_name" x-model="shipping.first_name" class="{{ $vInput }}">
                                </div>
                                <div>
                                    <label class="{{ $vLabel }}">Last Name</label>
                                    <input type="text" name="shipping_last_name" x-model="shipping.last_name" class="{{ $vInput }}">
                                </div>
                                <div>
                                    <label class="{{ $vLabel }}">Country</label>
                                    <select name="shipping_country_id" x-model="shipping.country_id" @change="onShippingCountryChange()" class="{{ $vSelect }}">
                                        <option value="">Select Country</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                {{-- Provider: Province --}}
                                <div x-show="shippingDropdowns.hasProvider">
                                    <label class="{{ $vLabel }}">Province</label>
                                    <select name="shipping_state" x-model="shipping.state" @change="onShippingProvinceChange()" class="{{ $vSelect }}">
                                        <option value="">Select Province</option>
                                        <template x-for="p in (shippingDropdowns.provinces || [])" :key="p.id">
                                            <option :value="p.name" x-text="p.name"></option>
                                        </template>
                                    </select>
                                </div>
                                {{-- Provider: City --}}
                                <div x-show="shippingDropdowns.hasProvider && (shippingDropdowns.cities || []).length > 0">
                                    <label class="{{ $vLabel }}">City / Municipality</label>
                                    <select name="shipping_city" x-model="shipping.city" @change="onShippingCityChange()" class="{{ $vSelect }}">
                                        <option value="">Select City</option>
                                        <template x-for="c in (shippingDropdowns.cities || [])" :key="c.id">
                                            <option :value="c.name" x-text="c.name"></option>
                                        </template>
                                    </select>
                                </div>
                                {{-- Provider: District --}}
                                <div x-show="shippingDropdowns.hasProvider && (shippingDropdowns.districts || []).length > 0">
                                    <label class="{{ $vLabel }}">District</label>
                                    <select name="shipping_district" x-model="shipping.district" @change="onShippingDistrictChange()" class="{{ $vSelect }}">
                                        <option value="">Select District</option>
                                        <template x-for="d in (shippingDropdowns.districts || [])" :key="d.id">
                                            <option :value="d.name" x-text="d.name"></option>
                                        </template>
                                    </select>
                                </div>
                                {{-- Provider: Barangay --}}
                                <div x-show="shippingDropdowns.hasProvider && (shippingDropdowns.barangays || []).length > 0">
                                    <label class="{{ $vLabel }}">Barangay</label>
                                    <select name="shipping_barangay" x-model="shipping.barangay" class="{{ $vSelect }}">
                                        <option value="">Select Barangay</option>
                                        <template x-for="b in (shippingDropdowns.barangays || [])" :key="b.id">
                                            <option :value="b.name" x-text="b.name"></option>
                                        </template>
                                    </select>
                                </div>
                                {{-- Core: State --}}
                                <div x-show="!shippingDropdowns.hasProvider && shipping.country_id">
                                    <label class="{{ $vLabel }}">State / Province</label>
                                    <template x-if="(shippingDropdowns.zones || []).length > 0">
                                        <select name="shipping_state" x-model="shipping.state" @change="$nextTick(() => refreshMethods())" class="{{ $vSelect }}">
                                            <option value="">Select State / Province</option>
                                            <template x-for="z in (shippingDropdowns.zones || [])" :key="z.id">
                                                <option :value="z.name" x-text="z.name"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="(shippingDropdowns.zones || []).length === 0">
                                        <input type="text" name="shipping_state" x-model="shipping.state" @change="$nextTick(() => refreshMethods())" class="{{ $vInput }}">
                                    </template>
                                </div>
                                {{-- Core: City --}}
                                <div x-show="!shippingDropdowns.hasProvider && shipping.country_id">
                                    <label class="{{ $vLabel }}">City</label>
                                    <input type="text" name="shipping_city" x-model="shipping.city" class="{{ $vInput }}">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="{{ $vLabel }}">Address Line 1</label>
                                    <input type="text" name="shipping_address_1" x-model="shipping.address_1" class="{{ $vInput }}">
                                </div>
                                <div>
                                    <label class="{{ $vLabel }}">Postcode</label>
                                    <input type="text" name="shipping_postcode" x-model="shipping.postcode" class="{{ $vInput }}">
                                </div>
                                <div>
                                    <label class="{{ $vLabel }}">Phone</label>
                                    <div class="flex gap-2">
                                        <span class="inline-flex items-center rounded-v-md border border-v-border bg-v-sunken px-3 text-[13px] text-v-muted" x-text="'+' + (phoneCode || '63')"></span>
                                        <input type="text" name="shipping_phone" x-model="shipping.phone" class="{{ $vInput }} flex-1" placeholder="Phone number">
                                    </div>
                                </div>
                            </div>
                        </x-admin.card>

                        {{-- Billing Address --}}
                        <x-admin.card title="Billing Address">
                            <label class="mb-3 flex cursor-pointer items-center gap-2">
                                <input type="checkbox" x-model="differentBilling" class="{{ $vCheck }}">
                                <span class="text-[13px] font-semibold text-v-text">Use a different billing address</span>
                            </label>

                            <div x-show="differentBilling" x-transition class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="{{ $vLabel }}">First Name</label>
                                    <input type="text" name="billing_first_name" x-model="billing.first_name" class="{{ $vInput }}">
                                </div>
                                <div>
                                    <label class="{{ $vLabel }}">Last Name</label>
                                    <input type="text" name="billing_last_name" x-model="billing.last_name" class="{{ $vInput }}">
                                </div>
                                <div>
                                    <label class="{{ $vLabel }}">Country</label>
                                    <select name="billing_country_id" x-model="billing.country_id" @change="onBillingCountryChange()" class="{{ $vSelect }}">
                                        <option value="">Select Country</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                {{-- Provider: Province --}}
                                <div x-show="billingDropdowns.hasProvider">
                                    <label class="{{ $vLabel }}">Province</label>
                                    <select name="billing_state" x-model="billing.state" @change="onBillingProvinceChange()" class="{{ $vSelect }}">
                                        <option value="">Select Province</option>
                                        <template x-for="p in (billingDropdowns.provinces || [])" :key="p.id">
                                            <option :value="p.name" x-text="p.name"></option>
                                        </template>
                                    </select>
                                </div>
                                {{-- Provider: City --}}
                                <div x-show="billingDropdowns.hasProvider && (billingDropdowns.cities || []).length > 0">
                                    <label class="{{ $vLabel }}">City / Municipality</label>
                                    <select name="billing_city" x-model="billing.city" @change="onBillingCityChange()" class="{{ $vSelect }}">
                                        <option value="">Select City</option>
                                        <template x-for="c in (billingDropdowns.cities || [])" :key="c.id">
                                            <option :value="c.name" x-text="c.name"></option>
                                        </template>
                                    </select>
                                </div>
                                {{-- Provider: District --}}
                                <div x-show="billingDropdowns.hasProvider && (billingDropdowns.districts || []).length > 0">
                                    <label class="{{ $vLabel }}">District</label>
                                    <select name="billing_district" x-model="billing.district" @change="onBillingDistrictChange()" class="{{ $vSelect }}">
                                        <option value="">Select District</option>
                                        <template x-for="d in (billingDropdowns.districts || [])" :key="d.id">
                                            <option :value="d.name" x-text="d.name"></option>
                                        </template>
                                    </select>
                                </div>
                                {{-- Provider: Barangay --}}
                                <div x-show="billingDropdowns.hasProvider && (billingDropdowns.barangays || []).length > 0">
                                    <label class="{{ $vLabel }}">Barangay</label>
                                    <select name="billing_barangay" x-model="billing.barangay" class="{{ $vSelect }}">
                                        <option value="">Select Barangay</option>
                                        <template x-for="b in (billingDropdowns.barangays || [])" :key="b.id">
                                            <option :value="b.name" x-text="b.name"></option>
                                        </template>
                                    </select>
                                </div>
                                {{-- Core: State --}}
                                <div x-show="!billingDropdowns.hasProvider && billing.country_id">
                                    <label class="{{ $vLabel }}">State / Province</label>
                                    <template x-if="(billingDropdowns.zones || []).length > 0">
                                        <select name="billing_state" x-model="billing.state" class="{{ $vSelect }}">
                                            <option value="">Select State / Province</option>
                                            <template x-for="z in (billingDropdowns.zones || [])" :key="z.id">
                                                <option :value="z.name" x-text="z.name"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="(billingDropdowns.zones || []).length === 0">
                                        <input type="text" name="billing_state" x-model="billing.state" class="{{ $vInput }}">
                                    </template>
                                </div>
                                {{-- Core: City --}}
                                <div x-show="!billingDropdowns.hasProvider && billing.country_id">
                                    <label class="{{ $vLabel }}">City</label>
                                    <input type="text" name="billing_city" x-model="billing.city" class="{{ $vInput }}">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="{{ $vLabel }}">Address Line 1</label>
                                    <input type="text" name="billing_address_1" x-model="billing.address_1" class="{{ $vInput }}">
                                </div>
                                <div>
                                    <label class="{{ $vLabel }}">Postcode</label>
                                    <input type="text" name="billing_postcode" x-model="billing.postcode" class="{{ $vInput }}">
                                </div>
                                <div>
                                    <label class="{{ $vLabel }}">Phone</label>
                                    <div class="flex gap-2">
                                        <span class="inline-flex items-center rounded-v-md border border-v-border bg-v-sunken px-3 text-xs text-v-muted"
                                            x-text="countriesPhoneCodes[billing.country_id] ? '+' + countriesPhoneCodes[billing.country_id] : '—'"></span>
                                        <input type="tel" name="billing_phone" x-model="billing.phone" class="{{ $vInput }} flex-1"
                                            placeholder="Mobile number" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                    </div>
                                </div>
                            </div>
                        </x-admin.card>

                        {{-- Shipping --}}
                        <x-admin.card title="Shipping">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="{{ $vLabel }}">Shipping Method</label>
                                    <input type="hidden" name="shipping_method" :value="shippingMethod === 'custom' ? shippingMethodCustom : shippingMethod">
                                    <div x-show="loadingShipping" class="flex items-center gap-2 py-2 text-[13px] text-v-muted">
                                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        Loading...
                                    </div>
                                    <select x-show="!loadingShipping" x-model="shippingMethod" @change="onShippingMethodChange()" class="{{ $vSelect }}">
                                        <option value="">Select...</option>
                                        <template x-for="sm in availableShippingMethods" :key="sm.key">
                                            <option :value="sm.name" x-text="sm.name + ' — ₱' + parseFloat(sm.cost).toFixed(2)"></option>
                                        </template>
                                        <option value="custom">Other (type below)</option>
                                    </select>
                                    <input x-show="shippingMethod === 'custom'" type="text" x-model="shippingMethodCustom" class="{{ $vInput }} mt-2" placeholder="Custom shipping method...">
                                    <p x-show="!loadingShipping && availableShippingMethods.length === 0 && items.length > 0 && shipping.country_id" class="mt-1 text-xs text-v-warning">No shipping methods available for this address. Use "Other" to set manually.</p>
                                </div>
                                <div>
                                    <label class="{{ $vLabel }}">Shipping Cost</label>
                                    <input type="number" name="shipping_cost" x-model.number="shippingCost" step="0.01" min="0" class="{{ $vInput }}">
                                </div>
                            </div>
                        </x-admin.card>

                        {{-- Payment, Status & Notes --}}
                        <x-admin.card title="Payment & Status">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="{{ $vLabel }}">Payment Method</label>
                                        <input type="hidden" name="payment_method" :value="paymentMethod === 'custom' ? paymentMethodCustom : paymentMethod">
                                        <div x-show="loadingPayments" class="flex items-center gap-2 py-2 text-[13px] text-v-muted">
                                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Loading...
                                        </div>
                                        <select x-show="!loadingPayments" x-model="paymentMethod" class="{{ $vSelect }}">
                                            <option value="">Select...</option>
                                            <template x-for="pm in availablePaymentMethods" :key="pm.key">
                                                <option :value="pm.key" x-text="pm.name"></option>
                                            </template>
                                            <option value="custom">Other (type below)</option>
                                        </select>
                                        <input x-show="paymentMethod === 'custom'" type="text" x-model="paymentMethodCustom" class="{{ $vInput }} mt-2" placeholder="Custom payment method...">
                                    </div>
                                    <div>
                                        <label for="order_status_id" class="{{ $vLabel }}">Order Status</label>
                                        <select name="order_status_id" id="order_status_id" required class="{{ $vSelect }}">
                                            @foreach ($statuses as $status)
                                                <option value="{{ $status->id }}"
                                                    {{ old('order_status_id', $order?->order_status_id) == $status->id ? 'selected' : '' }}>
                                                    {{ $status->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="{{ $vLabel }}">Order Comment</label>
                                    <textarea name="comment" rows="2" class="{{ $vInput }} h-auto py-2">{{ old('comment', $order?->comment) }}</textarea>
                                </div>
                                <div class="flex items-center gap-6">
                                    <label class="flex cursor-pointer items-center gap-2">
                                        <input type="checkbox" name="notify_customer" value="1" class="{{ $vCheck }}">
                                        <span class="text-[13px] text-v-text">Send order confirmation to customer</span>
                                    </label>
                                    <label class="flex cursor-pointer items-center gap-2">
                                        <input type="checkbox" name="notify_admin" value="1" class="{{ $vCheck }}">
                                        <span class="text-[13px] text-v-text">Send notification to admin</span>
                                    </label>
                                </div>
                            </div>
                        </x-admin.card>
                    </x-admin.tabs.panel>

                    {{-- ===================== PRODUCTS TAB ===================== --}}
                    <x-admin.tabs.panel name="products" class="space-y-6">
                        <x-admin.card title="Products">
                            {{-- No customer warning --}}
                            <div x-show="!customerId && !isNewCustomer" class="mb-4 rounded-v-md border border-v-warning bg-[color-mix(in_srgb,var(--warning)_10%,transparent)] p-4 text-[13px] text-v-warning">
                                Select or create a customer in the Details tab before adding products.
                            </div>

                            {{-- Product Search --}}
                            <div x-show="customerId || isNewCustomer" class="mb-4 flex items-center gap-3">
                                <div class="relative flex-1">
                                    <input type="text" x-model="productSearch" @input.debounce.300ms="searchProducts()" @focus="productDropdownOpen = true" @click.away="productDropdownOpen = false"
                                        placeholder="Search products by name or SKU..." autocomplete="off" class="{{ $vInput }}">
                                    <div x-show="productDropdownOpen && productResults.length > 0" x-cloak class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-v-md border border-v-border bg-v-surface shadow-lg">
                                        <template x-for="p in productResults" :key="(p.product_id || '') + '-' + (p.product_variant_id || '')">
                                            <button type="button" @click="addProduct(p)" class="w-full border-b border-v-border px-3 py-2 text-left text-[13px] transition-colors last:border-0 hover:bg-v-hover">
                                                <div class="flex items-center justify-between">
                                                    <span class="font-medium" x-text="p.name + (p.variant_label ? ' — ' + p.variant_label : '')"></span>
                                                    <span x-show="p.stock <= 0" class="ml-2 text-xs font-medium text-v-danger">Out of stock</span>
                                                    <span x-show="p.stock > 0 && p.stock <= 5" class="ml-2 text-xs font-medium text-v-warning" x-text="p.stock + ' left'"></span>
                                                </div>
                                                <div class="text-xs text-v-muted">
                                                    <span x-show="p.sku" x-text="'SKU: ' + p.sku"></span>
                                                    <span class="ml-2" x-text="'₱' + parseFloat(p.price).toFixed(2)"></span>
                                                    <span class="ml-2 text-v-muted" x-text="'Stock: ' + (p.stock ?? 0)"></span>
                                                </div>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <x-admin.button type="button" variant="secondary" @click="addCustomItem()">+ Custom Item</x-admin.button>
                            </div>

                            {{-- Line Items --}}
                            <div x-show="items.length === 0" class="py-8 text-center text-[13px] text-v-muted">
                                No items. Search for products above or add a custom item.
                            </div>

                            <div x-show="items.length > 0" class="overflow-x-auto">
                                <table class="v-table w-full text-[13px]">
                                    <thead class="bg-v-sunken">
                                        <tr>
                                            <th class="h-[var(--row-h)] px-3 text-left text-[10px] font-semibold uppercase tracking-widest text-v-muted" colspan="2">Product</th>
                                            <th class="h-[var(--row-h)] px-3 text-left text-[10px] font-semibold uppercase tracking-widest text-v-muted">SKU</th>
                                            <th class="h-[var(--row-h)] px-3 text-left text-[10px] font-semibold uppercase tracking-widest text-v-muted">Price</th>
                                            <th class="h-[var(--row-h)] px-3 text-left text-[10px] font-semibold uppercase tracking-widest text-v-muted">Customer Price</th>
                                            <th class="h-[var(--row-h)] px-3 text-left text-[10px] font-semibold uppercase tracking-widest text-v-muted">Qty</th>
                                            <th class="h-[var(--row-h)] px-3 text-left text-[10px] font-semibold uppercase tracking-widest text-v-muted">Total</th>
                                            <th class="h-[var(--row-h)] w-10 px-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-v-border">
                                        <template x-for="(item, iIndex) in items" :key="iIndex">
                                            <tr>
                                                <td class="w-12 py-2 pl-3">
                                                    <template x-if="item.image">
                                                        <img :src="item.image" :alt="item.name" class="h-10 w-10 rounded-v-md border border-v-border object-cover">
                                                    </template>
                                                    <template x-if="!item.image">
                                                        <div class="flex h-10 w-10 items-center justify-center rounded-v-md border border-v-border bg-v-sunken">
                                                            <x-admin.icon name="products" class="h-5 w-5 text-v-muted" />
                                                        </div>
                                                    </template>
                                                </td>
                                                <td class="px-2 py-2">
                                                    <template x-if="item.is_custom">
                                                        <input type="text" x-model="item.name" :name="'items['+iIndex+'][name]'" placeholder="Product name" class="{{ $vInputSm }} w-full">
                                                    </template>
                                                    <template x-if="!item.is_custom">
                                                        <div>
                                                            <div class="text-v-text" x-text="item.name"></div>
                                                            <div x-show="item.variant_label" class="text-xs text-v-accent" x-text="item.variant_label"></div>
                                                            <input type="hidden" :name="'items['+iIndex+'][name]'" :value="item.name">
                                                        </div>
                                                    </template>
                                                    <input type="hidden" :name="'items['+iIndex+'][product_id]'" :value="item.product_id || ''">
                                                    <input type="hidden" :name="'items['+iIndex+'][product_variant_id]'" :value="item.product_variant_id || ''">
                                                    <input type="hidden" :name="'items['+iIndex+'][variant_label]'" :value="item.variant_label || ''">
                                                    <input type="hidden" :name="'items['+iIndex+'][is_custom]'" :value="item.is_custom ? '1' : '0'">
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input type="text" x-model="item.sku" :name="'items['+iIndex+'][sku]'" class="{{ $vInputSm }} w-28" :class="item.is_custom ? '' : 'bg-v-sunken'" :readonly="!item.is_custom">
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input type="number" x-model="item.price" :name="'items['+iIndex+'][price]'" step="0.01" min="0" @input.debounce.500ms="refreshMethods()" class="{{ $vInputSm }} w-28">
                                                </td>
                                                <td class="px-3 py-2">
                                                    <template x-if="item.group_price_mode === 'sale'">
                                                        <div>
                                                            <span class="v-num text-xs text-v-muted line-through" x-text="parseFloat(item.base_price || 0).toFixed(2)"></span>
                                                            <span class="v-num ml-1 text-[13px] font-medium text-v-danger" x-text="parseFloat(item.group_price).toFixed(2)"></span>
                                                        </div>
                                                    </template>
                                                    <template x-if="item.group_price_mode === 'override'">
                                                        <span class="v-num text-[13px] font-medium text-v-accent" x-text="parseFloat(item.group_price).toFixed(2)"></span>
                                                    </template>
                                                    <template x-if="!item.group_price_mode || item.group_price_mode === 'regular'">
                                                        <span class="v-num text-[13px] text-v-muted" x-text="parseFloat(item.group_price || item.base_price || 0).toFixed(2)"></span>
                                                    </template>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input type="number" x-model.number="item.quantity" :name="'items['+iIndex+'][quantity]'" min="1" @input.debounce.500ms="refreshMethods()"
                                                        class="{{ $vInputSm }} w-20" :class="!item.is_custom && item.stock !== undefined && item.quantity > item.stock ? 'border-v-danger' : ''">
                                                    <template x-if="!item.is_custom && item.stock !== undefined && item.quantity > item.stock">
                                                        <p class="mt-1 text-xs text-v-danger" x-text="'Only ' + item.stock + ' in stock'"></p>
                                                    </template>
                                                </td>
                                                <td class="v-num px-3 py-2 text-[13px] font-medium text-v-text" x-text="(parseFloat(item.price || 0) * (item.quantity || 1)).toFixed(2)"></td>
                                                <td class="px-3 py-2">
                                                    <button type="button" @click="items.splice(iIndex, 1); $nextTick(() => refreshMethods())" class="text-v-danger hover:opacity-80">
                                                        <x-admin.icon name="trash" class="h-4 w-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            {{-- Coupon --}}
                            <div class="mt-4 rounded-v-md border border-v-border bg-v-sunken p-4">
                                <label class="{{ $vLabel }}">Coupon Code</label>
                                <input type="hidden" name="coupon_code" :value="couponCode">
                                <input type="hidden" name="coupon_discount" :value="couponDiscount">
                                <template x-if="!couponCode">
                                    <div>
                                        <div class="flex gap-2">
                                            <input type="text" x-model="couponInput" placeholder="Enter coupon code" @keydown.enter.prevent="applyCoupon()" class="{{ $vInput }} flex-1">
                                            <x-admin.button type="button" @click="applyCoupon()" x-bind:disabled="couponLoading || !couponInput.trim()">
                                                <span x-show="!couponLoading">Apply</span>
                                                <span x-show="couponLoading">...</span>
                                            </x-admin.button>
                                        </div>
                                        <p x-show="couponError" class="mt-1 text-xs text-v-danger" x-text="couponError"></p>
                                    </div>
                                </template>
                                <template x-if="couponCode">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="v-num text-[13px] font-medium text-v-accent" x-text="couponCode"></span>
                                            <span class="v-num ml-2 text-[13px] text-v-accent">-<span x-text="couponDiscount.toFixed(2)"></span></span>
                                        </div>
                                        <button type="button" @click="removeCoupon()" class="text-xs text-v-danger transition-colors hover:opacity-80">Remove</button>
                                    </div>
                                </template>
                            </div>

                            {{-- Totals --}}
                            <div class="mt-6 flex justify-end">
                                <div class="w-64 space-y-2">
                                    <div class="flex justify-between text-[13px]">
                                        <span class="text-v-muted">Subtotal</span>
                                        <span class="v-num font-medium text-v-text" x-text="subtotal.toFixed(2)"></span>
                                    </div>
                                    <div class="flex justify-between text-[13px] text-v-muted">
                                        <span>Shipping</span>
                                        <span class="v-num" x-text="(shippingCost || 0).toFixed(2)"></span>
                                    </div>
                                    <div x-show="couponDiscount > 0" class="flex justify-between text-[13px] text-v-accent">
                                        <span>Coupon (<span x-text="couponCode"></span>)</span>
                                        <span class="v-num">-<span x-text="couponDiscount.toFixed(2)"></span></span>
                                    </div>
                                    <div class="flex justify-between border-t border-v-border pt-2 text-[13px] font-bold text-v-text">
                                        <span>Total</span>
                                        <span class="v-num" x-text="orderTotal.toFixed(2)"></span>
                                    </div>
                                </div>
                            </div>
                        </x-admin.card>
                    </x-admin.tabs.panel>
                </x-admin.tabs>

                {{-- Submit --}}
                <div class="flex justify-end gap-3">
                    <x-admin.button variant="secondary" href="{{ route('admin.orders.index') }}">Cancel</x-admin.button>
                    <x-admin.button type="submit">{{ $order ? 'Update Order' : 'Create Order' }}</x-admin.button>
                </div>
            </form>

        </div>

        @php
            $formItems = $existingItems ?? [];
            if (old('items')) {
                $formItems = collect(old('items'))->map(function ($i) {
                    $image = null;
                    $productId = $i['product_id'] ?? null;
                    if ($productId) {
                        $product = \App\Models\Product::find($productId);
                        if ($product?->image) {
                            $image = storage_url($product->image);
                        }
                    }
                    return [
                        'product_id' => $productId ?? '',
                        'product_variant_id' => $i['product_variant_id'] ?? '',
                        'name' => $i['name'] ?? '',
                        'sku' => $i['sku'] ?? '',
                        'variant_label' => $i['variant_label'] ?? '',
                        'price' => $i['price'] ?? 0,
                        'quantity' => $i['quantity'] ?? 1,
                        'is_custom' => !empty($i['is_custom']),
                        'image' => $image,
                        'group_price' => null,
                    ];
                })->toArray();
            }
        @endphp

        @push('scripts')
        <script>
            function orderForm() {
                return {
                    storeId: '1',
                    items: @json($formItems),
                    shippingCost: {{ old('shipping_cost', $order?->shipping_cost ?? 0) }},

                    // Customer
                    customerId: '{{ old('customer_id', $order?->customer_id ?? '') }}',
                    customerFirstName: '{{ old('first_name', $order?->first_name ?? '') }}',
                    customerLastName: '{{ old('last_name', $order?->last_name ?? '') }}',
                    customerEmail: '{{ old('email', $order?->email ?? '') }}',
                    customerPhone: '{{ old('phone', $order?->phone ?? '') }}',
                    customerSearch: '',
                    customerResults: [],
                    customerDropdownOpen: false,
                    isNewCustomer: {{ old('is_new_customer') ? 'true' : 'false' }},

                    // Shipping address
                    shipping: {
                        first_name: '{{ old('shipping_first_name', $order?->shipping_first_name ?? '') }}',
                        last_name: '{{ old('shipping_last_name', $order?->shipping_last_name ?? '') }}',
                        country_id: '{{ old('shipping_country_id', $order?->shipping_country_id ?? '') }}',
                        state: '{{ old('shipping_state', $order?->shipping_state ?? '') }}',
                        city: '{{ old('shipping_city', $order?->shipping_city ?? '') }}',
                        district: '{{ old('shipping_district', $order?->shipping_district ?? '') }}',
                        barangay: '{{ old('shipping_barangay', $order?->shipping_barangay ?? '') }}',
                        address_1: '{{ old('shipping_address_1', $order?->shipping_address_1 ?? '') }}',
                        postcode: '{{ old('shipping_postcode', $order?->shipping_postcode ?? '') }}',
                        phone: '{{ old('shipping_phone', $order?->shipping_phone ?? '') }}',
                    },

                    phoneCode: @js($countries->find(old('shipping_country_id', $order?->shipping_country_id))?->phone_code ?? '63'),
                    countriesPhoneCodes: @js($countries->pluck('phone_code', 'id')),

                    // Billing address
                    differentBilling: {{ ($order?->billing_first_name) ? 'true' : 'false' }},
                    billing: {
                        first_name: '{{ old('billing_first_name', $order?->billing_first_name ?? '') }}',
                        last_name: '{{ old('billing_last_name', $order?->billing_last_name ?? '') }}',
                        address_1: '{{ old('billing_address_1', $order?->billing_address_1 ?? '') }}',
                        city: '{{ old('billing_city', $order?->billing_city ?? '') }}',
                        district: '{{ old('billing_district', $order?->billing_district ?? '') }}',
                        barangay: '{{ old('billing_barangay', $order?->billing_barangay ?? '') }}',
                        state: '{{ old('billing_state', $order?->billing_state ?? '') }}',
                        postcode: '{{ old('billing_postcode', $order?->billing_postcode ?? '') }}',
                        country_id: '{{ old('billing_country_id', $order?->billing_country_id ?? '') }}',
                        phone: '{{ old('billing_phone', $order?->billing_phone ?? '') }}',
                    },

                    // Payment & shipping method — start empty so the select shows "Select..."
                    // while options load. After fetch, we set these to the saved values.
                    paymentMethod: '',
                    paymentMethodCustom: '',
                    shippingMethod: '',
                    shippingMethodCustom: '',
                    availableShippingMethods: [],
                    availablePaymentMethods: [],
                    loadingShipping: false,
                    loadingPayments: false,
                    initialShippingMethod: @js(old('shipping_method', $order?->shipping_method ?? '')),
                    initialPaymentMethod: @js(old('payment_method', $order?->payment_method ?? '')),

                    // Customer addresses (for picker)
                    customerAddresses: [],

                    // Address dropdown data
                    providers: @json(app(\App\Services\AddressProviderRegistry::class)->toFrontendManifest()),
                    shippingDropdowns: { zones: [], provinces: [], cities: [], districts: [], barangays: [], hasProvider: false },
                    billingDropdowns: { zones: [], provinces: [], cities: [], districts: [], barangays: [], hasProvider: false },

                    // Product search
                    productSearch: '',
                    productResults: [],
                    productDropdownOpen: false,
                    customerGroupId: {{ json_encode($customerGroupId ?? null) }},
                    customerGroupName: @js($customerGroupName ?? null),

                    // Coupon
                    couponCode: '{{ old('coupon_code', $order?->coupon_code ?? '') }}',
                    couponDiscount: {{ old('coupon_discount', $order?->coupon_discount ?? 0) }},
                    couponInput: '',
                    couponLoading: false,
                    couponError: '',

                    get subtotal() {
                        return this.items.reduce((sum, i) => sum + (parseFloat(i.price || 0) * (i.quantity || 1)), 0);
                    },

                    get orderTotal() {
                        return Math.max(0, this.subtotal + (this.shippingCost || 0) - (this.couponDiscount || 0));
                    },

                    async searchCustomers() {
                        if (!this.storeId || this.customerSearch.length < 2) {
                            this.customerResults = [];
                            return;
                        }
                        const res = await fetch(`{{ route('admin.orders.search-customers') }}?store_id=${this.storeId}&q=${encodeURIComponent(this.customerSearch)}`);
                        this.customerResults = await res.json();
                        this.customerDropdownOpen = true;
                    },

                    clearCustomer() {
                        this.customerId = '';
                        this.customerFirstName = '';
                        this.customerLastName = '';
                        this.customerEmail = '';
                        this.customerPhone = '';
                        this.customerSearch = '';
                        this.customerAddresses = [];
                        this.customerGroupId = null;
                        this.customerGroupName = null;
                        this.isNewCustomer = false;
                        this.items = [];
                        this.availableShippingMethods = [];
                        this.availablePaymentMethods = [];
                        this.shippingMethod = '';
                        this.paymentMethod = '';
                        this.shipping = { first_name: '', last_name: '', country_id: '', state: '', city: '', district: '', barangay: '', address_1: '', postcode: '', phone: '' };
                        this.shippingDropdowns = { zones: [], provinces: [], cities: [], districts: [], barangays: [], hasProvider: false };
                        this.phoneCode = '63';
                    },

                    selectCustomer(c) {
                        this.customerId = c.id;
                        this.customerFirstName = c.first_name;
                        this.customerLastName = c.last_name;
                        this.customerEmail = c.email;
                        this.customerPhone = c.phone || '';
                        this.customerSearch = c.name;
                        this.customerDropdownOpen = false;
                        this.customerAddresses = c.addresses || [];
                        this.customerGroupId = c.customer_group_id || null;
                        this.customerGroupName = c.customer_group_name || null;
                        this.isNewCustomer = false;

                        // Customer group drives group-restricted shipping/payment rules,
                        // so recompute both when the selected customer changes.
                        this.$nextTick(() => this.refreshMethods());
                    },

                    loadCustomerAddress(addrId) {
                        const addr = this.customerAddresses.find(a => a.id == addrId);
                        if (addr) {
                            this.applyAddress(addr);
                        }
                    },

                    applyAddress(addr) {
                        this.shipping.first_name = addr.first_name || '';
                        this.shipping.last_name = addr.last_name || '';
                        this.shipping.country_id = String(addr.country_id || '');
                        this.shipping.state = addr.state || '';
                        this.shipping.city = addr.city || '';
                        this.shipping.district = addr.district || '';
                        this.shipping.barangay = addr.barangay || '';
                        this.shipping.address_1 = addr.address_1 || '';
                        this.shipping.postcode = addr.postcode || '';
                        this.shipping.phone = addr.phone || '';
                        this.phoneCode = this.countriesPhoneCodes[addr.country_id] || '63';
                        this.onShippingCountryChange(true);
                    },

                    async searchProducts() {
                        if (!this.storeId || this.productSearch.length < 2) {
                            this.productResults = [];
                            return;
                        }
                        let url = `{{ route('admin.orders.search-products') }}?store_id=${this.storeId}&q=${encodeURIComponent(this.productSearch)}`;
                        if (this.customerGroupId) {
                            url += `&customer_group_id=${this.customerGroupId}`;
                        }
                        const res = await fetch(url);
                        this.productResults = await res.json();
                        this.productDropdownOpen = true;
                    },

                    addProduct(p) {
                        this.items.push({
                            product_id: p.product_id,
                            product_variant_id: p.product_variant_id,
                            name: p.name,
                            sku: p.sku || '',
                            variant_label: p.variant_label || '',
                            price: p.price,
                            quantity: 1,
                            stock: p.stock ?? 0,
                            is_custom: false,
                            image: p.image || null,
                            group_price: p.group_price ?? null,
                            group_price_mode: p.group_price_mode || 'regular',
                            base_price: p.base_price || p.price,
                        });
                        this.productSearch = '';
                        this.productResults = [];
                        this.productDropdownOpen = false;
                        this.refreshMethods();
                    },

                    addCustomItem() {
                        this.items.push({
                            product_id: '',
                            product_variant_id: '',
                            name: '',
                            sku: '',
                            variant_label: '',
                            price: 0,
                            quantity: 1,
                            is_custom: true,
                            image: null,
                            group_price: null,
                            group_price_mode: 'regular',
                            base_price: 0,
                        });
                        this.$nextTick(() => this.refreshMethods());
                    },

                    // Generic sync helper
                    async syncSelect(obj, prop) {
                        const saved = obj[prop];
                        if (!saved) return;
                        obj[prop] = '';
                        await this.$nextTick();
                        obj[prop] = saved;
                    },

                    // ── Generic address handlers (work for both shipping and billing) ──

                    async _onCountryChange(addr, dropdowns, preserve) {
                        this.phoneCode = this.countriesPhoneCodes[addr.country_id] || '63';
                        if (!preserve) { addr.state = ''; addr.city = ''; addr.district = ''; addr.barangay = ''; }
                        Object.assign(dropdowns, { zones: [], provinces: [], cities: [], districts: [], barangays: [], hasProvider: false });
                        if (!addr.country_id) return;

                        const provider = this.providers[addr.country_id];
                        if (provider) {
                            dropdowns.hasProvider = true;
                            const rootField = provider.fields.find(f => !f.paramFrom);
                            if (rootField) {
                                const res = await fetch(rootField.endpoint);
                                dropdowns.provinces = await res.json();
                                if (preserve && addr.state) {
                                    await this.$nextTick();
                                    await this.syncSelect(addr, 'state');
                                    await this._onProvinceChange(addr, dropdowns, true);
                                }
                            }
                        } else {
                            const res = await fetch(`{{ route('admin.address.zones') }}?country_id=${addr.country_id}`);
                            dropdowns.zones = await res.json();
                            if (preserve && addr.state) { await this.$nextTick(); await this.syncSelect(addr, 'state'); }
                        }
                    },

                    async _onProvinceChange(addr, dropdowns, preserve) {
                        if (!preserve) { addr.city = ''; addr.district = ''; addr.barangay = ''; }
                        Object.assign(dropdowns, { cities: [], districts: [], barangays: [] });
                        const province = (dropdowns.provinces || []).find(p => p.name === addr.state);
                        if (!province) return;
                        const provider = this.providers[addr.country_id];
                        const cityField = provider?.fields.find(f => f.key === 'city');
                        if (!cityField) return;
                        const res = await fetch(`${cityField.endpoint}?${cityField.paramName}=${province.id}`);
                        dropdowns.cities = await res.json();
                        if (preserve && addr.city) { await this.$nextTick(); await this.syncSelect(addr, 'city'); await this._onCityChange(addr, dropdowns, true); }
                    },

                    async _onCityChange(addr, dropdowns, preserve) {
                        if (!preserve) { addr.district = ''; addr.barangay = ''; }
                        Object.assign(dropdowns, { districts: [], barangays: [] });
                        const city = (dropdowns.cities || []).find(c => c.name === addr.city);
                        if (!city) return;
                        const provider = this.providers[addr.country_id];
                        const distField = provider?.fields.find(f => f.key === 'district');
                        if (distField) {
                            const distRes = await fetch(`${distField.endpoint}?${distField.paramName}=${city.id}`);
                            dropdowns.districts = await distRes.json();
                            if (dropdowns.districts.length > 0) {
                                if (preserve && addr.district) { await this.$nextTick(); await this.syncSelect(addr, 'district'); await this._onDistrictChange(addr, dropdowns, true); }
                                return;
                            }
                        }
                        const brgyField = provider?.fields.find(f => f.key === 'barangay');
                        if (brgyField) {
                            const brgyRes = await fetch(`${brgyField.endpoint}?${brgyField.paramName}=${city.id}`);
                            dropdowns.barangays = await brgyRes.json();
                            if (preserve && addr.barangay) { await this.$nextTick(); await this.syncSelect(addr, 'barangay'); }
                        }
                    },

                    async _onDistrictChange(addr, dropdowns, preserve) {
                        if (!preserve) { addr.barangay = ''; }
                        dropdowns.barangays = [];
                        const district = (dropdowns.districts || []).find(d => d.name === addr.district);
                        if (!district) return;
                        const provider = this.providers[addr.country_id];
                        const brgyField = provider?.fields.find(f => f.key === 'barangay');
                        if (!brgyField) return;
                        const res = await fetch(`${brgyField.endpoint}?${brgyField.paramName}=${district.id}`);
                        dropdowns.barangays = await res.json();
                        if (preserve && addr.barangay) { await this.$nextTick(); await this.syncSelect(addr, 'barangay'); }
                    },

                    // ── Shipping wrappers ──
                    onShippingCountryChange(preserve) { this._onCountryChange(this.shipping, this.shippingDropdowns, preserve); this.$nextTick(() => this.refreshMethods()); },
                    onShippingProvinceChange(preserve) { this._onProvinceChange(this.shipping, this.shippingDropdowns, preserve); this.$nextTick(() => this.refreshMethods()); },
                    onShippingCityChange(preserve) { this._onCityChange(this.shipping, this.shippingDropdowns, preserve); this.$nextTick(() => this.refreshMethods()); },
                    onShippingDistrictChange(preserve) { return this._onDistrictChange(this.shipping, this.shippingDropdowns, preserve); },

                    // ── Billing wrappers ──
                    onBillingCountryChange(preserve) { return this._onCountryChange(this.billing, this.billingDropdowns, preserve); },
                    onBillingProvinceChange(preserve) { return this._onProvinceChange(this.billing, this.billingDropdowns, preserve); },
                    onBillingCityChange(preserve) { return this._onCityChange(this.billing, this.billingDropdowns, preserve); },
                    onBillingDistrictChange(preserve) { return this._onDistrictChange(this.billing, this.billingDropdowns, preserve); },

                    onShippingMethodChange() {
                        const selected = this.availableShippingMethods.find(sm => sm.name === this.shippingMethod);
                        if (selected) {
                            this.shippingCost = parseFloat(selected.cost) || 0;
                        }
                        this.fetchPaymentMethods();
                    },

                    refreshMethods() {
                        if (this.items.length > 0 && this.shipping.country_id) {
                            this.fetchShippingMethods().then(() => this.fetchPaymentMethods());
                        }
                    },

                    async fetchShippingMethods() {
                        if (!this.shipping.country_id || this.items.length === 0) {
                            this.availableShippingMethods = [];
                            return;
                        }
                        this.loadingShipping = true;
                        try {
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                            const res = await fetch('{{ route("admin.orders.available-shipping") }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    country_id: this.shipping.country_id,
                                    state: this.shipping.state,
                                    city: this.shipping.city,
                                    barangay: this.shipping.barangay,
                                    postcode: this.shipping.postcode,
                                    items: this.items.map(function(i) { return { product_id: i.product_id, product_variant_id: i.product_variant_id, quantity: i.quantity, price: i.price }; }),
                                    customer_group_id: this.customerGroupId,
                                }),
                            });
                            const data = await res.json();
                            this.availableShippingMethods = data.methods || [];
                            if (this.initialShippingMethod) {
                                const saved = this.initialShippingMethod;
                                this.initialShippingMethod = '';
                                const match = this.availableShippingMethods.find(sm => sm.name === saved);
                                this.$nextTick(() => {
                                    this.shippingMethod = match ? match.name : saved;
                                });
                            }
                        } catch (e) {
                            console.error('Failed to fetch shipping methods', e);
                            this.availableShippingMethods = [];
                        }
                        this.loadingShipping = false;
                    },

                    async fetchPaymentMethods() {
                        if (this.items.length === 0) {
                            this.availablePaymentMethods = [];
                            return;
                        }
                        this.loadingPayments = true;
                        try {
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                            const totalWeight = this.items.reduce(function(sum, i) { return sum + (parseFloat(i.weight) || 0) * (i.quantity || 1); }, 0);
                            const totalQty = this.items.reduce(function(sum, i) { return sum + (i.quantity || 1); }, 0);
                            const res = await fetch('{{ route("admin.orders.available-payments") }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    country_id: this.shipping.country_id,
                                    state: this.shipping.state,
                                    city: this.shipping.city,
                                    barangay: this.shipping.barangay,
                                    postcode: this.shipping.postcode,
                                    subtotal: this.subtotal,
                                    weight: totalWeight,
                                    quantity: totalQty,
                                    customer_group_id: this.customerGroupId,
                                    shipping_method_key: this.shippingMethod !== 'custom' ? this.shippingMethod : null,
                                }),
                            });
                            const data = await res.json();
                            this.availablePaymentMethods = data.methods || [];
                            if (this.initialPaymentMethod) {
                                const saved = this.initialPaymentMethod;
                                this.initialPaymentMethod = '';
                                const match = this.availablePaymentMethods.find(pm => pm.key === saved);
                                this.$nextTick(() => {
                                    this.paymentMethod = match ? match.key : saved;
                                });
                            }
                        } catch (e) {
                            console.error('Failed to fetch payment methods', e);
                            this.availablePaymentMethods = [];
                        }
                        this.loadingPayments = false;
                    },

                    async applyCoupon() {
                        const code = this.couponInput.trim();
                        if (!code) return;
                        this.couponLoading = true;
                        this.couponError = '';
                        try {
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                            const res = await fetch('{{ route("admin.orders.validate-coupon") }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    code: code,
                                    subtotal: this.subtotal,
                                    shipping_cost: this.shippingCost || 0,
                                    customer_id: this.customerId || null,
                                }),
                            });
                            const data = await res.json();
                            if (data.success) {
                                this.couponCode = data.coupon_code;
                                this.couponDiscount = data.discount;
                                this.couponInput = '';
                                this.couponError = '';
                            } else {
                                this.couponError = data.message || 'Invalid coupon.';
                            }
                        } catch (e) {
                            this.couponError = 'Failed to validate coupon.';
                        }
                        this.couponLoading = false;
                    },

                    removeCoupon() {
                        this.couponCode = '';
                        this.couponDiscount = 0;
                        this.couponInput = '';
                        this.couponError = '';
                    },

                    init() {
                        // Handle existing order values that may not match dynamic options
                        const pm = this.paymentMethod;
                        if (pm && pm !== 'custom') {
                            // Keep as-is for now; dynamic fetch will validate
                        }
                        const sm = this.shippingMethod;
                        if (sm && sm !== 'custom') {
                            // Keep as-is
                        }

                        if (this.shipping.country_id) { this.onShippingCountryChange(true); }
                        if (this.billing.country_id) { this.onBillingCountryChange(true); }

                        // Auto-fetch methods if editing an existing order with items
                        this.$nextTick(() => this.refreshMethods());
                    }
                }
            }
        </script>
        @endpush
    </x-admin.page>
</x-admin.shell>
