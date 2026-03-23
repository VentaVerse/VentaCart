<x-admin-layout>
    <x-slot name="header">{{ $brand ? 'Edit Brand' : 'New Brand' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.brands.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back to Brands
        </a>
    </x-slot>

    <form method="POST"
          action="{{ $brand ? route('admin.brands.update', $brand) : route('admin.brands.store') }}">
        @csrf
        @if ($brand) @method('PUT') @endif
        <input type="hidden" name="store_id" value="1">

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-6">
            {{-- Left: Main fields --}}
            <div class="space-y-6">
                {{-- Name --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <label for="name" class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Brand Name</label>
                    <input type="text" name="name" id="name" required value="{{ old('name', $brand?->name) }}"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="e.g. Wampler, Boss, Friedman">
                    @error('name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror

                    <div class="mt-3">
                        <label for="slug" class="block text-sm text-gray-600 mb-1">Slug</label>
                        <div class="flex items-center gap-1">
                            <span class="text-xs text-gray-400">/brand/</span>
                            <input type="text" name="slug" id="slug" value="{{ old('slug', $brand?->slug) }}"
                                class="flex-1 rounded-lg border-gray-300 text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="auto-generated-from-name">
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">Leave blank to auto-generate from name</p>
                        @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Image --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Logo / Image</label>
                    @include('admin.partials.single_image_picker', [
                        'fieldName' => 'image',
                        'currentImage' => $brand?->image,
                        'defaultFolder' => 'Brands',
                        'hint' => 'Recommended: transparent PNG, square or horizontal',
                    ])
                    @error('image') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Description --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <label for="description" class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Description</label>
                    <textarea name="description" id="description" rows="4"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Brief description of the brand (shown on brand page)">{{ old('description', $brand?->description) }}</textarea>
                    @error('description') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- SEO --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">SEO</label>
                    <div class="space-y-4">
                        <div>
                            <label for="meta_title" class="block text-sm text-gray-600 mb-1">Meta Title</label>
                            <input type="text" name="meta_title" id="meta_title" value="{{ old('meta_title', $brand?->meta_title) }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Leave blank to use brand name">
                            <p class="text-[11px] text-gray-400 mt-1">Recommended: 50-60 characters</p>
                        </div>
                        <div>
                            <label for="meta_description" class="block text-sm text-gray-600 mb-1">Meta Description</label>
                            <textarea name="meta_description" id="meta_description" rows="2"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Brief summary for search engine results">{{ old('meta_description', $brand?->meta_description) }}</textarea>
                            <p class="text-[11px] text-gray-400 mt-1">Recommended: 120-160 characters</p>
                        </div>
                        <div>
                            <label for="meta_keywords" class="block text-sm text-gray-600 mb-1">Meta Keywords</label>
                            <input type="text" name="meta_keywords" id="meta_keywords" value="{{ old('meta_keywords', $brand?->meta_keywords) }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="guitar pedals, effects, accessories">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: Settings --}}
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Settings</h3>
                    <div class="space-y-4">
                        {{-- Status --}}
                        <div>
                            <label class="flex items-center justify-between cursor-pointer">
                                <span class="text-sm text-gray-700">Active</span>
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $brand?->is_active ?? true) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                            </label>
                            <p class="text-[11px] text-gray-400 mt-1">Inactive brands are hidden from the storefront</p>
                        </div>

                        <hr class="border-gray-100">

                        {{-- Sort Order --}}
                        <div>
                            <label for="sort_order" class="block text-sm text-gray-700 mb-1">Sort Order</label>
                            <input type="number" name="sort_order" id="sort_order" min="0" value="{{ old('sort_order', $brand?->sort_order ?? 0) }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="text-[11px] text-gray-400 mt-1">Lower numbers appear first</p>
                        </div>
                    </div>
                </div>

                {{-- Stats (edit only) --}}
                @if ($brand)
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Info</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Products</dt>
                                <dd class="text-gray-900 font-medium">{{ $brand->products()->count() }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Slug</dt>
                                <dd class="text-gray-900 font-mono text-xs">{{ $brand->slug }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Created</dt>
                                <dd class="text-gray-900">{{ $brand->created_at->format('M d, Y') }}</dd>
                            </div>
                        </dl>
                    </div>
                @endif

                {{-- Save --}}
                <button type="submit" class="w-full px-4 py-3 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                    {{ $brand ? 'Update Brand' : 'Create Brand' }}
                </button>
            </div>
        </div>
    </form>
</x-admin-layout>
