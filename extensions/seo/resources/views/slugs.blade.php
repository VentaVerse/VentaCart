@php
    $slugTypes = ['products' => 'Products', 'categories' => 'Categories', 'brands' => 'Brands', 'blog' => 'Blog', 'pages' => 'Pages'];
@endphp
<x-admin.seo-shell title="Slugs" active="slugs">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Slugs
                <x-admin.help text="Changing a slug changes a live URL: existing links and bookmarks break unless you add a redirect for the old path." /></h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.extensions.seo.redirects.index') }}" class="v-btn v-btn--ghost">Manage redirects</a>
        </div>
    </div>

    <div class="v-settle" x-data="{
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
                const res = await fetch(@js(route('admin.extensions.seo.update-slug')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ entity_type: entityType, entity_id: entityId, slug: this.newSlug })
                });
                const data = await res.json();
                if (data.ok) {
                    document.getElementById('slug-display-' + entityId).textContent = this.newSlug;
                    Alpine.store('toasts').push('Slug updated.', 'good');
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
        <x-admin.panel :title="($slugTypes[$filter] ?? 'Products') . ' slugs'" flush>
            <x-slot:acts>
                <div class="v-tabs v-tabs--sub" role="tablist" aria-label="Content type">
                    @foreach ($slugTypes as $key => $label)
                        <a href="{{ route('admin.extensions.seo.slugs', ['type' => $key]) }}"
                           class="v-tab" role="tab" aria-selected="{{ $filter === $key ? 'true' : 'false' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <form method="GET" action="{{ route('admin.extensions.seo.slugs') }}" class="v-namerow">
                    <input type="hidden" name="type" value="{{ $filter }}">
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search by name…" aria-label="Search by name" class="v-control v-w-search">
                </form>
            </x-slot:acts>

            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Name</th><th>Slug</th><th>URL preview</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entities as $entity)
                            <tr>
                                <td>{{ $entity->display_name }}</td>
                                <td>
                                    <div x-show="editing !== {{ $entity->id }}">
                                        <code id="slug-display-{{ $entity->id }}" class="v-mono">{{ $entity->slug ?: '(none)' }}</code>
                                    </div>
                                    <div x-show="editing === {{ $entity->id }}" x-cloak class="v-namerow">
                                        <input type="text" x-model="newSlug" x-ref="slug_{{ $entity->id }}" aria-label="Slug"
                                               @keydown.enter="save('{{ $entityType }}', {{ $entity->id }})"
                                               @keydown.escape="cancel()"
                                               class="v-control v-mono" pattern="[a-z0-9][a-z0-9-]*">
                                        <button type="button" class="v-btn v-btn--primary v-btn--sm" @click="save('{{ $entityType }}', {{ $entity->id }})" :disabled="saving">Save</button>
                                        <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="cancel()">Cancel</button>
                                    </div>
                                    <p x-show="editing === {{ $entity->id }} && error" x-cloak class="v-field__error" x-text="error"></p>
                                </td>
                                <td class="v-mono v-cell-quiet">
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
                                </td>
                                <td class="v-cell-act">
                                    <button type="button" class="v-btn v-btn--ghost v-btn--sm" x-show="editing !== {{ $entity->id }}"
                                            @click="edit({{ $entity->id }}, '{{ $entity->slug }}')">Edit</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="v-empty"><p class="v-empty__title">No entities found</p></div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($entities->hasPages())
                <x-admin.pager :paginator="$entities" />
            @endif
        </x-admin.panel>
    </div>
</x-admin.seo-shell>
