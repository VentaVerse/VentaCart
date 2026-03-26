<x-admin-layout>
    <x-slot name="header">{{ $post ? 'Edit Post' : 'New Post' }}</x-slot>

    <form method="POST" action="{{ $post ? route('admin.blog.update', $post) : route('admin.blog.store') }}" class="space-y-6" onsubmit="if(typeof tinymce !== 'undefined') tinymce.triggerSave();">
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
                        <textarea name="content" id="content" rows="16"
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

                    {{-- AI SEO Optimizer --}}
                    <div x-data="{
                        loading: false,
                        suggestion: null,
                        error: null,
                        async optimize() {
                            this.loading = true;
                            this.error = null;
                            this.suggestion = null;
                            try {
                                const res = await fetch('{{ route("admin.extensions.seo.optimize") }}', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                    body: JSON.stringify({ entity_type: 'blog_post', entity_id: {{ $post->id ?? 0 }} })
                                });
                                const data = await res.json();
                                if (data.ok) {
                                    this.suggestion = data.suggestion;
                                } else {
                                    this.error = data.message || 'Failed to generate suggestion.';
                                }
                            } catch (e) {
                                this.error = 'Request failed. Check your Claude API key in Extensions > SEO > Settings.';
                            }
                            this.loading = false;
                        },
                        apply() {
                            if (!this.suggestion) return;
                            const s = this.suggestion;
                            const mt = document.querySelector('[name=meta_title]');
                            const md = document.querySelector('[name=meta_description]');
                            if (mt && s.suggested_meta_title) mt.value = s.suggested_meta_title;
                            if (md && s.suggested_meta_description) md.value = s.suggested_meta_description;
                            this.suggestion = null;
                        }
                    }" class="mt-4 pt-4 border-t border-gray-100">
                        @if ($post ?? false)
                            <button type="button" @click="optimize()" :disabled="loading"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 disabled:opacity-50 transition-colors">
                                <template x-if="!loading">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                                </template>
                                <template x-if="loading">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                                </template>
                                <span x-text="loading ? 'Optimizing...' : 'Optimize with AI'"></span>
                            </button>
                        @else
                            <p class="text-xs text-gray-400">Save the post first to use AI optimization.</p>
                        @endif

                        <div x-show="error" x-cloak class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700" x-text="error"></div>

                        <div x-show="suggestion" x-cloak class="mt-3 bg-purple-50 border border-purple-200 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-purple-800 mb-3">AI Suggestion Preview</h4>
                            <div class="space-y-3">
                                <template x-for="field in ['meta_title', 'meta_description', 'og_title', 'og_description']" :key="field">
                                    <div>
                                        <label class="text-xs font-medium text-gray-500 uppercase" x-text="field.replace(/_/g, ' ')"></label>
                                        <div class="grid grid-cols-2 gap-2 mt-1">
                                            <div class="bg-white rounded p-2 text-xs">
                                                <span class="text-gray-400 block mb-0.5">Current:</span>
                                                <span x-text="suggestion['current_' + field] || '(empty)'"></span>
                                            </div>
                                            <div class="bg-white rounded p-2 text-xs border-l-2 border-purple-400">
                                                <span class="text-purple-600 block mb-0.5">Suggested:</span>
                                                <span x-text="suggestion['suggested_' + field] || '(empty)'"></span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <div class="flex gap-2 mt-4">
                                <button type="button" @click="apply()" class="px-3 py-1.5 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700">Apply to Form</button>
                                <button type="button" @click="suggestion = null" class="px-3 py-1.5 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300">Dismiss</button>
                            </div>
                        </div>
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
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4" x-data="featuredImagePicker()">
                    <h3 class="text-sm font-semibold text-gray-700">Featured Image</h3>
                    <input type="hidden" name="featured_image" :value="selectedPath">

                    {{-- Preview --}}
                    <div x-show="selectedPath" class="relative inline-block">
                        <img :src="previewUrl" class="w-full max-w-[200px] h-auto rounded-lg border border-gray-200" alt="Featured image">
                        <button type="button" @click="selectedPath = ''" class="absolute top-1 right-1 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 shadow-sm">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <button type="button" @click="openBrowser()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        <span x-text="selectedPath ? 'Change Image' : 'Choose Image'"></span>
                    </button>

                    {{-- Browser Modal --}}
                    <div x-show="browserOpen" x-cloak x-transition.opacity.duration.200ms
                         class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                         style="background: rgba(0,0,0,0.45); backdrop-filter: blur(3px);"
                         @keydown.escape.window="browserOpen = false">
                        <div class="w-full max-w-4xl max-h-[80vh] flex flex-col bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden"
                             @click.outside="browserOpen = false">
                            {{-- Header --}}
                            <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-200 bg-gray-50">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                    <h3 class="text-sm font-semibold text-gray-900">Choose Featured Image</h3>
                                    <span class="text-gray-300">|</span>
                                    <nav class="flex items-center gap-0.5 text-sm">
                                        <button type="button" @click="browseDir('')" class="text-indigo-600 hover:text-indigo-800 font-medium">catalog</button>
                                        <template x-for="(crumb, i) in breadcrumbs" :key="i">
                                            <span class="flex items-center gap-0.5">
                                                <span class="text-gray-400">/</span>
                                                <button type="button" @click="browseDir(crumb.path)" class="text-indigo-600 hover:text-indigo-800 font-medium" x-text="crumb.name"></button>
                                            </span>
                                        </template>
                                    </nav>
                                </div>
                                <button type="button" @click="browserOpen = false" class="text-gray-400 hover:text-gray-700 p-1 rounded-lg hover:bg-gray-100">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            {{-- Content --}}
                            <div class="flex-1 overflow-y-auto p-5 bg-gray-50/50">
                                <div x-show="loading" class="flex items-center justify-center py-16">
                                    <svg class="w-6 h-6 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                </div>
                                <div x-show="!loading" class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));">
                                    <template x-if="currentPath !== ''">
                                        <button type="button" @click="browseDir(parentPath)" class="flex flex-col items-center p-3 rounded-xl hover:bg-gray-100 transition-colors group">
                                            <svg class="w-12 h-12 text-amber-500" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                                            <span class="text-xs text-gray-500 mt-1.5 font-medium">..</span>
                                        </button>
                                    </template>
                                    <template x-for="folder in folders" :key="folder.path">
                                        <button type="button" @click="browseDir(folder.path)" class="flex flex-col items-center p-3 rounded-xl hover:bg-gray-100 transition-colors group">
                                            <svg class="w-12 h-12 text-amber-500 group-hover:text-amber-600" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                                            <span class="text-xs text-gray-600 mt-1.5 truncate w-full text-center font-medium" x-text="folder.name"></span>
                                        </button>
                                    </template>
                                    <template x-for="file in files" :key="file.path">
                                        <button type="button" @click="selectFile(file)" class="flex flex-col items-center p-2 rounded-xl hover:bg-gray-100 transition-colors" :class="selectedPath === file.path ? 'ring-2 ring-indigo-500 bg-indigo-50' : ''">
                                            <div class="w-full aspect-square rounded-lg overflow-hidden bg-gray-100 border border-gray-200">
                                                <img :src="file.url" :alt="file.name" class="w-full h-full object-cover" loading="lazy">
                                            </div>
                                            <span class="text-[11px] text-gray-500 mt-1.5 truncate w-full text-center" x-text="file.name"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
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

        function featuredImagePicker() {
            return {
                selectedPath: @json(old('featured_image', $post?->featured_image ?? '')),
                browserOpen: false,
                loading: false,
                currentPath: 'blog',
                parentPath: '',
                folders: [],
                files: [],

                get previewUrl() {
                    if (!this.selectedPath) return '';
                    return '/storage/' + this.selectedPath.split('/').map(encodeURIComponent).join('/');
                },

                get breadcrumbs() {
                    if (!this.currentPath) return [];
                    return this.currentPath.split('/').map((part, i, arr) => ({
                        name: part,
                        path: arr.slice(0, i + 1).join('/'),
                    }));
                },

                async openBrowser() {
                    this.browserOpen = true;
                    await this.browseDir(this.currentPath);
                },

                async browseDir(path) {
                    this.loading = true;
                    const params = path ? '?path=' + encodeURIComponent(path) : '';
                    const res = await fetch(`{{ route('admin.products.images.browse') }}${params}`);
                    const data = await res.json();
                    if (data.ok) {
                        this.currentPath = data.path;
                        this.parentPath = data.parent ?? '';
                        this.folders = data.folders;
                        this.files = data.files;
                    }
                    this.loading = false;
                },

                selectFile(file) {
                    this.selectedPath = file.path;
                    this.browserOpen = false;
                },
            };
        }
    </script>
    @endpush
</x-admin-layout>
