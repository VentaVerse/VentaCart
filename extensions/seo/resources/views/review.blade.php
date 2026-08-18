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
<x-admin.seo-shell title="Review" active="review">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Review queue</h1>
        </div>
    </div>

    <div class="v-settle">
        <div class="v-tabs" role="tablist" aria-label="Content type">
            <a href="{{ route('admin.extensions.seo.review') }}" class="v-tab" role="tab"
               aria-selected="{{ $filter === 'all' ? 'true' : 'false' }}">All ({{ $totalPending }})</a>
            @foreach ($entityTypeLabels as $key => $label)
                @if (($counts[$key] ?? 0) > 0)
                    <a href="{{ route('admin.extensions.seo.review', ['type' => $key]) }}" class="v-tab" role="tab"
                       aria-selected="{{ $filter === $key ? 'true' : 'false' }}">{{ $label }} ({{ $counts[$key] }})</a>
                @endif
            @endforeach
        </div>

        @if ($suggestions->isEmpty())
            <x-admin.panel>
                <div class="v-empty">
                    <p class="v-empty__title">Queue clear</p>
                    <p class="v-empty__line">Next generation run: {{ $seoAutomation?->schedule_description ?? 'not scheduled.' }}</p>
                </div>
            </x-admin.panel>
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
                    <x-admin.panel x-data="{ showUnchanged: false }" class="v-gap">
                        <x-slot:acts>
                            <form method="POST" action="{{ route('admin.extensions.seo.accept', $suggestion) }}">
                                @csrf
                                <button type="submit" class="v-btn v-btn--primary v-btn--sm">Accept</button>
                            </form>
                            <form method="POST" action="{{ route('admin.extensions.seo.reject', $suggestion) }}">
                                @csrf
                                <button type="submit" class="v-btn v-btn--ghost v-btn--sm">Reject</button>
                            </form>
                        </x-slot:acts>
                        <div class="v-namerow v-gap-sm">
                            <label class="v-check">
                                <input type="checkbox" value="{{ $suggestion->id }}" x-model.number="selected"
                                       aria-label="Select {{ $entityName }} suggestion">
                                <span class="v-badge v-badge--quiet">{{ $typeLabel }}</span>
                                <strong>{{ $entityName }}</strong>
                            </label>
                            <span class="v-cell-quiet">Suggested {{ $suggestion->generated_at->diffForHumans() }}</span>
                        </div>

                        @forelse ($changedFields as $row)
                            <div class="v-diff">
                                <div class="v-label">{{ $row['label'] }}</div>
                                <div class="v-diff__cols">
                                    <div>
                                        <div class="v-cell-quiet">Current</div>
                                        <div class="v-diff__from">{{ $row['current'] ?: '(empty)' }}</div>
                                    </div>
                                    <div>
                                        <div class="v-cell-quiet">Suggested</div>
                                        <div class="v-diff__to">{{ $row['suggested'] }}</div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="v-cell-quiet">No changed fields. Every suggested value matches the current one.</p>
                        @endforelse

                        @if (! empty($unchangedFields))
                            <div class="v-gap-sm">
                                <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="showUnchanged = !showUnchanged">
                                    <span x-show="!showUnchanged">Show {{ count($unchangedFields) }} unchanged field{{ count($unchangedFields) === 1 ? '' : 's' }}</span>
                                    <span x-show="showUnchanged" x-cloak>Hide unchanged fields</span>
                                </button>
                            </div>
                            <div x-show="showUnchanged" x-cloak>
                                @foreach ($unchangedFields as $row)
                                    <div class="v-well">
                                        <div class="v-label">{{ $row['label'] }}</div>
                                        <div class="v-cell-quiet">{{ $row['suggested'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </x-admin.panel>
                @endforeach

                <form method="POST" action="{{ route('admin.extensions.seo.bulk-accept') }}"
                      class="v-bulkbar" x-show="selected.length > 0" x-cloak aria-label="Bulk actions"
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
                    <span class="v-actrow">
                        <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="selected = []">Clear</button>
                        <button type="submit" class="v-btn v-btn--primary v-btn--sm">Accept <span x-text="selected.length"></span> selected</button>
                    </span>
                </form>
            </div>

            @if ($suggestions->hasPages())
                <x-admin.pager :paginator="$suggestions" />
            @endif
        @endif
    </div>
</x-admin.seo-shell>
