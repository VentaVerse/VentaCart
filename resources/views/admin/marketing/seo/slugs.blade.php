@php
    $slugTypes = ['products' => 'Products', 'categories' => 'Categories', 'brands' => 'Brands', 'blog' => 'Blog', 'pages' => 'Pages'];
@endphp
<x-admin3.seo-shell title="Slugs" active="slugs">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Slugs
                <x-admin3.help text="Changing a slug changes a live URL: existing links and bookmarks break unless you add a redirect for the old path." /></h1>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.extensions.seo.redirects.index') }}" class="v3-btn v3-btn--ghost">Manage redirects</a>
        </div>
    </div>

    <div class="v3-settle" x-data="{
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
        <x-admin3.panel :title="($slugTypes[$filter] ?? 'Products') . ' slugs'" flush>
            <x-slot:acts>
                <div class="v3-tabs v3-tabs--sub" role="tablist" aria-label="Content type">
                    @foreach ($slugTypes as $key => $label)
                        <a href="{{ route('admin.extensions.seo.slugs', ['type' => $key]) }}"
                           class="v3-tab" role="tab" aria-selected="{{ $filter === $key ? 'true' : 'false' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <form method="GET" action="{{ route('admin.extensions.seo.slugs') }}" class="v3-namerow">
                    <input type="hidden" name="type" value="{{ $filter }}">
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search by name…" aria-label="Search by name" class="v3-control v3-w-search">
                </form>
            </x-slot:acts>

            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Name</th><th>Slug</th><th>URL preview</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entities as $entity)
                            <tr>
                                <td>{{ $entity->display_name }}</td>
                                <td>
                                    <div x-show="editing !== {{ $entity->id }}">
                                        <code id="slug-display-{{ $entity->id }}" class="v3-mono">{{ $entity->slug ?: '(none)' }}</code>
                                    </div>
                                    <div x-show="editing === {{ $entity->id }}" x-cloak class="v3-namerow">
                                        <input type="text" x-model="newSlug" x-ref="slug_{{ $entity->id }}" aria-label="Slug"
                                               @keydown.enter="save('{{ $entityType }}', {{ $entity->id }})"
                                               @keydown.escape="cancel()"
                                               class="v3-control v3-mono" pattern="[a-z0-9][a-z0-9-]*">
                                        <button type="button" class="v3-btn v3-btn--primary v3-btn--sm" @click="save('{{ $entityType }}', {{ $entity->id }})" :disabled="saving">Save</button>
                                        <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="cancel()">Cancel</button>
                                    </div>
                                    <p x-show="editing === {{ $entity->id }} && error" x-cloak class="v3-field__error" x-text="error"></p>
                                </td>
                                <td class="v3-mono v3-cell-quiet">
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
                                <td class="v3-cell-act">
                                    <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" x-show="editing !== {{ $entity->id }}"
                                            @click="edit({{ $entity->id }}, '{{ $entity->slug }}')">Edit</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="v3-empty"><p class="v3-empty__title">No entities found</p></div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($entities->hasPages())
                <div class="v3-pager">{{ $entities->links() }}</div>
            @endif
        </x-admin3.panel>
    </div>
</x-admin3.seo-shell>
