<x-admin-layout>
    <x-slot name="header">{{ $group ? 'Edit Customer Group' : 'Create Customer Group' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.customer-groups.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back to Groups
        </a>
    </x-slot>

    <form method="POST"
          action="{{ $group ? route('admin.customer-groups.update', $group) : route('admin.customer-groups.store') }}"
          class="max-w-2xl">
        @csrf
        @if ($group)
            @method('PUT')
        @endif

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">
            <input type="hidden" name="store_id" value="1">

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" id="name" required value="{{ old('name', $group?->name) }}"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" id="description" rows="3"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $group?->description) }}</textarea>
                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="flex items-center">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1" {{ old('is_default', $group?->is_default) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700">Default group for new customers</span>
                </label>
                <p class="mt-1 text-xs text-gray-400">Only one group per store can be the default. Setting this will unset the current default.</p>
            </div>
        </div>

        <div class="flex justify-end space-x-3 mt-6">
            <a href="{{ route('admin.customer-groups.index') }}" class="px-6 py-2.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                {{ $group ? 'Update Group' : 'Create Group' }}
            </button>
        </div>
    </form>
</x-admin-layout>
