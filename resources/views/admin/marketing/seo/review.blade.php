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
<x-admin.shell title="SEO Review">
    <x-admin.page title="Review Queue">
        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            @include('admin.marketing.seo.partials.nav-v2')

            <div class="min-w-0 flex-1 space-y-5">
                {{-- Entity-type filter chips --}}
                <div class="flex flex-wrap gap-2" role="tablist" aria-label="Filter suggestions by content type">
                    <a href="{{ route('admin.extensions.seo.review') }}"
                       class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[13px] font-medium transition-colors
                           {{ $filter === 'all' ? 'border-v-accent bg-v-accent-weak text-v-accent' : 'border-v-border bg-v-surface text-v-secondary hover:bg-v-hover hover:text-v-text' }}">
                        All
                        <span class="v-num rounded-full bg-v-sunken px-1.5 text-[11px] {{ $filter === 'all' ? 'text-v-accent' : 'text-v-muted' }}">{{ $totalPending }}</span>
                    </a>
                    @foreach ($entityTypeLabels as $key => $label)
                        @if (($counts[$key] ?? 0) > 0)
                            <a href="{{ route('admin.extensions.seo.review', ['type' => $key]) }}"
                               class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[13px] font-medium transition-colors
                                   {{ $filter === $key ? 'border-v-accent bg-v-accent-weak text-v-accent' : 'border-v-border bg-v-surface text-v-secondary hover:bg-v-hover hover:text-v-text' }}">
                                {{ $label }}
                                <span class="v-num rounded-full bg-v-sunken px-1.5 text-[11px] {{ $filter === $key ? 'text-v-accent' : 'text-v-muted' }}">{{ $counts[$key] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>

                @if ($suggestions->isEmpty())
                    <x-admin.card>
                        <x-admin.empty-state icon="check" title="Queue clear"
                            :description="'Next generation run: ' . ($seoAutomation?->schedule_description ?? 'not scheduled — configure a cadence in Settings → Automations.')" />
                    </x-admin.card>
                @else
                    <div x-data="{ selected: [] }" class="space-y-4 pb-20">
                        <div class="space-y-4">
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
                                <div data-suggestion="{{ $suggestion->id }}" x-data="{ showUnchanged: false }"
                                     class="overflow-hidden rounded-v-lg border border-v-border bg-v-surface">
                                    {{-- Card header --}}
                                    <div class="flex flex-wrap items-start gap-3 border-b border-v-border px-5 py-4">
                                        <input type="checkbox" value="{{ $suggestion->id }}" x-model.number="selected"
                                               aria-label="Select {{ $entityName }} suggestion"
                                               class="mt-1 h-4 w-4 shrink-0 rounded-v-sm border-v-strong bg-v-surface text-v-accent focus:ring-v-accent">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <x-admin.badge tone="info">{{ $typeLabel }}</x-admin.badge>
                                                <span class="truncate text-[13px] font-semibold text-v-text">{{ $entityName }}</span>
                                            </div>
                                            <p class="mt-0.5 text-xs text-v-muted">Suggested {{ $suggestion->generated_at->diffForHumans() }}</p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <form method="POST" action="{{ route('admin.extensions.seo.accept', $suggestion) }}">
                                                @csrf
                                                <x-admin.button type="submit" variant="primary" size="sm">Accept</x-admin.button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.extensions.seo.reject', $suggestion) }}">
                                                @csrf
                                                <x-admin.button type="submit" variant="ghost" size="sm">Reject</x-admin.button>
                                            </form>
                                        </div>
                                    </div>

                                    {{-- Field-by-field diff --}}
                                    <div class="space-y-3 px-5 py-4">
                                        @forelse ($changedFields as $row)
                                            <div class="rounded-v-md bg-v-accent-weak px-3 py-2.5">
                                                <p class="text-[10px] font-semibold uppercase tracking-widest text-v-accent">{{ $row['label'] }}</p>
                                                <div class="mt-1.5 grid gap-2 sm:grid-cols-2">
                                                    <div>
                                                        <p class="text-[11px] text-v-muted">Current</p>
                                                        <p class="text-[13px] {{ $row['current'] ? 'text-v-secondary' : 'italic text-v-muted' }}">{{ $row['current'] ?: '(empty)' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-[11px] font-medium text-v-accent">Suggested</p>
                                                        <p class="text-[13px] font-medium text-v-text">{{ $row['suggested'] }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <p class="text-[13px] text-v-muted">No changed fields — every suggested value matches the current one.</p>
                                        @endforelse

                                        @if (! empty($unchangedFields))
                                            <button type="button" class="text-xs font-medium text-v-secondary hover:text-v-text"
                                                    @click="showUnchanged = !showUnchanged">
                                                <span x-show="!showUnchanged">Show {{ count($unchangedFields) }} unchanged field{{ count($unchangedFields) === 1 ? '' : 's' }}</span>
                                                <span x-show="showUnchanged" x-cloak>Hide unchanged fields</span>
                                            </button>
                                            <div x-show="showUnchanged" x-cloak class="space-y-2">
                                                @foreach ($unchangedFields as $row)
                                                    <div class="rounded-v-md bg-v-sunken px-3 py-2">
                                                        <p class="text-[10px] font-semibold uppercase tracking-widest text-v-muted">{{ $row['label'] }}</p>
                                                        <p class="mt-1 text-[13px] text-v-secondary">{{ $row['suggested'] }}</p>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Sticky bulk bar --}}
                        <div id="bulk-bar" x-show="selected.length > 0" x-cloak
                             class="sticky bottom-4 z-10">
                            <form method="POST" action="{{ route('admin.extensions.seo.bulk-accept') }}"
                                  aria-label="Bulk actions"
                                  class="flex items-center justify-between gap-4 rounded-v-lg border border-v-border bg-v-surface px-5 py-3 shadow-[var(--shadow-modal)]"
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
                                <span class="text-[13px] font-medium text-v-text">
                                    <span x-text="selected.length"></span> selected
                                </span>
                                <div class="flex items-center gap-2">
                                    <x-admin.button type="button" variant="ghost" size="sm" @click="selected = []">Clear</x-admin.button>
                                    <x-admin.button type="submit" variant="primary" size="sm">
                                        Accept <span x-text="selected.length"></span> selected
                                    </x-admin.button>
                                </div>
                            </form>
                        </div>
                    </div>

                    @if ($suggestions->hasPages())
                        <div class="pt-2">{{ $suggestions->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
