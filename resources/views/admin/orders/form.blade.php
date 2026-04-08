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

            {{-- ===================== CUSTOMER & ADDRESS ===================== --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-6 space-y-6">
                    <input type="hidden" name="store_id" value="1">

                    {{-- Customer --}}
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Customer</h3>
                        <div class="max-w-xl">
                            <input type="hidden" name="customer_id" :value="customerId">
                            <input type="hidden" name="is_new_customer" :value="isNewCustomer && !customerId ? '1' : '0'">
                            <input type="hidden" name="phone" :value="customerPhone">

                            {{-- Search or New — unified input area --}}
                            <div x-show="!customerId" class="mb-3">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden text-xs">
                                        <button type="button" @click="isNewCustomer = false"
                                            :class="!isNewCustomer ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                            class="px-3 py-1.5 font-medium transition-colors">Existing</button>
                                        <button type="button" @click="isNewCustomer = true"
                                            :class="isNewCustomer ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                            class="px-3 py-1.5 font-medium transition-colors border-l border-gray-300">New Customer</button>
                                    </div>
                                </div>
                                <div x-show="!isNewCustomer" class="relative">
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
                            </div>

                            {{-- Selected Customer or New Customer Fields --}}
                            <div x-show="customerId || isNewCustomer" class="grid grid-cols-2 gap-3">
                                <div x-show="customerId" class="col-span-2 flex items-center justify-between bg-indigo-50 rounded-lg px-3 py-2 mb-1">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        <span class="text-sm text-indigo-700 font-medium" x-text="customerFirstName + ' ' + customerLastName + ' (' + customerEmail + ')'"></span>
                                    </div>
                                    <button type="button" @click="clearCustomer()" class="text-xs text-red-500 hover:text-red-700 font-medium shrink-0">Change</button>
                                </div>
                                <div x-show="isNewCustomer && !customerId" class="col-span-2 flex items-center gap-2 bg-emerald-50 rounded-lg px-3 py-2 mb-1">
                                    <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                                    <span class="text-sm text-emerald-700 font-medium">New customer — account will be created on save</span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                    <input type="text" name="first_name" required x-model="customerFirstName"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                    <input type="text" name="last_name" required x-model="customerLastName"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <input type="email" name="email" required x-model="customerEmail"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div class="col-span-2" x-show="customerAddresses.length > 0">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Address</label>
                                    <select @change="loadCustomerAddress($event.target.value)" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Select address...</option>
                                        <template x-for="a in customerAddresses" :key="a.id">
                                            <option :value="a.id" x-text="a.label"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Shipping Address --}}
                    <div class="border-t border-gray-200 pt-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Shipping Address</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                <input type="text" name="shipping_first_name" x-model="shipping.first_name"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                <input type="text" name="shipping_last_name" x-model="shipping.last_name"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">Province</label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">City / Municipality</label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">Barangay</label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">State / Province</label>
                                <template x-if="(shippingDropdowns.zones || []).length > 0">
                                    <select name="shipping_state" x-model="shipping.state" @change="$nextTick(() => refreshMethods())"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Select State / Province</option>
                                        <template x-for="z in (shippingDropdowns.zones || [])" :key="z.id">
                                            <option :value="z.name" x-text="z.name"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="(shippingDropdowns.zones || []).length === 0">
                                    <input type="text" name="shipping_state" x-model="shipping.state" @change="$nextTick(() => refreshMethods())"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </template>
                            </div>
                            {{-- Core: City --}}
                            <div x-show="!shippingDropdowns.hasProvider && shipping.country_id">
                                <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                <input type="text" name="shipping_city" x-model="shipping.city"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 1</label>
                                <input type="text" name="shipping_address_1" x-model="shipping.address_1"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Postcode</label>
                                <input type="text" name="shipping_postcode" x-model="shipping.postcode"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                <input type="text" name="billing_first_name" x-model="billing.first_name"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                <input type="text" name="billing_last_name" x-model="billing.last_name"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">Province</label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">City / Municipality</label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">Barangay</label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">State / Province</label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                <input type="text" name="billing_city" x-model="billing.city"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 1</label>
                                <input type="text" name="billing_address_1" x-model="billing.address_1"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Postcode</label>
                                <input type="text" name="billing_postcode" x-model="billing.postcode"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <div class="flex gap-2">
                                    <span class="inline-flex items-center px-3 rounded-md bg-gray-100 border border-gray-300 text-xs text-gray-500"
                                        x-text="countriesPhoneCodes[billing.country_id] ? '+' + countriesPhoneCodes[billing.country_id] : '—'"></span>
                                    <input type="tel" name="billing_phone" x-model="billing.phone"
                                        class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="Mobile number" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ===================== PRODUCTS ===================== --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Products</h3>
                </div>
                <div class="p-6">
                    {{-- No customer warning --}}
                    <div x-show="!customerId && !isNewCustomer" class="text-sm text-amber-600 bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4">
                        Select or create a customer above before adding products.
                    </div>

                    {{-- Product Search --}}
                    <div x-show="customerId || isNewCustomer" class="flex items-center gap-3 mb-4">
                        <div class="relative flex-1">
                            <input type="text" x-model="productSearch" @input.debounce.300ms="searchProducts()" @focus="productDropdownOpen = true" @click.away="productDropdownOpen = false"
                                placeholder="Search products by name or SKU..." autocomplete="off"
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
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase" colspan="2">Product</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Customer Price</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                    <th class="px-3 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template x-for="(item, iIndex) in items" :key="iIndex">
                                    <tr>
                                        <td class="pl-3 py-2 w-12">
                                            <template x-if="item.image">
                                                <img :src="item.image" :alt="item.name" class="w-10 h-10 rounded object-cover border border-gray-200">
                                            </template>
                                            <template x-if="!item.image">
                                                <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                </div>
                                            </template>
                                        </td>
                                        <td class="px-2 py-2">
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
                                            <template x-if="item.group_price_mode === 'sale'">
                                                <div>
                                                    <span class="text-xs text-gray-400 line-through" x-text="parseFloat(item.base_price || 0).toFixed(2)"></span>
                                                    <span class="text-sm text-red-600 font-medium ml-1" x-text="parseFloat(item.group_price).toFixed(2)"></span>
                                                </div>
                                            </template>
                                            <template x-if="item.group_price_mode === 'override'">
                                                <span class="text-sm text-emerald-600 font-medium" x-text="parseFloat(item.group_price).toFixed(2)"></span>
                                            </template>
                                            <template x-if="!item.group_price_mode || item.group_price_mode === 'regular'">
                                                <span class="text-sm text-gray-400" x-text="parseFloat(item.group_price || item.base_price || 0).toFixed(2)"></span>
                                            </template>
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

                    {{-- Coupon --}}
                    <div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Coupon Code</label>
                        <input type="hidden" name="coupon_code" :value="couponCode">
                        <input type="hidden" name="coupon_discount" :value="couponDiscount">
                        <template x-if="!couponCode">
                            <div>
                                <div class="flex gap-2">
                                    <input type="text" x-model="couponInput" placeholder="Enter coupon code"
                                        @keydown.enter.prevent="applyCoupon()"
                                        class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <button type="button" @click="applyCoupon()" :disabled="couponLoading || !couponInput.trim()"
                                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                                        <span x-show="!couponLoading">Apply</span>
                                        <span x-show="couponLoading">...</span>
                                    </button>
                                </div>
                                <p x-show="couponError" class="text-xs text-red-500 mt-1" x-text="couponError"></p>
                            </div>
                        </template>
                        <template x-if="couponCode">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-mono font-medium text-indigo-600" x-text="couponCode"></span>
                                    <span class="text-sm text-green-600 ml-2">-<span x-text="couponDiscount.toFixed(2)"></span></span>
                                </div>
                                <button type="button" @click="removeCoupon()" class="text-xs text-red-500 hover:text-red-700 transition-colors">Remove</button>
                            </div>
                        </template>
                    </div>

                    {{-- Totals --}}
                    <div class="mt-6 flex justify-end">
                        <div class="w-64 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Subtotal</span>
                                <span class="font-medium" x-text="subtotal.toFixed(2)"></span>
                            </div>
                            <div class="flex justify-between text-sm text-gray-400">
                                <span>Shipping</span>
                                <span x-text="(shippingCost || 0).toFixed(2)"></span>
                            </div>
                            <div x-show="couponDiscount > 0" class="flex justify-between text-sm text-green-600">
                                <span>Coupon (<span x-text="couponCode"></span>)</span>
                                <span>-<span x-text="couponDiscount.toFixed(2)"></span></span>
                            </div>
                            <div class="flex justify-between text-sm font-bold border-t border-gray-200 pt-2">
                                <span>Total</span>
                                <span x-text="orderTotal.toFixed(2)"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===================== SHIPPING ===================== --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Shipping</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="mb-1">
                                <label class="block text-sm font-medium text-gray-700">Shipping Method</label>
                            </div>
                            <input type="hidden" name="shipping_method" :value="shippingMethod === 'custom' ? shippingMethodCustom : shippingMethod">
                            <div x-show="loadingShipping" class="flex items-center gap-2 py-2 text-sm text-gray-400">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Loading...
                            </div>
                            <select x-show="!loadingShipping" x-model="shippingMethod" @change="onShippingMethodChange()"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select...</option>
                                <template x-for="sm in availableShippingMethods" :key="sm.key">
                                    <option :value="sm.name" x-text="sm.name + ' — ₱' + parseFloat(sm.cost).toFixed(2)"></option>
                                </template>
                                <option value="custom">Other (type below)</option>
                            </select>
                            <input x-show="shippingMethod === 'custom'" type="text" x-model="shippingMethodCustom"
                                class="mt-2 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Custom shipping method...">
                            <p x-show="!loadingShipping && availableShippingMethods.length === 0 && items.length > 0 && shipping.country_id" class="text-xs text-amber-500 mt-1">No shipping methods available for this address. Use "Other" to set manually.</p>
                        </div>
                        <div>
                            <div class="mb-1">
                                <label class="block text-sm font-medium text-gray-700">Shipping Cost</label>
                            </div>
                            <input type="number" name="shipping_cost" x-model.number="shippingCost" step="0.01" min="0"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===================== PAYMENT, STATUS & NOTES ===================== --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Payment & Status</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="mb-1">
                                <label class="block text-sm font-medium text-gray-700">Payment Method</label>
                            </div>
                            <input type="hidden" name="payment_method" :value="paymentMethod === 'custom' ? paymentMethodCustom : paymentMethod">
                            <div x-show="loadingPayments" class="flex items-center gap-2 py-2 text-sm text-gray-400">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Loading...
                            </div>
                            <select x-show="!loadingPayments" x-model="paymentMethod"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select...</option>
                                <template x-for="pm in availablePaymentMethods" :key="pm.key">
                                    <option :value="pm.key" x-text="pm.name"></option>
                                </template>
                                <option value="custom">Other (type below)</option>
                            </select>
                            <input x-show="paymentMethod === 'custom'" type="text" x-model="paymentMethodCustom"
                                class="mt-2 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Custom payment method...">
                        </div>
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Order Comment</label>
                        <textarea name="comment" rows="2" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('comment', $order?->comment) }}</textarea>
                    </div>
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

            {{-- Submit --}}
            <div class="flex justify-end space-x-3">
                <a href="{{ route('admin.orders.index') }}" class="px-6 py-2.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">Cancel</a>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    {{ $order ? 'Update Order' : 'Create Order' }}
                </button>
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

                // Payment & shipping method
                paymentMethod: @js(old('payment_method', $order?->payment_method ?? '')),
                paymentMethodCustom: '',
                shippingMethod: @js(old('shipping_method', $order?->shipping_method ?? '')),
                shippingMethodCustom: '',
                availableShippingMethods: [],
                availablePaymentMethods: [],
                loadingShipping: false,
                loadingPayments: false,

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
                    this.isNewCustomer = false;

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
</x-admin-layout>
