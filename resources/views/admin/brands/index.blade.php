<x-admin-layout>
    <x-slot name="header">Brands</x-slot>
    <x-slot name="headerActions">
        <button type="button" @click="$dispatch('open-brand-modal')" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Brand
        </button>
    </x-slot>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" action="{{ route('admin.brands.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search brand..."
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="flex space-x-2">
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">Filter</button>
                <a href="{{ route('admin.brands.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">Clear</a>
            </div>
        </form>
    </div>

    {{-- Brands Table --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Products</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sort Order</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($brands as $brand)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                @if ($brand->image)
                                    <img class="h-8 w-8 rounded object-contain mr-3" src="{{ storage_url($brand->image) }}" alt="{{ $brand->name }}">
                                @endif
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $brand->name }}</div>
                                    <div class="text-xs text-gray-400">{{ $brand->slug }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $brand->products_count }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $brand->sort_order }}</td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $brand->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">
                                {{ $brand->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right text-sm">
                            <a href="{{ route('admin.brands.edit', $brand) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                            <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}" class="inline" onsubmit="return confirm('Delete this brand? Products will keep their data but lose the brand link.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            No brands found. <a href="{{ route('admin.brands.create') }}" class="text-indigo-600 hover:underline">Create your first brand</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($brands->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $brands->links() }}
            </div>
        @endif
    </div>

    {{-- Quick Create Brand Modal --}}
    <div x-data="{ open: false, name: '', saving: false, error: '' }"
         @open-brand-modal.window="open = true; name = ''; error = ''"
         x-show="open" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.4); backdrop-filter: blur(2px);">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6" @click.outside="open = false" @keydown.escape.window="open = false">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">New Brand</h3>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Brand Name</label>
                <input type="text" x-model="name" @keydown.enter.prevent="$refs.saveBtn.click()" autofocus
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. Boss, Fender, Wampler">
                <p x-show="error" x-text="error" class="mt-1 text-sm text-red-600"></p>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="open = false" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                <button type="button" x-ref="saveBtn" :disabled="saving || !name.trim()"
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
                    "
                    class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                    <span x-show="!saving">Create & Edit</span>
                    <span x-show="saving">Creating...</span>
                </button>
            </div>
        </div>
    </div>
</x-admin-layout>
