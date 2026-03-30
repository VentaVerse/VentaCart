<x-admin-layout>
    <x-slot name="header">{{ $group ? 'Edit Group: ' . $group->name : 'New Banner Group' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.banners.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300">
            Back to Groups
        </a>
    </x-slot>

    <div class="max-w-2xl">
        <form method="POST" action="{{ $group ? route('admin.banners.update', $group) : route('admin.banners.store') }}" class="space-y-6">
            @csrf
            @if ($group) @method('PUT') @endif

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $group?->name) }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. Homepage Hero">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                <input type="text" name="slug" id="slug" value="{{ old('slug', $group?->slug) }}" required class="w-full rounded-lg border-gray-300 text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. homepage-hero">
                <p class="mt-1 text-xs text-gray-400">Used to reference this group in the theme.</p>
                @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label for="height" class="block text-sm font-medium text-gray-700 mb-1">Height (px)</label>
                    <input type="number" name="height" id="height" value="{{ old('height', $group?->height ?? 500) }}" min="100" max="2000" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('height') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="height_mobile" class="block text-sm font-medium text-gray-700 mb-1">Mobile Height (px)</label>
                    <input type="number" name="height_mobile" id="height_mobile" value="{{ old('height_mobile', $group?->height_mobile ?? 400) }}" min="100" max="2000" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('height_mobile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="speed" class="block text-sm font-medium text-gray-700 mb-1">Autoplay Speed (ms)</label>
                    <input type="number" name="speed" id="speed" value="{{ old('speed', $group?->speed ?? 5000) }}" min="1000" max="30000" step="500" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('speed') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="hidden" name="full_width" value="0">
                    <input type="checkbox" name="full_width" value="1" {{ old('full_width', $group?->full_width ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    Full Width
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="hidden" name="autoplay" value="0">
                    <input type="checkbox" name="autoplay" value="1" {{ old('autoplay', $group?->autoplay ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    Autoplay
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $group?->is_active ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    Active
                </label>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">
                    {{ $group ? 'Update Group' : 'Create Group' }}
                </button>
                <a href="{{ route('admin.banners.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">Cancel</a>
            </div>
        </form>
    </div>
</x-admin-layout>
