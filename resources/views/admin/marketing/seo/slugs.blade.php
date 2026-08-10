@php
    $slugTypes = ['products' => 'Products', 'categories' => 'Categories', 'brands' => 'Brands', 'blog' => 'Blog', 'pages' => 'Pages'];
@endphp
<x-admin.seo-shell title="SEO Slugs" heading="Slugs">
    <div class="space-y-5" x-data="{
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
        {{-- The one consequence a slug field cannot state in its own
             name: the URL it is part of is already in somebody's
             bookmarks and in Google's index. --}}
        <x-admin.empty-state variant="notice" title="Changing a slug changes a live URL"
            description="Existing links and bookmarks break unless you add a redirect for the old path.">
            <a href="{{ route('admin.extensions.seo.redirects.index') }}" class="v-notice__btn">Manage redirects</a>
        </x-admin.empty-state>

        <x-admin.segment label="Content type">
            @foreach ($slugTypes as $key => $label)
                <x-admin.segment.item :href="route('admin.extensions.seo.slugs', ['type' => $key])" :active="$filter === $key">{{ $label }}</x-admin.segment.item>
            @endforeach
            <x-slot:aside>
                <form method="GET" action="{{ route('admin.extensions.seo.slugs') }}" class="flex items-center gap-2">
                    <input type="hidden" name="type" value="{{ $filter }}">
                    <x-admin.input type="search" name="search" :value="$search" placeholder="Search by name&hellip;" class="w-48" aria-label="Search by name" />
                    <x-admin.button type="submit" variant="secondary" size="sm">Search</x-admin.button>
                </form>
            </x-slot:aside>
        </x-admin.segment>

        <x-admin.table>
            <x-slot:head>
                <x-admin.table.th>Name</x-admin.table.th>
                <x-admin.table.th>Slug</x-admin.table.th>
                <x-admin.table.th>URL preview</x-admin.table.th>
                <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
            </x-slot:head>
            @forelse ($entities as $entity)
                <x-admin.table.row>
                    <x-admin.table.cell class="font-medium text-v-text">{{ $entity->display_name }}</x-admin.table.cell>
                    <x-admin.table.cell>
                        <div x-show="editing !== {{ $entity->id }}">
                            <code id="slug-display-{{ $entity->id }}" class="v-num rounded-v-sm bg-v-sunken px-2 py-0.5 text-[13px] text-v-secondary">{{ $entity->slug ?: '(none)' }}</code>
                        </div>
                        <div x-show="editing === {{ $entity->id }}" x-cloak class="flex items-center gap-2">
                            <input type="text" x-model="newSlug" x-ref="slug_{{ $entity->id }}" aria-label="Slug"
                                @keydown.enter="save('{{ $entityType }}', {{ $entity->id }})"
                                @keydown.escape="cancel()"
                                class="v-control h-[var(--control-h-sm)] w-64 rounded-v-md border bg-v-surface px-2.5 font-mono text-[13px] text-v-text"
                                pattern="[a-z0-9][a-z0-9-]*">
                            <x-admin.button type="button" variant="primary" size="sm" @click="save('{{ $entityType }}', {{ $entity->id }})" x-bind:disabled="saving">Save</x-admin.button>
                            <x-admin.button type="button" variant="ghost" size="sm" @click="cancel()">Cancel</x-admin.button>
                        </div>
                        <p x-show="editing === {{ $entity->id }} && error" x-cloak class="v-field__error mt-1" x-text="error"></p>
                    </x-admin.table.cell>
                    <x-admin.table.cell class="v-num text-xs text-v-muted">
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
                    <x-admin.table.cell class="v-col-act">
                        <div class="v-rowactions v-rowactions--end" x-show="editing !== {{ $entity->id }}">
                            <x-admin.button type="button" size="sm" variant="ghost" @click="edit({{ $entity->id }}, '{{ $entity->slug }}')">Edit</x-admin.button>
                        </div>
                    </x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr>
                    <td colspan="4">
                        <x-admin.empty-state title="No entities found" />
                    </td>
                </tr>
            @endforelse
            @if ($entities->hasPages())
                <x-slot:footer>{{ $entities->links() }}</x-slot:footer>
            @endif
        </x-admin.table>
    </div>
</x-admin.seo-shell>
