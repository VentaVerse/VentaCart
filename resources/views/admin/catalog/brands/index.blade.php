<x-admin.shell title="Brands">
    <x-admin.page title="Brands">
        <x-slot:actions>
            <x-admin.button type="button" variant="primary" @click="$dispatch('open-brand-modal')">
                <x-admin.icon name="plus" class="w-4 h-4" /> Add Brand
            </x-admin.button>
        </x-slot:actions>

        <div>
            <x-admin.filters :action="route('admin.brands.index')">
                <x-admin.field name="search" label="Search" class="v-field--grow">
                    <x-admin.input type="text" name="search" :value="request('search')" placeholder="Search brand..." />
                </x-admin.field>
                <x-admin.button type="submit" variant="secondary">Filter</x-admin.button>
                @if (request('search'))
                    <a href="{{ route('admin.brands.index') }}" class="v-filters__reset">Clear</a>
                @endif
            </x-admin.filters>

            {{-- Brands Table --}}
            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.th>Brand</x-admin.table.th>
                    <x-admin.table.th align="right">Products</x-admin.table.th>
                    <x-admin.table.th align="right">Sort Order</x-admin.table.th>
                    <x-admin.table.th>Status</x-admin.table.th>
                    <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
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
                        <x-admin.table.cell class="v-col-act">
                            <div class="v-rowactions v-rowactions--end">
                                <x-admin.button size="sm" variant="ghost" :href="route('admin.brands.edit', $brand)">Edit</x-admin.button>
                                <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'delete-brand-{{ $brand->id }}')">Delete</x-admin.button>
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-admin.empty-state title="No brands found" :description="request('search') ? 'Try adjusting your search.' : null">
                                <x-admin.button variant="primary" :href="route('admin.brands.create')">Create your first brand</x-admin.button>
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
            <div class="v-modal relative w-full max-w-md rounded-v-lg border border-v-border bg-v-surface p-6"
                 @click.outside="open = false" @keydown.escape.window="open = false" role="dialog" aria-modal="true" aria-label="New Brand">
                <h3 class="mb-4 text-[15px] font-semibold text-v-text">New Brand</h3>
                <div class="v-field mb-4">
                    <label for="quick-brand-name" class="v-field__label v-label">Brand Name</label>
                    <input type="text" id="quick-brand-name" x-model="name" @keydown.enter.prevent="$refs.saveBtn.click()" autofocus
                        class="v-control h-[var(--control-h)] w-full rounded-v-md border border-v-border bg-v-surface px-3 text-[13px] text-v-text placeholder:text-v-muted"
                        placeholder="e.g. Boss, Fender, Wampler">
                    <p x-show="error" x-cloak x-text="error" class="v-field__error"></p>
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
