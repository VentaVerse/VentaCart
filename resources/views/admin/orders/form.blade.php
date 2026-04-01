<x-admin-layout>
    <x-slot name="header">{{ $order ? 'Order #' . $order->id : 'Create Order' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.orders.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back to Orders
        </a>
    </x-slot>

    <div x-data="orderForm()" x-init="init()">
        <form method="POST"
              action="{{ $order ? route('admin.orders.update', $order) : route('admin.orders.store') }}"
              class="space-y-6">
            @csrf
            @if ($order)
                @method('PUT')
            @endif

            {{-- Validation Errors --}}
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                    <h4 class="text-sm font-medium text-red-800 mb-2">Please fix the following errors:</h4>
                    <ul class="list-disc list-inside space-y-1 text-sm text-red-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Tab Navigation --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <nav class="flex border-b border-gray-200 overflow-x-auto">
                    <template x-for="t in tabs" :key="t.id">
                        <button type="button" @click="activeTab = t.id"
                            :class="activeTab === t.id ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors"
                            x-text="t.label"></button>
                    </template>
                </nav>

                {{-- ===================== DETAILS ===================== --}}
                <div x-show="activeTab === 'details'" class="p-6 space-y-6">
                    <input type="hidden" name="store_id" value="1">

                    {{-- Customer --}}
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Customer</h3>
                        <div class="max-w-xl">
                            <div class="relative mb-3">
                                <input type="text" x-model="customerSearch" @input.debounce.300ms="searchCustomers()" @focus="customerDropdownOpen = true" @click.away="customerDropdownOpen = false"
                                    placeholder="Search customer by name or email..." autocomplete="off"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <div x-show="customerDropdownOpen && customerResults.length > 0" x-cloak class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                    <template x-for="c in customerResults" :key="c.id">
                                        <button type="button" @click="selectCustomer(c)" class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 transition-colors">
                                            <span class="font-medium" x-text="c.name"></span>
                                            <span class="text-gray-400 ml-1" x-text="c.email"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <input type="hidden" name="customer_id" :value="customerId">
                            <input type="hidden" name="phone" :value="customerPhone">
                            <div class="grid grid-cols-2 gap-3" x-show="customerId">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">First Name</label>
                                    <input type="text" name="first_name" required x-model="customerFirstName"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Last Name</label>
                                    <input type="text" name="last_name" required x-model="customerLastName"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Email</label>
                                    <input type="email" name="email" required x-model="customerEmail"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div class="col-span-2" x-show="customerAddresses.length > 0">
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Delivery Address</label>
                                    <select @change="loadCustomerAddress($event.target.value)" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Select address...</option>
                                        <template x-for="a in customerAddresses" :key="a.id">
                                            <option :value="a.id" x-text="a.label"></option>
                                        </template>
                                    </select>
                                </div>
                                <p x-show="customerAddresses.length === 0" class="col-span-2 text-xs text-gray-400">Customer has no saved addresses. Fill shipping details manually below.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Shipping Address --}}
                    <div class="border-t border-gray-200 pt-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Shipping Address</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">First Name</label>
                                <input type="text" name="shipping_first_name" x-model="shipping.first_name"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Last Name</label>
                                <input type="text" name="shipping_last_name" x-model="shipping.last_name"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Country</label>
                                <select name="shipping_country_id" x-model="shipping.country_id" @change="onShippingCountryChange()"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select Country</option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- Provider: Province --}}
                            <div x-show="shippingDropdowns.hasProvider">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Province</label>
                                <select name="shipping_state" x-model="shipping.state" @change="onShippingProvinceChange()"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select Province</option>
                                    <template x-for="p in (shippingDropdowns.provinces || [])" :key="p.id">
                                        <option :value="p.name" x-text="p.name"></option>
                                    </template>
                                </select>
                            </div>
                            {{-- Provider: City --}}
                            <div x-show="shippingDropdowns.hasProvider && (shippingDropdowns.cities || []).length > 0">
                                <label class="block text-xs font-medium text-gray-500 mb-1">City / Municipality</label>
                                <select name="shipping_city" x-model="shipping.city" @change="onShippingCityChange()"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select City</option>
                                    <template x-for="c in (shippingDropdowns.cities || [])" :key="c.id">
                                        <option :value="c.name" x-text="c.name"></option>
                                    </template>
                                </select>
                            </div>
                            {{-- Provider: District --}}
                            <div x-show="shippingDropdowns.hasProvider && (shippingDropdowns.districts || []).length > 0">
                                <label class="block text-xs font-medium text-gray-500 mb-1">District</label>
                                <select name="shipping_district" x-model="shipping.district" @change="onShippingDistrictChange()"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select District</option>
                                    <template x-for="d in (shippingDropdowns.districts || [])" :key="d.id">
                                        <option :value="d.name" x-text="d.name"></option>
                                    </template>
                                </select>
                            </div>
                            {{-- Provider: Barangay --}}
                            <div x-show="shippingDropdowns.hasProvider && (shippingDropdowns.barangays || []).length > 0">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Barangay</label>
                                <select name="shipping_barangay" x-model="shipping.barangay"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select Barangay</option>
                                    <template x-for="b in (shippingDropdowns.barangays || [])" :key="b.id">
                                        <option :value="b.name" x-text="b.name"></option>
                                    </template>
                                </select>
                            </div>
                            {{-- Core: State --}}
                            <div x-show="!shippingDropdowns.hasProvider && shipping.country_id">
                                <label class="block text-xs font-medium text-gray-500 mb-1">State / Province</label>
                                <template x-if="(shippingDropdowns.zones || []).length > 0">
                                    <select name="shipping_state" x-model="shipping.state"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Select State / Province</option>
                                        <template x-for="z in (shippingDropdowns.zones || [])" :key="z.id">
                                            <option :value="z.name" x-text="z.name"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="(shippingDropdowns.zones || []).length === 0">
                                    <input type="text" name="shipping_state" x-model="shipping.state"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </template>
                            </div>
                            {{-- Core: City --}}
                            <div x-show="!shippingDropdowns.hasProvider && shipping.country_id">
                                <label class="block text-xs font-medium text-gray-500 mb-1">City</label>
                                <input type="text" name="shipping_city" x-model="shipping.city"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Address Line 1</label>
                                <input type="text" name="shipping_address_1" x-model="shipping.address_1"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Postcode</label>
                                <input type="text" name="shipping_postcode" x-model="shipping.postcode"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Phone</label>
                                <div class="flex gap-2">
                                    <span class="inline-flex items-center px-3 rounded-lg border border-gray-300 bg-gray-50 text-sm text-gray-500" x-text="'+' + (phoneCode || '63')"></span>
                                    <input type="text" name="shipping_phone" x-model="shipping.phone"
                                        class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Phone number">
                                </div>
                            </div>
                        </div>
                    </div>


                    {{-- Billing Address --}}
                    <div class="border-t border-gray-200 pt-4">
                        <label class="flex items-center gap-2 mb-3 cursor-pointer">
                            <input type="checkbox" x-model="differentBilling" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-semibold text-gray-700">Use a different billing address</span>
                        </label>

                        <div x-show="differentBilling" x-transition class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">First Name</label>
                                <input type="text" name="billing_first_name" x-model="billing.first_name"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Last Name</label>
                                <input type="text" name="billing_last_name" x-model="billing.last_name"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Country</label>
                                <select name="billing_country_id" x-model="billing.country_id" @change="onBillingCountryChange()"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select Country</option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- Provider: Province --}}
                            <div x-show="billingDropdowns.hasProvider">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Province</label>
                                <select name="billing_state" x-model="billing.state" @change="onBillingProvinceChange()"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select Province</option>
                                    <template x-for="p in (billingDropdowns.provinces || [])" :key="p.id">
                                        <option :value="p.name" x-text="p.name"></option>
                                    </template>
                                </select>
                            </div>
                            {{-- Provider: City --}}
                            <div x-show="billingDropdowns.hasProvider && (billingDropdowns.cities || []).length > 0">
                                <label class="block text-xs font-medium text-gray-500 mb-1">City / Municipality</label>
                                <select name="billing_city" x-model="billing.city" @change="onBillingCityChange()"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select City</option>
                                    <template x-for="c in (billingDropdowns.cities || [])" :key="c.id">
                                        <option :value="c.name" x-text="c.name"></option>
                                    </template>
                                </select>
                            </div>
                            {{-- Provider: District --}}
                            <div x-show="billingDropdowns.hasProvider && (billingDropdowns.districts || []).length > 0">
                                <label class="block text-xs font-medium text-gray-500 mb-1">District</label>
                                <select name="billing_district" x-model="billing.district" @change="onBillingDistrictChange()"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select District</option>
                                    <template x-for="d in (billingDropdowns.districts || [])" :key="d.id">
                                        <option :value="d.name" x-text="d.name"></option>
                                    </template>
                                </select>
                            </div>
                            {{-- Provider: Barangay --}}
                            <div x-show="billingDropdowns.hasProvider && (billingDropdowns.barangays || []).length > 0">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Barangay</label>
                                <select name="billing_barangay" x-model="billing.barangay"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select Barangay</option>
                                    <template x-for="b in (billingDropdowns.barangays || [])" :key="b.id">
                                        <option :value="b.name" x-text="b.name"></option>
                                    </template>
                                </select>
                            </div>
                            {{-- Core: State --}}
                            <div x-show="!billingDropdowns.hasProvider && billing.country_id">
                                <label class="block text-xs font-medium text-gray-500 mb-1">State / Province</label>
                                <template x-if="(billingDropdowns.zones || []).length > 0">
                                    <select name="billing_state" x-model="billing.state"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Select State / Province</option>
                                        <template x-for="z in (billingDropdowns.zones || [])" :key="z.id">
                                            <option :value="z.name" x-text="z.name"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="(billingDropdowns.zones || []).length === 0">
                                    <input type="text" name="billing_state" x-model="billing.state"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </template>
                            </div>
                            {{-- Core: City --}}
                            <div x-show="!billingDropdowns.hasProvider && billing.country_id">
                                <label class="block text-xs font-medium text-gray-500 mb-1">City</label>
                                <input type="text" name="billing_city" x-model="billing.city"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Address Line 1</label>
                                <input type="text" name="billing_address_1" x-model="billing.address_1"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Postcode</label>
                                <input type="text" name="billing_postcode" x-model="billing.postcode"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Phone</label>
                                <input type="text" name="billing_phone" x-model="billing.phone"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </div>
                    </div>

                    {{-- Payment & Shipping Method --}}
                    <div class="border-t border-gray-200 pt-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                                <input type="hidden" name="payment_method" :value="paymentMethod === 'custom' ? paymentMethodCustom : paymentMethod">
                                <select x-model="paymentMethod"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select...</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="hitpay">HitPay</option>
                                    <option value="cod">Cash on Delivery</option>
                                    <option value="custom">Other (type below)</option>
                                </select>
                                <input x-show="paymentMethod === 'custom'" type="text" x-model="paymentMethodCustom"
                                    class="mt-2 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Custom payment method...">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Shipping Method</label>
                                <input type="hidden" name="shipping_method" :value="shippingMethod === 'custom' ? shippingMethodCustom : shippingMethod">
                                <select x-model="shippingMethod"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select...</option>
                                    <option value="Free Shipping">Free Shipping</option>
                                    <option value="Weight-Based">Weight-Based</option>
                                    <option value="Flat Rate">Flat Rate</option>
                                    <option value="Delivery PH">Delivery PH</option>
                                    <option value="DHL Express">DHL Express</option>
                                    <option value="custom">Other (type below)</option>
                                </select>
                                <input x-show="shippingMethod === 'custom'" type="text" x-model="shippingMethodCustom"
                                    class="mt-2 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Custom shipping method...">
                            </div>
                        </div>
                    </div>

                    {{-- Order Status --}}
                    <div class="border-t border-gray-200 pt-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="order_status_id" class="block text-sm font-medium text-gray-700 mb-1">Order Status</label>
                                <select name="order_status_id" id="order_status_id" required
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->id }}"
                                            {{ old('order_status_id', $order?->order_status_id) == $status->id ? 'selected' : '' }}>
                                            {{ $status->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Comment --}}
                    <div class="border-t border-gray-200 pt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Order Comment</label>
                        <textarea name="comment" rows="2" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('comment', $order?->comment) }}</textarea>
                    </div>

                    {{-- Notifications --}}
                    <div class="border-t border-gray-200 pt-4">
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="notify_customer" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Send order confirmation to customer</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="notify_admin" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Send notification to admin</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- ===================== PRODUCTS ===================== --}}
                <div x-show="activeTab === 'products'" class="p-6">
                    {{-- Product Search --}}
                    <div class="flex items-center gap-3 mb-4">
                        <div class="relative flex-1">
                            <input type="text" x-model="productSearch" @input.debounce.300ms="searchProducts()" @focus="productDropdownOpen = true" @click.away="productDropdownOpen = false"
                                placeholder="Search products by name, SKU, or model..." autocomplete="off"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <div x-show="productDropdownOpen && productResults.length > 0" x-cloak class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                <template x-for="p in productResults" :key="(p.product_id || '') + '-' + (p.product_variant_id || '')">
                                    <button type="button" @click="addProduct(p)" class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 transition-colors border-b border-gray-100 last:border-0">
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium" x-text="p.name + (p.variant_label ? ' — ' + p.variant_label : '')"></span>
                                            <span x-show="p.stock <= 0" class="text-xs font-medium text-red-600 ml-2">Out of stock</span>
                                            <span x-show="p.stock > 0 && p.stock <= 5" class="text-xs font-medium text-amber-600 ml-2" x-text="p.stock + ' left'"></span>
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            <span x-show="p.sku" x-text="'SKU: ' + p.sku"></span>
                                            <span class="ml-2" x-text="'₱' + parseFloat(p.price).toFixed(2)"></span>
                                            <span class="ml-2 text-gray-300" x-text="'Stock: ' + (p.stock ?? 0)"></span>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <button type="button" @click="addCustomItem()" class="px-3 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors whitespace-nowrap">
                            + Custom Item
                        </button>
                    </div>

                    {{-- Line Items --}}
                    <div x-show="items.length === 0" class="text-sm text-gray-400 py-8 text-center">
                        No items. Search for products above or add a custom item.
                    </div>

                    <div x-show="items.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                    <th class="px-3 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template x-for="(item, iIndex) in items" :key="iIndex">
                                    <tr>
                                        <td class="px-3 py-2">
                                            <template x-if="item.is_custom">
                                                <input type="text" x-model="item.name" :name="'items['+iIndex+'][name]'" placeholder="Product name"
                                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </template>
                                            <template x-if="!item.is_custom">
                                                <div>
                                                    <div class="text-sm text-gray-900" x-text="item.name"></div>
                                                    <div x-show="item.variant_label" class="text-xs text-indigo-500" x-text="item.variant_label"></div>
                                                    <input type="hidden" :name="'items['+iIndex+'][name]'" :value="item.name">
                                                </div>
                                            </template>
                                            <input type="hidden" :name="'items['+iIndex+'][product_id]'" :value="item.product_id || ''">
                                            <input type="hidden" :name="'items['+iIndex+'][product_variant_id]'" :value="item.product_variant_id || ''">
                                            <input type="hidden" :name="'items['+iIndex+'][model]'" :value="item.model || ''">
                                            <input type="hidden" :name="'items['+iIndex+'][variant_label]'" :value="item.variant_label || ''">
                                            <input type="hidden" :name="'items['+iIndex+'][is_custom]'" :value="item.is_custom ? '1' : '0'">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="text" x-model="item.sku" :name="'items['+iIndex+'][sku]'"
                                                class="w-28 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                :class="item.is_custom ? '' : 'bg-gray-50'" :readonly="!item.is_custom">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" x-model="item.price" :name="'items['+iIndex+'][price]'" step="0.01" min="0"
                                                class="w-28 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" x-model.number="item.quantity" :name="'items['+iIndex+'][quantity]'" min="1"
                                                class="w-20 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                :class="!item.is_custom && item.stock !== undefined && item.quantity > item.stock ? 'border-red-300 ring-1 ring-red-300' : ''">
                                            <template x-if="!item.is_custom && item.stock !== undefined && item.quantity > item.stock">
                                                <p class="text-xs text-red-600 mt-1" x-text="'Only ' + item.stock + ' in stock'"></p>
                                            </template>
                                        </td>
                                        <td class="px-3 py-2 text-sm font-medium text-gray-900" x-text="(parseFloat(item.price || 0) * (item.quantity || 1)).toFixed(2)"></td>
                                        <td class="px-3 py-2">
                                            <button type="button" @click="items.splice(iIndex, 1)" class="text-red-500 hover:text-red-700">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Totals --}}
                    <div class="mt-6 flex justify-end">
                        <div class="w-64 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Subtotal</span>
                                <span class="font-medium" x-text="subtotal.toFixed(2)"></span>
                            </div>
                            <div class="flex justify-between text-sm items-center">
                                <span class="text-gray-500">Shipping</span>
                                <input type="number" name="shipping_cost" x-model.number="shippingCost" step="0.01" min="0"
                                    class="w-28 rounded-lg border-gray-300 text-sm text-right focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="flex justify-between text-sm font-bold border-t border-gray-200 pt-2">
                                <span>Total</span>
                                <span x-text="orderTotal.toFixed(2)"></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Submit --}}
            <div class="flex justify-end space-x-3">
                <a href="{{ route('admin.orders.index') }}" class="px-6 py-2.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">Cancel</a>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    {{ $order ? 'Update Order' : 'Create Order' }}
                </button>
            </div>
        </form>

    </div>

    @push('scripts')
    <script>
        function orderForm() {
            return {
                activeTab: 'details',
                tabs: [
                    { id: 'details', label: 'Details' },
                    { id: 'products', label: 'Products' },
                ],
                storeId: '1',
                items: @json($existingItems ?? []),
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

                // Payment & shipping method
                paymentMethod: @js(old('payment_method', $order?->payment_method ?? '')),
                paymentMethodCustom: '',
                shippingMethod: @js(old('shipping_method', $order?->shipping_method ?? '')),
                shippingMethodCustom: '',

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

                get subtotal() {
                    return this.items.reduce((sum, i) => sum + (parseFloat(i.price || 0) * (i.quantity || 1)), 0);
                },

                get orderTotal() {
                    return this.subtotal + (this.shippingCost || 0);
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

                selectCustomer(c) {
                    this.customerId = c.id;
                    this.customerFirstName = c.first_name;
                    this.customerLastName = c.last_name;
                    this.customerEmail = c.email;
                    this.customerPhone = c.phone || '';
                    this.customerSearch = c.name;
                    this.customerDropdownOpen = false;
                    this.customerAddresses = c.addresses || [];

                    if (c.address) {
                        this.applyAddress(c.address);
                    }
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
                    const res = await fetch(`{{ route('admin.orders.search-products') }}?store_id=${this.storeId}&q=${encodeURIComponent(this.productSearch)}`);
                    this.productResults = await res.json();
                    this.productDropdownOpen = true;
                },

                addProduct(p) {
                    this.items.push({
                        product_id: p.product_id,
                        product_variant_id: p.product_variant_id,
                        name: p.name,
                        sku: p.sku || '',
                        model: p.model || '',
                        variant_label: p.variant_label || '',
                        price: p.price,
                        quantity: 1,
                        stock: p.stock ?? 0,
                        is_custom: false,
                    });
                    this.productSearch = '';
                    this.productResults = [];
                    this.productDropdownOpen = false;
                },

                addCustomItem() {
                    this.items.push({
                        product_id: '',
                        product_variant_id: '',
                        name: '',
                        sku: '',
                        model: '',
                        variant_label: '',
                        price: 0,
                        quantity: 1,
                        is_custom: true,
                    });
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
                onShippingCountryChange(preserve) { return this._onCountryChange(this.shipping, this.shippingDropdowns, preserve); },
                onShippingProvinceChange(preserve) { return this._onProvinceChange(this.shipping, this.shippingDropdowns, preserve); },
                onShippingCityChange(preserve) { return this._onCityChange(this.shipping, this.shippingDropdowns, preserve); },
                onShippingDistrictChange(preserve) { return this._onDistrictChange(this.shipping, this.shippingDropdowns, preserve); },

                // ── Billing wrappers ──
                onBillingCountryChange(preserve) { return this._onCountryChange(this.billing, this.billingDropdowns, preserve); },
                onBillingProvinceChange(preserve) { return this._onProvinceChange(this.billing, this.billingDropdowns, preserve); },
                onBillingCityChange(preserve) { return this._onCityChange(this.billing, this.billingDropdowns, preserve); },
                onBillingDistrictChange(preserve) { return this._onDistrictChange(this.billing, this.billingDropdowns, preserve); },

                init() {
                    const knownPayments = ['bank_transfer', 'hitpay', 'cod', ''];
                    const pm = this.paymentMethod;
                    if (pm && !knownPayments.includes(pm) && !pm.startsWith('bank_transfer')) {
                        this.paymentMethodCustom = pm;
                        this.paymentMethod = 'custom';
                    } else if (pm && pm.startsWith('bank_transfer')) {
                        this.paymentMethod = 'bank_transfer';
                    }

                    const knownShipping = ['Free Shipping', 'Weight-Based', 'Flat Rate', 'Delivery PH', 'DHL Express', ''];
                    const sm = this.shippingMethod;
                    if (sm && !knownShipping.includes(sm)) {
                        this.shippingMethodCustom = sm;
                        this.shippingMethod = 'custom';
                    }

                    if (this.shipping.country_id) { this.onShippingCountryChange(true); }
                    if (this.billing.country_id) { this.onBillingCountryChange(true); }
                }
            }
        }
    </script>
    @endpush
</x-admin-layout>
