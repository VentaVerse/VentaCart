<x-admin-layout>
    <x-slot name="header">{{ $page ? 'Edit Page' : 'New Page' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.pages.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back to Pages
        </a>
    </x-slot>

    <form method="POST" action="{{ $page ? route('admin.pages.update', $page) : route('admin.pages.store') }}">
        @csrf
        @if ($page) @method('PUT') @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main content --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                            <input type="text" name="title" value="{{ old('title', $page?->title) }}" required
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-400">/</span>
                                <input type="text" name="slug" value="{{ old('slug', $page?->slug) }}"
                                    placeholder="auto-generated from title"
                                    class="flex-1 rounded-lg border-gray-300 text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Content</label>
                            <textarea name="body" class="tinymce-editor w-full rounded-lg border-gray-300 text-sm">{{ old('body', $page?->body) }}</textarea>
                            @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- SEO --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">SEO</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Meta Title</label>
                            <input type="text" name="meta_title" value="{{ old('meta_title', $page?->meta_title) }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Defaults to page title">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Meta Description</label>
                            <textarea name="meta_description" rows="2"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Brief description for search engines">{{ old('meta_description', $page?->meta_description) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Publish</h3>
                    <div class="space-y-4">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1"
                                {{ old('is_active', $page?->is_active ?? true) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600">
                            <span class="text-sm text-gray-700">Active</span>
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input type="number" name="sort_order" value="{{ old('sort_order', $page?->sort_order ?? 0) }}" min="0"
                                class="w-20 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div class="mt-6 flex gap-2">
                        <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                            {{ $page ? 'Update' : 'Create' }}
                        </button>
                    </div>
                    @if ($page)
                        <div class="mt-3 pt-3 border-t border-gray-100">
                            <a href="/{{ $page->slug }}" target="_blank" class="text-xs text-indigo-600 hover:underline">View page &rarr;</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </form>

    @push('scripts')
    <style>
        .tox.tox-tinymce-aux .tox-textfield,
        .tox.tox-tinymce-aux .tox-textarea,
        .tox .tox-dialog .tox-textfield,
        .tox .tox-dialog .tox-textarea {
            border: 1px solid #999 !important;
            border-radius: 4px !important;
            padding: 6px 8px !important;
        }
    </style>
    <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
    <script>
        tinymce.init({
            selector: '.tinymce-editor',
            height: 450,
            menubar: false,
            plugins: 'lists link autolink code table media',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link table media | removeformat code',
            content_style: 'body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: 14px; }',
            branding: false,
            promotion: false,
            license_key: 'gpl',
            relative_urls: false,
            remove_script_host: true,
        });
    </script>
    @endpush
</x-admin-layout>
