<x-admin-layout>
    <x-slot name="header">SEO Optimization</x-slot>

    @include('admin.extensions.seo.partials.tabs')

    {{-- Filters Bar --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" action="{{ route('admin.extensions.seo.entities', ['type' => $type]) }}" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[180px]">
                <label for="search" class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Search by name..." class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="w-44">
                <label for="seo_status" class="block text-xs font-medium text-gray-600 mb-1">SEO Status</label>
                <select name="seo_status" id="seo_status" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All</option>
                    <option value="complete" {{ $status === 'complete' ? 'selected' : '' }}>Complete</option>
                    <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>Partial</option>
                    <option value="missing" {{ $status === 'missing' ? 'selected' : '' }}>Missing</option>
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending Review</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 transition-colors">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Filter
                </button>
                @if ($search || $status)
                    <a href="{{ route('admin.extensions.seo.entities', ['type' => $type]) }}" class="text-sm text-gray-500 hover:text-gray-700 underline">Clear</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Entity Table --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200" x-data="{
        selected: [],
        expanded: null,
        optimizing: null,
        suggestion: null,
        selectAll: false,
        entityIds: {{ json_encode($entities->pluck('id')->toArray()) }},
        pendingSuggestions: {{ json_encode($pendingSuggestions->mapWithKeys(fn($s, $k) => [$k => ['id' => $s->id, 'suggested_meta_title' => $s->suggested_meta_title, 'suggested_meta_description' => $s->suggested_meta_description, 'suggested_og_title' => $s->suggested_og_title, 'suggested_og_description' => $s->suggested_og_description, 'current_meta_title' => $s->current_meta_title, 'current_meta_description' => $s->current_meta_description, 'current_og_title' => $s->current_og_title, 'current_og_description' => $s->current_og_description]])) }},
        toggleAll() {
            if (this.selectAll) {
                this.selected = [...this.entityIds];
            } else {
                this.selected = [];
            }
        },
        async optimize(entityType, entityId) {
            this.optimizing = entityId;
            try {
                const res = await fetch('{{ route('admin.extensions.seo.optimize') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ entity_type: entityType, entity_id: entityId })
                });
                const data = await res.json();
                this.optimizing = null;
                if (data.ok) {
                    this.suggestion = data.suggestion;
                    this.pendingSuggestions[entityId] = data.suggestion;
                    this.expanded = entityId;
                } else {
                    alert(data.error || 'Failed to generate suggestion.');
                }
            } catch (e) {
                this.optimizing = null;
                alert('Network error. Please try again.');
            }
        },
        async acceptSuggestion(suggestionId, entityId) {
            try {
                const res = await fetch('/opensesame/extensions/seo/suggestions/' + suggestionId + '/accept', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.ok) {
                    delete this.pendingSuggestions[entityId];
                    this.expanded = null;
                    this.suggestion = null;
                    // Update the row status badge
                    const badge = document.getElementById('status-badge-' + entityId);
                    if (badge) {
                        badge.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800';
                        badge.textContent = 'Complete';
                    }
                }
            } catch (e) {
                alert('Network error. Please try again.');
            }
        },
        async rejectSuggestion(suggestionId, entityId) {
            try {
                const res = await fetch('/opensesame/extensions/seo/suggestions/' + suggestionId + '/reject', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.ok) {
                    delete this.pendingSuggestions[entityId];
                    this.expanded = null;
                    this.suggestion = null;
                }
            } catch (e) {
                alert('Network error. Please try again.');
            }
        }
    }">

        {{-- Mass Action Bar --}}
        <div x-show="selected.length > 0" x-cloak class="flex items-center justify-between px-5 py-3 bg-indigo-50 border-b border-indigo-200">
            <span class="text-sm font-medium text-indigo-800" x-text="selected.length + ' item(s) selected'"></span>
            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('admin.extensions.seo.generate') }}" @submit.prevent="
                    const form = $el;
                    selected.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'entity_ids[]';
                        input.value = id;
                        form.appendChild(input);
                    });
                    form.submit();
                ">
                    @csrf
                    <input type="hidden" name="entity_type" value="{{ $entityType }}">
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 transition-colors">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Generate SEO for Selected
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.extensions.seo.bulk-accept') }}" @submit.prevent="
                    const form = $el;
                    selected.forEach(id => {
                        if (pendingSuggestions[id]) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'ids[]';
                            input.value = pendingSuggestions[id].id;
                            form.appendChild(input);
                        }
                    });
                    form.submit();
                ">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded-md hover:bg-green-700 transition-colors">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Accept All Pending
                    </button>
                </form>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 w-10">
                            <input type="checkbox" x-model="selectAll" @change="toggleAll()" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </th>
                        <th class="px-4 py-3 w-14 font-medium">Image</th>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium w-28">SEO Status</th>
                        <th class="px-4 py-3 font-medium">Meta Title</th>
                        <th class="px-4 py-3 font-medium w-28 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($entities as $entity)
                        @php
                            $entityName = $entityType === 'blog_post' || $entityType === 'page' ? $entity->title : $entity->name;
                            $entityImage = $entityType === 'blog_post' ? $entity->featured_image : $entity->image;
                            $hasPending = $pendingSuggestions->has($entity->id);
                            $hasMeta = !empty($entity->meta_title) && !empty($entity->meta_description);
                            $hasPartial = !empty($entity->meta_title) || !empty($entity->meta_description);
                            if ($hasPending) {
                                $badgeClass = 'bg-amber-100 text-amber-800';
                                $badgeLabel = 'Pending Review';
                            } elseif ($hasMeta) {
                                $badgeClass = 'bg-green-100 text-green-800';
                                $badgeLabel = 'Complete';
                            } elseif ($hasPartial) {
                                $badgeClass = 'bg-yellow-100 text-yellow-800';
                                $badgeLabel = 'Partial';
                            } else {
                                $badgeClass = 'bg-red-100 text-red-800';
                                $badgeLabel = 'Missing';
                            }
                        @endphp
                        {{-- Main Row --}}
                        <tr class="hover:bg-gray-50 cursor-pointer" @click="expanded = expanded === {{ $entity->id }} ? null : {{ $entity->id }}; suggestion = pendingSuggestions[{{ $entity->id }}] || null;">
                            <td class="px-4 py-3" @click.stop>
                                <input type="checkbox" value="{{ $entity->id }}" x-model.number="selected" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            </td>
                            <td class="px-4 py-3">
                                @if ($entityImage)
                                    <img src="{{ storage_url($entityImage) }}" alt="" class="w-10 h-10 object-cover rounded border border-gray-200">
                                @else
                                    <div class="w-10 h-10 rounded border border-gray-200 bg-gray-50 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.41a2.25 2.25 0 013.182 0l2.909 2.91M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z"/></svg>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route($editRoute, $entity) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800" @click.stop>{{ Str::limit($entityName, 50) }}</a>
                            </td>
                            <td class="px-4 py-3">
                                <span id="status-badge-{{ $entity->id }}" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">{{ $badgeLabel }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-xs">
                                {{ $entity->meta_title ? Str::limit($entity->meta_title, 40) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right" @click.stop>
                                <button @click="optimize('{{ $entityType }}', {{ $entity->id }})"
                                    :disabled="optimizing === {{ $entity->id }}"
                                    class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-wait transition-colors">
                                    <template x-if="optimizing === {{ $entity->id }}">
                                        <svg class="animate-spin w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    </template>
                                    <template x-if="optimizing !== {{ $entity->id }}">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    </template>
                                    <span x-text="optimizing === {{ $entity->id }} ? 'Optimizing...' : 'Optimize'"></span>
                                </button>
                            </td>
                        </tr>

                        {{-- Expandable Suggestion Row --}}
                        <tr x-show="expanded === {{ $entity->id }}" x-cloak>
                            <td colspan="6" class="px-0 py-0">
                                <div class="bg-gray-50 border-t border-b border-gray-200 px-6 py-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide" x-text="suggestion ? 'SEO Suggestion Preview' : 'Current SEO Meta'"></h4>
                                        <div class="flex items-center gap-2">
                                            <template x-if="suggestion">
                                                <div class="flex items-center gap-2">
                                                    <button @click="acceptSuggestion(suggestion.id, {{ $entity->id }})" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded-md hover:bg-green-700 transition-colors">
                                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                        Accept
                                                    </button>
                                                    <button @click="rejectSuggestion(suggestion.id, {{ $entity->id }})" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded-md hover:bg-red-700 transition-colors">
                                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        Reject
                                                    </button>
                                                </div>
                                            </template>
                                            <button @click="expanded = null" class="inline-flex items-center px-2 py-1.5 text-xs text-gray-500 hover:text-gray-700">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </div>

                                    {{-- With suggestion: side-by-side --}}
                                    <template x-if="suggestion">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            @foreach (['meta_title' => 'Meta Title', 'meta_description' => 'Meta Description', 'og_title' => 'OG Title', 'og_description' => 'OG Description'] as $field => $label)
                                                <div>
                                                    <p class="text-xs font-medium text-gray-500 mb-1">Current {{ $label }}</p>
                                                    <p class="text-sm text-gray-700 bg-white border border-gray-200 rounded px-3 py-2 min-h-[2.5rem]" x-text="suggestion.current_{{ $field }} || '(empty)'"></p>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-medium text-green-600 mb-1">Suggested {{ $label }}</p>
                                                    <p class="text-sm text-gray-700 bg-green-50 border border-green-200 rounded px-3 py-2 min-h-[2.5rem]" x-text="suggestion.suggested_{{ $field }} || '(no change)'"></p>
                                                </div>
                                            @endforeach
                                        </div>
                                    </template>

                                    {{-- Without suggestion: current values only --}}
                                    <template x-if="!suggestion">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <p class="text-xs font-medium text-gray-500 mb-1">Meta Title</p>
                                                <p class="text-sm text-gray-700 bg-white border border-gray-200 rounded px-3 py-2 min-h-[2.5rem]">{{ $entity->meta_title ?: '(empty)' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-gray-500 mb-1">Meta Description</p>
                                                <p class="text-sm text-gray-700 bg-white border border-gray-200 rounded px-3 py-2 min-h-[2.5rem]">{{ $entity->meta_description ?: '(empty)' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-gray-500 mb-1">OG Title</p>
                                                <p class="text-sm text-gray-700 bg-white border border-gray-200 rounded px-3 py-2 min-h-[2.5rem]">{{ $entity->og_title ?: '(empty)' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-gray-500 mb-1">OG Description</p>
                                                <p class="text-sm text-gray-700 bg-white border border-gray-200 rounded px-3 py-2 min-h-[2.5rem]">{{ $entity->og_description ?: '(empty)' }}</p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center">
                                <svg class="mx-auto h-10 w-10 text-gray-300 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                                <p class="text-sm font-medium text-gray-500">No {{ $type }} found</p>
                                <p class="text-xs text-gray-400 mt-1">
                                    @if ($search || $status)
                                        Try adjusting your filters or <a href="{{ route('admin.extensions.seo.entities', ['type' => $type]) }}" class="text-indigo-600 hover:underline">clear all filters</a>.
                                    @else
                                        There are no active {{ $type }} to optimize.
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($entities->hasPages())
            <div class="px-5 py-3 border-t border-gray-200">
                {{ $entities->withQueryString()->links() }}
            </div>
        @endif
    </div>

    {{-- Flash Messages --}}
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="fixed bottom-4 right-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg shadow-lg text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" class="fixed bottom-4 right-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg shadow-lg text-sm">
            {{ session('error') }}
        </div>
    @endif
</x-admin-layout>
