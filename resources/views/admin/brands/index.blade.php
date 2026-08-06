<x-admin.shell title="Brands">
    <x-admin.page title="Brands" :breadcrumb="[['label' => 'Catalog']]">
        <x-slot:actions>
            <x-admin.button type="button" variant="primary" @click="$dispatch('open-brand-modal')">
                <x-admin.icon name="plus" class="w-4 h-4" /> Add Brand
            </x-admin.button>
        </x-slot:actions>

        <div class="space-y-5">
            {{-- Filters --}}
            <x-admin.card>
                <form method="GET" action="{{ route('admin.brands.index') }}" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px] flex-1">
                        <x-admin.field name="search" label="Search">
                            <x-admin.input type="text" name="search" :value="request('search')" placeholder="Search brand..." />
                        </x-admin.field>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-admin.button type="submit" variant="primary">Filter</x-admin.button>
                        @if (request('search'))
                            <a href="{{ route('admin.brands.index') }}" class="text-[13px] text-v-muted underline hover:text-v-text">Clear</a>
                        @endif
                    </div>
                </form>
            </x-admin.card>

            {{-- Brands Table --}}
            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.th>Brand</x-admin.table.th>
                    <x-admin.table.th align="right">Products</x-admin.table.th>
                    <x-admin.table.th align="right">Sort Order</x-admin.table.th>
                    <x-admin.table.th>Status</x-admin.table.th>
                    <x-admin.table.th align="right">Actions</x-admin.table.th>
                </x-slot:head>
                @forelse ($brands as $brand)
                    <x-admin.table.row href="{{ route('admin.brands.edit', $brand) }}">
                        <x-admin.table.cell>
                            <div class="flex items-center gap-3">
                                @if ($brand->image)
                                    <img class="h-8 w-8 rounded-v-sm object-contain shrink-0" src="{{ storage_url($brand->image) }}" alt="{{ $brand->name }}">
                                @endif
                                <div class="min-w-0">
                                    <div class="truncate text-[13px] font-medium text-v-text">{{ $brand->name }}</div>
                                    <div class="text-xs text-v-muted">{{ $brand->slug }}</div>
                                </div>
                            </div>
                        </x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>{{ $brand->products_count }}</x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>{{ $brand->sort_order }}</x-admin.table.cell>
                        <x-admin.table.cell>
                            <x-admin.badge :tone="$brand->is_active ? 'success' : 'neutral'">
                                {{ $brand->is_active ? 'Active' : 'Inactive' }}
                            </x-admin.badge>
                        </x-admin.table.cell>
                        <x-admin.table.cell align="right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.brands.edit', $brand) }}" class="text-[13px] text-v-accent hover:opacity-80">Edit</a>
                                <button type="button" class="text-[13px] text-v-danger hover:opacity-80" @click="$dispatch('open-modal', 'delete-brand-{{ $brand->id }}')">Delete</button>
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-2">
                            <x-admin.empty-state icon="brands" title="No brands found" :description="request('search') ? 'Try adjusting your search.' : null">
                                <a href="{{ route('admin.brands.create') }}" class="text-[13px] text-v-accent hover:underline">Create your first brand</a>
                            </x-admin.empty-state>
                        </td>
                    </tr>
                @endforelse
                @if ($brands->hasPages())
                    <x-slot:footer>
                        {{ $brands->links() }}
                    </x-slot:footer>
                @endif
            </x-admin.table>
        </div>

        @foreach ($brands as $brand)
            <x-admin.confirm-dialog name="delete-brand-{{ $brand->id }}" title="Delete {{ $brand->name }}?"
                message="Products will keep their data but lose the brand link. This cannot be undone."
                confirm="Delete" :action="route('admin.brands.destroy', $brand)" method="DELETE" />
        @endforeach

        {{-- Quick Create Brand Modal --}}
        <div x-data="{ open: false, name: '', saving: false, error: '' }"
             @open-brand-modal.window="open = true; name = ''; error = ''"
             x-show="open" x-cloak x-transition.opacity
             class="fixed inset-0 z-[90] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
            <div class="relative w-full max-w-md rounded-v-lg border border-v-border bg-v-surface p-6 shadow-2xl"
                 @click.outside="open = false" @keydown.escape.window="open = false" role="dialog" aria-modal="true" aria-label="New Brand">
                <h3 class="mb-4 text-[15px] font-semibold text-v-text">New Brand</h3>
                <div class="mb-4">
                    <label class="mb-1 block text-[13px] font-medium text-v-text">Brand Name</label>
                    <input type="text" x-model="name" @keydown.enter.prevent="$refs.saveBtn.click()" autofocus
                        class="v-control h-[var(--control-h)] w-full rounded-v-md border border-v-border bg-v-surface px-3 text-[13px] text-v-text placeholder:text-v-muted"
                        placeholder="e.g. Boss, Fender, Wampler">
                    <p x-show="error" x-cloak x-text="error" class="mt-1 text-[13px] text-v-danger"></p>
                </div>
                <div class="flex justify-end gap-2">
                    <x-admin.button type="button" variant="secondary" @click="open = false">Cancel</x-admin.button>
                    <x-admin.button type="button" variant="primary" x-ref="saveBtn"
                        x-bind:disabled="saving || !name.trim()"
                        @click="
                            saving = true; error = '';
                            fetch('{{ route('admin.brands.quick-create') }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                body: JSON.stringify({ name: name.trim() })
                            }).then(function(r) { return r.json(); }).then(function(d) {
                                if (d.success) { window.location.href = d.redirect; }
                                else { error = d.message || 'Failed to create brand.'; saving = false; }
                            }).catch(function() { error = 'Network error.'; saving = false; });
                        ">
                        <span x-show="!saving">Create &amp; Edit</span>
                        <span x-show="saving" x-cloak>Creating...</span>
                    </x-admin.button>
                </div>
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
