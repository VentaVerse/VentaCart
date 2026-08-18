<x-admin.shell :title="$coupon ? 'Edit Coupon' : 'New Coupon'">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.coupons.index') }}">Coupons</a>
                <span aria-hidden="true">/</span>
                <span>{{ $coupon->code ?? 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $coupon ? $coupon->code : 'New coupon' }}</h1>
        </div>
    </div>

    <x-admin.form :action="$coupon ? route('admin.coupons.update', $coupon) : route('admin.coupons.store')"
                   :method="$coupon ? 'PUT' : 'POST'" class="v-settle">
        {{-- Inner scope, never on the form element itself: the doctrine
             form already carries x-data="vForm()". --}}
        <div x-data="{
                 type: '{{ old('type', $coupon?->type ?? 'percent') }}',
                 generateCode() {
                     const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                     let code = '';
                     for (let i = 0; i < 10; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));
                     document.getElementById('code').value = code;
                 }
             }" class="v-settle">
        <x-admin.panel title="Coupon">
            <div class="v-grid v-grid--2">
                <x-admin.field name="code" label="Code" required hint="Letters, numbers, and hyphens only; stored uppercased.">
                    <span class="v-namerow">
                        <input type="text" id="code" name="code" required maxlength="32" class="v-control v-mono" value="{{ old('code', $coupon?->code) }}">
                        <button type="button" class="v-btn v-btn--secondary" @click="generateCode(); touch()">Generate</button>
                    </span>
                </x-admin.field>
                <x-admin.field name="name" label="Name" required>
                    <input type="text" name="name" required class="v-control" value="{{ old('name', $coupon?->name) }}">
                </x-admin.field>
            </div>
            <div class="v-gap v-actrow">
                <label class="v-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon?->is_active ?? true))>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    <span>Active</span>
                    <x-admin.help text="An inactive coupon cannot be applied at checkout." />
                </label>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Discount">
            <div class="v-grid v-grid--2">
                <x-admin.field name="type" label="Type" required>
                    <select name="type" x-model="type" class="v-control">
                        <option value="percent">Percentage discount</option>
                        <option value="fixed">Fixed amount discount</option>
                        <option value="free_shipping">Free shipping</option>
                    </select>
                </x-admin.field>
                <div x-show="type !== 'free_shipping'">
                    <label class="v-field">
                        <span class="v-field__label" x-text="type === 'percent' ? 'Discount (%)' : 'Discount amount'"></span>
                        <input type="number" name="discount_value" step="0.01" min="0" class="v-control v-mono" value="{{ old('discount_value', $coupon?->discount_value) }}">
                    </label>
                    @error('discount_value') <span class="v-field__error">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="v-gap v-grid v-grid--2">
                <x-admin.field name="min_order_total" label="Minimum order total" hint="Leave blank for no minimum.">
                    <input type="number" name="min_order_total" step="0.01" min="0" class="v-control v-mono" value="{{ old('min_order_total', $coupon?->min_order_total) }}">
                </x-admin.field>
                <div x-show="type === 'percent'">
                    <x-admin.field name="max_discount" label="Max discount cap" hint="Caps a percent discount at this amount; blank means no cap.">
                        <input type="number" name="max_discount" step="0.01" min="0" class="v-control v-mono" value="{{ old('max_discount', $coupon?->max_discount) }}">
                    </x-admin.field>
                </div>
            </div>
            <div class="v-gap v-actrow">
                <label class="v-toggle">
                    <input type="hidden" name="free_shipping" value="0">
                    <input type="checkbox" name="free_shipping" value="1" @checked(old('free_shipping', $coupon?->free_shipping ?? false))>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    <span>Grant free shipping</span>
                    <x-admin.help text="Free shipping is added on top of the discount." />
                </label>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Restrictions"
            note="Left empty, the coupon applies to everything. Add entries and it applies only to those.">
            @foreach ([
                ['type' => 'product', 'label' => 'Products', 'url' => route('admin.coupons.search-products')],
                ['type' => 'category', 'label' => 'Categories', 'url' => route('admin.coupons.search-categories')],
                ['type' => 'brand', 'label' => 'Brands', 'url' => route('admin.coupons.search-brands')],
            ] as $r)
                <div @class(['v-gap' => ! $loop->first]) x-data="{
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
                    <label class="v-field">
                        <span class="v-field__label">{{ $r['label'] }}</span>
                        <input type="hidden" name="restriction_{{ $r['type'] }}s" :value="JSON.stringify(items.map(i => i.id))">
                        <span class="v-combo">
                            <input type="text" x-model="query" @input.debounce.300ms="search()"
                                   @focus="if (results.length) open = true" @click.outside="open = false"
                                   aria-label="Search {{ strtolower($r['label']) }}" class="v-control">
                            <span x-show="open && results.length > 0" x-cloak class="v-combo__menu">
                                <template x-for="result in results" :key="result.id">
                                    <button type="button" class="v-combo__opt" @click="add(result); touch()"
                                            :disabled="!!items.find(i => i.id === result.id)">
                                        <span class="v-combo__main">
                                            <span x-text="result.label"></span>
                                            <span class="v-cell-quiet v-mono" x-text="'#' + result.id"></span>
                                        </span>
                                    </button>
                                </template>
                            </span>
                        </span>
                    </label>
                    <template x-for="item in items" :key="item.id">
                        <span class="v-pickband v-gap-sm">
                            <span class="v-pickband__who"><strong x-text="item.label"></strong></span>
                            <button type="button" class="v-row__x" @click="remove(item.id); touch()" :aria-label="'Remove ' + item.label">
                                <x-admin.icon name="x" />
                            </button>
                        </span>
                    </template>
                </div>
            @endforeach
        </x-admin.panel>

        <x-admin.panel title="Eligibility and limits">
            <div class="v-grid v-grid--2">
                <x-admin.field name="customer_eligibility" label="Who can use" required>
                    <select name="customer_eligibility" class="v-control">
                        <option value="all" @selected(old('customer_eligibility', $coupon?->customer_eligibility) === 'all')>Everyone</option>
                        <option value="logged_in" @selected(old('customer_eligibility', $coupon?->customer_eligibility) === 'logged_in')>Logged-in customers</option>
                        <option value="guest" @selected(old('customer_eligibility', $coupon?->customer_eligibility) === 'guest')>Guests only</option>
                    </select>
                </x-admin.field>
                <x-admin.field name="customer_group_id" label="Customer group">
                    <select name="customer_group_id" class="v-control">
                        <option value="">All groups</option>
                        @foreach ($customerGroups as $group)
                            <option value="{{ $group->id }}" @selected((int) old('customer_group_id', $coupon?->customer_group_id) === $group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
                <x-admin.field name="usage_limit" label="Total usage limit" hint="Leave blank for unlimited.">
                    <input type="number" name="usage_limit" min="1" class="v-control v-mono" value="{{ old('usage_limit', $coupon?->usage_limit) }}">
                </x-admin.field>
                <x-admin.field name="usage_per_customer" label="Per customer limit" hint="Leave blank for unlimited.">
                    <input type="number" name="usage_per_customer" min="1" class="v-control v-mono" value="{{ old('usage_per_customer', $coupon?->usage_per_customer) }}">
                </x-admin.field>
                <x-admin.field name="date_start" label="Start date">
                    <input type="date" name="date_start" class="v-control v-mono" value="{{ old('date_start', $coupon?->date_start?->format('Y-m-d')) }}">
                </x-admin.field>
                <x-admin.field name="date_end" label="End date">
                    <input type="date" name="date_end" class="v-control v-mono" value="{{ old('date_end', $coupon?->date_end?->format('Y-m-d')) }}">
                </x-admin.field>
            </div>
        </x-admin.panel>

        @if ($coupon)
            <x-admin.panel title="Info">
                <dl class="v-facts">
                    <dt>Times used</dt>
                    <dd class="v-mono">{{ $coupon->times_used }}</dd>
                    @if ($coupon->source)
                        <dt>Source</dt>
                        <dd>{{ $coupon->source }}</dd>
                    @endif
                    <dt>Created</dt>
                    <dd>{{ $coupon->created_at->format('M d, Y') }}</dd>
                    <dt>Updated</dt>
                    <dd>{{ $coupon->updated_at->format('M d, Y') }}</dd>
                </dl>
            </x-admin.panel>
        @endif
        </div>
    </x-admin.form>
</x-admin.shell>
