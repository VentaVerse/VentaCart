@php
    $slugTypes = ['products' => 'Products', 'categories' => 'Categories', 'brands' => 'Brands', 'blog' => 'Blog', 'pages' => 'Pages'];
@endphp
<x-admin.shell title="SEO Slugs">
    <x-admin.page title="Slugs"
        :breadcrumb="[['label' => 'SEO', 'url' => route('admin.extensions.seo.dashboard')], ['label' => 'Slugs']]">
        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            @include('admin.extensions.seo.partials.nav-v2')

            <div class="min-w-0 flex-1 space-y-5" x-data="{
                editing: null,
                newSlug: '',
                saving: false,
                error: null,
                edit(id, currentSlug) {
                    this.editing = id;
                    this.newSlug = currentSlug;
                    this.error = null;
                    this.$nextTick(() => this.$refs['slug_' + id]?.focus());
                },
                cancel() {
                    this.editing = null;
                    this.error = null;
                },
                async save(entityType, entityId) {
                    this.saving = true;
                    this.error = null;
                    try {
                        const res = await fetch('{{ route('admin.extensions.seo.update-slug') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                            body: JSON.stringify({ entity_type: entityType, entity_id: entityId, slug: this.newSlug })
                        });
                        const data = await res.json();
                        if (data.ok) {
                            document.getElementById('slug-display-' + entityId).textContent = this.newSlug;
                            this.editing = null;
                        } else {
                            this.error = data.error || 'Failed to update slug.';
                        }
                    } catch (e) {
                        this.error = 'Request failed.';
                    }
                    this.saving = false;
                }
            }">
                {{-- Warning --}}
                <div class="flex items-start gap-3 rounded-v-lg border border-v-border bg-[color-mix(in_srgb,var(--warning)_10%,transparent)] p-4">
                    <x-admin.icon name="warning" class="mt-0.5 h-5 w-5 shrink-0 text-v-warning" />
                    <div>
                        <p class="text-[13px] font-medium text-v-text">Changing slugs affects your URLs and SEO rankings.</p>
                        <p class="mt-0.5 text-xs text-v-secondary">Only change slugs if absolutely necessary. Changing a slug will break existing links and bookmarks unless you set up redirects.</p>
                    </div>
                </div>

                {{-- Filter bar --}}
                <x-admin.card>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex flex-wrap gap-2">
                            @foreach ($slugTypes as $key => $label)
                                <a href="{{ route('admin.extensions.seo.slugs', ['type' => $key]) }}"
                                   class="rounded-v-md border px-3 py-1.5 text-[13px] font-medium transition-colors
                                       {{ $filter === $key ? 'border-v-accent bg-v-accent-weak text-v-accent' : 'border-v-border bg-v-surface text-v-secondary hover:bg-v-hover hover:text-v-text' }}">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                        <form method="GET" action="{{ route('admin.extensions.seo.slugs') }}" class="ml-auto flex items-center gap-2">
                            <input type="hidden" name="type" value="{{ $filter }}">
                            <x-admin.input type="text" name="search" :value="$search" placeholder="Search by name..." class="w-48" />
                            <x-admin.button type="submit" variant="secondary" size="md">Search</x-admin.button>
                        </form>
                    </div>
                </x-admin.card>

                {{-- Slug table --}}
                <x-admin.table>
                    <x-slot name="head">
                        <x-admin.table.th>Name</x-admin.table.th>
                        <x-admin.table.th>Slug</x-admin.table.th>
                        <x-admin.table.th>URL Preview</x-admin.table.th>
                        <x-admin.table.th align="right">Actions</x-admin.table.th>
                    </x-slot>
                    @forelse ($entities as $entity)
                        <x-admin.table.row>
                            <x-admin.table.cell class="font-medium text-v-text">{{ $entity->display_name }}</x-admin.table.cell>
                            <x-admin.table.cell>
                                <div x-show="editing !== {{ $entity->id }}">
                                    <code id="slug-display-{{ $entity->id }}" class="rounded bg-v-sunken px-2 py-0.5 font-mono text-[13px] text-v-secondary">{{ $entity->slug ?: '(none)' }}</code>
                                </div>
                                <div x-show="editing === {{ $entity->id }}" x-cloak class="flex items-center gap-2">
                                    <input type="text" x-model="newSlug" x-ref="slug_{{ $entity->id }}"
                                        @keydown.enter="save('{{ $entityType }}', {{ $entity->id }})"
                                        @keydown.escape="cancel()"
                                        class="v-control h-[var(--control-h)] w-64 rounded-v-md border border-v-border bg-v-surface px-2.5 font-mono text-[13px] text-v-text"
                                        pattern="[a-z0-9][a-z0-9-]*">
                                    <x-admin.button type="button" variant="primary" size="sm" @click="save('{{ $entityType }}', {{ $entity->id }})" x-bind:disabled="saving">Save</x-admin.button>
                                    <x-admin.button type="button" variant="ghost" size="sm" @click="cancel()">Cancel</x-admin.button>
                                </div>
                                <p x-show="editing === {{ $entity->id }} && error" x-cloak class="mt-1 text-xs text-v-danger" x-text="error"></p>
                            </x-admin.table.cell>
                            <x-admin.table.cell class="font-mono text-xs text-v-muted">
                                @php
                                    $prefix = match($entityType) {
                                        'product' => '/product/',
                                        'category' => '/category/',
                                        'brand' => '/brand/',
                                        'blog_post' => '/blog/',
                                        'page' => '/',
                                        default => '/',
                                    };
                                @endphp
                                {{ $prefix }}<span id="slug-preview-{{ $entity->id }}">{{ $entity->slug }}</span>
                            </x-admin.table.cell>
                            <x-admin.table.cell align="right">
                                <button x-show="editing !== {{ $entity->id }}" @click="edit({{ $entity->id }}, '{{ $entity->slug }}')"
                                    class="text-[13px] text-v-accent hover:opacity-80">Edit</button>
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center">
                                <x-admin.empty-state icon="seo" title="No entities found" />
                            </td>
                        </tr>
                    @endforelse
                </x-admin.table>

                @if ($entities->hasPages())
                    <div class="pt-2">{{ $entities->links() }}</div>
                @endif
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
