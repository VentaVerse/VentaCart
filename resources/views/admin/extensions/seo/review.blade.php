<x-admin-layout>
    <x-slot name="header">SEO Optimization</x-slot>
    @include('admin.extensions.seo.partials.tabs')

    {{-- Filter bar --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex items-center gap-4">
            <span class="text-sm font-medium text-gray-700">Filter:</span>
            <div class="flex gap-2">
                <a href="{{ route('admin.extensions.seo.review') }}"
                   class="px-3 py-1.5 text-sm rounded-lg border transition-colors {{ $filter === 'all' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                    All ({{ $totalPending }})
                </a>
                @foreach (['product' => 'Products', 'category' => 'Categories', 'brand' => 'Brands', 'blog_post' => 'Blog'] as $key => $label)
                    @if (($counts[$key] ?? 0) > 0)
                        <a href="{{ route('admin.extensions.seo.review', ['type' => $key]) }}"
                           class="px-3 py-1.5 text-sm rounded-lg border transition-colors {{ $filter === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                            {{ $label }} ({{ $counts[$key] ?? 0 }})
                        </a>
                    @endif
                @endforeach
            </div>

            @if ($totalPending > 0)
                <form method="POST" action="{{ route('admin.extensions.seo.bulk-accept') }}" class="ml-auto" onsubmit="return confirm('Accept all {{ $totalPending }} pending suggestions?')">
                    @csrf
                    @foreach ($suggestions as $s)
                        <input type="hidden" name="suggestion_ids[]" value="{{ $s->id }}">
                    @endforeach
                    <button type="submit" class="px-4 py-1.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                        Accept All ({{ $totalPending }})
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($suggestions->isEmpty())
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm font-medium text-gray-500">No pending suggestions</p>
            <p class="text-xs text-gray-400 mt-1">Generate suggestions from the Products, Categories, Brands, or Blog tabs.</p>
        </div>
    @else
        <div class="space-y-3" x-data="{
            expanded: null,
            async accept(id, el) {
                const res = await fetch(`{{ url(config('admin.prefix', 'admin') . '/extensions/seo/suggestions') }}/${id}/accept`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                });
                if ((await res.json()).ok) {
                    el.closest('[data-suggestion]').remove();
                }
            },
            async reject(id, el) {
                const res = await fetch(`{{ url(config('admin.prefix', 'admin') . '/extensions/seo/suggestions') }}/${id}/reject`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                });
                if ((await res.json()).ok) {
                    el.closest('[data-suggestion]').remove();
                }
            }
        }">
            @foreach ($suggestions as $suggestion)
                @php
                    $entity = $suggestion->entity;
                    $entityName = $entity ? ($entity->name ?? $entity->title ?? 'Unknown') : 'Deleted';
                    $typeLabel = str_replace('_', ' ', $suggestion->entity_type);
                @endphp
                <div data-suggestion="{{ $suggestion->id }}" class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    {{-- Header row (always visible) --}}
                    <div class="flex items-center gap-4 px-5 py-3 cursor-pointer hover:bg-gray-50 transition-colors"
                         @click="expanded = expanded === {{ $suggestion->id }} ? null : {{ $suggestion->id }}">
                        <div class="flex-shrink-0">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 capitalize">{{ $typeLabel }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $entityName }}</p>
                            <p class="text-xs text-gray-400">Suggested {{ $suggestion->generated_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click.stop="accept({{ $suggestion->id }}, $el)"
                                class="px-3 py-1 bg-green-600 text-white text-xs font-medium rounded hover:bg-green-700 transition-colors">Accept</button>
                            <button type="button" @click.stop="reject({{ $suggestion->id }}, $el)"
                                class="px-3 py-1 bg-red-500 text-white text-xs font-medium rounded hover:bg-red-600 transition-colors">Reject</button>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="expanded === {{ $suggestion->id }} && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </div>

                    {{-- Expanded diff --}}
                    <div x-show="expanded === {{ $suggestion->id }}" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         class="border-t border-gray-100 bg-gray-50 px-5 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach ([
                                'meta_title' => 'Meta Title',
                                'meta_description' => 'Meta Description',
                                'og_title' => 'OG Title',
                                'og_description' => 'OG Description',
                                'slug' => 'Slug',
                            ] as $field => $label)
                                @php
                                    $current = $suggestion->{"current_{$field}"};
                                    $suggested = $suggestion->{"suggested_{$field}"};
                                @endphp
                                @if ($suggested || $current)
                                    <div class="md:col-span-2">
                                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $label }}</label>
                                        <div class="grid grid-cols-2 gap-2 mt-1">
                                            <div class="bg-white rounded p-3 text-sm border border-gray-200">
                                                <span class="text-xs text-gray-400 block mb-1">Current</span>
                                                <span class="{{ $current ? 'text-gray-700' : 'text-gray-300 italic' }}">{{ $current ?: '(empty)' }}</span>
                                            </div>
                                            <div class="bg-white rounded p-3 text-sm border-l-3 border border-indigo-200 bg-indigo-50/30">
                                                <span class="text-xs text-indigo-500 block mb-1">Suggested</span>
                                                <span class="{{ $suggested ? 'text-gray-700' : 'text-gray-300 italic' }}">{{ $suggested ?: '(no change)' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $suggestions->links() }}
        </div>
    @endif
</x-admin-layout>
