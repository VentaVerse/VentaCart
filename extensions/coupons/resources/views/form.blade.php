<x-admin3.shell :title="$coupon ? 'Edit Coupon' : 'New Coupon'">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.coupons.index') }}">Coupons</a>
                <span aria-hidden="true">/</span>
                <span>{{ $coupon->code ?? 'New' }}</span>
            </div>
            <h1 class="v3-head__title">{{ $coupon ? $coupon->code : 'New coupon' }}</h1>
        </div>
    </div>

    <x-admin3.form :action="$coupon ? route('admin.coupons.update', $coupon) : route('admin.coupons.store')"
                   :method="$coupon ? 'PUT' : 'POST'" class="v3-settle">
        {{-- Inner scope, never on the form element itself: the doctrine
             form already carries x-data="v3Form()". --}}
        <div x-data="{
                 type: '{{ old('type', $coupon?->type ?? 'percent') }}',
                 generateCode() {
                     const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                     let code = '';
                     for (let i = 0; i < 10; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));
                     document.getElementById('code').value = code;
                 }
             }" class="v3-settle">
        <x-admin3.panel title="Coupon">
            <div class="v3-grid v3-grid--2">
                <x-admin3.field name="code" label="Code" required hint="Letters, numbers, and hyphens only; stored uppercased.">
                    <span class="v3-namerow">
                        <input type="text" id="code" name="code" required maxlength="32" class="v3-control v3-mono" value="{{ old('code', $coupon?->code) }}">
                        <button type="button" class="v3-btn v3-btn--secondary" @click="generateCode(); touch()">Generate</button>
                    </span>
                </x-admin3.field>
                <x-admin3.field name="name" label="Name" required>
                    <input type="text" name="name" required class="v3-control" value="{{ old('name', $coupon?->name) }}">
                </x-admin3.field>
            </div>
            <div class="v3-gap v3-actrow">
                <label class="v3-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon?->is_active ?? true))>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    <span>Active</span>
                    <x-admin3.help text="An inactive coupon cannot be applied at checkout." />
                </label>
            </div>
        </x-admin3.panel>

        <x-admin3.panel title="Discount">
            <div class="v3-grid v3-grid--2">
                <x-admin3.field name="type" label="Type" required>
                    <select name="type" x-model="type" class="v3-control">
                        <option value="percent">Percentage discount</option>
                        <option value="fixed">Fixed amount discount</option>
                        <option value="free_shipping">Free shipping</option>
                    </select>
                </x-admin3.field>
                <div x-show="type !== 'free_shipping'">
                    <label class="v3-field">
                        <span class="v3-field__label" x-text="type === 'percent' ? 'Discount (%)' : 'Discount amount'"></span>
                        <input type="number" name="discount_value" step="0.01" min="0" class="v3-control v3-mono" value="{{ old('discount_value', $coupon?->discount_value) }}">
                    </label>
                    @error('discount_value') <span class="v3-field__error">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="v3-gap v3-grid v3-grid--2">
                <x-admin3.field name="min_order_total" label="Minimum order total" hint="Leave blank for no minimum.">
                    <input type="number" name="min_order_total" step="0.01" min="0" class="v3-control v3-mono" value="{{ old('min_order_total', $coupon?->min_order_total) }}">
                </x-admin3.field>
                <div x-show="type === 'percent'">
                    <x-admin3.field name="max_discount" label="Max discount cap" hint="Caps a percent discount at this amount; blank means no cap.">
                        <input type="number" name="max_discount" step="0.01" min="0" class="v3-control v3-mono" value="{{ old('max_discount', $coupon?->max_discount) }}">
                    </x-admin3.field>
                </div>
            </div>
            <div class="v3-gap v3-actrow">
                <label class="v3-toggle">
                    <input type="hidden" name="free_shipping" value="0">
                    <input type="checkbox" name="free_shipping" value="1" @checked(old('free_shipping', $coupon?->free_shipping ?? false))>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    <span>Grant free shipping</span>
                    <x-admin3.help text="Free shipping is added on top of the discount." />
                </label>
            </div>
        </x-admin3.panel>

        <x-admin3.panel title="Restrictions"
            note="Left empty, the coupon applies to everything. Add entries and it applies only to those.">
            @foreach ([
                ['type' => 'product', 'label' => 'Products', 'url' => route('admin.coupons.search-products')],
                ['type' => 'category', 'label' => 'Categories', 'url' => route('admin.coupons.search-categories')],
                ['type' => 'brand', 'label' => 'Brands', 'url' => route('admin.coupons.search-brands')],
            ] as $r)
                <div @class(['v3-gap' => ! $loop->first]) x-data="{
                    items: {{ json_encode($restrictions[$r['type']] ?? []) }},
                    query: '',
                    results: [],
                    loading: false,
                    open: false,
                    async search() {
                        if (this.query.length < 2) { this.results = []; return; }
                        this.loading = true;
                        const res = await fetch('{{ $r['url'] }}?q=' + encodeURIComponent(this.query));
                        this.results = await res.json();
                        this.open = true;
                        this.loading = false;
                    },
                    add(item) {
                        if (!this.items.find(i => i.id === item.id)) {
                            this.items.push(item);
                        }
                        this.query = '';
                        this.results = [];
                        this.open = false;
                    },
                    remove(id) {
                        this.items = this.items.filter(i => i.id !== id);
                    }
                }">
                    <label class="v3-field">
                        <span class="v3-field__label">{{ $r['label'] }}</span>
                        <input type="hidden" name="restriction_{{ $r['type'] }}s" :value="JSON.stringify(items.map(i => i.id))">
                        <span class="v3-combo">
                            <input type="text" x-model="query" @input.debounce.300ms="search()"
                                   @focus="if (results.length) open = true" @click.outside="open = false"
                                   aria-label="Search {{ strtolower($r['label']) }}" class="v3-control">
                            <span x-show="open && results.length > 0" x-cloak class="v3-combo__menu">
                                <template x-for="result in results" :key="result.id">
                                    <button type="button" class="v3-combo__opt" @click="add(result); touch()"
                                            :disabled="!!items.find(i => i.id === result.id)">
                                        <span class="v3-combo__main">
                                            <span x-text="result.label"></span>
                                            <span class="v3-cell-quiet v3-mono" x-text="'#' + result.id"></span>
                                        </span>
                                    </button>
                                </template>
                            </span>
                        </span>
                    </label>
                    <template x-for="item in items" :key="item.id">
                        <span class="v3-pickband v3-gap-sm">
                            <span class="v3-pickband__who"><strong x-text="item.label"></strong></span>
                            <button type="button" class="v3-row__x" @click="remove(item.id); touch()" :aria-label="'Remove ' + item.label">
                                <x-admin3.icon name="x" />
                            </button>
                        </span>
                    </template>
                </div>
            @endforeach
        </x-admin3.panel>

        <x-admin3.panel title="Eligibility and limits">
            <div class="v3-grid v3-grid--2">
                <x-admin3.field name="customer_eligibility" label="Who can use" required>
                    <select name="customer_eligibility" class="v3-control">
                        <option value="all" @selected(old('customer_eligibility', $coupon?->customer_eligibility) === 'all')>Everyone</option>
                        <option value="logged_in" @selected(old('customer_eligibility', $coupon?->customer_eligibility) === 'logged_in')>Logged-in customers</option>
                        <option value="guest" @selected(old('customer_eligibility', $coupon?->customer_eligibility) === 'guest')>Guests only</option>
                    </select>
                </x-admin3.field>
                <x-admin3.field name="customer_group_id" label="Customer group">
                    <select name="customer_group_id" class="v3-control">
                        <option value="">All groups</option>
                        @foreach ($customerGroups as $group)
                            <option value="{{ $group->id }}" @selected((int) old('customer_group_id', $coupon?->customer_group_id) === $group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </x-admin3.field>
                <x-admin3.field name="usage_limit" label="Total usage limit" hint="Leave blank for unlimited.">
                    <input type="number" name="usage_limit" min="1" class="v3-control v3-mono" value="{{ old('usage_limit', $coupon?->usage_limit) }}">
                </x-admin3.field>
                <x-admin3.field name="usage_per_customer" label="Per customer limit" hint="Leave blank for unlimited.">
                    <input type="number" name="usage_per_customer" min="1" class="v3-control v3-mono" value="{{ old('usage_per_customer', $coupon?->usage_per_customer) }}">
                </x-admin3.field>
                <x-admin3.field name="date_start" label="Start date">
                    <input type="date" name="date_start" class="v3-control v3-mono" value="{{ old('date_start', $coupon?->date_start?->format('Y-m-d')) }}">
                </x-admin3.field>
                <x-admin3.field name="date_end" label="End date">
                    <input type="date" name="date_end" class="v3-control v3-mono" value="{{ old('date_end', $coupon?->date_end?->format('Y-m-d')) }}">
                </x-admin3.field>
            </div>
        </x-admin3.panel>

        @if ($coupon)
            <x-admin3.panel title="Info">
                <dl class="v3-facts">
                    <dt>Times used</dt>
                    <dd class="v3-mono">{{ $coupon->times_used }}</dd>
                    @if ($coupon->source)
                        <dt>Source</dt>
                        <dd>{{ $coupon->source }}</dd>
                    @endif
                    <dt>Created</dt>
                    <dd>{{ $coupon->created_at->format('M d, Y') }}</dd>
                    <dt>Updated</dt>
                    <dd>{{ $coupon->updated_at->format('M d, Y') }}</dd>
                </dl>
            </x-admin3.panel>
        @endif
        </div>
    </x-admin3.form>
</x-admin3.shell>
