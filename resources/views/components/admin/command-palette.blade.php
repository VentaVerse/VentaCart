<div x-data="vPalette(@js(\App\Support\AdminNav::paletteEntries(Auth::user())), @js(route('admin.palette.search')))"
     x-show="open" x-cloak
     x-on:keydown.escape.window="open = false"
     class="fixed inset-0 z-[95] flex items-start justify-center pt-[15vh] px-4">
    <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
    <div class="v-palette relative w-full max-w-lg rounded-v-lg bg-v-surface border border-v-border shadow-2xl overflow-hidden">
        <div class="flex items-center gap-2 border-b border-v-border px-4">
            <x-admin.icon name="search" class="w-4 h-4 text-v-muted shrink-0" />
            <input x-ref="input" x-model="q" @input="search()"
                   @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="go()"
                   type="text" placeholder="Jump to a page, or search products, orders, customers…"
                   class="w-full bg-transparent py-3 text-[13px] text-v-text placeholder:text-v-muted border-0 focus:ring-0">
        </div>
        <ul class="max-h-72 overflow-y-auto py-1">
            <template x-for="(r, i) in results" :key="i">
                <li>
                    <a :href="r.url" @mouseenter="selected = i"
                       class="flex items-center justify-between px-4 py-2 text-[13px]"
                       :class="selected === i ? 'bg-v-accent-weak text-v-accent' : 'text-v-text'">
                        <span x-text="r.label"></span>
                        <span class="text-xs text-v-muted" x-text="r.sub"></span>
                    </a>
                </li>
            </template>
            <li x-show="results.length === 0" class="px-4 py-6 text-center text-[13px] text-v-muted">Nothing matches.</li>
        </ul>
    </div>
</div>
