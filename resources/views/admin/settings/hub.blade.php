<x-admin.shell title="Settings">
    <x-admin.page title="Settings">
        <x-slot:actions>
            <div x-data class="relative">
                <x-admin.icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-v-muted" />
                <input type="search" placeholder="Search settings…" x-ref="q"
                       @input="$dispatch('settings-search', $event.target.value)"
                       class="v-control h-[var(--control-h)] w-56 rounded-v-md border border-v-border bg-v-surface pl-8 pr-3 text-[13px] text-v-text placeholder:text-v-muted">
            </div>
        </x-slot:actions>

        <div x-data="{ q: '' }" x-on:settings-search.window="q = $event.detail.toLowerCase()" class="space-y-8">
            @foreach ($groups as $group)
                @php
                    $haystacks = collect($group['items'])->map(fn ($i) => strtolower($i['label'] . ' ' . $i['description'] . ' ' . implode(' ', $i['keywords'])));
                @endphp
                <section x-show="!q || @js($haystacks->values()) .some(h => h.includes(q))">
                    <h2 class="mb-3 text-[10px] font-semibold uppercase tracking-widest text-v-muted">{{ $group['label'] }}</h2>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($group['items'] as $item)
                            <a href="{{ \App\Support\SettingsNav::url($item) }}"
                               x-show="!q || @js(strtolower($item['label'] . ' ' . $item['description'] . ' ' . implode(' ', $item['keywords']))).includes(q)"
                               class="v-hub-card group flex items-start gap-3 rounded-v-lg border border-v-border bg-v-surface p-4 transition-colors hover:border-v-strong hover:bg-v-hover">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-v-md bg-v-sunken text-v-secondary group-hover:text-v-accent">
                                    <x-admin.icon :name="$item['icon']" class="w-[18px] h-[18px]" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-[13px] font-semibold text-v-text">{{ $item['label'] }}</span>
                                    <span class="mt-0.5 block text-xs leading-snug text-v-secondary">{{ $item['description'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div x-show="q && !@js(collect($groups)->flatMap(fn ($g) => collect($g['items'])->map(fn ($i) => strtolower($i['label'] . ' ' . $i['description'] . ' ' . implode(' ', $i['keywords']))))->values()).some(h => h.includes(q))" x-cloak>
                <x-admin.empty-state icon="search" title="Nothing matches" description="No setting matches that search. Try a different word — cards also match hidden synonyms like “smtp” or “gcash”." />
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
