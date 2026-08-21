<x-admin.shell :title="$post ? 'Edit Post' : 'New Post'">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.blog.index') }}">Blog</a>
                <span aria-hidden="true">/</span>
                <span>{{ $post->title ?? 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $post ? $post->title : 'New post' }}</h1>
        </div>
    </div>

    <x-admin.form id="v-post-form" :action="$post ? route('admin.blog.update', $post) : route('admin.blog.store')"
                   :method="$post ? 'PUT' : 'POST'" class="v-settle"
                   onsubmit="if (typeof tinymce !== 'undefined') tinymce.triggerSave();">
        <x-admin.panel title="Post">
            <x-admin.field name="title" label="Title" required>
                <input type="text" name="title" required class="v-control" value="{{ old('title', $post?->title) }}">
            </x-admin.field>
            <div class="v-gap">
                <x-admin.field name="slug" label="Slug" hint="Leave blank to auto-generate from the title.">
                    <input type="text" name="slug" class="v-control v-mono" value="{{ old('slug', $post?->slug) }}">
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.field name="content" label="Content" required>
                    <textarea name="content" id="v-post-content" rows="16" class="v-control">{{ old('content', $post?->content) }}</textarea>
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.field name="excerpt" label="Excerpt">
                    <textarea name="excerpt" rows="3" class="v-control">{{ old('excerpt', $post?->excerpt) }}</textarea>
                </x-admin.field>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Publish">
            <div class="v-grid v-grid--2">
                <x-admin.field name="blog_category_id" label="Category">
                    <select name="blog_category_id" class="v-control">
                        <option value="">(None)</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('blog_category_id', $post?->blog_category_id) == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
                <x-admin.field name="published_at" label="Published date">
                    <input type="datetime-local" name="published_at" class="v-control v-mono" value="{{ old('published_at', $post?->published_at?->format('Y-m-d\TH:i')) }}">
                </x-admin.field>
            </div>
            <div class="v-gap v-actrow">
                <label class="v-toggle">
                    <input type="hidden" name="is_published" value="0">
                    <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $post?->is_published ?? false))>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    <span>Published</span>
                </label>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Featured image">
            {{-- Was its own hand-rolled browser: read-only, no upload, no
                 search, no delete, and a root crumb labelled "blog" that
                 rendered "blog / blog / blog / eamusic". --}}
            <x-admin.media-picker name="featured_image"
                                  :value="old('featured_image', $post?->featured_image)"
                                  root="blog" label="Featured image" />
        </x-admin.panel>

        <x-admin.panel title="Tags">
            @if ($tags->isEmpty())
                <p class="v-empty__line">No tags created yet.</p>
            @else
                <x-admin.picklist name="tags" label="Tags"
                    :items="$tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->all()"
                    :selected="old('tags', $post?->tags?->pluck('id')->toArray() ?? [])" />
            @endif
        </x-admin.panel>

        <x-admin.panel title="SEO">
            <div class="v-grid v-grid--2">
                <x-admin.field name="meta_title" label="Meta title">
                    <input type="text" name="meta_title" class="v-control" value="{{ old('meta_title', $post?->meta_title) }}">
                </x-admin.field>
                <x-admin.field name="meta_description" label="Meta description">
                    <textarea name="meta_description" rows="2" class="v-control">{{ old('meta_description', $post?->meta_description) }}</textarea>
                </x-admin.field>
            </div>

            {{-- The optimizer belongs to the SEO extension, which ships NOT
                 installed (owner ruling 2026-08-19). Route::has is how one
                 extension asks whether another is present without naming its
                 class; without this the whole form 500s on a store that simply
                 never installed SEO. Same guard core uses in
                 admin/partials/seo_optimizer.blade.php. --}}
            @if (Route::has('admin.extensions.seo.optimize'))
            {{-- AI SEO Optimizer (SEO extension's endpoint; the button only
                 renders for a saved post) --}}
            <div class="v-gap" x-data="{
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
                        this.error = 'Request failed. Check the Claude API key under Extensions, SEO, Settings.';
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
            }">
                @if ($post ?? false)
                    <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="optimize()" :disabled="loading">
                        <span x-text="loading ? 'Optimizing…' : 'Optimize with AI'"></span>
                    </button>
                @endif

                <p x-show="error" x-cloak class="v-field__error v-gap-sm" x-text="error"></p>

                <div x-show="suggestion" x-cloak class="v-well v-gap">
                    <template x-for="field in ['meta_title', 'meta_description', 'og_title', 'og_description']" :key="field">
                        <div class="v-gap-sm">
                            <span class="v-field__label" x-text="field.replace(/_/g, ' ')"></span>
                            <div class="v-grid v-grid--2">
                                <div>
                                    <span class="v-cell-quiet v-block">Current</span>
                                    <span x-text="suggestion?.['current_' + field] || '(empty)'"></span>
                                </div>
                                <div>
                                    <span class="v-tone-act v-block">Suggested</span>
                                    <span x-text="suggestion?.['suggested_' + field] || '(empty)'"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div class="v-gap v-actrow">
                        <button type="button" class="v-btn v-btn--primary v-btn--sm" @click="apply(); touch()">Apply to form</button>
                        <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="suggestion = null">Dismiss</button>
                    </div>
                </div>
            </div>
            @endif
        </x-admin.panel>
    </x-admin.form>

    @push('scripts')
    <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
    <script>
        tinymce.init({
            selector: '#v-post-content',
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
            setup: function (editor) {
                // The editor lives outside the form's input events; sync it
                // back to the textarea and tell the save doctrine on every
                // edit, so the save bar and leave guard see TinyMCE changes.
                editor.on('input change undo redo', function () {
                    editor.save();
                    var form = document.getElementById('v-post-form');
                    if (form) form.dispatchEvent(new Event('input'));
                });
            },
        });

    </script>
    @endpush
</x-admin.shell>
