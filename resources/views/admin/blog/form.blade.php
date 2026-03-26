<x-admin-layout>
    <x-slot name="header">{{ $post ? 'Edit Post' : 'New Post' }}</x-slot>

    <form method="POST" action="{{ $post ? route('admin.blog.update', $post) : route('admin.blog.store') }}" class="space-y-6">
        @csrf
        @if ($post)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main Content --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                        <input type="text" name="title" id="title" value="{{ old('title', $post?->title) }}" required
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">Slug <span class="text-gray-400 font-normal">(auto-generated if empty)</span></label>
                        <input type="text" name="slug" id="slug" value="{{ old('slug', $post?->slug) }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('slug') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="content" class="block text-sm font-medium text-gray-700 mb-1">Content</label>
                        <textarea name="content" id="content" rows="16" required
                            class="tinymce-editor w-full rounded-lg border-gray-300 text-sm">{{ old('content', $post?->content) }}</textarea>
                        @error('content') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="excerpt" class="block text-sm font-medium text-gray-700 mb-1">Excerpt</label>
                        <textarea name="excerpt" id="excerpt" rows="3"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('excerpt', $post?->excerpt) }}</textarea>
                    </div>
                </div>

                {{-- SEO --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700">SEO</h3>
                    <div>
                        <label for="meta_title" class="block text-sm font-medium text-gray-700 mb-1">Meta Title</label>
                        <input type="text" name="meta_title" id="meta_title" value="{{ old('meta_title', $post?->meta_title) }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="meta_description" class="block text-sm font-medium text-gray-700 mb-1">Meta Description</label>
                        <textarea name="meta_description" id="meta_description" rows="2"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('meta_description', $post?->meta_description) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Publish --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700">Publish</h3>

                    <div class="flex items-center gap-2">
                        <input type="hidden" name="is_published" value="0">
                        <input type="checkbox" name="is_published" id="is_published" value="1" {{ old('is_published', $post?->is_published) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-indigo-600">
                        <label for="is_published" class="text-sm text-gray-700">Published</label>
                    </div>

                    <div>
                        <label for="published_at" class="block text-sm font-medium text-gray-700 mb-1">Published Date</label>
                        <input type="datetime-local" name="published_at" id="published_at" value="{{ old('published_at', $post?->published_at?->format('Y-m-d\TH:i')) }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                            {{ $post ? 'Update' : 'Create' }}
                        </button>
                        <a href="{{ route('admin.blog.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">Cancel</a>
                    </div>
                </div>

                {{-- Category --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700">Category</h3>
                    <select name="blog_category_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— None —</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('blog_category_id', $post?->blog_category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Featured Image --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700">Featured Image</h3>
                    <input type="text" name="featured_image" id="featured_image" value="{{ old('featured_image', $post?->featured_image) }}" placeholder="Path to image"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-400">Enter path from Media Manager (e.g., blog/my-image.jpg)</p>
                </div>

                {{-- Tags --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700">Tags</h3>
                    @php $selectedTags = old('tags', $post?->tags?->pluck('id')->toArray() ?? []); @endphp
                    <div class="space-y-1.5 max-h-48 overflow-y-auto">
                        @foreach ($tags as $tag)
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" name="tags[]" value="{{ $tag->id }}" {{ in_array($tag->id, $selectedTags) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600">
                                {{ $tag->name }}
                            </label>
                        @endforeach
                    </div>
                    @if ($tags->isEmpty())
                        <p class="text-xs text-gray-400">No tags created yet.</p>
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
            height: 600,
            menubar: 'file edit view insert format table',
            plugins: 'lists link autolink code table media image fullscreen',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | alignleft aligncenter alignright | link image media table | removeformat code fullscreen',
            content_style: 'body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: 14px; } img { max-width: 100%; height: auto; }',
            branding: false,
            promotion: false,
            license_key: 'gpl',
            relative_urls: false,
            remove_script_host: true,
            image_advtab: true,
            image_dimensions: true,
            extended_valid_elements: 'iframe[src|width|height|name|align|frameborder|scrolling|allowfullscreen|allow|style|class]',
            sandbox_iframes: false,
            convert_unsafe_embeds: false,
            media_live_embeds: true,
        });
    </script>
    @endpush
</x-admin-layout>
