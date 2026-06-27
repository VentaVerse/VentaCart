import Alpine from 'alpinejs';

window.Alpine = Alpine;

/* ============================================
   Scroll Reveal Observer
   ============================================ */
document.addEventListener('DOMContentLoaded', () => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');

                // Stagger children if it's a stagger container
                if (entry.target.classList.contains('reveal-stagger')) {
                    const children = entry.target.children;
                    Array.from(children).forEach((child, i) => {
                        child.style.transitionDelay = `${i * 80}ms`;
                    });
                }

                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -40px 0px'
    });

    document.querySelectorAll('.reveal, .reveal-stagger').forEach(el => {
        observer.observe(el);
    });
});

/* ============================================
   Image Slider (Hero Banner)
   ============================================ */
Alpine.data('imageSlider', (config = {}) => ({
    current: 0,
    slides: config.slides || [],
    autoplay: config.autoplay || false,
    speed: config.speed || 5000,
    timer: null,

    init() {
        if (this.autoplay && this.slides.length > 1) {
            this.startAutoplay();
        }
    },

    next() {
        this.current = (this.current + 1) % this.slides.length;
        this.resetAutoplay();
    },

    prev() {
        this.current = (this.current - 1 + this.slides.length) % this.slides.length;
        this.resetAutoplay();
    },

    goTo(index) {
        this.current = index;
        this.resetAutoplay();
    },

    startAutoplay() {
        this.timer = setInterval(() => this.next(), this.speed);
    },

    resetAutoplay() {
        if (this.timer) clearInterval(this.timer);
        if (this.autoplay && this.slides.length > 1) {
            this.startAutoplay();
        }
    },

    destroy() {
        if (this.timer) clearInterval(this.timer);
    }
}));

/* ============================================
   Brand Carousel (auto-scroll)
   ============================================ */
Alpine.data('brandCarousel', (config = {}) => ({
    autoplay: config.autoplay || false,
    speed: config.speed || 3000,
    timer: null,
    scrollContainer: null,

    init() {
        this.scrollContainer = this.$refs.scroll;
        if (this.autoplay && this.scrollContainer) {
            this.startAutoplay();
        }
    },

    scrollLeft() {
        if (!this.scrollContainer) return;
        this.scrollContainer.scrollBy({ left: -160, behavior: 'smooth' });
    },

    scrollRight() {
        if (!this.scrollContainer) return;
        this.scrollContainer.scrollBy({ left: 160, behavior: 'smooth' });
    },

    startAutoplay() {
        this.timer = setInterval(() => {
            if (!this.scrollContainer) return;
            const maxScroll = this.scrollContainer.scrollWidth - this.scrollContainer.clientWidth;
            if (this.scrollContainer.scrollLeft >= maxScroll - 10) {
                this.scrollContainer.scrollTo({ left: 0, behavior: 'smooth' });
            } else {
                this.scrollRight();
            }
        }, this.speed);
    },

    destroy() {
        if (this.timer) clearInterval(this.timer);
    }
}));

/* ============================================
   Category Tabs
   ============================================ */
Alpine.data('categoryTabs', () => ({
    activeTab: 0,

    setTab(index) {
        this.activeTab = index;
    }
}));

/* ============================================
   Currency Selector
   ============================================ */
Alpine.data('currencySelector', (config = {}) => ({
    open: false,
    current: config.current || 'PHP',
    symbol: config.symbol || '₱',

    toggle() {
        this.open = !this.open;
    },

    select(code, symbol) {
        this.current = code;
        this.symbol = symbol;
        this.open = false;
        document.cookie = `currency=${code};path=/;max-age=31536000`;
        window.location.reload();
    }
}));

/* ============================================
   Mobile Navigation
   ============================================ */
Alpine.data('mobileNav', () => ({
    open: false,

    toggle() {
        this.open = !this.open;
        document.body.classList.toggle('overflow-hidden', this.open);
    },

    close() {
        this.open = false;
        document.body.classList.remove('overflow-hidden');
    }
}));

/* ============================================
   Countdown Timer
   ============================================ */
Alpine.data('countdown', (config = {}) => ({
    endDate: config.endDate || null,
    days: '00',
    hours: '00',
    minutes: '00',
    seconds: '00',
    expired: false,
    timer: null,

    init() {
        if (!this.endDate) {
            this.expired = true;
            return;
        }
        this.update();
        this.timer = setInterval(() => this.update(), 1000);
    },

    update() {
        const end = new Date(this.endDate).getTime();
        const now = Date.now();
        const diff = end - now;

        if (diff <= 0) {
            this.expired = true;
            if (this.timer) clearInterval(this.timer);
            return;
        }

        this.days = String(Math.floor(diff / 86400000)).padStart(2, '0');
        this.hours = String(Math.floor((diff % 86400000) / 3600000)).padStart(2, '0');
        this.minutes = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
        this.seconds = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
    },

    destroy() {
        if (this.timer) clearInterval(this.timer);
    }
}));

/* ============================================
   Search Autocomplete
   ============================================ */
Alpine.data('searchAutocomplete', () => ({
    query: '',
    results: [],
    showResults: false,
    loading: false,
    searched: false,
    controller: null,

    async search() {
        if (this.query.length < 2) {
            this.results = [];
            this.showResults = false;
            this.searched = false;
            return;
        }

        // Cancel previous request
        if (this.controller) this.controller.abort();
        this.controller = new AbortController();

        this.loading = true;
        this.showResults = true;

        try {
            const resp = await fetch(`/search/autocomplete?q=${encodeURIComponent(this.query)}`, {
                signal: this.controller.signal,
            });
            this.results = await resp.json();
            this.searched = true;
        } catch (e) {
            if (e.name !== 'AbortError') {
                this.results = [];
            }
        } finally {
            this.loading = false;
        }
    },

    close() {
        this.showResults = false;
    }
}));

/* ============================================
   Wishlist Button (localStorage)
   ============================================ */
Alpine.data('wishlistBtn', (productId) => ({
    wishlisted: false,

    init() {
        const list = JSON.parse(localStorage.getItem('ngd_wishlist') || '[]');
        this.wishlisted = list.includes(productId);
    },

    toggle() {
        let list = JSON.parse(localStorage.getItem('ngd_wishlist') || '[]');
        if (this.wishlisted) {
            list = list.filter(id => id !== productId);
        } else {
            list.push(productId);
        }
        localStorage.setItem('ngd_wishlist', JSON.stringify(list));
        this.wishlisted = !this.wishlisted;
    }
}));

/* ============================================
   Back to Top Button
   ============================================ */
Alpine.data('backToTop', () => ({
    visible: false,

    init() {
        const onScroll = () => {
            this.visible = window.scrollY > 400;
        };
        window.addEventListener('scroll', onScroll, { passive: true });
    },

    scrollUp() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}));

/* ============================================
   Add to Cart (product page)
   ============================================ */
Alpine.data('addToCart', (config = {}) => ({
    productId: config.productId,
    hasVariants: config.hasVariants || false,
    variantMatrix: config.variantMatrix || {},
    basePrice: config.basePrice || 0,
    priceTiers: config.priceTiers || [],
    productPrice: config.productPrice || config.basePrice || 0,
    selectedValues: {},
    quantity: 1,
    loading: false,
    activeVariant: null,
    message: '',
    messageType: '',
    notifyModal: false,
    notifyName: config.customerName || '',
    notifyEmail: config.customerEmail || '',
    notifyLoading: false,
    notifyMessage: '',
    notifySuccess: false,

    get isOutOfStock() {
        if (!config.trackStock) return false;
        if (this.hasVariants) {
            if (!this.activeVariant) return false;
            return parseInt(this.activeVariant.quantity) <= 0;
        }
        return parseInt(config.productQuantity) <= 0;
    },

    // Deepest quantity tier whose threshold <= current quantity (current group).
    get activeTier() {
        if (!this.priceTiers || !this.priceTiers.length) return null;
        let best = null;
        for (const t of this.priceTiers) {
            if (t.qty <= this.quantity && (!best || t.qty > best.qty)) best = t;
        }
        return best;
    },

    get unitPrice() {
        const tier = this.activeTier;
        if (this.hasVariants && this.activeVariant) {
            const variantPrice = parseFloat(this.activeVariant.price);
            // The variant's upcharge over base must be measured from its REGULAR
            // price. activeVariant.price is already special-discounted by the
            // controller's ripple, so using it here subtracts the discount twice
            // (e.g. tier 240 + (240 − 350) = 130 instead of 240). regular_price
            // is the true variant price; this matches CartService::resolvePrice,
            // which uses the stored (regular) variant price as its base.
            const variantRegular = parseFloat(this.activeVariant.regular_price ?? variantPrice);
            if (tier) return tier.price + (variantRegular - this.productPrice);
            return variantPrice;
        }
        return tier ? tier.price : this.basePrice;
    },

    get displayPrice() {
        return this.unitPrice;
    },

    get totalPrice() {
        return this.unitPrice * this.quantity;
    },

    init() {
        // Initialize selected values for each variant type
        (config.variantTypes || []).forEach(type => {
            this.selectedValues[type.id] = '';
        });
    },

    selectValue(typeId, valueId) {
        this.selectedValues[typeId] = valueId;
        this.resolveVariant();
    },

    resolveVariant() {
        if (!this.hasVariants) return;

        const key = Object.keys(this.selectedValues)
            .sort((a, b) => a - b)
            .map(k => this.selectedValues[k])
            .filter(v => v !== '')
            .join('_');

        this.activeVariant = this.variantMatrix[key] || null;
    },

    async submit() {
        if (this.hasVariants && !this.activeVariant) {
            this.message = 'Please select all options';
            this.messageType = 'error';
            return;
        }

        this.loading = true;
        this.message = '';

        try {
            const resp = await fetch('/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    product_id: this.productId,
                    quantity: this.quantity,
                    variant_id: this.activeVariant?.variant_id || null,
                }),
            });

            const data = await resp.json();

            if (data.success) {
                this.message = data.message;
                this.messageType = 'success';
                // Dispatch event to update cart badge in header
                window.dispatchEvent(new CustomEvent('cart-updated', {
                    detail: { count: data.count, subtotal: data.subtotal }
                }));
            } else {
                this.message = data.message || 'Failed to add to cart';
                this.messageType = 'error';
            }
        } catch (e) {
            this.message = 'Something went wrong';
            this.messageType = 'error';
        } finally {
            this.loading = false;
            setTimeout(() => { this.message = ''; }, 3000);
        }
    },

    async submitNotify() {
        this.notifyLoading = true;
        this.notifyMessage = '';
        try {
            const resp = await fetch('/notify-stock', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    product_id: this.productId,
                    variant_id: this.activeVariant?.variant_id || null,
                    name: this.notifyName,
                    email: this.notifyEmail,
                    'cf-turnstile-response': document.querySelector('#turnstile-notify [name="cf-turnstile-response"]')?.value
                        || document.querySelector('[name="cf-turnstile-response"]')?.value || '',
                }),
            });
            const data = await resp.json();
            this.notifyMessage = data.message;
            this.notifySuccess = data.success !== false;
            if (this.notifySuccess) {
                setTimeout(() => { this.notifyModal = false; }, 2000);
            }
        } catch (e) {
            this.notifyMessage = 'Something went wrong. Please try again.';
            this.notifySuccess = false;
        }
        this.notifyLoading = false;
    },
}));

/* ============================================
   Cart Page
   ============================================ */
Alpine.data('cartPage', (initialItems, initialSubtotal, initialOutOfStockIds) => ({
    items: initialItems || [],
    subtotal: initialSubtotal || 0,
    outOfStockIds: initialOutOfStockIds || [],
    stockWarning: '',

    recalcSubtotal() {
        this.subtotal = this.items
            .filter(i => !i._removed)
            .reduce((sum, i) => sum + (i.price * i.quantity), 0);
    },

    async setQty(item, val) {
        const qty = Math.max(1, parseInt(val) || 1);
        item.quantity = qty;
        this.recalcSubtotal();
        await this.syncQty(item);
    },

    async increment(item) {
        item.quantity++;
        this.recalcSubtotal();
        await this.syncQty(item);
    },

    async decrement(item) {
        if (item.quantity <= 1) return;
        item.quantity--;
        this.recalcSubtotal();
        await this.syncQty(item);
    },

    async syncQty(item) {
        this.stockWarning = '';
        const resp = await fetch(`/cart/${item.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ quantity: item.quantity }),
        });
        const data = await resp.json();
        if (data.success) {
            if (data.price != null) item.price = parseFloat(data.price);
            if (data.stock_warning) {
                this.stockWarning = data.stock_warning;
                item.quantity = data.quantity;
                this.recalcSubtotal();
                setTimeout(() => { this.stockWarning = ''; }, 5000);
            }
            this.subtotal = parseFloat(data.subtotal);
            window.dispatchEvent(new CustomEvent('cart-updated', {
                detail: { count: data.count, subtotal: data.subtotal }
            }));
        }
    },

    async removeItem(item) {
        item._removed = true;
        this.recalcSubtotal();

        const resp = await fetch(`/cart/${item.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });
        const data = await resp.json();
        if (data.success) {
            window.dispatchEvent(new CustomEvent('cart-updated', {
                detail: { count: data.count, subtotal: data.subtotal }
            }));
        }

        // If all items removed, reload to show empty state
        if (this.items.every(i => i._removed)) {
            window.location.reload();
        }
    }
}));

/* ============================================
   Address Form (provider-driven cascading dropdowns)
   ============================================ */
Alpine.data('addressForm', (config = {}) => ({
    countryCode: config.countryCode || '',
    state: config.state || '',
    city: config.city || '',
    district: config.district || '',
    barangay: config.barangay || '',
    phoneCodes: config.phoneCodes || window.__phoneCodes || {},
    countryIso2Map: window.__countryIso2Map || {},

    // Provider system
    providers: window.__addressProviders || {},
    providerFields: [],
    providerData: {},
    providerLoading: {},
    zones: [],
    loadingZones: false,

    // Backward compat getters for templates that still use these
    get provinces() { return this.providerData.state || []; },
    get cities() { return this.providerData.city || []; },
    get districts() { return this.providerData.district || []; },
    get barangays() { return this.providerData.barangay || []; },
    get loadingProvinces() { return this.providerLoading.state || false; },
    get loadingCities() { return this.providerLoading.city || false; },
    get loadingDistricts() { return this.providerLoading.district || false; },
    get loadingBarangays() { return this.providerLoading.barangay || false; },

    get countryIso2() {
        return this.countryIso2Map[this.countryCode] || '';
    },

    get hasProvider() {
        return !!this.providers[this.countryCode];
    },

    init() {
        if (this.countryCode) {
            this.onCountryChange(false);
        }
    },

    async syncSelect(prop) {
        const saved = this[prop];
        if (!saved) return;
        this[prop] = '';
        await this.$nextTick();
        this[prop] = saved;
    },

    async onCountryChange(clear = true) {
        if (clear) {
            this.state = '';
            this.city = '';
            this.district = '';
            this.barangay = '';
        }
        this.providerFields = [];
        this.providerData = {};
        this.providerLoading = {};
        this.zones = [];

        if (!this.countryCode) return;

        const provider = this.providers[this.countryCode];
        if (provider) {
            this.providerFields = provider.fields;
            const root = provider.fields.find(f => !f.paramFrom);
            if (root) {
                await this._loadField(root, !clear);
            }
        } else {
            this.loadingZones = true;
            try {
                const resp = await fetch(`/address-lookup/zones?country_id=${this.countryCode}`);
                this.zones = await resp.json();
                if (!clear && this.state) {
                    await this.$nextTick();
                    await this.syncSelect('state');
                }
            } finally {
                this.loadingZones = false;
            }
        }
    },

    // Generic field change handler — works for any provider
    async onFieldChange(fieldKey, clear = true) {
        const provider = this.providers[this.countryCode];
        if (!provider) return;

        // Clear downstream fields
        const idx = provider.fields.findIndex(f => f.key === fieldKey);
        if (clear) {
            for (let i = idx + 1; i < provider.fields.length; i++) {
                const f = provider.fields[i];
                this[f.key] = '';
                this.providerData[f.key] = [];
            }
        }

        if (!this[fieldKey]) return;

        // Load dependent fields
        for (const f of provider.fields) {
            if (!f.paramFrom) continue;
            const sources = f.paramFrom.split('|');
            if (!sources.includes(fieldKey)) continue;
            await this._loadField(f, !clear);
        }
    },

    async _loadField(field, restore = false) {
        if (!field.paramFrom) {
            // Root field — no dependency
            this.providerLoading[field.key] = true;
            try {
                const resp = await fetch(field.endpoint);
                this.providerData[field.key] = await resp.json();
                if (restore && this[field.key]) {
                    await this.$nextTick();
                    await this.syncSelect(field.key);
                    await this.onFieldChange(field.key, false);
                }
            } finally {
                this.providerLoading[field.key] = false;
            }
            return;
        }

        // Dependent field — resolve parent ID
        const sources = field.paramFrom.split('|');
        let parentId = null;
        for (const src of [...sources].reverse()) {
            const val = this[src];
            if (!val) continue;
            const items = this.providerData[src] || [];
            const match = items.find(item => item.name === val);
            if (match) { parentId = match.id; break; }
        }
        if (!parentId) return;

        this.providerLoading[field.key] = true;
        try {
            const resp = await fetch(`${field.endpoint}?${field.paramName}=${parentId}`);
            this.providerData[field.key] = await resp.json();
            if (restore && this[field.key]) {
                await this.$nextTick();
                await this.syncSelect(field.key);
                await this.onFieldChange(field.key, false);
            }
        } finally {
            this.providerLoading[field.key] = false;
        }
    },

    // Backward compat methods — delegate to generic handler
    async onProvinceChange(clear = true) { await this.onFieldChange('state', clear); },
    async onCityChange(clear = true) { await this.onFieldChange('city', clear); },
    async onDistrictChange(clear = true) { await this.onFieldChange('district', clear); },
}));

/* ============================================
   Checkout Wizard
   ============================================ */
Alpine.data('checkoutWizard', (addresses, defaultAddressId, items, subtotal, shippingMethods, paymentMethods, countries, customerName = {}) => ({
    step: 1,
    addresses,
    items,
    subtotal,
    shippingMethods,
    paymentMethods,
    countries,
    customerName,
    selectedAddressId: defaultAddressId,
    selectedShipping: shippingMethods[0]?.key || 'flat_rate',
    selectedPayment: paymentMethods[0]?.key || '',
    differentBilling: false,
    showNewAddressForm: addresses.length === 0,
    editingAddress: null,
    savingAddress: false,
    addressErrors: [],
    placing: false,
    showConfirmModal: false,
    comment: '',
    stockWarning: '',
    couponCode: '',
    couponDiscount: 0,
    couponFreeShipping: false,
    couponInput: '',
    couponLoading: false,
    couponError: '',
    qrOpen: false,
    qrSrc: '',
    qrName: '',
    billing: { first_name: customerName.firstName || '', last_name: customerName.lastName || '', address_1: '', city: '', district: '', barangay: '', state: '', postcode: '', country_id: '', phone: '' },
    newAddr: { first_name: customerName.firstName || '', last_name: customerName.lastName || '', address_1: '', city: '', district: '', barangay: '', state: '', postcode: '', country_id: '', phone: '' },

    get selectedShippingMethod() {
        return this.shippingMethods.find(m => m.key === this.selectedShipping) || null;
    },

    get selectedAddress() {
        return this.addresses.find(a => a.id === this.selectedAddressId) || {};
    },

    get selectedPaymentMethod() {
        return this.paymentMethods.find(m => m.key === this.selectedPayment) || {};
    },

    get shippingCost() {
        const method = this.shippingMethods.find(m => m.key === this.selectedShipping);
        return method ? method.cost : 0;
    },

    get effectiveShippingCost() {
        if (this.couponFreeShipping) return 0;
        return this.shippingCost;
    },

    get total() {
        return Math.max(0, this.subtotal + this.effectiveShippingCost - this.couponDiscount);
    },

    goToStep(n) {
        if (n === 2 && !this.selectedAddressId) return;
        this.step = n;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    loadingShipping: false,

    selectAddress(id) {
        if (this.selectedAddressId !== id) {
            this.selectedAddressId = id;
            this.refreshShippingRates();
        }
    },

    async refreshShippingRates() {
        if (!this.selectedAddressId) return;
        this.loadingShipping = true;
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const res = await fetch('/checkout/shipping-rates', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ address_id: this.selectedAddressId })
            });
            const data = await res.json();
            if (data.methods) {
                this.shippingMethods = data.methods;
                if (data.methods.length > 0) {
                    this.selectedShipping = data.methods[0].key;
                }
            }
        } catch (e) {
            console.error('Failed to fetch shipping rates', e);
        }
        this.loadingShipping = false;
    },

    openNewAddressForm() {
        this.editingAddress = null;
        this.newAddr = { first_name: this.customerName.firstName || '', last_name: this.customerName.lastName || '', address_1: '', city: '', district: '', barangay: '', state: '', postcode: '', country_id: '', phone: '' };
        this.showNewAddressForm = true;
    },

    editAddress(addr) {
        this.editingAddress = addr;
        this.newAddr = {
            first_name: addr.first_name || '',
            last_name: addr.last_name || '',
            address_1: addr.address_1 || '',
            city: addr.city || '',
            district: addr.district || '',
            barangay: addr.barangay || '',
            state: addr.state || '',
            postcode: addr.postcode || '',
            country_id: String(addr.country_id || ''),
            phone: addr.phone || '',
        };
        this.showNewAddressForm = true;
    },

    cancelAddressForm() {
        this.showNewAddressForm = false;
        this.editingAddress = null;
        this.newAddr = { first_name: this.customerName.firstName || '', last_name: this.customerName.lastName || '', address_1: '', city: '', district: '', barangay: '', state: '', postcode: '', country_id: '', phone: '' };
    },

    async saveNewAddress() {
        this.savingAddress = true;
        this.addressErrors = [];
        try {
            const body = { ...this.newAddr };
            if (!this.editingAddress) {
                body.is_default = this.addresses.length === 0 ? 1 : 0;
            }
            const url = this.editingAddress
                ? `/account/addresses/${this.editingAddress.id}`
                : '/checkout/address';
            const method = this.editingAddress ? 'PUT' : 'POST';
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body),
            });
            const data = await res.json();

            if (!res.ok) {
                if (data.errors) {
                    this.addressErrors = Object.values(data.errors).flat();
                } else if (data.message) {
                    this.addressErrors = [data.message];
                } else {
                    this.addressErrors = ['Failed to save address. Please check all required fields.'];
                }
                this.savingAddress = false;
                return;
            }

            if (data.success || data.addresses) {
                this.addresses = data.addresses || this.addresses;
                if (data.address) {
                    this.selectedAddressId = data.address.id;
                }
                this.showNewAddressForm = false;
                this.editingAddress = null;
                this.addressErrors = [];
                this.newAddr = { first_name: this.customerName.firstName || '', last_name: this.customerName.lastName || '', address_1: '', city: '', district: '', barangay: '', state: '', postcode: '', country_id: '', phone: '' };
                this.refreshShippingRates();
            }
        } catch (e) {
            this.addressErrors = ['A network error occurred. Please try again.'];
            console.error('Save address failed:', e);
        }
        this.savingAddress = false;
    },

    async updateItemQty(item, newQty) {
        newQty = parseInt(newQty) || 0;
        if (newQty < 1) {
            if (!confirm('Remove this item from cart?')) return;
            newQty = 0;
        }
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        try {
            const res = await fetch(`/cart/${item.id}`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ quantity: newQty })
            });
            const data = await res.json();
            if (data.success) {
                if (data.stock_warning) {
                    this.stockWarning = data.stock_warning;
                    newQty = data.quantity;
                    setTimeout(() => { this.stockWarning = ''; }, 5000);
                }
                if (newQty === 0) {
                    this.items = this.items.filter(function(i) { return i.id !== item.id; });
                    if (this.items.length === 0) {
                        window.location.href = '/cart';
                        return;
                    }
                } else {
                    item.quantity = newQty;
                    if (data.price != null) item.price = parseFloat(data.price);
                }
                this.subtotal = parseFloat(data.subtotal);
                window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: data.count, subtotal: data.subtotal, silent: true } }));
                this.refreshShippingRates();
            }
        } catch (e) {
            console.error('Failed to update quantity', e);
        }
    },

    async removeCheckoutItem(item) {
        await this.updateItemQty(item, 0);
    },

    async applyCoupon() {
        const code = this.couponInput.trim();
        if (!code) return;
        this.couponLoading = true;
        this.couponError = '';
        try {
            const res = await fetch('/checkout/coupon/apply', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ code }),
            });
            const data = await res.json();
            if (data.success) {
                this.couponCode = data.coupon_code;
                this.couponDiscount = parseFloat(data.discount);
                this.couponFreeShipping = data.free_shipping || false;
                this.couponInput = '';
                this.couponError = '';
                this.refreshShippingRates();
            } else {
                this.couponError = data.message || 'Invalid coupon code.';
            }
        } catch (e) {
            this.couponError = 'Something went wrong. Please try again.';
            console.error('Apply coupon failed:', e);
        }
        this.couponLoading = false;
    },

    async removeCoupon() {
        try {
            await fetch('/checkout/coupon/remove', {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });
        } catch (e) {
            console.error('Remove coupon failed:', e);
        }
        this.couponCode = '';
        this.couponDiscount = 0;
        this.couponFreeShipping = false;
        this.couponError = '';
        this.refreshShippingRates();
    },

    submitOrder() {
        if (!this.selectedAddressId || !this.selectedPayment) return;
        this.showConfirmModal = true;
    },

    closeConfirmModal() {
        this.showConfirmModal = false;
        this.placing = false;
    },

    confirmOrder() {
        this.placing = true;
        this.$refs.orderForm.submit();
    },
}));

Alpine.data('miniCart', (initialCount, initialSubtotal, symbol) => ({
    open: false,
    loading: false,
    items: [],
    count: initialCount,
    subtotal: initialSubtotal,
    cartSubtotal: 0,
    symbol: symbol,
    fetched: false,
    leaveTimer: null,

    autoCloseTimer: null,

    onCartUpdated(detail) {
        this.count = detail.count;
        this.subtotal = parseFloat(detail.subtotal);
        this.fetched = false;
        // Show mini cart briefly after add-to-cart (not for internal removes or cart page)
        if (this.count > 0 && !detail.silent && window.location.pathname !== '/cart') {
            this.open = true;
            this.fetchItems();
            this.clearAutoCloseTimer();
            this.autoCloseTimer = setTimeout(() => { this.open = false; }, 4000);
        }
    },

    clearAutoCloseTimer() {
        if (this.autoCloseTimer) {
            clearTimeout(this.autoCloseTimer);
            this.autoCloseTimer = null;
        }
    },

    onHover() {
        this.clearLeaveTimer();
        this.clearAutoCloseTimer();
        if (this.count === 0) return;
        this.open = true;
        if (!this.fetched) {
            this.fetchItems();
        }
    },

    onLeave() {
        this.clearAutoCloseTimer();
        this.leaveTimer = setTimeout(() => { this.open = false; }, 200);
    },

    clearLeaveTimer() {
        if (this.leaveTimer) {
            clearTimeout(this.leaveTimer);
            this.leaveTimer = null;
        }
    },

    async removeItem(itemId, index) {
        try {
            const res = await fetch(`/cart/${itemId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });
            const data = await res.json();
            this.items.splice(index, 1);
            this.count = data.count ?? this.items.reduce((s, i) => s + i.quantity, 0);
            this.subtotal = data.subtotal ?? this.items.reduce((s, i) => s + i.line_total, 0);
            this.cartSubtotal = this.subtotal;
            this.fetched = false;
            window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: this.count, subtotal: this.subtotal, silent: true } }));
            if (this.items.length === 0) {
                this.open = false;
            }
        } catch (e) {
            console.error('Remove item failed:', e);
        }
    },

    async fetchItems() {
        this.loading = true;
        try {
            const res = await fetch('/cart/mini', {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            this.items = data.items;
            this.cartSubtotal = data.subtotal;
            this.count = data.count;
            this.fetched = true;
        } catch (e) {
            console.error('Mini cart fetch failed:', e);
        }
        this.loading = false;
    },
}));

/* ============================================
   Recently Viewed (localStorage)
   ============================================ */
document.addEventListener('DOMContentLoaded', function() {
    const productIdMeta = document.querySelector('meta[name="product-id"]');
    if (!productIdMeta) return;

    const productId = parseInt(productIdMeta.content);
    if (!productId) return;

    let viewed = JSON.parse(localStorage.getItem('ngd_recently_viewed') || '[]');
    viewed = viewed.filter(id => id !== productId);
    viewed.unshift(productId);
    viewed = viewed.slice(0, 20);
    localStorage.setItem('ngd_recently_viewed', JSON.stringify(viewed));
});

/* ============================================
   Recently Viewed (Homepage)
   ============================================ */
Alpine.data('recentlyViewed', () => ({
    products: [],

    async init() {
        const ids = JSON.parse(localStorage.getItem('ngd_recently_viewed') || '[]');
        if (ids.length === 0) return;

        try {
            const resp = await fetch('/recently-viewed?' + ids.slice(0, 12).map(id => 'ids[]=' + id).join('&'));
            if (resp.ok) {
                this.products = await resp.json();
            }
        } catch (e) {
            // Silently fail
        }
    }
}));

Alpine.start();
