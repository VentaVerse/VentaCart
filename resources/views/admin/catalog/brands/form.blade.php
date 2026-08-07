@php
    $vLabel = 'block text-[13px] font-medium text-v-text mb-1';
@endphp
<x-admin.shell :title="$brand ? 'Edit Brand' : 'New Brand'">
    <x-admin.page :title="$brand ? 'Edit Brand' : 'New Brand'" :breadcrumb="[['label' => 'Catalog'], ['label' => 'Brands', 'url' => route('admin.brands.index')]]">
        <x-slot:actions>
            <x-admin.button variant="ghost" href="{{ route('admin.brands.index') }}">Back to Brands</x-admin.button>
        </x-slot:actions>

        <form method="POST"
              action="{{ $brand ? route('admin.brands.update', $brand) : route('admin.brands.store') }}"
              data-v-form>
            @csrf
            @if ($brand) @method('PUT') @endif
            <input type="hidden" name="store_id" value="1">

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-6">
                {{-- Left: Main fields --}}
                <div class="space-y-6">
                    {{-- Name --}}
                    <x-admin.card>
                        <x-admin.field name="name" label="Brand Name">
                            <x-admin.input type="text" name="name" required :value="$brand?->name" placeholder="e.g. Wampler, Boss, Friedman" />
                        </x-admin.field>
                    </x-admin.card>

                    {{-- Image --}}
                    <x-admin.card title="Logo / Image">
                        @include('admin.partials.single_image_picker', [
                            'fieldName' => 'image',
                            'currentImage' => $brand?->image,
                            'root' => 'brands',
                            'defaultFolder' => 'Brands',
                            'hint' => 'Recommended: transparent PNG, square or horizontal',
                        ])
                        @error('image') <p class="mt-1.5 text-[13px] text-v-danger">{{ $message }}</p> @enderror
                    </x-admin.card>

                    {{-- Description --}}
                    <x-admin.card title="Description">
                        <x-admin.textarea name="description" rows="4" :value="$brand?->description" placeholder="Brief description of the brand (shown on brand page)" />
                        @error('description') <p class="mt-1.5 text-[13px] text-v-danger">{{ $message }}</p> @enderror
                    </x-admin.card>

                    {{-- SEO --}}
                    <x-admin.card title="SEO">
                        <div class="space-y-4">
                            <x-admin.field name="slug" label="Slug" help="Leave blank to auto-generate from name">
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-v-muted">/brand/</span>
                                    <x-admin.input type="text" name="slug" class="flex-1 font-mono" :value="$brand?->slug" placeholder="auto-generated-from-name" />
                                </div>
                            </x-admin.field>
                            <x-admin.field name="meta_title" label="Meta Title" help="Recommended: 50-60 characters">
                                <x-admin.input type="text" name="meta_title" :value="$brand?->meta_title" placeholder="Leave blank to use brand name" />
                            </x-admin.field>
                            <x-admin.field name="meta_description" label="Meta Description" help="Recommended: 120-160 characters">
                                <x-admin.textarea name="meta_description" rows="2" :value="$brand?->meta_description" placeholder="Brief summary for search engine results" />
                            </x-admin.field>
                            <x-admin.field name="meta_keywords" label="Meta Keywords">
                                <x-admin.input type="text" name="meta_keywords" :value="$brand?->meta_keywords" placeholder="guitar pedals, effects, accessories" />
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
                                            body: JSON.stringify({ entity_type: 'brand', entity_id: {{ $brand->id ?? 0 }} })
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
                                @if ($brand ?? false)
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
                                    <p class="text-xs text-v-muted">Save the brand first to use AI optimization.</p>
                                @endif

                                <div x-show="error" x-cloak class="mt-3 rounded-v-md border border-v-danger bg-[color-mix(in_srgb,var(--danger)_10%,transparent)] p-3 text-[13px] text-v-danger" x-text="error"></div>

                                <div x-show="suggestion" x-cloak class="mt-3 rounded-v-md border border-v-border bg-v-accent-weak p-4">
                                    <h4 class="mb-3 text-[13px] font-semibold text-v-text">AI Suggestion Preview</h4>
                                    <div class="space-y-3">
                                        <template x-for="field in ['meta_title', 'meta_description', 'og_title', 'og_description']" :key="field">
                                            <div>
                                                <label class="text-xs font-medium uppercase text-v-muted" x-text="field.replace(/_/g, ' ')"></label>
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

                {{-- Right: Settings --}}
                <div class="space-y-6">
                    <x-admin.card title="Settings">
                        <div class="space-y-4">
                            {{-- Status --}}
                            <div>
                                <label class="flex items-center justify-between cursor-pointer">
                                    <span class="text-[13px] text-v-text">Active</span>
                                    <x-admin.toggle name="is_active" :checked="$brand?->is_active ?? true" />
                                </label>
                                <p class="text-xs text-v-muted mt-1">Inactive brands are hidden from the storefront</p>
                            </div>

                            <hr class="border-v-border">

                            {{-- Sort Order --}}
                            <x-admin.field name="sort_order" label="Sort Order" help="Lower numbers appear first">
                                <x-admin.input type="number" name="sort_order" min="0" :value="$brand?->sort_order ?? 0" />
                            </x-admin.field>
                        </div>
                    </x-admin.card>

                    {{-- Stats (edit only) --}}
                    @if ($brand)
                        <x-admin.card title="Info">
                            <dl class="space-y-2 text-[13px]">
                                <div class="flex justify-between">
                                    <dt class="text-v-muted">Products</dt>
                                    <dd class="text-v-text font-medium">{{ $brand->products()->count() }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-v-muted">Slug</dt>
                                    <dd class="text-v-text font-mono text-xs">{{ $brand->slug }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-v-muted">Created</dt>
                                    <dd class="text-v-text">{{ $brand->created_at->format('M d, Y') }}</dd>
                                </div>
                            </dl>
                        </x-admin.card>
                    @endif

                    {{-- Save --}}
                    <x-admin.button type="submit" class="w-full justify-center">
                        {{ $brand ? 'Update Brand' : 'Create Brand' }}
                    </x-admin.button>
                </div>
            </div>
        </form>
    </x-admin.page>
</x-admin.shell>
