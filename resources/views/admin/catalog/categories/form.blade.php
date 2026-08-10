@php
    $vLabel = 'v-field__label v-label mb-1';
@endphp
<x-admin.shell :title="$category ? 'Edit Category' : 'Create Category'">
    <x-admin.page :title="$category ? 'Edit Category' : 'Create Category'">
        <x-slot:actions>
            <x-admin.button variant="ghost" href="{{ route('admin.categories.index') }}">Back to Categories</x-admin.button>
        </x-slot:actions>

        <div class="max-w-3xl">
            <x-admin.card>
                <form method="POST"
                      action="{{ $category ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
                      data-v-form class="space-y-6">
                    @csrf
                    @if ($category)
                        @method('PUT')
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <input type="hidden" name="store_id" value="1">

                        <x-admin.field name="parent_id" label="Parent Category">
                            <x-admin.select name="parent_id">
                                <option value="">None (top-level)</option>
                                @foreach ($parentCategories as $parent)
                                    <option value="{{ $parent->id }}"
                                        {{ old('parent_id', $category?->parent_id) == $parent->id ? 'selected' : '' }}>
                                        {{ $parent->name }}
                                    </option>
                                @endforeach
                            </x-admin.select>
                        </x-admin.field>

                        <div class="md:col-span-2">
                            <x-admin.field name="name" label="Name" required>
                                <x-admin.input type="text" name="name" required :value="$category?->name" />
                            </x-admin.field>
                        </div>

                        <div class="md:col-span-2">
                            <x-admin.field name="description" label="Description">
                                <x-admin.textarea name="description" rows="4" :value="$category?->description" />
                            </x-admin.field>
                        </div>

                        <div class="md:col-span-2">
                            <label class="{{ $vLabel }}">Image</label>
                            @include('admin.partials.single_image_picker', [
                                'fieldName' => 'image',
                                'currentImage' => $category?->image,
                                'root' => 'categories',
                                'defaultFolder' => 'Categories',
                            ])
                            @error('image') <p class="mt-1 text-[13px] text-v-danger">{{ $message }}</p> @enderror
                        </div>

                        <x-admin.field name="sort_order" label="Sort Order">
                            <x-admin.input type="number" name="sort_order" min="0" class="w-32" :value="$category?->sort_order ?? 0" />
                        </x-admin.field>
                    </div>

                    {{-- Status --}}
                    <div class="border-t border-v-border pt-6">
                        <x-admin.toggle name="is_active" :checked="$category?->is_active ?? true" label="Active" />
                    </div>

                    {{-- SEO --}}
                    <div class="border-t border-v-border pt-6">
                        <h3 class="v-subhead mb-4">SEO</h3>
                        <div class="space-y-4">
                            <x-admin.field name="slug" label="Slug" help="Leave blank to auto-generate from name">
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-v-muted">/category/</span>
                                    <x-admin.input type="text" name="slug" class="flex-1 font-mono" :value="$category?->slug" placeholder="auto-generated-from-name" />
                                </div>
                            </x-admin.field>
                            <x-admin.field name="meta_title" label="Meta Title">
                                <x-admin.input type="text" name="meta_title" maxlength="255" :value="$category?->meta_title" />
                            </x-admin.field>
                            <x-admin.field name="meta_description" label="Meta Description">
                                <x-admin.textarea name="meta_description" rows="2" maxlength="500" :value="$category?->meta_description" />
                            </x-admin.field>
                            <x-admin.field name="meta_keywords" label="Meta Keywords">
                                <x-admin.input type="text" name="meta_keywords" maxlength="255" :value="$category?->meta_keywords" />
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
                                            body: JSON.stringify({ entity_type: 'category', entity_id: {{ $category->id ?? 0 }} })
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
                                @if ($category ?? false)
                                    <x-admin.button type="button" variant="primary" @click="optimize()" x-bind:disabled="loading">
                                        <template x-if="!loading">
                                            <x-admin.icon name="sparkles" class="w-4 h-4" />
                                        </template>
                                        <template x-if="loading">
                                            <x-admin.icon name="spinner" class="w-4 h-4 animate-spin" />
                                        </template>
                                        <span x-text="loading ? 'Optimizing...' : 'Optimize with AI'"></span>
                                    </x-admin.button>
                                @else
                                    <p class="text-xs text-v-muted">Save the category first to use AI optimization.</p>
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
                    </div>

                    <x-admin.form-actions>
                        <x-admin.button variant="secondary" href="{{ route('admin.categories.index') }}">Cancel</x-admin.button>
                        <x-admin.button type="submit">{{ $category ? 'Update Category' : 'Create Category' }}</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>
        </div>
    </x-admin.page>
</x-admin.shell>
