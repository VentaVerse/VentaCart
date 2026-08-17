@php
    $entityTypeLabels = [
        'product' => 'Products',
        'category' => 'Categories',
        'brand' => 'Brands',
        'blog_post' => 'Blog',
        'page' => 'Pages',
    ];

    $diffFieldDefs = [
        'meta_title' => 'Meta Title',
        'meta_description' => 'Meta Description',
        'meta_keywords' => 'Keywords',
        'og_title' => 'OG Title',
        'og_description' => 'OG Description',
        'slug' => 'Slug',
    ];
@endphp
<x-admin3.seo-shell title="Review" active="review">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Review queue</h1>
        </div>
    </div>

    <div class="v3-settle">
        <div class="v3-tabs" role="tablist" aria-label="Content type">
            <a href="{{ route('admin.extensions.seo.review') }}" class="v3-tab" role="tab"
               aria-selected="{{ $filter === 'all' ? 'true' : 'false' }}">All ({{ $totalPending }})</a>
            @foreach ($entityTypeLabels as $key => $label)
                @if (($counts[$key] ?? 0) > 0)
                    <a href="{{ route('admin.extensions.seo.review', ['type' => $key]) }}" class="v3-tab" role="tab"
                       aria-selected="{{ $filter === $key ? 'true' : 'false' }}">{{ $label }} ({{ $counts[$key] }})</a>
                @endif
            @endforeach
        </div>

        @if ($suggestions->isEmpty())
            <x-admin3.panel>
                <div class="v3-empty">
                    <p class="v3-empty__title">Queue clear</p>
                    <p class="v3-empty__line">Next generation run: {{ $seoAutomation?->schedule_description ?? 'not scheduled.' }}</p>
                </div>
            </x-admin3.panel>
        @else
            <div x-data="{ selected: [] }">
                @foreach ($suggestions as $suggestion)
                    @php
                        $entity = $suggestion->entity;
                        $entityName = $entity ? ($entity->name ?? $entity->title ?? 'Unknown') : 'Deleted';
                        $typeLabel = $entityTypeLabels[$suggestion->entity_type] ?? \Illuminate\Support\Str::headline($suggestion->entity_type);

                        $changedFields = [];
                        $unchangedFields = [];
                        foreach ($diffFieldDefs as $field => $label) {
                            $suggested = $suggestion->{"suggested_{$field}"};
                            if (! filled($suggested)) {
                                continue;
                            }
                            $current = $suggestion->{"current_{$field}"};
                            $row = ['field' => $field, 'label' => $label, 'current' => $current, 'suggested' => $suggested];
                            if ($suggested !== $current) {
                                $changedFields[] = $row;
                            } else {
                                $unchangedFields[] = $row;
                            }
                        }
                    @endphp
                    <x-admin3.panel x-data="{ showUnchanged: false }" class="v3-gap">
                        <x-slot:acts>
                            <form method="POST" action="{{ route('admin.extensions.seo.accept', $suggestion) }}">
                                @csrf
                                <button type="submit" class="v3-btn v3-btn--primary v3-btn--sm">Accept</button>
                            </form>
                            <form method="POST" action="{{ route('admin.extensions.seo.reject', $suggestion) }}">
                                @csrf
                                <button type="submit" class="v3-btn v3-btn--ghost v3-btn--sm">Reject</button>
                            </form>
                        </x-slot:acts>
                        <div class="v3-namerow v3-gap-sm">
                            <label class="v3-check">
                                <input type="checkbox" value="{{ $suggestion->id }}" x-model.number="selected"
                                       aria-label="Select {{ $entityName }} suggestion">
                                <span class="v3-badge v3-badge--quiet">{{ $typeLabel }}</span>
                                <strong>{{ $entityName }}</strong>
                            </label>
                            <span class="v3-cell-quiet">Suggested {{ $suggestion->generated_at->diffForHumans() }}</span>
                        </div>

                        @forelse ($changedFields as $row)
                            <div class="v3-diff">
                                <div class="v3-label">{{ $row['label'] }}</div>
                                <div class="v3-diff__cols">
                                    <div>
                                        <div class="v3-cell-quiet">Current</div>
                                        <div class="v3-diff__from">{{ $row['current'] ?: '(empty)' }}</div>
                                    </div>
                                    <div>
                                        <div class="v3-cell-quiet">Suggested</div>
                                        <div class="v3-diff__to">{{ $row['suggested'] }}</div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="v3-cell-quiet">No changed fields. Every suggested value matches the current one.</p>
                        @endforelse

                        @if (! empty($unchangedFields))
                            <div class="v3-gap-sm">
                                <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="showUnchanged = !showUnchanged">
                                    <span x-show="!showUnchanged">Show {{ count($unchangedFields) }} unchanged field{{ count($unchangedFields) === 1 ? '' : 's' }}</span>
                                    <span x-show="showUnchanged" x-cloak>Hide unchanged fields</span>
                                </button>
                            </div>
                            <div x-show="showUnchanged" x-cloak>
                                @foreach ($unchangedFields as $row)
                                    <div class="v3-well">
                                        <div class="v3-label">{{ $row['label'] }}</div>
                                        <div class="v3-cell-quiet">{{ $row['suggested'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </x-admin3.panel>
                @endforeach

                <form method="POST" action="{{ route('admin.extensions.seo.bulk-accept') }}"
                      class="v3-bulkbar" x-show="selected.length > 0" x-cloak aria-label="Bulk actions"
                      @submit.prevent="
                          const form = $el;
                          selected.forEach((id) => {
                              const input = document.createElement('input');
                              input.type = 'hidden';
                              input.name = 'ids[]';
                              input.value = id;
                              form.appendChild(input);
                          });
                          form.submit();
                      ">
                    @csrf
                    <span><strong x-text="selected.length"></strong> selected</span>
                    <span class="v3-actrow">
                        <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="selected = []">Clear</button>
                        <button type="submit" class="v3-btn v3-btn--primary v3-btn--sm">Accept <span x-text="selected.length"></span> selected</button>
                    </span>
                </form>
            </div>

            @if ($suggestions->hasPages())
                <x-admin3.pager :paginator="$suggestions" />
            @endif
        @endif
    </div>
</x-admin3.seo-shell>
