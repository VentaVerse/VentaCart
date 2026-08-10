<x-admin.shell title="Settings">
    <x-admin.page title="Settings">
        <x-slot:actions>
            <div x-data class="relative">
                <x-admin.icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-v-muted" />
                <input type="search" placeholder="Search settings&hellip;" x-ref="q" aria-label="Search settings"
                       @input="$dispatch('settings-search', $event.target.value)"
                       class="v-control h-[var(--control-h)] w-56 rounded-v-md border bg-v-surface pl-8 pr-3 text-[13px] text-v-text placeholder:text-v-muted">
            </div>
        </x-slot:actions>

        <div x-data="{ q: '' }" x-on:settings-search.window="q = $event.detail.toLowerCase()" class="space-y-8">
            @foreach ($groups as $group)
                @php
                    $haystacks = collect($group['items'])->map(fn ($i) => strtolower($i['label'] . ' ' . $i['description'] . ' ' . implode(' ', $i['keywords'])));
                @endphp
                <section x-show="!q || @js($haystacks->values()) .some(h => h.includes(q))">
                    <h2 class="mb-3 v-label">{{ $group['label'] }}</h2>
                    <div class="v-slates">
                        @foreach ($group['items'] as $item)
                            <x-admin.slate :title="$item['label']" :icon="$item['icon']" :note="$item['description']"
                                :href="\App\Support\SettingsNav::url($item)"
                                x-show="!q || @js(strtolower($item['label'] . ' ' . $item['description'] . ' ' . implode(' ', $item['keywords']))).includes(q)" />
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div x-show="q && !@js(collect($groups)->flatMap(fn ($g) => collect($g['items'])->map(fn ($i) => strtolower($i['label'] . ' ' . $i['description'] . ' ' . implode(' ', $i['keywords']))))->values()).some(h => h.includes(q))" x-cloak>
                <x-admin.empty-state title="Nothing matches" description="No setting matches that search. Try a different word: cards also match hidden synonyms like &ldquo;smtp&rdquo; or &ldquo;gcash&rdquo;." />
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
