<x-admin.shell title="Products" active="catalog">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Products</h1>
        </div>
        <div class="v-head__actions">
            <button type="button" class="v-btn v-btn--primary" @click="$dispatch('open-product-modal')">Add product</button>
        </div>
    </div>

    <div class="v-settle">
        <form method="GET" action="{{ route('admin.products.index') }}" class="v-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Name or SKU…"
                   aria-label="Search products" class="v-control v-filterbar__grow">
            <select name="category_id" aria-label="Filter by category" class="v-control v-w-name">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="Filter by status" class="v-control v-w-name">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            <select name="stock" aria-label="Filter by stock" class="v-control v-w-name">
                <option value="">All stock</option>
                <option value="low" @selected(request('stock') === 'low')>Low stock</option>
            </select>
            <button type="submit" class="v-btn v-btn--secondary">Filter</button>
            @if (request('search') || request('category_id') || request('status') || request('stock'))
                <a href="{{ route('admin.products.index') }}" class="v-link">Clear</a>
            @endif
        </form>

        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        @php
                            // One header, one question. The arrow points where
                            // the next press will take it, and aria-sort says
                            // the same thing to anyone not looking at arrows.
                            $vSort = function (string $key, string $label, string $class = '') use ($sort, $dir) {
                                $on = $sort === $key;
                                $next = $on && $dir === 'asc' ? 'desc' : 'asc';
                                $href = request()->fullUrlWithQuery(['sort' => $key, 'dir' => $next, 'page' => null]);
                                $aria = $on ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none';

                                return compact('on', 'href', 'aria', 'label', 'class', 'dir');
                            };
                        @endphp
                        <tr>
                            @php $vName = $vSort('name', 'Product'); @endphp
                            <th scope="col" colspan="2" aria-sort="{{ $vName['aria'] }}">
                                <a href="{{ $vName['href'] }}" class="v-sorth {{ $vName['on'] ? 'is-on' : '' }}">
                                    Product <span class="v-sorth__i" aria-hidden="true">{{ $vName['on'] ? ($dir === 'asc' ? '↑' : '↓') : '↕' }}</span>
                                </a>
                            </th>
                            <th scope="col">SKU</th>
                            @php $vPrice = $vSort('price', 'Price'); @endphp
                            <th scope="col" class="v-cell-num" aria-sort="{{ $vPrice['aria'] }}">
                                <a href="{{ $vPrice['href'] }}" class="v-sorth {{ $vPrice['on'] ? 'is-on' : '' }}">
                                    Price <span class="v-sorth__i" aria-hidden="true">{{ $vPrice['on'] ? ($dir === 'asc' ? '↑' : '↓') : '↕' }}</span>
                                </a>
                            </th>
                            @php $vStock = $vSort('stock', 'Stock'); @endphp
                            <th scope="col" class="v-cell-num" aria-sort="{{ $vStock['aria'] }}">
                                <a href="{{ $vStock['href'] }}" class="v-sorth {{ $vStock['on'] ? 'is-on' : '' }}">
                                    Stock <span class="v-sorth__i" aria-hidden="true">{{ $vStock['on'] ? ($dir === 'asc' ? '↑' : '↓') : '↕' }}</span>
                                </a>
                            </th>
                            <th scope="col">Status</th>
                            {{-- Only what is on screen can be sorted. An Updated
                                 sort with no Updated column would order the list by
                                 something the merchant cannot see. --}}
                            <th scope="col" class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            @php $vCount = $product->variants->count(); @endphp
                            <tr @class(['is-parent' => $vCount > 0])>
                                {{-- The indent gutter the child rows align under.
                                     It used to hold a disclosure chevron; variants
                                     are always shown now, so there is nothing left
                                     to disclose. --}}
                                <td class="v-cell-open" id="p{{ $product->id }}"></td>
                                <td>
                                    <span class="v-actrow">
                                        @if ($product->image)
                                            <img class="v-thumb" src="{{ thumb_url($product->image) }}" alt="" loading="lazy" decoding="async" width="40" height="40">
                                        @else
                                            <span class="v-thumb v-thumb--blank"><x-admin.icon name="image" /></span>
                                        @endif
                                        <span>
                                            <a href="{{ route('admin.products.edit', $product) }}" class="v-rowlink">{{ $product->name }}</a>
                                            @if ($vCount > 0)
                                                <span class="v-cell-quiet v-block">{{ $vCount }} {{ Str::plural('variant', $vCount) }}</span>
                                            @endif
                                        </span>
                                    </span>
                                </td>
                                <td class="v-mono v-cell-quiet">
                                    {{-- A product with variants has no SKU of its own, and a bare
                                         dash read as "this has no code", which sent merchants
                                         hunting for one that does not exist. --}}
                                    @if ($product->sku)
                                        {{ $product->sku }}
                                    @elseif ($vCount > 0)
                                        <span class="v-cell-quiet">per variant</span>
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="v-cell-num v-mono">
                                    @if ($vCount > 0)
                                        @php
                                            $minPrice = $product->variants->min('price');
                                            $maxPrice = $product->variants->max('price');
                                        @endphp
                                        @if ($minPrice == $maxPrice)
                                            {{ number_format($minPrice, 2) }}
                                        @else
                                            {{ number_format($minPrice, 2) }} to {{ number_format($maxPrice, 2) }}
                                        @endif
                                    @else
                                        {{ number_format($product->price, 2) }}
                                    @endif
                                </td>
                                <td class="v-cell-num v-mono">
                                    {{-- The sum hid the fact that one colourway was at zero while
                                         the total read 888, so this row disagreed with the low-stock
                                         filter that had just surfaced it. The total still shows,
                                         because it is what gets reordered; the worst child is what
                                         gets flagged. --}}
                                    @if ($vCount > 0)
                                        @php
                                            $vTotal = $product->variants->sum('quantity');
                                            $vWorst = $product->variants->min('quantity');
                                            $vOut = $product->variants->where('quantity', '<=', 0)->count();
                                        @endphp
                                        <span @class(['v-stock--out' => $vWorst <= 0])>{{ $vTotal }}</span>
                                        @if ($vOut > 0)
                                            <span class="v-block v-cell-quiet">{{ $vOut }} out</span>
                                        @endif
                                    @else
                                        <span @class(['v-stock--out' => $product->quantity <= 0])>{{ $product->quantity }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="v-badge v-badge--{{ $product->is_active ? 'good' : 'quiet' }}">
                                        {{ $product->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.products.edit', $product) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                            @click="$dispatch('v-confirm', 'delete-product-{{ $product->id }}')">Delete</button>
                                </td>
                            </tr>

                            {{-- The variants themselves, server-rendered like every
                                 other row on this page. A colourway is what gets
                                 picked, counted and photographed, so it is shown
                                 outright rather than hidden behind a disclosure the
                                 merchant had to open one product at a time. --}}
                            @foreach ($product->variants as $vRow)
                                    <tr class="is-child">
                                        <td class="v-cell-open"></td>
                                        <td>
                                            <span class="v-actrow">
                                                @if ($vRow->image)
                                                    <img class="v-thumb v-thumb--sm" src="{{ thumb_url($vRow->image) }}" alt="" loading="lazy" decoding="async" width="28" height="28">
                                                @else
                                                    <span class="v-thumb v-thumb--sm v-thumb--blank"><x-admin.icon name="image" /></span>
                                                @endif
                                                <span class="v-childname">{{ $vRow->name ?: $vRow->values->pluck('value')->implode(' / ') ?: 'Variant' }}</span>
                                            </span>
                                        </td>
                                        <td class="v-mono v-cell-quiet">{{ $vRow->sku ?: '—' }}</td>
                                        <td class="v-cell-num v-mono">{{ number_format($vRow->price, 2) }}</td>
                                        <td class="v-cell-num v-mono">
                                            <span @class(['v-stock--out' => $vRow->quantity <= 0])>{{ $vRow->quantity }}</span>
                                        </td>
                                        <td>
                                            <span class="v-badge v-badge--{{ $vRow->is_active ? 'good' : 'quiet' }}">
                                                {{ $vRow->is_active ? 'For sale' : 'Off' }}
                                            </span>
                                        </td>
                                        <td class="v-cell-act">
                                            {{-- Editing a variant is editing its product, and the
                                                 form opens on the tab that holds it. --}}
                                            <a href="{{ route('admin.products.edit', $product) }}?tab=variants"
                                               class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No products found</p>
                                        <p class="v-empty__line">{{ (request('search') || request('category_id') || request('status') || request('stock')) ? 'Try adjusting the filters.' : 'The catalog starts here.' }}</p>
                                        @unless (request('search') || request('category_id') || request('status') || request('stock'))
                                            <button type="button" class="v-btn v-btn--primary v-btn--sm" @click="$dispatch('open-product-modal')">Create your first product</button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        <div class="v-pagerow">
            <x-admin.pager :paginator="$products" />

            {{-- How many rows at a time. A merchant working through the
                 catalogue was doing it twenty at a time over ninety-four
                 pages with no way to say otherwise. Not a state control: it
                 is navigation, so it is a link per size and nothing here
                 talks to a save. --}}
            <span class="v-perpage">
                <span class="v-cell-quiet">Rows</span>
                @foreach ([20, 50, 100] as $vSize)
                    @if ($vSize === $perPage)
                        <span class="v-pager__n is-on" aria-current="true">{{ $vSize }}</span>
                    @else
                        <a href="{{ request()->fullUrlWithQuery(['per' => $vSize, 'page' => null]) }}"
                           class="v-pager__n" aria-label="Show {{ $vSize }} rows per page">{{ $vSize }}</a>
                    @endif
                @endforeach
            </span>
        </div>

        @foreach ($products as $product)
            <x-admin.confirm name="delete-product-{{ $product->id }}" title="Delete {{ $product->name }}?"
                message="This permanently removes the product, its images, variants, and pricing. This cannot be undone."
                confirm="Delete" :action="route('admin.products.destroy', $product)" method="DELETE" />
        @endforeach

        {{-- Quick create: brand + name, then straight into the edit page. --}}
        <div class="v-passthru" x-data="{
                open: false, name: '', brandId: '', brandSearch: '', brandResults: [], saving: false, error: '',
                async searchBrands() {
                    if (this.brandSearch.length < 1) { this.brandResults = []; return; }
                    const res = await fetch('{{ route('admin.brands.search') }}?store_id={{ config('app.store_id', 1) }}&q=' + encodeURIComponent(this.brandSearch));
                    this.brandResults = await res.json();
                },
                selectBrand(brand) {
                    this.brandId = brand.id;
                    this.brandSearch = brand.name;
                    this.brandResults = [];
                }
             }"
             @open-product-modal.window="open = true; name = ''; brandId = ''; brandSearch = ''; error = ''"
             @keydown.escape.window="open = false">
            <template x-if="open">
                <div class="v-overlay" @click.self="open = false">
                    <div class="v-modal" role="dialog" aria-modal="true" aria-label="New product">
                        <h2 class="v-modal__title">New product</h2>
                        <label class="v-field v-gap-sm">
                            <span class="v-field__label">Brand</span>
                            <span class="v-combo">
                                <input type="text" x-model="brandSearch" @input.debounce.300ms="searchBrands()" @keydown.enter.prevent
                                       class="v-control" aria-label="Search brand">
                                <span x-show="brandResults.length > 0" x-cloak class="v-combo__menu">
                                    <template x-for="brand in brandResults" :key="brand.id">
                                        <button type="button" class="v-combo__opt" @click="selectBrand(brand)" x-text="brand.name"></button>
                                    </template>
                                </span>
                            </span>
                        </label>
                        <label class="v-field v-gap-sm">
                            <span class="v-field__label">Product name</span>
                            <input type="text" x-model="name" class="v-control">
                        </label>
                        <p x-show="error" x-cloak x-text="error" class="v-field__error"></p>
                        <div class="v-modal__acts">
                            <button type="button" class="v-btn v-btn--ghost" @click="open = false">Cancel</button>
                            <button type="button" class="v-btn v-btn--primary"
                                :disabled="saving || !name.trim() || !brandId"
                                @click="
                                    saving = true; error = '';
                                    fetch('{{ route('admin.products.quick-create') }}', {
                                        method: 'POST',
                                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                        body: JSON.stringify({ name: name.trim(), brand_id: brandId })
                                    }).then(function(r) { return r.json(); }).then(function(d) {
                                        if (d.success) { window.location.href = d.redirect; }
                                        else { error = d.message || 'Failed to create product.'; saving = false; }
                                    }).catch(function() { error = 'Network error.'; saving = false; });
                                ">
                                <span x-text="saving ? 'Creating…' : 'Create and edit'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-admin.shell>
