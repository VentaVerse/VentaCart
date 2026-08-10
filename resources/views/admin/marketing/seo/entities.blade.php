@php
    $entityTypeLabels2 = ['products' => 'Products', 'categories' => 'Categories', 'brands' => 'Brands', 'blog' => 'Blog', 'pages' => 'Pages'];
    $vHeading = $entityTypeLabels2[$type] ?? ucfirst($type);
@endphp
<x-admin.seo-shell :title="'SEO ' . $vHeading" :heading="$vHeading">
    <div class="space-y-5">
        @if ($type === 'pages')
            <x-admin.card title="Homepage" x-data="{
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
                <x-slot name="headerActions">
                    <x-admin.button type="button" variant="primary" size="sm" @click="optimize()" x-bind:disabled="loading">
                        <span x-show="!loading">Optimize with AI</span>
                        <span x-show="loading" x-cloak>Optimizing&hellip;</span>
                    </x-admin.button>
                </x-slot>

                <dl class="v-facts v-facts--flush">
                    <dt class="v-label">Meta title</dt>
                    <dd class="text-[13px] text-v-text">{{ $homepageMeta['default_meta_title'] ?? '(not set)' }}</dd>
                    <dt class="v-label">Meta description</dt>
                    <dd class="text-[13px] text-v-text">{{ Str::limit($homepageMeta['default_meta_description'] ?? '(not set)', 60) }}</dd>
                </dl>

                <p x-show="error" x-cloak class="v-field__error mt-3" x-text="error"></p>

                <div x-show="suggestion" x-cloak class="mt-4 rounded-v-lg bg-v-accent-weak p-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <p class="v-label mb-1">Current meta title</p>
                            <p class="v-sheet p-2 text-[13px] text-v-text" x-text="suggestion?.current_meta_title || '(empty)'"></p>
                        </div>
                        <div>
                            <p class="v-label mb-1 text-v-accent">Suggested meta title</p>
                            <p class="v-sheet p-2 text-[13px] text-v-text" x-text="suggestion?.suggested_meta_title || '(no change)'"></p>
                        </div>
                        <div>
                            <p class="v-label mb-1">Current meta description</p>
                            <p class="v-sheet p-2 text-[13px] text-v-text" x-text="suggestion?.current_meta_description || '(empty)'"></p>
                        </div>
                        <div>
                            <p class="v-label mb-1 text-v-accent">Suggested meta description</p>
                            <p class="v-sheet p-2 text-[13px] text-v-text" x-text="suggestion?.suggested_meta_description || '(no change)'"></p>
                        </div>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <x-admin.button type="button" variant="primary" size="sm" @click="apply()">Accept and apply</x-admin.button>
                        <x-admin.button type="button" variant="ghost" size="sm" @click="suggestion = null">Dismiss</x-admin.button>
                    </div>
                </div>
            </x-admin.card>
        @endif

        <x-admin.filters :action="route('admin.extensions.seo.entities', ['type' => $type])">
            <x-admin.field name="search" label="Search" class="v-field--grow">
                <x-admin.input type="search" name="search" :value="$search" placeholder="Search by name&hellip;" />
            </x-admin.field>
            <x-admin.field name="seo_status" label="SEO status" class="w-44">
                <x-admin.select name="seo_status">
                    <option value="">All</option>
                    <option value="complete" {{ $status === 'complete' ? 'selected' : '' }}>Complete</option>
                    <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>Partial</option>
                    <option value="missing" {{ $status === 'missing' ? 'selected' : '' }}>Missing</option>
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending review</option>
                </x-admin.select>
            </x-admin.field>
            <x-admin.button type="submit" variant="secondary">Filter</x-admin.button>
            @if ($search || $status)
                <a href="{{ route('admin.extensions.seo.entities', ['type' => $type]) }}" class="v-filters__reset">Clear</a>
            @endif
        </x-admin.filters>

        <x-admin.panel flush x-data="{
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
                            badge.className = 'v-badge v-badge--warning';
                            badge.textContent = 'Pending review';
                        }
                    } else {
                        window.Alpine.store('toasts').push(data.error || 'Could not generate a suggestion.', 'danger');
                    }
                } catch (e) {
                    this.optimizing = null;
                    window.Alpine.store('toasts').push('Network error. Nothing was changed, try again.', 'danger');
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
                            badge.className = 'v-badge v-badge--success';
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
                    window.Alpine.store('toasts').push('Network error. Nothing was changed, try again.', 'danger');
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
                    window.Alpine.store('toasts').push('Network error. Nothing was changed, try again.', 'danger');
                }
            }
        }">
            {{-- The bar a selection puts above the list. --}}
            <div x-show="selected.length > 0" x-cloak class="v-panel__body pb-0">
                <div class="v-flash">
                <span class="v-flash__text" x-text="selected.length + ' selected'"></span>
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
                    <x-admin.button type="submit" variant="primary" size="sm">Accept all pending</x-admin.button>
                </form>
                </div>
            </div>

            <x-admin.table bare roomy>
                <x-slot:head>
                    <x-admin.table.th class="w-10">
                        <input type="checkbox" x-model="selectAll" @change="toggleAll()" aria-label="Select every row"
                            class="rounded-v-sm border bg-v-surface text-v-accent focus:ring-v-accent">
                    </x-admin.table.th>
                    <x-admin.table.th class="w-14">Image</x-admin.table.th>
                    <x-admin.table.th>Name</x-admin.table.th>
                    <x-admin.table.th class="w-28">SEO status</x-admin.table.th>
                    <x-admin.table.th>Meta title</x-admin.table.th>
                    <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                </x-slot:head>
                @forelse ($entities as $entity)
                    @php
                        $entityName = $entityType === 'blog_post' || $entityType === 'page' ? $entity->title : $entity->name;
                        $entityImage = $entityType === 'blog_post' ? $entity->featured_image : $entity->image;
                        $hasPending = $pendingSuggestions->has($entity->id);
                        $hasMeta = !empty($entity->meta_title) && !empty($entity->meta_description);
                        $hasPartial = !empty($entity->meta_title) || !empty($entity->meta_description);
                        if ($hasPending) {
                            $badgeTone = 'warning';
                            $badgeLabel = 'Pending review';
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
                    <x-admin.table.row class="cursor-pointer" @click="expanded = expanded === {{ $entity->id }} ? null : {{ $entity->id }}; suggestion = pendingSuggestions[{{ $entity->id }}] || null;">
                        <x-admin.table.cell @click.stop>
                            <input type="checkbox" value="{{ $entity->id }}" x-model.number="selected"
                                aria-label="Select {{ Str::limit($entityName, 50) }}"
                                class="rounded-v-sm border bg-v-surface text-v-accent focus:ring-v-accent">
                        </x-admin.table.cell>
                        <x-admin.table.cell>
                            @if ($entityImage)
                                <img src="{{ storage_url($entityImage) }}" alt="" class="h-10 w-10 rounded-v-md border border-v-border object-cover">
                            @else
                                <span class="flex h-10 w-10 items-center justify-center rounded-v-md border border-v-border bg-v-sunken">
                                    <x-admin.icon name="media" class="h-5 w-5 text-v-muted" />
                                </span>
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell>
                            <a href="{{ route($editRoute, $entity) }}" class="v-quietlink" @click.stop>{{ Str::limit($entityName, 50) }}</a>
                        </x-admin.table.cell>
                        <x-admin.table.cell>
                            <x-admin.badge id="status-badge-{{ $entity->id }}" :tone="$badgeTone">{{ $badgeLabel }}</x-admin.badge>
                        </x-admin.table.cell>
                        <x-admin.table.cell class="text-xs text-v-secondary" id="meta-title-{{ $entity->id }}">
                            {{ $entity->meta_title ? Str::limit($entity->meta_title, 40) : '-' }}
                        </x-admin.table.cell>
                        <x-admin.table.cell class="v-col-act" @click.stop>
                            <div class="v-rowactions v-rowactions--end">
                                <x-admin.button type="button" variant="secondary" size="sm"
                                    @click="optimize('{{ $entityType }}', {{ $entity->id }})"
                                    x-bind:disabled="optimizing === {{ $entity->id }}">
                                    <span x-text="optimizing === {{ $entity->id }} ? 'Optimizing…' : 'Optimize'"></span>
                                </x-admin.button>
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>

                    {{-- What the row opens onto: the record's meta as it
                         stands, or the suggestion beside it. --}}
                    <tr x-show="expanded === {{ $entity->id }}" x-cloak>
                        <td colspan="6" class="border-y border-v-border bg-v-sunken px-6 py-4">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="v-subhead" x-text="suggestion ? 'Suggested' : 'Current'"></h3>
                                <div class="flex items-center gap-2">
                                    <template x-if="suggestion">
                                        <span class="flex items-center gap-2">
                                            <x-admin.button type="button" variant="primary" size="sm" @click="acceptSuggestion(suggestion.id, {{ $entity->id }})">Accept</x-admin.button>
                                            <x-admin.button type="button" variant="danger" size="sm" @click="rejectSuggestion(suggestion.id, {{ $entity->id }})">Reject</x-admin.button>
                                        </span>
                                    </template>
                                    <x-admin.button type="button" variant="ghost" size="sm" @click="expanded = null" aria-label="Close">
                                        <x-admin.icon name="x" class="h-3.5 w-3.5" />
                                    </x-admin.button>
                                </div>
                            </div>

                            <template x-if="suggestion">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    @foreach (['meta_title' => 'Meta title', 'meta_description' => 'Meta description', 'og_title' => 'OG title', 'og_description' => 'OG description'] as $field => $label)
                                        <div>
                                            <p class="v-label mb-1">Current {{ strtolower($label) }}</p>
                                            <p class="v-sheet min-h-[2.5rem] px-3 py-2 text-[13px] text-v-text" x-text="suggestion.current_{{ $field }} || '(empty)'"></p>
                                        </div>
                                        <div>
                                            <p class="v-label mb-1 text-v-accent">Suggested {{ strtolower($label) }}</p>
                                            <p class="min-h-[2.5rem] rounded-v-lg border border-v-accent bg-v-accent-weak px-3 py-2 text-[13px] text-v-text" x-text="suggestion.suggested_{{ $field }} || '(no change)'"></p>
                                        </div>
                                    @endforeach
                                </div>
                            </template>

                            <template x-if="!suggestion">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    @foreach (['meta_title' => 'Meta title', 'meta_description' => 'Meta description', 'og_title' => 'OG title', 'og_description' => 'OG description'] as $field => $label)
                                        <div>
                                            <p class="v-label mb-1">{{ $label }}</p>
                                            <p class="v-sheet min-h-[2.5rem] px-3 py-2 text-[13px] text-v-text">{{ $entity->getAttribute($field) ?: '(empty)' }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </template>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-admin.empty-state title="No {{ $type }} found"
                                :description="($search || $status) ? 'Try adjusting your filters.' : 'There are no active ' . $type . ' to optimize.'">
                                @if ($search || $status)
                                    <x-admin.button variant="secondary" :href="route('admin.extensions.seo.entities', ['type' => $type])">Clear all filters</x-admin.button>
                                @endif
                            </x-admin.empty-state>
                        </td>
                    </tr>
                @endforelse
                @if ($entities->hasPages())
                    <x-slot:footer>{{ $entities->withQueryString()->links() }}</x-slot:footer>
                @endif
            </x-admin.table>
        </x-admin.panel>
    </div>
</x-admin.seo-shell>
