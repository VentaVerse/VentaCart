<x-admin-layout>
    <x-slot name="header">{{ $category ? 'Edit Blog Category' : 'New Blog Category' }}</x-slot>

    <div class="max-w-xl">
        <form method="POST" action="{{ $category ? route('admin.blog-categories.update', $category) : route('admin.blog-categories.store') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
            @csrf
            @if ($category)
                @method('PUT')
            @endif

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $category?->name) }}" required
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">Slug <span class="text-gray-400 font-normal">(auto-generated if empty)</span></label>
                <input type="text" name="slug" id="slug" value="{{ old('slug', $category?->slug) }}"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $category?->sort_order ?? 0) }}" min="0"
                    class="w-24 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    {{ $category ? 'Update' : 'Create' }}
                </button>
                <a href="{{ route('admin.blog-categories.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">Cancel</a>
            </div>
        </form>
    </div>
</x-admin-layout>
