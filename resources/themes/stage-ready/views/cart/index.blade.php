<x-store-layout title="Shopping Cart">
    <div class="container-store py-10">
        <h1 class="section-title reveal">SHOPPING CART</h1>

        @if ($items->isEmpty())
            <div class="cart-empty-state reveal">
                <svg class="w-20 h-20 mx-auto text-[#333] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                <p class="text-[#555] text-lg mb-6" class="font-heading-wide">YOUR CART IS EMPTY</p>
                <a href="{{ route('home') }}" class="btn-checkout inline-block">CONTINUE SHOPPING</a>
            </div>
        @else
            @if ($hasOutOfStock)
            <div class="bg-red-900/30 border border-red-700 rounded-lg p-4 mb-6 reveal">
                <p class="text-red-300 text-sm">Some items in your cart are out of stock or have insufficient quantity. Please remove or update them before checking out.</p>
            </div>
        @endif

        <div x-data="cartPage({{ $items->toJson() }}, {{ $subtotal }}, {{ json_encode($outOfStockIds) }})" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {{-- Items List --}}
                <div class="lg:col-span-2">
                    <div class="cart-table">
                        <template x-for="item in items" :key="item.id">
                            <div class="cart-item" x-show="!item._removed" x-transition.opacity>
                                <a :href="item.product ? '/product/' + item.product.slug : '#'" class="cart-item-image">
                                    <template x-if="item.product && item.product.images && item.product.images.length">
                                        <img :src="storageUrl(item.product.images[0].path)" :alt="item.name" loading="lazy">
                                    </template>
                                    <template x-if="!item.product || !item.product.images || !item.product.images.length">
                                        <div class="w-full h-full flex items-center justify-center text-[#333]">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    </template>
                                </a>

                                <div class="cart-item-details">
                                    <a :href="item.product ? '/product/' + item.product.slug : '#'" class="cart-item-name hover:text-[var(--color-primary)] transition-colors" x-text="item.name"></a>
                                    <div class="cart-item-variant" x-show="item.variant_label" x-text="item.variant_label"></div>
                                    <div class="cart-item-sku" x-show="item.sku" x-text="'SKU: ' + item.sku"></div>
                                    <div x-show="outOfStockIds.includes(item.id)" class="mt-1">
                                        <span class="text-xs font-medium text-red-400 bg-red-900/40 px-2 py-0.5 rounded">OUT OF STOCK</span>
                                    </div>
                                </div>

                                <div class="cart-item-price" class="font-heading">
                                    {{ $defaultCurrency?->symbol ?? '₱' }}<span x-text="parseFloat(item.price).toFixed(2)"></span>
                                </div>

                                <div class="cart-item-qty">
                                    <button @click="decrement(item)" class="qty-btn">-</button>
                                    <input type="number" :value="item.quantity" @change="setQty(item, $event.target.value)" min="1" class="qty-input">
                                    <button @click="increment(item)" class="qty-btn">+</button>
                                </div>

                                <div class="cart-item-total" class="font-heading">
                                    {{ $defaultCurrency?->symbol ?? '₱' }}<span x-text="(item.price * item.quantity).toFixed(2)"></span>
                                </div>

                                <button @click="removeItem(item)" class="cart-item-remove" title="Remove">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Order Summary --}}
                <div>
                    <div class="order-summary-box">
                        <h3 class="text-lg tracking-[0.15em] mb-6 text-[#E8E8E8]" class="font-heading">ORDER SUMMARY</h3>

                        <div class="flex justify-between text-sm mb-3">
                            <span class="text-[#888]">Subtotal</span>
                            <span class="text-[#E8E8E8]" class="font-heading">{{ $defaultCurrency?->symbol ?? '₱' }}<span x-text="subtotal.toFixed(2)">{{ number_format($subtotal, 2) }}</span></span>
                        </div>

                        {{-- Shipping Estimate --}}
                        <div class="mb-3" x-data="shippingEstimate()">
                            <div class="flex justify-between text-sm mb-2">
                                <span class="text-[#888]">Shipping</span>
                                <button type="button" @click="expanded = !expanded" class="text-[var(--color-primary)] text-xs hover:underline">
                                    <span x-show="!expanded">Estimate Shipping</span>
                                    <span x-show="expanded">Hide</span>
                                </button>
                            </div>

                            <div x-show="expanded" x-transition.duration.200ms class="space-y-3 mb-3">
                                {{-- Country --}}
                                <select x-model="countryCode" @change="onCountryChange()" class="w-full rounded-lg bg-[#1A1A1A] border-[#333] text-sm text-[#CCC] focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)] py-2">
                                    <option value="">Select Country</option>
                                    <template x-for="c in countries" :key="c.id">
                                        <option :value="c.id" x-text="c.name"></option>
                                    </template>
                                </select>

                                {{-- Provider fields (e.g. PH: Province → City) --}}
                                <template x-for="field in providerFields" :key="field.key">
                                    <div x-show="field.conditional ? (providerData[field.key] || []).length > 0 : true">
                                        <select x-model="fieldValues[field.key]" @change="onProviderFieldChange(field.key)"
                                            class="w-full rounded-lg bg-[#1A1A1A] border-[#333] text-sm text-[#CCC] focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)] py-2">
                                            <option value="" x-text="'Select ' + field.label"></option>
                                            <template x-for="item in (providerData[field.key] || [])" :key="item.id">
                                                <option :value="item.name" x-text="item.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                </template>

                                {{-- Generic: State/Region (non-provider countries) --}}
                                <div x-show="countryCode && !hasProvider">
                                    <template x-if="zones.length > 0">
                                        <select x-model="state" class="w-full rounded-lg bg-[#1A1A1A] border-[#333] text-sm text-[#CCC] focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)] py-2">
                                            <option value="">Select State / Region</option>
                                            <template x-for="z in zones" :key="z.id">
                                                <option :value="z.name" x-text="z.name"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="zones.length === 0 && countryCode">
                                        <input type="text" x-model="state" placeholder="State / Region" class="w-full rounded-lg bg-[#1A1A1A] border-[#333] text-sm text-[#CCC] focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)] py-2">
                                    </template>
                                </div>

                                {{-- Generic: City (non-provider countries) --}}
                                <div x-show="countryCode && !hasProvider">
                                    <input type="text" x-model="city" placeholder="City" class="w-full rounded-lg bg-[#1A1A1A] border-[#333] text-sm text-[#CCC] focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)] py-2">
                                </div>

                                <button type="button" @click="getEstimate()" :disabled="!canEstimate || loading"
                                    class="w-full py-2 rounded-lg text-sm font-medium transition-colors"
                                    :class="!canEstimate || loading ? 'bg-[#333] text-[#666] cursor-not-allowed' : 'bg-[var(--color-primary)] text-black hover:brightness-110'">
                                    <span x-show="!loading">Get Estimate</span>
                                    <span x-show="loading">Loading...</span>
                                </button>

                                <template x-if="methods.length > 0">
                                    <div class="space-y-2">
                                        <template x-for="m in methods" :key="m.key">
                                            <div class="flex justify-between text-sm py-1">
                                                <span class="text-[#999]">
                                                    <span x-text="m.name"></span>
                                                    <template x-if="m.estimated_days">
                                                        <span class="text-[#666] text-xs" x-text="'~' + m.estimated_days + ' days'"></span>
                                                    </template>
                                                </span>
                                                <span class="text-[#E8E8E8]" class="font-heading">
                                                    <template x-if="m.cost === 0">
                                                        <span class="text-emerald-400">FREE</span>
                                                    </template>
                                                    <template x-if="m.cost > 0">
                                                        <span>{{ $defaultCurrency?->symbol ?? '₱' }}<span x-text="m.cost.toFixed(2)"></span></span>
                                                    </template>
                                                </span>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="noResults">
                                    <p class="text-xs text-[#666]">No shipping options available for this location.</p>
                                </template>
                            </div>

                            <template x-if="!expanded && methods.length === 0">
                                <div class="flex justify-between text-sm">
                                    <span></span>
                                    <span class="text-[#555]">Calculated at checkout</span>
                                </div>
                            </template>
                        </div>

                        <hr class="divider-glow my-4">

                        <div class="flex justify-between mb-6">
                            <span class="text-[#E8E8E8]" class="font-heading-wide">TOTAL</span>
                            <span class="text-xl text-[#E8E8E8]" class="font-heading">{{ $defaultCurrency?->symbol ?? '₱' }}<span x-text="subtotal.toFixed(2)">{{ number_format($subtotal, 2) }}</span></span>
                        </div>

                        <template x-if="outOfStockIds.length > 0">
                            <div class="btn-checkout block text-center mb-3 opacity-50 cursor-not-allowed">
                                PROCEED TO CHECKOUT
                            </div>
                        </template>
                        <template x-if="outOfStockIds.length === 0">
                            <div>
                                @auth('customer')
                                    <a href="{{ route('checkout.index') }}" class="btn-checkout block text-center mb-3"
                                       x-data="{ clicked: false }"
                                       @click="clicked = true"
                                       :class="clicked && 'btn-checkout-loading'">
                                        <span x-show="!clicked">PROCEED TO CHECKOUT</span>
                                        <span x-show="clicked" x-cloak class="inline-flex items-center gap-2">
                                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            LOADING...
                                        </span>
                                    </a>
                                @else
                                    <a href="{{ route('customer.login', ['redirect' => 'checkout']) }}" class="btn-checkout block text-center mb-3"
                                       x-data="{ clicked: false }"
                                       @click="clicked = true"
                                       :class="clicked && 'btn-checkout-loading'">
                                        <span x-show="!clicked">PROCEED TO CHECKOUT</span>
                                        <span x-show="clicked" x-cloak class="inline-flex items-center gap-2">
                                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            LOADING...
                                        </span>
                                    </a>
                                @endauth
                            </div>
                        </template>
                        <a href="{{ route('home') }}" class="block text-center text-sm text-[#555] hover:text-[var(--color-primary)] transition-colors">Continue Shopping</a>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @pushOnce('scripts')
    <script>
    function shippingEstimate() {
        return {
            expanded: false,
            countries: [],
            countryCode: '',
            zones: [],
            state: '',
            city: '',
            methods: [],
            loading: false,
            noResults: false,

            // Address provider system
            providers: window.__addressProviders || {},
            providerFields: [],
            providerData: {},
            fieldValues: {},

            init() {
                fetch('{{ route("storefront.address.countries") }}')
                    .then(r => r.json())
                    .then(d => this.countries = d);
            },

            get hasProvider() {
                return !!this.providers[this.countryCode];
            },

            get canEstimate() {
                if (!this.countryCode) return false;
                if (this.hasProvider) {
                    // Need at least the required fields filled
                    const required = this.providerFields.filter(f => f.required);
                    return required.every(f => this.fieldValues[f.key]);
                }
                return !!this.city;
            },

            onCountryChange() {
                this.state = '';
                this.city = '';
                this.zones = [];
                this.providerFields = [];
                this.providerData = {};
                this.fieldValues = {};
                this.methods = [];
                this.noResults = false;

                const provider = this.providers[this.countryCode];
                if (provider) {
                    this.providerFields = provider.fields;
                    // Load the root field (no paramFrom)
                    const root = provider.fields.find(f => !f.paramFrom);
                    if (root) {
                        fetch(root.endpoint)
                            .then(r => r.json())
                            .then(d => this.providerData[root.key] = d);
                    }
                } else if (this.countryCode) {
                    fetch('{{ route("storefront.address.zones") }}?country_id=' + this.countryCode)
                        .then(r => r.json())
                        .then(d => this.zones = d);
                }
            },

            onProviderFieldChange(fieldKey) {
                // Clear downstream fields
                const idx = this.providerFields.findIndex(f => f.key === fieldKey);
                for (let i = idx + 1; i < this.providerFields.length; i++) {
                    const f = this.providerFields[i];
                    this.fieldValues[f.key] = '';
                    this.providerData[f.key] = [];
                }

                // Map provider field values to state/city for the estimate
                this._syncFieldValues();

                // Load dependent fields
                for (const f of this.providerFields) {
                    if (!f.paramFrom) continue;
                    const sources = f.paramFrom.split('|');
                    if (!sources.includes(fieldKey)) continue;

                    // Resolve parent ID
                    let parentId = null;
                    for (const src of [...sources].reverse()) {
                        const val = this.fieldValues[src];
                        if (!val) continue;
                        const items = this.providerData[src] || [];
                        const match = items.find(item => item.name === val);
                        if (match) { parentId = match.id; break; }
                    }
                    if (!parentId) continue;

                    fetch(`${f.endpoint}?${f.paramName}=${parentId}`)
                        .then(r => r.json())
                        .then(d => this.providerData[f.key] = d);
                }
            },

            _syncFieldValues() {
                // Map provider fields to state/city used by the estimate endpoint
                for (const f of this.providerFields) {
                    if (f.key === 'state') this.state = this.fieldValues.state || '';
                    if (f.key === 'city') this.city = this.fieldValues.city || '';
                }
            },

            async getEstimate() {
                this._syncFieldValues();
                this.loading = true;
                this.methods = [];
                this.noResults = false;
                try {
                    const res = await fetch('{{ route("cart.estimate-shipping") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            country_id: this.countryCode,
                            state: this.state,
                            city: this.city,
                        })
                    });
                    const data = await res.json();
                    this.methods = data.methods || [];
                    if (this.methods.length === 0) this.noResults = true;
                } catch (e) {
                    console.error('Shipping estimate failed', e);
                    this.noResults = true;
                }
                this.loading = false;
            }
        };
    }
    </script>
    @endPushOnce
</x-store-layout>
