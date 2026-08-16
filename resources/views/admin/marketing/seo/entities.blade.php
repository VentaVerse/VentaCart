@php
    $entityTypeLabels2 = ['products' => 'Products', 'categories' => 'Categories', 'brands' => 'Brands', 'blog' => 'Blog', 'pages' => 'Pages'];
    $vHeading = $entityTypeLabels2[$type] ?? ucfirst($type);
@endphp
<x-admin3.seo-shell :title="$vHeading" :active="$type">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">{{ $vHeading }}</h1>
        </div>
    </div>

    <div class="v3-settle">
        @if ($type === 'pages')
            <x-admin3.panel title="Homepage" x-data="{
                loading: false,
                suggestion: null,
                error: null,
                async optimize() {
                    this.loading = true; this.error = null; this.suggestion = null;
                    try {
                        const res = await fetch('{{ route('admin.extensions.seo.optimize') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                            body: JSON.stringify({ entity_type: 'homepage', entity_id: 0 })
                        });
                        const data = await res.json();
                        if (data.ok) { this.suggestion = data.suggestion; } else { this.error = data.message || data.error || 'Failed.'; }
                    } catch (e) { this.error = 'Request failed.'; }
                    this.loading = false;
                },
                async apply() {
                    if (!this.suggestion) return;
                    await fetch('{{ route('admin.extensions.seo.save-settings') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
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
                <x-slot:acts>
                    <button type="button" class="v3-btn v3-btn--primary v3-btn--sm" @click="optimize()" :disabled="loading"
                            x-text="loading ? 'Optimizing…' : 'Optimize with AI'">Optimize with AI</button>
                </x-slot:acts>

                <dl class="v3-facts">
                    <dt>Meta title</dt>
                    <dd>{{ $homepageMeta['default_meta_title'] ?? '(not set)' }}</dd>
                    <dt>Meta description</dt>
                    <dd>{{ Str::limit($homepageMeta['default_meta_description'] ?? '(not set)', 60) }}</dd>
                </dl>

                <p x-show="error" x-cloak class="v3-field__error" x-text="error"></p>

                <div x-show="suggestion" x-cloak class="v3-gap">
                    <div class="v3-diff">
                        <div class="v3-diff__cols">
                            <div>
                                <div class="v3-cell-quiet">Current meta title</div>
                                <div class="v3-diff__from" x-text="suggestion?.current_meta_title || '(empty)'"></div>
                            </div>
                            <div>
                                <div class="v3-cell-quiet">Suggested meta title</div>
                                <div class="v3-diff__to" x-text="suggestion?.suggested_meta_title || '(no change)'"></div>
                            </div>
                            <div>
                                <div class="v3-cell-quiet">Current meta description</div>
                                <div class="v3-diff__from" x-text="suggestion?.current_meta_description || '(empty)'"></div>
                            </div>
                            <div>
                                <div class="v3-cell-quiet">Suggested meta description</div>
                                <div class="v3-diff__to" x-text="suggestion?.suggested_meta_description || '(no change)'"></div>
                            </div>
                        </div>
                        <div class="v3-gap-sm v3-actrow">
                            <button type="button" class="v3-btn v3-btn--primary v3-btn--sm" @click="apply()">Accept and apply</button>
                            <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="suggestion = null">Dismiss</button>
                        </div>
                    </div>
                </div>
            </x-admin3.panel>
        @endif

        <x-admin3.panel flush x-data="{
            selected: [],
            expanded: null,
            optimizing: null,
            suggestion: null,
            selectAll: false,
            entityIds: {{ json_encode($entities->pluck('id')->toArray()) }},
            pendingSuggestions: {{ json_encode($pendingSuggestions->mapWithKeys(fn($s, $k) => [$k => ['id' => $s->id, 'suggested_meta_title' => $s->suggested_meta_title, 'suggested_meta_description' => $s->suggested_meta_description, 'suggested_og_title' => $s->suggested_og_title, 'suggested_og_description' => $s->suggested_og_description, 'current_meta_title' => $s->current_meta_title, 'current_meta_description' => $s->current_meta_description, 'current_og_title' => $s->current_og_title, 'current_og_description' => $s->current_og_description]])) }},
            toggleAll() {
                this.selected = this.selectAll ? [...this.entityIds] : [];
            },
            async optimize(entityType, entityId) {
                this.optimizing = entityId;
                try {
                    const res = await fetch('{{ route('admin.extensions.seo.optimize') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
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
                            badge.className = 'v3-badge v3-badge--warn';
                            badge.textContent = 'Pending review';
                        }
                    } else {
                        window.Alpine.store('toasts').push(data.error || 'Could not generate a suggestion.', 'bad');
                    }
                } catch (e) {
                    this.optimizing = null;
                    window.Alpine.store('toasts').push('Network error. Nothing was changed, try again.', 'bad');
                }
            },
            async acceptSuggestion(suggestionId, entityId) {
                try {
                    const url = '{{ route('admin.extensions.seo.accept', ['suggestion' => 'SUGGESTION_ID']) }}'.replace('SUGGESTION_ID', suggestionId);
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    if (data.ok) {
                        const accepted = this.pendingSuggestions[entityId];
                        delete this.pendingSuggestions[entityId];
                        this.expanded = null;
                        this.suggestion = null;
                        const badge = document.getElementById('status-badge-' + entityId);
                        if (badge) {
                            badge.className = 'v3-badge v3-badge--good';
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
                    window.Alpine.store('toasts').push('Network error. Nothing was changed, try again.', 'bad');
                }
            },
            async rejectSuggestion(suggestionId, entityId) {
                try {
                    const url = '{{ route('admin.extensions.seo.reject', ['suggestion' => 'SUGGESTION_ID']) }}'.replace('SUGGESTION_ID', suggestionId);
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    if (data.ok) {
                        delete this.pendingSuggestions[entityId];
                        this.expanded = null;
                        this.suggestion = null;
                    }
                } catch (e) {
                    window.Alpine.store('toasts').push('Network error. Nothing was changed, try again.', 'bad');
                }
            }
        }">
            <x-slot:acts>
                <form method="GET" action="{{ route('admin.extensions.seo.entities', ['type' => $type]) }}" class="v3-namerow">
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search by name…" aria-label="Search by name" class="v3-control v3-w-search">
                    <select name="seo_status" aria-label="SEO status" class="v3-control" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        <option value="complete" @selected($status === 'complete')>Complete</option>
                        <option value="partial" @selected($status === 'partial')>Partial</option>
                        <option value="missing" @selected($status === 'missing')>Missing</option>
                        <option value="pending" @selected($status === 'pending')>Pending review</option>
                    </select>
                    @if ($search || $status)
                        <a href="{{ route('admin.extensions.seo.entities', ['type' => $type]) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Clear</a>
                    @endif
                </form>
            </x-slot:acts>

            <div class="v3-bulkbar" x-show="selected.length > 0" x-cloak>
                <span><strong x-text="selected.length"></strong> selected</span>
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
                    <button type="submit" class="v3-btn v3-btn--primary v3-btn--sm">Accept all pending</button>
                </form>
            </div>

            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>
                                <label class="v3-check">
                                    <input type="checkbox" x-model="selectAll" @change="toggleAll()" aria-label="Select every row">
                                </label>
                            </th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>SEO status</th>
                            <th>Meta title</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entities as $entity)
                            @php
                                $entityName = $entityType === 'blog_post' || $entityType === 'page' ? $entity->title : $entity->name;
                                $entityImage = $entityType === 'blog_post' ? $entity->featured_image : $entity->image;
                                $hasPending = $pendingSuggestions->has($entity->id);
                                $hasMeta = !empty($entity->meta_title) && !empty($entity->meta_description);
                                $hasPartial = !empty($entity->meta_title) || !empty($entity->meta_description);
                                if ($hasPending) { $badgeClass = 'v3-badge--warn'; $badgeLabel = 'Pending review'; }
                                elseif ($hasMeta) { $badgeClass = 'v3-badge--good'; $badgeLabel = 'Complete'; }
                                elseif ($hasPartial) { $badgeClass = 'v3-badge--warn'; $badgeLabel = 'Partial'; }
                                else { $badgeClass = 'v3-badge--bad'; $badgeLabel = 'Missing'; }
                            @endphp
                            <tr class="v3-row-click" @click="expanded = expanded === {{ $entity->id }} ? null : {{ $entity->id }}; suggestion = pendingSuggestions[{{ $entity->id }}] || null;">
                                <td @click.stop>
                                    <label class="v3-check">
                                        <input type="checkbox" value="{{ $entity->id }}" x-model.number="selected"
                                               aria-label="Select {{ Str::limit($entityName, 50) }}">
                                    </label>
                                </td>
                                <td>
                                    @if ($entityImage)
                                        <img src="{{ storage_url($entityImage) }}" alt="" class="v3-thumb">
                                    @else
                                        <span class="v3-thumb v3-thumb--blank"><x-admin3.icon name="modules" /></span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route($editRoute, $entity) }}" @click.stop>{{ Str::limit($entityName, 50) }}</a>
                                </td>
                                <td>
                                    <span id="status-badge-{{ $entity->id }}" class="v3-badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                                </td>
                                <td class="v3-cell-quiet" id="meta-title-{{ $entity->id }}">
                                    {{ $entity->meta_title ? Str::limit($entity->meta_title, 40) : '-' }}
                                </td>
                                <td class="v3-cell-act" @click.stop>
                                    <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm"
                                            @click="optimize('{{ $entityType }}', {{ $entity->id }})"
                                            :disabled="optimizing === {{ $entity->id }}"
                                            x-text="optimizing === {{ $entity->id }} ? 'Optimizing…' : 'Optimize'">Optimize</button>
                                </td>
                            </tr>

                            <tr x-show="expanded === {{ $entity->id }}" x-cloak>
                                <td colspan="6" class="v3-cell-open">
                                    <div class="v3-namerow v3-gap-sm">
                                        <strong x-text="suggestion ? 'Suggested' : 'Current'"></strong>
                                        <span class="v3-actrow">
                                            <template x-if="suggestion">
                                                <span class="v3-actrow">
                                                    <button type="button" class="v3-btn v3-btn--primary v3-btn--sm" @click="acceptSuggestion(suggestion.id, {{ $entity->id }})">Accept</button>
                                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="rejectSuggestion(suggestion.id, {{ $entity->id }})">Reject</button>
                                                </span>
                                            </template>
                                            <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="expanded = null">Close</button>
                                        </span>
                                    </div>

                                    <template x-if="suggestion">
                                        <div>
                                            @foreach (['meta_title' => 'Meta title', 'meta_description' => 'Meta description', 'og_title' => 'OG title', 'og_description' => 'OG description'] as $field => $label)
                                                <div class="v3-diff">
                                                    <div class="v3-label">{{ $label }}</div>
                                                    <div class="v3-diff__cols">
                                                        <div>
                                                            <div class="v3-cell-quiet">Current</div>
                                                            <div class="v3-diff__from" x-text="suggestion.current_{{ $field }} || '(empty)'"></div>
                                                        </div>
                                                        <div>
                                                            <div class="v3-cell-quiet">Suggested</div>
                                                            <div class="v3-diff__to" x-text="suggestion.suggested_{{ $field }} || '(no change)'"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </template>

                                    <template x-if="!suggestion">
                                        <div>
                                            @foreach (['meta_title' => 'Meta title', 'meta_description' => 'Meta description', 'og_title' => 'OG title', 'og_description' => 'OG description'] as $field => $label)
                                                <div class="v3-well">
                                                    <div class="v3-label">{{ $label }}</div>
                                                    <div>{{ $entity->getAttribute($field) ?: '(empty)' }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No {{ $type }} found</p>
                                        <p class="v3-empty__line">{{ ($search || $status) ? 'Try adjusting your filters.' : 'There are no active ' . $type . ' to optimize.' }}</p>
                                        @if ($search || $status)
                                            <a href="{{ route('admin.extensions.seo.entities', ['type' => $type]) }}" class="v3-btn v3-btn--secondary v3-btn--sm">Clear all filters</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($entities->hasPages())
                <div class="v3-pager">{{ $entities->withQueryString()->links() }}</div>
            @endif
        </x-admin3.panel>
    </div>
</x-admin3.seo-shell>
