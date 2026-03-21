<x-admin-layout>
    <x-slot name="header">{{ $tag ? 'Edit Tag' : 'New Tag' }}</x-slot>

    <div class="max-w-md">
        <form method="POST" action="{{ $tag ? route('admin.tags.update', $tag) : route('admin.tags.store') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
            @csrf
            @if ($tag)
                @method('PUT')
            @endif

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $tag?->name) }}" required
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                @if ($tag)
                    <p class="text-xs text-gray-400 mt-1">Slug: {{ $tag->slug }}</p>
                @endif
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    {{ $tag ? 'Update' : 'Create' }}
                </button>
                <a href="{{ route('admin.tags.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">Cancel</a>
            </div>
        </form>
    </div>
</x-admin-layout>
