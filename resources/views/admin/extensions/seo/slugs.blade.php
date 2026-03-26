<x-admin-layout>
    <x-slot name="header">SEO Optimization</x-slot>
    @include('admin.extensions.seo.partials.tabs')

    {{-- Warning --}}
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6 flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
        <div>
            <p class="text-sm font-medium text-amber-800">Changing slugs affects your URLs and SEO rankings.</p>
            <p class="text-xs text-amber-700 mt-0.5">Only change slugs if absolutely necessary. Changing a slug will break existing links and bookmarks unless you set up redirects.</p>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex items-center gap-4 flex-wrap">
            <div class="flex gap-2">
                @foreach (['products' => 'Products', 'categories' => 'Categories', 'brands' => 'Brands', 'blog' => 'Blog', 'pages' => 'Pages'] as $key => $label)
                    <a href="{{ route('admin.extensions.seo.slugs', ['type' => $key]) }}"
                       class="px-3 py-1.5 text-sm rounded-lg border transition-colors {{ $filter === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('admin.extensions.seo.slugs') }}" class="flex items-center gap-2 ml-auto">
                <input type="hidden" name="type" value="{{ $filter }}">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name..."
                    class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 w-48">
                <button type="submit" class="px-3 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700">Search</button>
            </form>
        </div>
    </div>

    {{-- Slug table --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" x-data="{
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
                const res = await fetch('{{ route("admin.extensions.seo.update-slug") }}', {
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
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">URL Preview</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($entities as $entity)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $entity->display_name }}</td>
                        <td class="px-6 py-3">
                            <div x-show="editing !== {{ $entity->id }}">
                                <code id="slug-display-{{ $entity->id }}" class="text-sm font-mono text-gray-600 bg-gray-50 px-2 py-0.5 rounded">{{ $entity->slug ?: '(none)' }}</code>
                            </div>
                            <div x-show="editing === {{ $entity->id }}" x-cloak class="flex items-center gap-2">
                                <input type="text" x-model="newSlug" x-ref="slug_{{ $entity->id }}"
                                    @keydown.enter="save('{{ $entityType }}', {{ $entity->id }})"
                                    @keydown.escape="cancel()"
                                    class="rounded border-gray-300 text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500 w-64"
                                    pattern="[a-z0-9][a-z0-9-]*">
                                <button @click="save('{{ $entityType }}', {{ $entity->id }})" :disabled="saving"
                                    class="px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 disabled:opacity-50">Save</button>
                                <button @click="cancel()" class="px-2 py-1 bg-gray-200 text-gray-600 text-xs rounded hover:bg-gray-300">Cancel</button>
                            </div>
                            <p x-show="editing === {{ $entity->id }} && error" x-cloak class="text-xs text-red-600 mt-1" x-text="error"></p>
                        </td>
                        <td class="px-6 py-3 text-xs text-gray-400 font-mono">
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
                        <td class="px-6 py-3 text-right">
                            <button x-show="editing !== {{ $entity->id }}" @click="edit({{ $entity->id }}, '{{ $entity->slug }}')"
                                class="text-indigo-600 hover:text-indigo-900 text-sm">Edit</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">No entities found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($entities->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $entities->links() }}
            </div>
        @endif
    </div>
</x-admin-layout>
