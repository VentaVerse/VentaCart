<x-admin-layout>
    <x-slot name="header">{{ $brand ? 'Edit Brand' : 'Create Brand' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.brands.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back to Brands
        </a>
    </x-slot>

    <form method="POST"
          action="{{ $brand ? route('admin.brands.update', $brand) : route('admin.brands.store') }}"
          class="max-w-2xl">
        @csrf
        @if ($brand)
            @method('PUT')
        @endif

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">
            <input type="hidden" name="store_id" value="1">

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" id="name" required value="{{ old('name', $brand?->name) }}"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Logo / Image</label>
                @include('admin.partials.single_image_picker', [
                    'fieldName' => 'image',
                    'currentImage' => $brand?->image,
                    'defaultFolder' => 'Brands',
                ])
                @error('image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                <input type="number" name="sort_order" id="sort_order" min="0" value="{{ old('sort_order', $brand?->sort_order ?? 0) }}"
                    class="w-32 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('sort_order') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end space-x-3 mt-6">
            <a href="{{ route('admin.brands.index') }}" class="px-6 py-2.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                {{ $brand ? 'Update Brand' : 'Create Brand' }}
            </button>
        </div>
    </form>
</x-admin-layout>
