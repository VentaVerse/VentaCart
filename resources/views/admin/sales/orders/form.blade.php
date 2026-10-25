<x-admin.shell :title="$order ? 'Order #' . $order->id : 'Create order'" active="sales">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.orders.index') }}">Orders</a>
                <span aria-hidden="true">/</span>
                <span>{{ $order ? '#' . $order->id : 'New order' }}</span>
            </div>
            <h1 class="v-head__title">{{ $order ? 'Order #' . $order->id : 'Create order' }}</h1>
            @if ($order)
                <p class="v-head__standing">Placed {{ $order->created_at->format('M d, Y H:i') }}</p>
            @endif
        </div>
        <div class="v-head__actions">
            @if ($order)
                <a href="{{ route('admin.orders.show', $order) }}" class="v-btn v-btn--secondary">View order</a>
            @endif
        </div>
    </div>

    <x-admin.form :action="$order ? route('admin.orders.update', $order) : route('admin.orders.store')" :method="$order ? 'PUT' : 'POST'">
        <input type="hidden" name="store_id" value="1">

        <div x-data="orderForm()" x-init="init()">
            <div class="v-settle">
                @if ($errors->any())
                    <div class="v-notice v-notice--bad">
                        <p class="v-notice__title">Please fix the following</p>
                        <ul class="v-notice__list">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div>
                    <div class="v-settle">

                        <x-admin.panel title="Customer">
                            <div class="v-settle">
                                <input type="hidden" name="customer_id" :value="customerId">
                                <input type="hidden" name="is_new_customer" :value="isNewCustomer && !customerId ? '1' : '0'">
                                <input type="hidden" name="phone" :value="customerPhone">

                                <div x-show="!customerId">
                                    <div class="v-seg v-gap-sm" role="group" aria-label="Customer source">
                                        <button type="button" class="v-seg__opt" :class="!isNewCustomer && 'is-on'" @click="isNewCustomer = false">Existing</button>
                                        <button type="button" class="v-seg__opt" :class="isNewCustomer && 'is-on'" @click="isNewCustomer = true">New customer</button>
                                    </div>
                                    <div x-show="!isNewCustomer" class="v-combo">
                                        <input type="text" x-model="customerSearch" @input.debounce.300ms="searchCustomers()" @focus="customerDropdownOpen = true" @click.away="customerDropdownOpen = false"
                                            placeholder="Search customers by name or email" autocomplete="off" aria-label="Search customers" class="v-control">
                                        <div x-show="customerDropdownOpen && customerResults.length > 0" x-cloak class="v-combo__menu">
                                            <template x-for="c in customerResults" :key="c.id">
                                                <button type="button" class="v-combo__opt" @click="selectCustomer(c)">
                                                    <span class="v-combo__main">
                                                        <span><strong x-text="c.name"></strong> <span class="v-cell-quiet" x-text="c.email"></span></span>
                                                        <span x-show="c.customer_group_name" class="v-badge v-badge--quiet" x-text="c.customer_group_name"></span>
                                                    </span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <div x-show="customerId || isNewCustomer" class="v-settle">
                                    <div x-show="customerId" class="v-pickband">
                                        <span class="v-pickband__who">
                                            <strong x-text="customerFirstName + ' ' + customerLastName + ' (' + customerEmail + ')'"></strong>
                                            <span x-show="customerGroupName" class="v-badge v-badge--info" x-text="customerGroupName"></span>
                                        </span>
                                        <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="clearCustomer()">Change</button>
                                    </div>
                                    <div x-show="isNewCustomer && !customerId" class="v-pickband v-pickband--good">
                                        <span>New customer: the account is created on save</span>
                                    </div>
                                    <div class="v-grid v-grid--2">
                                        <x-admin.field name="first_name" label="First name" required>
                                            <input type="text" name="first_name" required x-model="customerFirstName" class="v-control">
                                        </x-admin.field>
                                        <x-admin.field name="last_name" label="Last name" required>
                                            <input type="text" name="last_name" required x-model="customerLastName" class="v-control">
                                        </x-admin.field>
                                        <div class="v-span2">
                                            <x-admin.field name="email" label="Email" required>
                                                <input type="email" name="email" required x-model="customerEmail" class="v-control">
                                            </x-admin.field>
                                        </div>
                                        <div class="v-span2" x-show="customerAddresses.length > 0">
                                            <x-admin.field name="customer_address" label="Delivery address">
                                                <select @change="loadCustomerAddress($event.target.value)" class="v-control">
                                                    <option value="">Select an address</option>
                                                    <template x-for="a in customerAddresses" :key="a.id">
                                                        <option :value="a.id" x-text="a.label"></option>
                                                    </template>
                                                </select>
                                            </x-admin.field>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </x-admin.panel>

                        {{-- Products sit here, directly under the customer whose group
                             prices them, and ABOVE the shipping address and method that
                             are quoted FROM them. They used to live on a second tab, so
                             the page taught a sequence it could not honour: you reached
                             the shipping method and found an empty control, with the
                             thing that fills it on a tab you had not opened. --}}
                        <x-admin.panel title="Products">
                            <div class="v-settle">
                                <div x-show="!customerId && !isNewCustomer" class="v-notice">
                                    <p class="v-notice__text">Select or create a customer before adding products.</p>
                                </div>

                                <div x-show="customerId || isNewCustomer" class="v-inline">
                                    <div class="v-combo">
                                        <input type="text" x-model="productSearch" @input.debounce.300ms="searchProducts()" @focus="productDropdownOpen = true" @click.away="productDropdownOpen = false"
                                            placeholder="Search products by name or SKU" autocomplete="off" aria-label="Search products" class="v-control">
                                        <div x-show="productDropdownOpen && productResults.length > 0" x-cloak class="v-combo__menu">
                                            <template x-for="p in productResults" :key="(p.product_id || '') + '-' + (p.product_variant_id || '')">
                                                {{-- The photo leads the row because the row is often a
                                                     colourway: four cable lengths read as four near
                                                     identical lines of text, and the picture is the only
                                                     thing that tells them apart at a glance. --}}
                                                <button type="button" class="v-combo__opt v-combo__opt--media" @click="addProduct(p)">
                                                    <template x-if="p.image">
                                                        <img :src="p.image" alt="" class="v-thumb v-thumb--sm" loading="lazy" decoding="async" width="28" height="28">
                                                    </template>
                                                    <template x-if="!p.image">
                                                        <span class="v-thumb v-thumb--sm v-thumb--blank"><x-admin.icon name="products" /></span>
                                                    </template>
                                                    <span class="v-combo__body">
                                                        <span class="v-combo__main">
                                                            <strong x-text="p.name + (p.variant_label ? ' (' + p.variant_label + ')' : '')"></strong>
                                                            <span x-show="p.stock <= 0" class="v-badge v-badge--bad">Out of stock</span>
                                                            <span x-show="p.stock > 0 && p.stock <= 5" class="v-badge v-badge--warn" x-text="p.stock + ' left'"></span>
                                                        </span>
                                                        <span class="v-combo__meta">
                                                            <span x-show="p.sku" x-text="'SKU ' + p.sku"></span>
                                                            <span x-text="'₱' + parseFloat(p.price).toFixed(2)"></span>
                                                            <span x-text="'Stock ' + (p.stock ?? 0)"></span>
                                                        </span>
                                                    </span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                    <button type="button" class="v-btn v-btn--secondary" @click="addCustomItem()">Add custom item</button>
                                </div>

                                <div x-show="items.length === 0" class="v-empty">
                                    <p class="v-empty__title">No items yet</p>
                                    <p class="v-empty__line">Search the catalog above or add a custom line.</p>
                                </div>

                                <div x-show="items.length > 0" class="v-tablewrap">
                                    <table class="v-table">
                                        <thead>
                                            <tr>
                                                <th colspan="2">Product</th>
                                                <th>SKU</th>
                                                <th>Price</th>
                                                <th>Customer price</th>
                                                <th>Qty</th>
                                                <th class="v-cell-num">Total</th>
                                                <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(item, iIndex) in items" :key="iIndex">
                                                <tr>
                                                    <td>
                                                        <template x-if="item.image">
                                                            <img :src="item.image" :alt="item.name" class="v-thumb">
                                                        </template>
                                                        <template x-if="!item.image">
                                                            <span class="v-thumb v-thumb--blank"><x-admin.icon name="products" /></span>
                                                        </template>
                                                    </td>
                                                    <td>
                                                        <template x-if="item.is_custom">
                                                            <input type="text" x-model="item.name" :name="'items['+iIndex+'][name]'" placeholder="Product name" class="v-control v-control--sm">
                                                        </template>
                                                        <template x-if="!item.is_custom">
                                                            <span>
                                                                <span x-text="item.name"></span>
                                                                <span x-show="item.variant_label" class="v-subnote v-tone-act v-block" x-text="item.variant_label"></span>
                                                                <input type="hidden" :name="'items['+iIndex+'][name]'" :value="item.name">
                                                            </span>
                                                        </template>
                                                        <input type="hidden" :name="'items['+iIndex+'][product_id]'" :value="item.product_id || ''">
                                                        <input type="hidden" :name="'items['+iIndex+'][product_variant_id]'" :value="item.product_variant_id || ''">
                                                        <input type="hidden" :name="'items['+iIndex+'][variant_label]'" :value="item.variant_label || ''">
                                                        <input type="hidden" :name="'items['+iIndex+'][is_custom]'" :value="item.is_custom ? '1' : '0'">
                                                    </td>
                                                    <td>
                                                        <input type="text" x-model="item.sku" :name="'items['+iIndex+'][sku]'" class="v-control v-control--sm v-w-rate" :readonly="!item.is_custom">
                                                    </td>
                                                    <td>
                                                        <input type="number" x-model="item.price" :name="'items['+iIndex+'][price]'" step="0.01" min="0" @input.debounce.500ms="refreshMethods()" class="v-control v-control--sm v-w-rate">
                                                    </td>
                                                    <td>
                                                        <template x-if="item.group_price_mode === 'sale'">
                                                            <span class="v-mono">
                                                                <span class="v-strike v-cell-quiet" x-text="parseFloat(item.base_price || 0).toFixed(2)"></span>
                                                                <span class="v-tone-bad" x-text="parseFloat(item.group_price).toFixed(2)"></span>
                                                            </span>
                                                        </template>
                                                        <template x-if="item.group_price_mode === 'override'">
                                                            <span class="v-mono v-tone-act" x-text="parseFloat(item.group_price).toFixed(2)"></span>
                                                        </template>
                                                        <template x-if="!item.group_price_mode || item.group_price_mode === 'regular'">
                                                            <span class="v-mono v-cell-quiet" x-text="parseFloat(item.group_price || item.base_price || 0).toFixed(2)"></span>
                                                        </template>
                                                    </td>
                                                    <td>
                                                        <input type="number" x-model.number="item.quantity" :name="'items['+iIndex+'][quantity]'" min="1" @input.debounce.500ms="refreshMethods()"
                                                            class="v-control v-control--sm v-w-sort" :class="!item.is_custom && item.stock !== undefined && item.quantity > item.stock ? 'v-control--bad' : ''">
                                                        <template x-if="!item.is_custom && item.stock !== undefined && item.quantity > item.stock">
                                                            <span class="v-subnote v-tone-bad v-block" x-text="'Only ' + item.stock + ' in stock'"></span>
                                                        </template>
                                                    </td>
                                                    <td class="v-cell-num v-mono" x-text="(parseFloat(item.price || 0) * (item.quantity || 1)).toFixed(2)"></td>
                                                    <td class="v-cell-act">
                                                        <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="items.splice(iIndex, 1); $nextTick(() => refreshMethods())">Remove</button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Coupon entry renders only while the coupons extension
                                     is enabled (its route carries the apply endpoint). An
                                     existing order's stored discount still shows in the
                                     summary below either way. --}}
                                @if (Route::has('admin.orders.validate-coupon'))
                                <div class="v-well">
                                    <span class="v-field__label">Coupon code</span>
                                    <input type="hidden" name="coupon_code" :value="couponCode">
                                    <input type="hidden" name="coupon_discount" :value="couponDiscount">
                                    <template x-if="!couponCode">
                                        <span class="v-block">
                                            <span class="v-inline">
                                                <input type="text" x-model="couponInput" placeholder="Enter coupon code" @keydown.enter.prevent="applyCoupon()" aria-label="Coupon code" class="v-control">
                                                <button type="button" class="v-btn v-btn--secondary" @click="applyCoupon()" :disabled="couponLoading || !couponInput.trim()">
                                                    <span x-text="couponLoading ? '…' : 'Apply'"></span>
                                                </button>
                                            </span>
                                            <span x-show="couponError" class="v-subnote v-tone-bad v-block" x-text="couponError"></span>
                                        </span>
                                    </template>
                                    <template x-if="couponCode">
                                        <span class="v-between">
                                            <span class="v-mono"><strong x-text="couponCode"></strong> <span class="v-tone-act">-<span x-text="couponDiscount.toFixed(2)"></span></span></span>
                                            <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="removeCoupon()">Remove</button>
                                        </span>
                                    </template>
                                </div>
                                @endif

                            </div>
                        </x-admin.panel>

                        <x-admin.panel title="Shipping address">
                            <div class="v-grid v-grid--2">
                                <x-admin.field name="shipping_first_name" label="First name">
                                    <input type="text" name="shipping_first_name" x-model="shipping.first_name" class="v-control">
                                </x-admin.field>
                                <x-admin.field name="shipping_last_name" label="Last name">
                                    <input type="text" name="shipping_last_name" x-model="shipping.last_name" class="v-control">
                                </x-admin.field>
                                <x-admin.field name="shipping_country_id" label="Country">
                                    <select name="shipping_country_id" x-model="shipping.country_id" @change="onShippingCountryChange()" class="v-control">
                                        <option value="">Select country</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                                        @endforeach
                                    </select>
                                </x-admin.field>
                                {{-- Provider: Province --}}
                                <div x-show="shippingDropdowns.hasProvider">
                                    <x-admin.field name="shipping_state" label="Province">
                                        <select name="shipping_state" x-model="shipping.state" @change="onShippingProvinceChange()" class="v-control">
                                            <option value="">Select province</option>
                                            <template x-for="p in (shippingDropdowns.provinces || [])" :key="p.id">
                                                <option :value="p.name" x-text="p.name"></option>
                                            </template>
                                        </select>
                                    </x-admin.field>
                                </div>
                                {{-- Provider: City --}}
                                <div x-show="shippingDropdowns.hasProvider && (shippingDropdowns.cities || []).length > 0">
                                    <x-admin.field name="shipping_city" label="City / Municipality">
                                        <select name="shipping_city" x-model="shipping.city" @change="onShippingCityChange()" class="v-control">
                                            <option value="">Select city</option>
                                            <template x-for="c in (shippingDropdowns.cities || [])" :key="c.id">
                                                <option :value="c.name" x-text="c.name"></option>
                                            </template>
                                        </select>
                                    </x-admin.field>
                                </div>
                                {{-- Provider: District --}}
                                <div x-show="shippingDropdowns.hasProvider && (shippingDropdowns.districts || []).length > 0">
                                    <x-admin.field name="shipping_district" label="District" :required="true">
                                        <select name="shipping_district" x-model="shipping.district" :required="shippingDropdowns.hasProvider && (shippingDropdowns.districts || []).length > 0" @change="onShippingDistrictChange()" class="v-control">
                                            <option value="">Select district</option>
                                            <template x-for="d in (shippingDropdowns.districts || [])" :key="d.id">
                                                <option :value="d.name" x-text="d.name"></option>
                                            </template>
                                        </select>
                                    </x-admin.field>
                                </div>
                                {{-- Provider: Barangay --}}
                                <div x-show="shippingDropdowns.hasProvider && (shippingDropdowns.barangays || []).length > 0">
                                    <x-admin.field name="shipping_barangay" label="Barangay" :required="true">
                                        <select name="shipping_barangay" x-model="shipping.barangay" :required="shippingDropdowns.hasProvider && (shippingDropdowns.barangays || []).length > 0" class="v-control">
                                            <option value="">Select barangay</option>
                                            <template x-for="b in (shippingDropdowns.barangays || [])" :key="b.id">
                                                <option :value="b.name" x-text="b.name"></option>
                                            </template>
                                        </select>
                                    </x-admin.field>
                                </div>
                                {{-- Core: State --}}
                                <div x-show="!shippingDropdowns.hasProvider && shipping.country_id">
                                    <x-admin.field name="shipping_state" label="State / Province">
                                        <template x-if="(shippingDropdowns.zones || []).length > 0">
                                            <select name="shipping_state" x-model="shipping.state" @change="$nextTick(() => refreshMethods())" class="v-control">
                                                <option value="">Select state / province</option>
                                                <template x-for="z in (shippingDropdowns.zones || [])" :key="z.id">
                                                    <option :value="z.name" x-text="z.name"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="(shippingDropdowns.zones || []).length === 0">
                                            <input type="text" name="shipping_state" x-model="shipping.state" @change="$nextTick(() => refreshMethods())" class="v-control">
                                        </template>
                                    </x-admin.field>
                                </div>
                                {{-- Core: City --}}
                                <div x-show="!shippingDropdowns.hasProvider && shipping.country_id">
                                    <x-admin.field name="shipping_city" label="City">
                                        <input type="text" name="shipping_city" x-model="shipping.city" class="v-control">
                                    </x-admin.field>
                                </div>
                                <div class="v-span2">
                                    <x-admin.field name="shipping_address_1" label="Address line 1">
                                        <input type="text" name="shipping_address_1" x-model="shipping.address_1" class="v-control">
                                    </x-admin.field>
                                </div>
                                <x-admin.field name="shipping_postcode" label="Postcode">
                                    <input type="text" name="shipping_postcode" x-model="shipping.postcode" class="v-control">
                                </x-admin.field>
                                <x-admin.field name="shipping_phone" label="Phone">
                                    <span class="v-adorn">
                                        <span class="v-adorn__fix" x-text="'+' + (phoneCode || '63')"></span>
                                        <input type="text" name="shipping_phone" x-model="shipping.phone" placeholder="Phone number" class="v-control">
                                    </span>
                                </x-admin.field>
                            </div>
                        </x-admin.panel>

                        <x-admin.panel title="Billing address">
                            <div class="v-settle">
                                <label class="v-check">
                                    <input type="checkbox" x-model="differentBilling">
                                    <span>Use a different billing address</span>
                                </label>

                                <div x-show="differentBilling" x-transition class="v-grid v-grid--2">
                                    <x-admin.field name="billing_first_name" label="First name">
                                        <input type="text" name="billing_first_name" x-model="billing.first_name" class="v-control">
                                    </x-admin.field>
                                    <x-admin.field name="billing_last_name" label="Last name">
                                        <input type="text" name="billing_last_name" x-model="billing.last_name" class="v-control">
                                    </x-admin.field>
                                    <x-admin.field name="billing_country_id" label="Country">
                                        <select name="billing_country_id" x-model="billing.country_id" @change="onBillingCountryChange()" class="v-control">
                                            <option value="">Select country</option>
                                            @foreach ($countries as $country)
                                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                                            @endforeach
                                        </select>
                                    </x-admin.field>
                                    {{-- Provider: Province --}}
                                    <div x-show="billingDropdowns.hasProvider">
                                        <x-admin.field name="billing_state" label="Province">
                                            <select name="billing_state" x-model="billing.state" @change="onBillingProvinceChange()" class="v-control">
                                                <option value="">Select province</option>
                                                <template x-for="p in (billingDropdowns.provinces || [])" :key="p.id">
                                                    <option :value="p.name" x-text="p.name"></option>
                                                </template>
                                            </select>
                                        </x-admin.field>
                                    </div>
                                    {{-- Provider: City --}}
                                    <div x-show="billingDropdowns.hasProvider && (billingDropdowns.cities || []).length > 0">
                                        <x-admin.field name="billing_city" label="City / Municipality">
                                            <select name="billing_city" x-model="billing.city" @change="onBillingCityChange()" class="v-control">
                                                <option value="">Select city</option>
                                                <template x-for="c in (billingDropdowns.cities || [])" :key="c.id">
                                                    <option :value="c.name" x-text="c.name"></option>
                                                </template>
                                            </select>
                                        </x-admin.field>
                                    </div>
                                    {{-- Provider: District --}}
                                    <div x-show="billingDropdowns.hasProvider && (billingDropdowns.districts || []).length > 0">
                                        <x-admin.field name="billing_district" label="District" :required="true">
                                            <select name="billing_district" x-model="billing.district" :required="differentBilling && billingDropdowns.hasProvider && (billingDropdowns.districts || []).length > 0" @change="onBillingDistrictChange()" class="v-control">
                                                <option value="">Select district</option>
                                                <template x-for="d in (billingDropdowns.districts || [])" :key="d.id">
                                                    <option :value="d.name" x-text="d.name"></option>
                                                </template>
                                            </select>
                                        </x-admin.field>
                                    </div>
                                    {{-- Provider: Barangay --}}
                                    <div x-show="billingDropdowns.hasProvider && (billingDropdowns.barangays || []).length > 0">
                                        <x-admin.field name="billing_barangay" label="Barangay" :required="true">
                                            <select name="billing_barangay" x-model="billing.barangay" :required="differentBilling && billingDropdowns.hasProvider && (billingDropdowns.barangays || []).length > 0" class="v-control">
                                                <option value="">Select barangay</option>
                                                <template x-for="b in (billingDropdowns.barangays || [])" :key="b.id">
                                                    <option :value="b.name" x-text="b.name"></option>
                                                </template>
                                            </select>
                                        </x-admin.field>
                                    </div>
                                    {{-- Core: State --}}
                                    <div x-show="!billingDropdowns.hasProvider && billing.country_id">
                                        <x-admin.field name="billing_state" label="State / Province">
                                            <template x-if="(billingDropdowns.zones || []).length > 0">
                                                <select name="billing_state" x-model="billing.state" class="v-control">
                                                    <option value="">Select state / province</option>
                                                    <template x-for="z in (billingDropdowns.zones || [])" :key="z.id">
                                                        <option :value="z.name" x-text="z.name"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="(billingDropdowns.zones || []).length === 0">
                                                <input type="text" name="billing_state" x-model="billing.state" class="v-control">
                                            </template>
                                        </x-admin.field>
                                    </div>
                                    {{-- Core: City --}}
                                    <div x-show="!billingDropdowns.hasProvider && billing.country_id">
                                        <x-admin.field name="billing_city" label="City">
                                            <input type="text" name="billing_city" x-model="billing.city" class="v-control">
                                        </x-admin.field>
                                    </div>
                                    <div class="v-span2">
                                        <x-admin.field name="billing_address_1" label="Address line 1">
                                            <input type="text" name="billing_address_1" x-model="billing.address_1" class="v-control">
                                        </x-admin.field>
                                    </div>
                                    <x-admin.field name="billing_postcode" label="Postcode">
                                        <input type="text" name="billing_postcode" x-model="billing.postcode" class="v-control">
                                    </x-admin.field>
                                    <x-admin.field name="billing_phone" label="Phone">
                                        <span class="v-adorn">
                                            <span class="v-adorn__fix" x-text="countriesPhoneCodes[billing.country_id] ? '+' + countriesPhoneCodes[billing.country_id] : '-'"></span>
                                            <input type="tel" name="billing_phone" x-model="billing.phone" placeholder="Mobile number"
                                                inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="v-control">
                                        </span>
                                    </x-admin.field>
                                </div>
                            </div>
                        </x-admin.panel>

                        {{-- Flush, because this panel closes with a ledger. Same idiom as
                             the order view, where the ledger is the footer rule under the
                             items table rather than a card of its own. --}}
                        <x-admin.panel title="Shipping" flush>
                            <div class="v-panelpad v-grid v-grid--2">
                                <x-admin.field name="shipping_method" label="Shipping method">
                                    <input type="hidden" name="shipping_method" :value="shippingMethod === 'custom' ? shippingMethodCustom : shippingMethod">
                                    <span x-show="loadingShipping" class="v-loadline">
                                        <x-admin.icon name="spinner" class="v-spin" />
                                        Loading
                                    </span>
                                    <select x-show="!loadingShipping" x-model="shippingMethod" @change="onShippingMethodChange()" class="v-control">
                                        <option value="">Select</option>
                                        <template x-for="sm in availableShippingMethods" :key="sm.key">
                                            <option :value="sm.name" x-text="sm.name + ': ₱' + parseFloat(sm.cost).toFixed(2)"></option>
                                        </template>
                                        <option value="custom">Other (type below)</option>
                                    </select>
                                    <input x-show="shippingMethod === 'custom'" type="text" x-model="shippingMethodCustom" placeholder="Custom shipping method" class="v-control v-gap">
                                    {{-- The rate is quoted FROM the basket, so with nothing in it
                                         the control has no answer to give. It used to say nothing at
                                         all, which read as a broken dropdown. --}}
                                    <span x-show="!loadingShipping && items.length === 0" class="v-subnote">Quoted once the order has products.</span>
                                    <span x-show="!loadingShipping && availableShippingMethods.length === 0 && items.length > 0 && shipping.country_id" class="v-subnote v-tone-warn">
                                        No shipping methods reach this address. Use Other to set one manually.
                                    </span>
                                </x-admin.field>
                                <x-admin.field name="shipping_cost" label="Shipping cost">
                                    <input type="number" name="shipping_cost" x-model.number="shippingCost" step="0.01" min="0" class="v-control">
                                </x-admin.field>
                            </div>

                            {{-- The money, closing the panel that holds the last input
                                 feeding it. Every figure above it on the page: the basket,
                                 the coupon, the shipping cost. --}}
                            <div class="v-ledger">
                                <dl>
                                    <dt>Subtotal</dt>
                                    <dd class="v-mono" x-text="subtotal.toFixed(2)"></dd>
                                    <dt>Shipping</dt>
                                    <dd class="v-mono" x-text="(shippingCost || 0).toFixed(2)"></dd>
                                    <template x-if="couponDiscount > 0">
                                        <dt>Coupon (<span x-text="couponCode"></span>)</dt>
                                    </template>
                                    <template x-if="couponDiscount > 0">
                                        <dd class="v-mono v-tone-act">-<span x-text="couponDiscount.toFixed(2)"></span></dd>
                                    </template>
                                    <dt class="v-facts__sum">Total</dt>
                                    <dd class="v-facts__sum v-mono" x-text="orderTotal.toFixed(2)"></dd>
                                </dl>
                            </div>
                        </x-admin.panel>

                        <x-admin.panel title="Payment and status">
                            <div class="v-settle">
                                <div class="v-grid v-grid--2">
                                    <x-admin.field name="payment_method" label="Payment method">
                                        <input type="hidden" name="payment_method" :value="paymentMethod === 'custom' ? paymentMethodCustom : paymentMethod">
                                        <span x-show="loadingPayments" class="v-loadline">
                                            <x-admin.icon name="spinner" class="v-spin" />
                                            Loading
                                        </span>
                                        <select x-show="!loadingPayments" x-model="paymentMethod" class="v-control">
                                            <option value="">Select</option>
                                            <template x-for="pm in availablePaymentMethods" :key="pm.key">
                                                <option :value="pm.key" x-text="pm.name"></option>
                                            </template>
                                            <option value="custom">Other (type below)</option>
                                        </select>
                                        <input x-show="paymentMethod === 'custom'" type="text" x-model="paymentMethodCustom" placeholder="Custom payment method" class="v-control v-gap">
                                    </x-admin.field>
                                    <x-admin.field name="order_status_id" label="Order status" required>
                                        <select name="order_status_id" required class="v-control">
                                            @foreach ($statuses as $status)
                                                <option value="{{ $status->id }}" @selected(old('order_status_id', $order?->order_status_id) == $status->id)>
                                                    {{ $status->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </x-admin.field>
                                </div>
                                <x-admin.field name="comment" label="Order comment">
                                    <textarea name="comment" rows="2" class="v-control">{{ old('comment', $order?->comment) }}</textarea>
                                </x-admin.field>
                                <div class="v-actrow">
                                    <label class="v-check">
                                        <input type="checkbox" name="notify_customer" value="1">
                                        <span>Send order confirmation to customer</span>
                                    </label>
                                    <label class="v-check">
                                        <input type="checkbox" name="notify_admin" value="1">
                                        <span>Send notification to admin</span>
                                    </label>
                                </div>
                            </div>
                        </x-admin.panel>
                    </div>
                </div>
            </div>
        </div>
    </x-admin.form>

    @php
        $formItems = $existingItems ?? [];
        if (old('items')) {
            // One lookup per table for the whole basket, never a find per row.
            // The variant's own photo wins: a rejected save must come back
            // showing the colourway the merchant actually picked.
            $oldRows = collect(old('items'));
            $productPhotos = \App\Models\Product::whereIn('id', $oldRows->pluck('product_id')->filter()->unique())
                ->pluck('image', 'id');
            $variantPhotos = \App\Models\ProductVariant::whereIn('id', $oldRows->pluck('product_variant_id')->filter()->unique())
                ->pluck('image', 'id');

            $formItems = $oldRows->map(function ($i) use ($productPhotos, $variantPhotos) {
                $photo = ($variantPhotos[$i['product_variant_id'] ?? null] ?? null)
                    ?: ($productPhotos[$i['product_id'] ?? null] ?? null);
                $image = $photo ? thumb_url($photo) : null;
                $productId = $i['product_id'] ?? null;
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

                // Payment & shipping method — start empty so the select shows "Select"
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
                        const res = await fetch('{{ Route::has("admin.orders.validate-coupon") ? route("admin.orders.validate-coupon") : "" }}', {
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

                async init() {
                    // Restore the saved address cascades, then the method lists that
                    // depend on them, before telling the save doctrine this is the
                    // clean state. The dirty flag must not fire on hydration.
                    if (this.shipping.country_id) { await this._onCountryChange(this.shipping, this.shippingDropdowns, true); }
                    if (this.billing.country_id) { await this._onCountryChange(this.billing, this.billingDropdowns, true); }
                    if (this.items.length > 0 && this.shipping.country_id) {
                        await this.fetchShippingMethods();
                        await this.fetchPaymentMethods();
                    }
                    await this.$nextTick();
                    await this.$nextTick();
                    this.$el.dispatchEvent(new CustomEvent('v-rebaseline', { bubbles: true }));
                }
            }
        }
    </script>
    @endpush
</x-admin.shell>
