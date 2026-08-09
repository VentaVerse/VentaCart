<x-admin.shell :title="$post ? 'Edit Post' : 'New Post'">
    <x-admin.page :title="$post ? 'Edit Post' : 'New Post'">
        <x-slot:actions>
            <x-admin.button variant="ghost" href="{{ route('admin.blog.index') }}">Back to Posts</x-admin.button>
        </x-slot:actions>

        <form method="POST" action="{{ $post ? route('admin.blog.update', $post) : route('admin.blog.store') }}"
              data-v-form onsubmit="if(typeof tinymce !== 'undefined') tinymce.triggerSave();">
            @csrf
            @if ($post)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Main Content --}}
                <div class="lg:col-span-2 space-y-6">
                    <x-admin.card>
                        <div class="space-y-4">
                            <x-admin.field name="title" label="Title">
                                <x-admin.input type="text" name="title" required :value="$post?->title" />
                            </x-admin.field>

                            <x-admin.field name="slug" label="Slug" help="Auto-generated if empty">
                                <x-admin.input type="text" name="slug" :value="$post?->slug" />
                            </x-admin.field>

                            <x-admin.field name="content" label="Content">
                                <textarea name="content" id="content" rows="16"
                                    class="tinymce-editor v-control w-full text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">{{ old('content', $post?->content) }}</textarea>
                            </x-admin.field>

                            <x-admin.field name="excerpt" label="Excerpt">
                                <x-admin.textarea name="excerpt" rows="3" :value="$post?->excerpt" />
                            </x-admin.field>
                        </div>
                    </x-admin.card>

                    {{-- SEO --}}
                    <x-admin.card title="SEO">
                        <div class="space-y-4">
                            <x-admin.field name="meta_title" label="Meta Title">
                                <x-admin.input type="text" name="meta_title" :value="$post?->meta_title" />
                            </x-admin.field>
                            <x-admin.field name="meta_description" label="Meta Description">
                                <x-admin.textarea name="meta_description" rows="2" :value="$post?->meta_description" />
                            </x-admin.field>

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
                            }" class="mt-4 pt-4 border-t border-v-border">
                                @if ($post ?? false)
                                    <x-admin.button type="button" variant="primary" @click="optimize()" x-bind:disabled="loading">
                                        <template x-if="!loading">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                                        </template>
                                        <template x-if="loading">
                                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                                        </template>
                                        <span x-text="loading ? 'Optimizing...' : 'Optimize with AI'"></span>
                                    </x-admin.button>
                                @else
                                    <p class="text-xs text-v-muted">Save the post first to use AI optimization.</p>
                                @endif

                                <div x-show="error" x-cloak class="mt-3 rounded-v-md border border-v-danger bg-[color-mix(in_srgb,var(--danger)_10%,transparent)] p-3 text-[13px] text-v-danger" x-text="error"></div>

                                <div x-show="suggestion" x-cloak class="mt-3 rounded-v-md border border-v-border bg-v-accent-weak p-4">
                                    <h4 class="mb-3 text-[13px] font-semibold text-v-text">AI Suggestion Preview</h4>
                                    <div class="space-y-3">
                                        <template x-for="field in ['meta_title', 'meta_description', 'og_title', 'og_description']" :key="field">
                                            <div>
                                                <label class="v-label" x-text="field.replace(/_/g, ' ')"></label>
                                                <div class="mt-1 grid grid-cols-2 gap-2">
                                                    <div class="rounded-v-sm bg-v-surface p-2 text-xs">
                                                        <span class="mb-0.5 block text-v-muted">Current:</span>
                                                        <span x-text="suggestion['current_' + field] || '(empty)'"></span>
                                                    </div>
                                                    <div class="rounded-v-sm border-l-2 border-v-accent bg-v-surface p-2 text-xs">
                                                        <span class="mb-0.5 block text-v-accent">Suggested:</span>
                                                        <span x-text="suggestion['suggested_' + field] || '(empty)'"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="mt-4 flex gap-2">
                                        <x-admin.button type="button" variant="primary" size="sm" @click="apply()">Apply to Form</x-admin.button>
                                        <x-admin.button type="button" variant="secondary" size="sm" @click="suggestion = null">Dismiss</x-admin.button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-admin.card>
                </div>

                {{-- Sidebar --}}
                <div class="space-y-6">
                    {{-- Publish --}}
                    <x-admin.card title="Publish">
                        <div class="space-y-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <x-admin.toggle name="is_published" :checked="$post?->is_published ?? false" />
                                <span class="text-[13px] text-v-text">Published</span>
                            </label>

                            <x-admin.field name="published_at" label="Published Date">
                                <x-admin.input type="datetime-local" name="published_at" :value="$post?->published_at?->format('Y-m-d\TH:i')" />
                            </x-admin.field>

                            <div class="flex gap-2">
                                <x-admin.button type="submit" class="flex-1 justify-center">
                                    {{ $post ? 'Update' : 'Create' }}
                                </x-admin.button>
                                <x-admin.button variant="secondary" href="{{ route('admin.blog.index') }}">Cancel</x-admin.button>
                            </div>
                        </div>
                    </x-admin.card>

                    {{-- Category --}}
                    <x-admin.card title="Category">
                        <x-admin.select name="blog_category_id">
                            <option value="">(None)</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('blog_category_id', $post?->blog_category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </x-admin.select>
                    </x-admin.card>

                    {{-- Featured Image --}}
                    <x-admin.card title="Featured Image">
                        <div x-data="featuredImagePicker()" class="space-y-4">
                            <input type="hidden" name="featured_image" :value="selectedPath">

                            {{-- Preview --}}
                            <div x-show="selectedPath" class="relative inline-block">
                                <img :src="previewUrl" class="w-full max-w-[200px] h-auto rounded-v-md border border-v-border" alt="Featured image">
                                <button type="button" @click="selectedPath = ''" aria-label="Remove featured image" class="absolute top-1 right-1 w-6 h-6 bg-v-danger text-v-danger-text rounded-full flex items-center justify-center hover:opacity-90 shadow-sm">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <x-admin.button type="button" variant="primary" @click="openBrowser()">
                                <x-admin.icon name="media" class="w-4 h-4" />
                                <span x-text="selectedPath ? 'Change Image' : 'Choose Image'"></span>
                            </x-admin.button>

                            {{-- Browser Modal --}}
                            <div x-show="browserOpen" x-cloak x-transition.opacity.duration.200ms
                                 class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                                 style="background: rgba(0,0,0,0.45); backdrop-filter: blur(3px);"
                                 @keydown.escape.window="browserOpen = false">
                                <div class="w-full max-w-4xl max-h-[80vh] flex flex-col bg-v-surface rounded-v-lg shadow-2xl border border-v-border overflow-hidden"
                                     @click.outside="browserOpen = false">
                                    {{-- Header --}}
                                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-v-border bg-v-sunken">
                                        <div class="flex items-center gap-2">
                                            <x-admin.icon name="media" class="w-5 h-5 text-v-accent" />
                                            <h3 class="text-[13px] font-semibold text-v-text">Choose Featured Image</h3>
                                            <span class="text-v-border">|</span>
                                            <nav class="flex items-center gap-0.5 text-[13px]">
                                                <button type="button" @click="browseDir(root)" class="text-v-accent hover:opacity-80 font-medium">blog</button>
                                                <template x-for="(crumb, i) in breadcrumbs" :key="i">
                                                    <span class="flex items-center gap-0.5">
                                                        <span class="text-v-muted">/</span>
                                                        <button type="button" @click="browseDir(crumb.path)" class="text-v-accent hover:opacity-80 font-medium" x-text="crumb.name"></button>
                                                    </span>
                                                </template>
                                            </nav>
                                        </div>
                                        <button type="button" @click="browserOpen = false" aria-label="Close" class="text-v-muted hover:text-v-text p-1 rounded-v-md hover:bg-v-hover">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    {{-- Content --}}
                                    <div class="flex-1 overflow-y-auto p-5 bg-v-sunken/50">
                                        <div x-show="loading" class="flex items-center justify-center py-16">
                                            <svg class="w-6 h-6 animate-spin text-v-accent" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        </div>
                                        <div x-show="!loading" class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));">
                                            <template x-if="currentPath !== ''">
                                                <button type="button" @click="browseDir(parentPath)" class="flex flex-col items-center p-3 rounded-v-lg hover:bg-v-hover transition-colors group">
                                                    <svg class="w-12 h-12 text-v-warning" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                                                    <span class="text-xs text-v-muted mt-1.5 font-medium">..</span>
                                                </button>
                                            </template>
                                            <template x-for="folder in folders" :key="folder.path">
                                                <button type="button" @click="browseDir(folder.path)" class="flex flex-col items-center p-3 rounded-v-lg hover:bg-v-hover transition-colors group">
                                                    <svg class="w-12 h-12 text-v-warning" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                                                    <span class="text-xs text-v-secondary mt-1.5 truncate w-full text-center font-medium" x-text="folder.name"></span>
                                                </button>
                                            </template>
                                            <template x-for="file in files" :key="file.path">
                                                <button type="button" @click="selectFile(file)" class="flex flex-col items-center p-2 rounded-v-lg hover:bg-v-hover transition-colors" :class="selectedPath === file.path ? 'ring-2 ring-v-accent bg-v-accent-weak' : ''">
                                                    <div class="w-full aspect-square rounded-v-md overflow-hidden bg-v-sunken border border-v-border">
                                                        <img :src="file.url" :alt="file.name" class="w-full h-full object-cover" loading="lazy">
                                                    </div>
                                                    <span class="text-[11px] text-v-muted mt-1.5 truncate w-full text-center" x-text="file.name"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-admin.card>

                    {{-- Tags --}}
                    <x-admin.card title="Tags">
                        @php $selectedTags = old('tags', $post?->tags?->pluck('id')->toArray() ?? []); @endphp
                        <div class="space-y-1.5 max-h-48 overflow-y-auto">
                            @foreach ($tags as $tag)
                                <label class="flex items-center gap-2 text-[13px] cursor-pointer text-v-text">
                                    <input type="checkbox" name="tags[]" value="{{ $tag->id }}" {{ in_array($tag->id, $selectedTags) ? 'checked' : '' }}
                                        class="w-4 h-4 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                    {{ $tag->name }}
                                </label>
                            @endforeach
                        </div>
                        @if ($tags->isEmpty())
                            <p class="text-xs text-v-muted">No tags created yet.</p>
                        @endif
                    </x-admin.card>
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
                    currentPath: '',
                    parentPath: '',
                    folders: [],
                    files: [],
                    lastBrowsedPath: null,
                    root: 'blog',

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

                        let startPath = '';

                        if (this.lastBrowsedPath !== null) {
                            startPath = this.lastBrowsedPath;
                        } else if (this.selectedPath) {
                            const lastSlash = this.selectedPath.lastIndexOf('/');
                            if (lastSlash > 0) {
                                // Navigate to the existing image's folder (full path).
                                startPath = this.selectedPath.substring(0, lastSlash);
                            }
                        } else {
                            startPath = this.root;
                        }

                        await this.browseDir(startPath);
                    },

                    async browseDir(path) {
                        this.loading = true;
                        const qs = new URLSearchParams();
                        if (path) qs.set('path', path);
                        qs.set('root', this.root);
                        const res = await fetch(`{{ route('admin.products.images.browse') }}?${qs}`);
                        const data = await res.json();
                        if (data.ok) {
                            this.currentPath = data.path;
                            this.parentPath = data.parent ?? '';
                            this.folders = data.folders;
                            this.files = data.files;
                            this.lastBrowsedPath = data.path;
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
    </x-admin.page>
</x-admin.shell>
