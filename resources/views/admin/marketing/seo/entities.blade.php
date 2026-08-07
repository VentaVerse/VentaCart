@php
    $entityTypeLabels2 = ['products' => 'Products', 'categories' => 'Categories', 'brands' => 'Brands', 'blog' => 'Blog', 'pages' => 'Pages'];
@endphp
<x-admin.shell title="SEO {{ $entityTypeLabels2[$type] ?? ucfirst($type) }}">
    <x-admin.page :title="$entityTypeLabels2[$type] ?? ucfirst($type)"
        :breadcrumb="[['label' => 'SEO', 'url' => route('admin.extensions.seo.dashboard')], ['label' => $entityTypeLabels2[$type] ?? ucfirst($type)]]">
        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            @include('admin.marketing.seo.partials.nav-v2')

            <div class="min-w-0 flex-1 space-y-5">
                @if ($type === 'pages')
                    {{-- Homepage SEO --}}
                    <x-admin.card x-data="{
                        loading: false,
                        suggestion: null,
                        error: null,
                        async optimize() {
                            this.loading = true; this.error = null; this.suggestion = null;
                            try {
                                const res = await fetch('{{ route('admin.extensions.seo.optimize') }}', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                    body: JSON.stringify({ entity_type: 'homepage', entity_id: 0 })
                                });
                                const data = await res.json();
                                if (data.ok) { this.suggestion = data.suggestion; } else { this.error = data.message || data.error || 'Failed.'; }
                            } catch (e) { this.error = 'Request failed.'; }
                            this.loading = false;
                        },
                        async apply() {
                            if (!this.suggestion) return;
                            const res = await fetch('{{ route('admin.extensions.seo.save-settings') }}', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                body: JSON.stringify({
                                    default_meta_title: this.suggestion.suggested_meta_title,
                                    default_meta_description: this.suggestion.suggested_meta_description,
                                    _homepage_apply: true
                                })
                            });
                            this.suggestion = null;
                            window.location.reload();
                        }
                    }">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h3 class="text-[13px] font-semibold text-v-text">Homepage SEO</h3>
                                <p class="mt-0.5 text-xs text-v-muted">
                                    Meta Title: <span class="text-v-secondary">{{ $homepageMeta['default_meta_title'] ?? '(not set)' }}</span>
                                    &middot;
                                    Meta Description: <span class="text-v-secondary">{{ Str::limit($homepageMeta['default_meta_description'] ?? '(not set)', 60) }}</span>
                                </p>
                            </div>
                            <x-admin.button type="button" variant="primary" size="md" @click="optimize()" x-bind:disabled="loading">
                                <span x-show="!loading">Optimize with AI</span>
                                <span x-show="loading" x-cloak>Optimizing...</span>
                            </x-admin.button>
                        </div>
                        <div x-show="error" x-cloak class="mt-3 rounded-v-md border border-v-border bg-[color-mix(in_srgb,var(--danger)_10%,transparent)] px-3 py-2.5 text-[13px] text-v-danger" x-text="error"></div>
                        <div x-show="suggestion" x-cloak class="mt-4 rounded-v-lg border border-v-border bg-v-accent-weak p-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="mb-1 text-xs font-medium text-v-muted">Current Meta Title</p>
                                    <p class="rounded-v-md border border-v-border bg-v-surface p-2 text-[13px] text-v-text" x-text="suggestion?.current_meta_title || '(empty)'"></p>
                                </div>
                                <div>
                                    <p class="mb-1 text-xs font-medium text-v-accent">Suggested Meta Title</p>
                                    <p class="rounded-v-md border-l-2 border-v-accent bg-v-surface p-2 text-[13px] text-v-text" x-text="suggestion?.suggested_meta_title || '(no change)'"></p>
                                </div>
                                <div>
                                    <p class="mb-1 text-xs font-medium text-v-muted">Current Meta Description</p>
                                    <p class="rounded-v-md border border-v-border bg-v-surface p-2 text-[13px] text-v-text" x-text="suggestion?.current_meta_description || '(empty)'"></p>
                                </div>
                                <div>
                                    <p class="mb-1 text-xs font-medium text-v-accent">Suggested Meta Description</p>
                                    <p class="rounded-v-md border-l-2 border-v-accent bg-v-surface p-2 text-[13px] text-v-text" x-text="suggestion?.suggested_meta_description || '(no change)'"></p>
                                </div>
                            </div>
                            <div class="mt-3 flex gap-2">
                                <x-admin.button type="button" variant="primary" size="sm" @click="apply()">Accept & Apply</x-admin.button>
                                <x-admin.button type="button" variant="ghost" size="sm" @click="suggestion = null">Dismiss</x-admin.button>
                            </div>
                        </div>
                    </x-admin.card>
                @endif

                {{-- Filters Bar --}}
                <x-admin.card>
                    <form method="GET" action="{{ route('admin.extensions.seo.entities', ['type' => $type]) }}" class="flex flex-wrap items-end gap-3">
                        <div class="min-w-[180px] flex-1">
                            <x-admin.field name="search" label="Search">
                                <x-admin.input type="text" name="search" :value="$search" placeholder="Search by name..." />
                            </x-admin.field>
                        </div>
                        <div class="w-44">
                            <x-admin.field name="seo_status" label="SEO Status">
                                <x-admin.select name="seo_status">
                                    <option value="">All</option>
                                    <option value="complete" {{ $status === 'complete' ? 'selected' : '' }}>Complete</option>
                                    <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>Partial</option>
                                    <option value="missing" {{ $status === 'missing' ? 'selected' : '' }}>Missing</option>
                                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending Review</option>
                                </x-admin.select>
                            </x-admin.field>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-admin.button type="submit" variant="primary" size="md">Filter</x-admin.button>
                            @if ($search || $status)
                                <a href="{{ route('admin.extensions.seo.entities', ['type' => $type]) }}" class="text-[13px] text-v-muted underline hover:text-v-text">Clear</a>
                            @endif
                        </div>
                    </form>
                </x-admin.card>

                {{-- Entity Table --}}
                <div x-data="{
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
                                const badge = document.getElementById('status-badge-' + entityId);
                                if (badge) {
                                    badge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-[color-mix(in_srgb,var(--warning)_12%,transparent)] text-v-warning';
                                    badge.textContent = 'Pending Review';
                                }
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
                            const url = '{{ route('admin.extensions.seo.accept', ['suggestion' => 'SUGGESTION_ID']) }}'.replace('SUGGESTION_ID', suggestionId);
                            const res = await fetch(url, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                            });
                            const data = await res.json();
                            if (data.ok) {
                                const accepted = this.pendingSuggestions[entityId];
                                delete this.pendingSuggestions[entityId];
                                this.expanded = null;
                                this.suggestion = null;
                                const badge = document.getElementById('status-badge-' + entityId);
                                if (badge) {
                                    badge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-v-accent-weak text-v-accent';
                                    badge.textContent = 'Complete';
                                }
                                const metaCell = document.getElementById('meta-title-' + entityId);
                                if (metaCell && accepted?.suggested_meta_title) {
                                    metaCell.textContent = accepted.suggested_meta_title.length > 40
                                        ? accepted.suggested_meta_title.substring(0, 40) + '...'
                                        : accepted.suggested_meta_title;
                                }
                            }
                        } catch (e) {
                            alert('Network error. Please try again.');
                        }
                    },
                    async rejectSuggestion(suggestionId, entityId) {
                        try {
                            const url = '{{ route('admin.extensions.seo.reject', ['suggestion' => 'SUGGESTION_ID']) }}'.replace('SUGGESTION_ID', suggestionId);
                            const res = await fetch(url, {
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
                }" class="bg-v-surface border border-v-border rounded-v-lg">

                    {{-- Mass Action Bar --}}
                    <div x-show="selected.length > 0" x-cloak class="flex items-center justify-between border-b border-v-border bg-v-accent-weak px-5 py-3">
                        <span class="text-[13px] font-medium text-v-accent" x-text="selected.length + ' item(s) selected'"></span>
                        <div class="flex items-center gap-3">
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
                                <x-admin.button type="submit" variant="primary" size="sm">Accept All Pending</x-admin.button>
                            </form>
                        </div>
                    </div>

                    {{-- Table --}}
                    <div class="overflow-x-auto">
                        <table class="v-table w-full text-left text-[13px]">
                            <thead class="bg-v-sunken">
                                <tr>
                                    <th class="w-10 px-4 h-[var(--row-h)]">
                                        <input type="checkbox" x-model="selectAll" @change="toggleAll()" class="rounded-v-sm border-v-strong bg-v-surface text-v-accent focus:ring-v-accent">
                                    </th>
                                    <th class="w-14 px-4 h-[var(--row-h)] text-[10px] font-semibold uppercase tracking-widest text-v-muted">Image</th>
                                    <th class="px-4 h-[var(--row-h)] text-[10px] font-semibold uppercase tracking-widest text-v-muted">Name</th>
                                    <th class="w-28 px-4 h-[var(--row-h)] text-[10px] font-semibold uppercase tracking-widest text-v-muted">SEO Status</th>
                                    <th class="px-4 h-[var(--row-h)] text-[10px] font-semibold uppercase tracking-widest text-v-muted">Meta Title</th>
                                    <th class="w-28 px-4 h-[var(--row-h)] text-right text-[10px] font-semibold uppercase tracking-widest text-v-muted">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-v-border">
                                @forelse ($entities as $entity)
                                    @php
                                        $entityName = $entityType === 'blog_post' || $entityType === 'page' ? $entity->title : $entity->name;
                                        $entityImage = $entityType === 'blog_post' ? $entity->featured_image : $entity->image;
                                        $hasPending = $pendingSuggestions->has($entity->id);
                                        $hasMeta = !empty($entity->meta_title) && !empty($entity->meta_description);
                                        $hasPartial = !empty($entity->meta_title) || !empty($entity->meta_description);
                                        if ($hasPending) {
                                            $badgeTone = 'warning';
                                            $badgeLabel = 'Pending Review';
                                        } elseif ($hasMeta) {
                                            $badgeTone = 'success';
                                            $badgeLabel = 'Complete';
                                        } elseif ($hasPartial) {
                                            $badgeTone = 'warning';
                                            $badgeLabel = 'Partial';
                                        } else {
                                            $badgeTone = 'danger';
                                            $badgeLabel = 'Missing';
                                        }
                                    @endphp
                                    {{-- Main Row --}}
                                    <tr class="v-row cursor-pointer hover:bg-v-hover" @click="expanded = expanded === {{ $entity->id }} ? null : {{ $entity->id }}; suggestion = pendingSuggestions[{{ $entity->id }}] || null;">
                                        <td class="px-4 py-2 align-middle" @click.stop>
                                            <input type="checkbox" value="{{ $entity->id }}" x-model.number="selected" class="rounded-v-sm border-v-strong bg-v-surface text-v-accent focus:ring-v-accent">
                                        </td>
                                        <td class="px-4 py-2 align-middle">
                                            @if ($entityImage)
                                                <img src="{{ storage_url($entityImage) }}" alt="" class="h-10 w-10 rounded-v-md border border-v-border object-cover">
                                            @else
                                                <div class="flex h-10 w-10 items-center justify-center rounded-v-md border border-v-border bg-v-sunken">
                                                    <x-admin.icon name="media" class="h-5 w-5 text-v-muted" />
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 align-middle">
                                            <a href="{{ route($editRoute, $entity) }}" class="text-[13px] font-medium text-v-accent hover:opacity-80" @click.stop>{{ Str::limit($entityName, 50) }}</a>
                                        </td>
                                        <td class="px-4 py-2 align-middle">
                                            <x-admin.badge id="status-badge-{{ $entity->id }}" :tone="$badgeTone">{{ $badgeLabel }}</x-admin.badge>
                                        </td>
                                        <td class="px-4 py-2 align-middle text-xs text-v-secondary" id="meta-title-{{ $entity->id }}">
                                            {{ $entity->meta_title ? Str::limit($entity->meta_title, 40) : '—' }}
                                        </td>
                                        <td class="px-4 py-2 align-middle text-right" @click.stop>
                                            <x-admin.button type="button" variant="secondary" size="sm"
                                                @click="optimize('{{ $entityType }}', {{ $entity->id }})"
                                                x-bind:disabled="optimizing === {{ $entity->id }}">
                                                <span x-text="optimizing === {{ $entity->id }} ? 'Optimizing...' : 'Optimize'"></span>
                                            </x-admin.button>
                                        </td>
                                    </tr>

                                    {{-- Expandable Suggestion Row --}}
                                    <tr x-show="expanded === {{ $entity->id }}" x-cloak>
                                        <td colspan="6" class="p-0">
                                            <div class="border-t border-b border-v-border bg-v-sunken px-6 py-4">
                                                <div class="mb-3 flex items-center justify-between">
                                                    <h4 class="text-xs font-semibold uppercase tracking-wide text-v-secondary" x-text="suggestion ? 'SEO Suggestion Preview' : 'Current SEO Meta'"></h4>
                                                    <div class="flex items-center gap-2">
                                                        <template x-if="suggestion">
                                                            <div class="flex items-center gap-2">
                                                                <x-admin.button type="button" variant="primary" size="sm" @click="acceptSuggestion(suggestion.id, {{ $entity->id }})">Accept</x-admin.button>
                                                                <x-admin.button type="button" variant="danger" size="sm" @click="rejectSuggestion(suggestion.id, {{ $entity->id }})">Reject</x-admin.button>
                                                            </div>
                                                        </template>
                                                        <button @click="expanded = null" class="inline-flex items-center px-2 py-1.5 text-v-muted hover:text-v-text">
                                                            <x-admin.icon name="x" class="h-3.5 w-3.5" />
                                                        </button>
                                                    </div>
                                                </div>

                                                {{-- With suggestion: side-by-side --}}
                                                <template x-if="suggestion">
                                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                        @foreach (['meta_title' => 'Meta Title', 'meta_description' => 'Meta Description', 'og_title' => 'OG Title', 'og_description' => 'OG Description'] as $field => $label)
                                                            <div>
                                                                <p class="mb-1 text-xs font-medium text-v-muted">Current {{ $label }}</p>
                                                                <p class="min-h-[2.5rem] rounded-v-md border border-v-border bg-v-surface px-3 py-2 text-[13px] text-v-text" x-text="suggestion.current_{{ $field }} || '(empty)'"></p>
                                                            </div>
                                                            <div>
                                                                <p class="mb-1 text-xs font-medium text-v-accent">Suggested {{ $label }}</p>
                                                                <p class="min-h-[2.5rem] rounded-v-md border border-v-accent bg-v-accent-weak px-3 py-2 text-[13px] text-v-text" x-text="suggestion.suggested_{{ $field }} || '(no change)'"></p>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </template>

                                                {{-- Without suggestion: current values only --}}
                                                <template x-if="!suggestion">
                                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                        <div>
                                                            <p class="mb-1 text-xs font-medium text-v-muted">Meta Title</p>
                                                            <p class="min-h-[2.5rem] rounded-v-md border border-v-border bg-v-surface px-3 py-2 text-[13px] text-v-text">{{ $entity->meta_title ?: '(empty)' }}</p>
                                                        </div>
                                                        <div>
                                                            <p class="mb-1 text-xs font-medium text-v-muted">Meta Description</p>
                                                            <p class="min-h-[2.5rem] rounded-v-md border border-v-border bg-v-surface px-3 py-2 text-[13px] text-v-text">{{ $entity->meta_description ?: '(empty)' }}</p>
                                                        </div>
                                                        <div>
                                                            <p class="mb-1 text-xs font-medium text-v-muted">OG Title</p>
                                                            <p class="min-h-[2.5rem] rounded-v-md border border-v-border bg-v-surface px-3 py-2 text-[13px] text-v-text">{{ $entity->og_title ?: '(empty)' }}</p>
                                                        </div>
                                                        <div>
                                                            <p class="mb-1 text-xs font-medium text-v-muted">OG Description</p>
                                                            <p class="min-h-[2.5rem] rounded-v-md border border-v-border bg-v-surface px-3 py-2 text-[13px] text-v-text">{{ $entity->og_description ?: '(empty)' }}</p>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-12 text-center">
                                            <x-admin.empty-state icon="search" title="No {{ $type }} found"
                                                :description="($search || $status) ? 'Try adjusting your filters.' : 'There are no active ' . $type . ' to optimize.'">
                                                @if ($search || $status)
                                                    <a href="{{ route('admin.extensions.seo.entities', ['type' => $type]) }}" class="text-[13px] text-v-accent hover:underline">Clear all filters</a>
                                                @endif
                                            </x-admin.empty-state>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination --}}
                    @if ($entities->hasPages())
                        <div class="border-t border-v-border px-5 py-3">
                            {{ $entities->withQueryString()->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
