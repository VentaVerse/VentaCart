<x-admin.shell :title="$coupon ? 'Edit Coupon' : 'New Coupon'">
    <x-admin.page :title="$coupon ? 'Edit Coupon' : 'New Coupon'">
        <x-slot:actions>
            <x-admin.button variant="ghost" href="{{ route('admin.coupons.index') }}">Back to Coupons</x-admin.button>
        </x-slot:actions>

        <form method="POST"
              action="{{ $coupon ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}"
              data-v-form
              x-data="{
                  type: '{{ old('type', $coupon?->type ?? 'percent') }}',
                  generateCode() {
                      const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                      let code = '';
                      for (let i = 0; i < 10; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));
                      document.getElementById('code').value = code;
                  }
              }">
            @csrf
            @if ($coupon) @method('PUT') @endif

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-6">
                {{-- Left: Main fields --}}
                <div class="space-y-6">
                    {{-- Coupon Code --}}
                    <x-admin.card title="Coupon Code">
                        <div class="space-y-4">
                            <x-admin.field name="code" label="Code" help="Letters, numbers, and hyphens only. Will be uppercased." required>
                                <div class="flex gap-2">
                                    <x-admin.input type="text" name="code" required :value="$coupon?->code" maxlength="32"
                                        class="flex-1 font-mono uppercase" placeholder="e.g. SUMMER2026" />
                                    <x-admin.button type="button" variant="secondary" @click="generateCode()">Generate</x-admin.button>
                                </div>
                            </x-admin.field>
                            <x-admin.field name="name" label="Name" required>
                                <x-admin.input type="text" name="name" required :value="$coupon?->name" placeholder="e.g. Summer Sale 10% Off" />
                            </x-admin.field>
                        </div>
                    </x-admin.card>

                    {{-- Discount --}}
                    <x-admin.card title="Discount">
                        <div class="space-y-4">
                            <x-admin.field name="type" label="Type" required>
                                <x-admin.select name="type" x-model="type">
                                    <option value="percent">Percentage Discount</option>
                                    <option value="fixed">Fixed Amount Discount</option>
                                    <option value="free_shipping">Free Shipping</option>
                                </x-admin.select>
                            </x-admin.field>
                            <div x-show="type !== 'free_shipping'" x-transition class="space-y-1.5">
                                <label for="discount_value" class="block text-[13px] font-medium text-v-text">
                                    <span x-text="type === 'percent' ? 'Discount (%)' : 'Discount Amount'"></span>
                                </label>
                                <x-admin.input type="number" name="discount_value" step="0.01" min="0" :value="$coupon?->discount_value" placeholder="0.00" />
                                @error('discount_value') <p class="text-xs text-v-danger">{{ $message }}</p> @enderror
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <x-admin.field name="min_order_total" label="Minimum Order Total">
                                    <x-admin.input type="number" name="min_order_total" step="0.01" min="0" :value="$coupon?->min_order_total" placeholder="No minimum" />
                                </x-admin.field>
                                <div x-show="type === 'percent'" x-transition>
                                    <x-admin.field name="max_discount" label="Max Discount Cap" help="Caps percent discount to this amount">
                                        <x-admin.input type="number" name="max_discount" step="0.01" min="0" :value="$coupon?->max_discount" placeholder="No cap" />
                                    </x-admin.field>
                                </div>
                            </div>
                        </div>
                    </x-admin.card>

                    {{-- Restrictions --}}
                    <x-admin.card title="Restrictions">
                        <x-slot name="headerActions">
                            <x-admin.hint label="How restrictions apply" class="v-hint--end">Left empty, the coupon applies to everything. Add entries and it applies only to those.</x-admin.hint>
                        </x-slot>
                        <div class="space-y-5">
                            @foreach ([
                                ['type' => 'product', 'label' => 'Products', 'url' => route('admin.coupons.search-products'), 'placeholder' => 'Search products by name or SKU...'],
                                ['type' => 'category', 'label' => 'Categories', 'url' => route('admin.coupons.search-categories'), 'placeholder' => 'Search categories...'],
                                ['type' => 'brand', 'label' => 'Brands', 'url' => route('admin.coupons.search-brands'), 'placeholder' => 'Search brands...'],
                            ] as $r)
                                <div x-data="{
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
                                    <label class="block text-[13px] font-medium text-v-text mb-1">{{ $r['label'] }}</label>
                                    <input type="hidden" name="restriction_{{ $r['type'] }}s" :value="JSON.stringify(items.map(i => i.id))">

                                    {{-- Search input --}}
                                    <div class="relative">
                                        <input type="text" x-model="query" @input.debounce.300ms="search()" @focus="if (results.length) open = true" @click.outside="open = false"
                                            placeholder="{{ $r['placeholder'] }}"
                                            class="v-control w-full h-[var(--control-h)] px-3 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md placeholder:text-v-muted">
                                        <div x-show="loading" class="absolute right-3 top-1/2 -translate-y-1/2">
                                            <x-admin.icon name="spinner" class="w-4 h-4 animate-spin text-v-muted" />
                                        </div>

                                        {{-- Dropdown results --}}
                                        <div x-show="open && results.length > 0" x-transition x-cloak
                                             class="absolute z-20 mt-1 w-full max-h-48 overflow-y-auto rounded-v-md border border-v-border bg-v-surface shadow-v-lift-2">
                                            <template x-for="result in results" :key="result.id">
                                                <button type="button" @click="add(result)"
                                                    class="w-full text-left px-3 py-2 text-[13px] transition-colors flex items-center justify-between hover:bg-v-hover"
                                                    :class="items.find(i => i.id === result.id) ? 'opacity-40 pointer-events-none' : ''">
                                                    <span x-text="result.label"></span>
                                                    <span class="text-xs text-v-muted" x-text="'#' + result.id"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Selected items --}}
                                    <div class="flex flex-wrap gap-2 mt-2" x-show="items.length > 0">
                                        <template x-for="item in items" :key="item.id">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-v-accent-weak text-v-accent text-xs font-medium rounded-v-md">
                                                <span x-text="item.label"></span>
                                                <button type="button" @click="remove(item.id)" class="text-v-accent hover:opacity-70" :aria-label="'Remove ' + item.label">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </span>
                                        </template>
                                    </div>
                                    <p class="text-xs text-v-muted mt-1" x-show="items.length === 0">No restrictions: applies to all {{ strtolower($r['label']) }}.</p>
                                </div>
                            @endforeach
                        </div>
                    </x-admin.card>
                </div>

                {{-- Right: Sidebar --}}
                <div class="space-y-6">
                    {{-- Status --}}
                    <x-admin.card title="Status">
                        <label class="flex items-center justify-between cursor-pointer">
                            <span class="text-[13px] text-v-text">Active</span>
                            <x-admin.toggle name="is_active" :checked="$coupon?->is_active ?? true" />
                        </label>
                        <p class="text-xs text-v-muted mt-1">Inactive coupons cannot be applied at checkout</p>
                    </x-admin.card>

                    {{-- Eligibility --}}
                    <x-admin.card title="Eligibility">
                        <div class="space-y-4">
                            <x-admin.field name="customer_eligibility" label="Who can use" required>
                                <x-admin.select name="customer_eligibility">
                                    <option value="all" {{ old('customer_eligibility', $coupon?->customer_eligibility) === 'all' ? 'selected' : '' }}>Everyone</option>
                                    <option value="logged_in" {{ old('customer_eligibility', $coupon?->customer_eligibility) === 'logged_in' ? 'selected' : '' }}>Logged-in Customers</option>
                                    <option value="guest" {{ old('customer_eligibility', $coupon?->customer_eligibility) === 'guest' ? 'selected' : '' }}>Guests Only</option>
                                </x-admin.select>
                            </x-admin.field>
                            <x-admin.field name="customer_group_id" label="Customer Group">
                                <x-admin.select name="customer_group_id">
                                    <option value="">All Groups</option>
                                    @foreach ($customerGroups as $group)
                                        <option value="{{ $group->id }}" {{ (int) old('customer_group_id', $coupon?->customer_group_id) === $group->id ? 'selected' : '' }}>
                                            {{ $group->name }}
                                        </option>
                                    @endforeach
                                </x-admin.select>
                            </x-admin.field>
                        </div>
                    </x-admin.card>

                    {{-- Free Shipping --}}
                    <x-admin.card title="Free Shipping">
                        <label class="flex items-center justify-between cursor-pointer">
                            <span class="text-[13px] text-v-text">Grant free shipping</span>
                            <x-admin.toggle name="free_shipping" :checked="$coupon?->free_shipping ?? false" />
                        </label>
                        <p class="text-xs text-v-muted mt-1">Add free shipping on top of the discount</p>
                    </x-admin.card>

                    {{-- Limits --}}
                    <x-admin.card title="Limits">
                        <div class="space-y-4">
                            <x-admin.field name="usage_limit" label="Total Usage Limit" help="Leave blank for unlimited">
                                <x-admin.input type="number" name="usage_limit" min="1" :value="$coupon?->usage_limit" placeholder="Unlimited" />
                            </x-admin.field>
                            <x-admin.field name="usage_per_customer" label="Per Customer Limit" help="Leave blank for unlimited.">
                                <x-admin.input type="number" name="usage_per_customer" min="1" :value="$coupon?->usage_per_customer" placeholder="Unlimited" />
                            </x-admin.field>
                        </div>
                    </x-admin.card>

                    {{-- Dates --}}
                    <x-admin.card title="Dates">
                        <div class="space-y-4">
                            <x-admin.field name="date_start" label="Start Date">
                                <x-admin.input type="date" name="date_start" :value="$coupon?->date_start?->format('Y-m-d')" />
                            </x-admin.field>
                            <x-admin.field name="date_end" label="End Date">
                                <x-admin.input type="date" name="date_end" :value="$coupon?->date_end?->format('Y-m-d')" />
                            </x-admin.field>
                        </div>
                    </x-admin.card>

                    {{-- Source (edit only) --}}
                    @if ($coupon && $coupon->source)
                        <x-admin.card title="Source">
                            <p class="text-[13px] text-v-secondary">{{ $coupon->source }}</p>
                        </x-admin.card>
                    @endif

                    {{-- Info (edit only) --}}
                    @if ($coupon)
                        <x-admin.card title="Info">
                            <dl class="space-y-2 text-[13px]">
                                <div class="flex justify-between">
                                    <dt class="text-v-muted">Times Used</dt>
                                    <dd class="text-v-text font-medium">{{ $coupon->times_used }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-v-muted">Created</dt>
                                    <dd class="text-v-text">{{ $coupon->created_at->format('M d, Y') }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-v-muted">Updated</dt>
                                    <dd class="text-v-text">{{ $coupon->updated_at->format('M d, Y') }}</dd>
                                </div>
                            </dl>
                        </x-admin.card>
                    @endif

                    {{-- Save --}}
                    <x-admin.button type="submit" class="w-full justify-center">
                        {{ $coupon ? 'Update Coupon' : 'Create Coupon' }}
                    </x-admin.button>
                </div>
            </div>
        </form>
    </x-admin.page>
</x-admin.shell>
