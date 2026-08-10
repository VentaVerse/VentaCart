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
<x-admin.seo-shell title="SEO Review" heading="Review Queue">
    <div class="space-y-5">
        <x-admin.segment label="Content type">
            <x-admin.segment.item :href="route('admin.extensions.seo.review')" :active="$filter === 'all'" :count="$totalPending">All</x-admin.segment.item>
            @foreach ($entityTypeLabels as $key => $label)
                @if (($counts[$key] ?? 0) > 0)
                    <x-admin.segment.item :href="route('admin.extensions.seo.review', ['type' => $key])" :active="$filter === $key" :count="$counts[$key]">{{ $label }}</x-admin.segment.item>
                @endif
            @endforeach
        </x-admin.segment>

        @if ($suggestions->isEmpty())
            <x-admin.panel flush>
                <x-admin.empty-state title="Queue clear"
                    :description="'Next generation run: ' . ($seoAutomation?->schedule_description ?? 'not scheduled.')" />
            </x-admin.panel>
        @else
            <div x-data="{ selected: [] }" class="space-y-4 pb-20">
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
                    <x-admin.panel data-suggestion="{{ $suggestion->id }}" x-data="{ showUnchanged: false }">
                        <x-slot:head>
                            <input type="checkbox" value="{{ $suggestion->id }}" x-model.number="selected"
                                   aria-label="Select {{ $entityName }} suggestion"
                                   class="h-4 w-4 shrink-0 self-center rounded-v-sm border bg-v-surface text-v-accent focus:ring-v-accent">
                            <x-admin.badge tone="info">{{ $typeLabel }}</x-admin.badge>
                            <h2 class="v-subhead min-w-0 truncate">{{ $entityName }}</h2>
                            <span class="v-panel__note">Suggested {{ $suggestion->generated_at->diffForHumans() }}</span>
                            <div class="ml-auto flex shrink-0 items-center gap-2">
                                <form method="POST" action="{{ route('admin.extensions.seo.accept', $suggestion) }}">
                                    @csrf
                                    <x-admin.button type="submit" variant="primary" size="sm">Accept</x-admin.button>
                                </form>
                                <form method="POST" action="{{ route('admin.extensions.seo.reject', $suggestion) }}">
                                    @csrf
                                    <x-admin.button type="submit" variant="ghost" size="sm">Reject</x-admin.button>
                                </form>
                            </div>
                        </x-slot:head>

                        <div class="space-y-3">
                            @forelse ($changedFields as $row)
                                <div class="rounded-v-md bg-v-accent-weak px-3 py-2.5">
                                    <p class="v-label text-v-accent">{{ $row['label'] }}</p>
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
                                <p class="v-panel__blank">No changed fields. Every suggested value matches the current one.</p>
                            @endforelse

                            @if (! empty($unchangedFields))
                                <x-admin.button type="button" variant="ghost" size="sm" @click="showUnchanged = !showUnchanged">
                                    <span x-show="!showUnchanged">Show {{ count($unchangedFields) }} unchanged field{{ count($unchangedFields) === 1 ? '' : 's' }}</span>
                                    <span x-show="showUnchanged" x-cloak>Hide unchanged fields</span>
                                </x-admin.button>
                                <div x-show="showUnchanged" x-cloak class="space-y-2">
                                    @foreach ($unchangedFields as $row)
                                        <div class="rounded-v-md bg-v-sunken px-3 py-2">
                                            <p class="v-label">{{ $row['label'] }}</p>
                                            <p class="mt-1 text-[13px] text-v-secondary">{{ $row['suggested'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </x-admin.panel>
                @endforeach

                {{-- The bar that follows a selection down the page. --}}
                <div id="bulk-bar" x-show="selected.length > 0" x-cloak class="sticky bottom-4 z-10">
                    <form method="POST" action="{{ route('admin.extensions.seo.bulk-accept') }}"
                          aria-label="Bulk actions"
                          class="v-sheet flex items-center justify-between gap-4 px-5 py-3 shadow-[var(--shadow-modal)]"
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
</x-admin.seo-shell>
