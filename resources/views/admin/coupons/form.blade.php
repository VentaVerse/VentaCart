<x-admin-layout>
    <x-slot name="header">{{ $coupon ? 'Edit Coupon' : 'New Coupon' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.coupons.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back to Coupons
        </a>
    </x-slot>

    <form method="POST"
          action="{{ $coupon ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}"
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
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Coupon Code</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="code" class="block text-sm text-gray-600 mb-1">Code</label>
                            <div class="flex gap-2">
                                <input type="text" name="code" id="code" required value="{{ old('code', $coupon?->code) }}"
                                    class="flex-1 rounded-lg border-gray-300 text-sm font-mono uppercase focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="e.g. SUMMER2026" maxlength="32">
                                <button type="button" @click="generateCode()"
                                    class="px-3 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200 transition-colors whitespace-nowrap">
                                    Generate
                                </button>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Letters, numbers, and hyphens only. Will be uppercased.</p>
                            @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="name" class="block text-sm text-gray-600 mb-1">Name</label>
                            <input type="text" name="name" id="name" required value="{{ old('name', $coupon?->name) }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="e.g. Summer Sale 10% Off">
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Discount --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Discount</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="type" class="block text-sm text-gray-600 mb-1">Type</label>
                            <select name="type" id="type" x-model="type"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="percent">Percentage Discount</option>
                                <option value="fixed">Fixed Amount Discount</option>
                                <option value="free_shipping">Free Shipping</option>
                            </select>
                            @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div x-show="type !== 'free_shipping'" x-transition>
                            <label for="discount_value" class="block text-sm text-gray-600 mb-1">
                                <span x-text="type === 'percent' ? 'Discount (%)' : 'Discount Amount'"></span>
                            </label>
                            <input type="number" name="discount_value" id="discount_value" step="0.01" min="0"
                                value="{{ old('discount_value', $coupon?->discount_value) }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="0.00">
                            @error('discount_value') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="min_order_total" class="block text-sm text-gray-600 mb-1">Minimum Order Total</label>
                                <input type="number" name="min_order_total" id="min_order_total" step="0.01" min="0"
                                    value="{{ old('min_order_total', $coupon?->min_order_total) }}"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="No minimum">
                                @error('min_order_total') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div x-show="type === 'percent'" x-transition>
                                <label for="max_discount" class="block text-sm text-gray-600 mb-1">Max Discount Cap</label>
                                <input type="number" name="max_discount" id="max_discount" step="0.01" min="0"
                                    value="{{ old('max_discount', $coupon?->max_discount) }}"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="No cap">
                                <p class="text-xs text-gray-400 mt-1">Caps percent discount to this amount</p>
                                @error('max_discount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Restrictions --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Restrictions</h3>
                    <p class="text-xs text-gray-400 mb-4">Leave blank to apply to all products. Add specific items to restrict this coupon.</p>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $r['label'] }}</label>
                                <input type="hidden" name="restriction_{{ $r['type'] }}s" :value="JSON.stringify(items.map(i => i.id))">

                                {{-- Search input --}}
                                <div class="relative">
                                    <input type="text" x-model="query" @input.debounce.300ms="search()" @focus="if (results.length) open = true" @click.outside="open = false"
                                        placeholder="{{ $r['placeholder'] }}"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <div x-show="loading" class="absolute right-3 top-1/2 -translate-y-1/2">
                                        <svg class="w-4 h-4 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    </div>

                                    {{-- Dropdown results --}}
                                    <div x-show="open && results.length > 0" x-transition
                                         class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                        <template x-for="result in results" :key="result.id">
                                            <button type="button" @click="add(result)"
                                                class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 transition-colors flex items-center justify-between"
                                                :class="items.find(i => i.id === result.id) ? 'opacity-40 pointer-events-none' : ''">
                                                <span x-text="result.label"></span>
                                                <span class="text-xs text-gray-400" x-text="'#' + result.id"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                {{-- Selected items --}}
                                <div class="flex flex-wrap gap-2 mt-2" x-show="items.length > 0">
                                    <template x-for="item in items" :key="item.id">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-lg">
                                            <span x-text="item.label"></span>
                                            <button type="button" @click="remove(item.id)" class="text-indigo-400 hover:text-indigo-600">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </span>
                                    </template>
                                </div>
                                <p class="text-xs text-gray-400 mt-1" x-show="items.length === 0">No restrictions — applies to all {{ strtolower($r['label']) }}.</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Right: Sidebar --}}
            <div class="space-y-6">
                {{-- Status --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Status</h3>
                    <label class="flex items-center justify-between cursor-pointer">
                        <span class="text-sm text-gray-700">Active</span>
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $coupon?->is_active ?? true) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                    </label>
                    <p class="text-xs text-gray-400 mt-1">Inactive coupons cannot be applied at checkout</p>
                </div>

                {{-- Eligibility --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Eligibility</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="customer_eligibility" class="block text-sm text-gray-600 mb-1">Who can use</label>
                            <select name="customer_eligibility" id="customer_eligibility"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="all" {{ old('customer_eligibility', $coupon?->customer_eligibility) === 'all' ? 'selected' : '' }}>Everyone</option>
                                <option value="logged_in" {{ old('customer_eligibility', $coupon?->customer_eligibility) === 'logged_in' ? 'selected' : '' }}>Logged-in Customers</option>
                                <option value="guest" {{ old('customer_eligibility', $coupon?->customer_eligibility) === 'guest' ? 'selected' : '' }}>Guests Only</option>
                            </select>
                        </div>
                        <div>
                            <label for="customer_group_id" class="block text-sm text-gray-600 mb-1">Customer Group</label>
                            <select name="customer_group_id" id="customer_group_id"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Groups</option>
                                @foreach ($customerGroups as $group)
                                    <option value="{{ $group->id }}" {{ (int) old('customer_group_id', $coupon?->customer_group_id) === $group->id ? 'selected' : '' }}>
                                        {{ $group->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Free Shipping --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Free Shipping</h3>
                    <label class="flex items-center justify-between cursor-pointer">
                        <span class="text-sm text-gray-700">Grant free shipping</span>
                        <input type="hidden" name="free_shipping" value="0">
                        <input type="checkbox" name="free_shipping" value="1" {{ old('free_shipping', $coupon?->free_shipping ?? false) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                    </label>
                    <p class="text-xs text-gray-400 mt-1">Add free shipping on top of the discount</p>
                </div>

                {{-- Limits --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Limits</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="usage_limit" class="block text-sm text-gray-600 mb-1">Total Usage Limit</label>
                            <input type="number" name="usage_limit" id="usage_limit" min="1"
                                value="{{ old('usage_limit', $coupon?->usage_limit) }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Unlimited">
                            <p class="text-xs text-gray-400 mt-1">Leave blank for unlimited</p>
                        </div>
                        <div>
                            <label for="usage_per_customer" class="block text-sm text-gray-600 mb-1">Per Customer Limit</label>
                            <input type="number" name="usage_per_customer" id="usage_per_customer" min="1" required
                                value="{{ old('usage_per_customer', $coupon?->usage_per_customer ?? 1) }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                {{-- Dates --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Dates</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="date_start" class="block text-sm text-gray-600 mb-1">Start Date</label>
                            <input type="date" name="date_start" id="date_start"
                                value="{{ old('date_start', $coupon?->date_start?->format('Y-m-d')) }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="date_end" class="block text-sm text-gray-600 mb-1">End Date</label>
                            <input type="date" name="date_end" id="date_end"
                                value="{{ old('date_end', $coupon?->date_end?->format('Y-m-d')) }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('date_end') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Source (edit only) --}}
                @if ($coupon && $coupon->source)
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4">Source</h3>
                        <p class="text-sm text-gray-600">{{ $coupon->source }}</p>
                    </div>
                @endif

                {{-- Info (edit only) --}}
                @if ($coupon)
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4">Info</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Times Used</dt>
                                <dd class="text-gray-900 font-medium">{{ $coupon->times_used }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Created</dt>
                                <dd class="text-gray-900">{{ $coupon->created_at->format('M d, Y') }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Updated</dt>
                                <dd class="text-gray-900">{{ $coupon->updated_at->format('M d, Y') }}</dd>
                            </div>
                        </dl>
                    </div>
                @endif

                {{-- Save --}}
                <button type="submit" class="w-full px-4 py-3 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                    {{ $coupon ? 'Update Coupon' : 'Create Coupon' }}
                </button>
            </div>
        </div>
    </form>
</x-admin-layout>
