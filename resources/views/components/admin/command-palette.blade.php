{{-- The palette is a dialog holding a combobox. Both halves are stated:
     the panel is the dialog (named, focus-holding, Escape-closing), and
     the input owns the result list through aria-activedescendant, so
     Down and Up move the selection without moving focus off the field
     the person is still typing into. --}}
<div x-data="vPalette(@js(array_merge(\App\Support\AdminNav::paletteEntries(Auth::user()), \App\Support\SettingsNav::paletteEntries(Auth::user()))), @js(route('admin.palette.search')))"
     x-show="open" x-cloak
     x-on:keydown.escape.window="open = false"
     class="fixed inset-0 z-[95] flex items-start justify-center pt-[15vh] px-4">
    <div class="absolute inset-0 bg-black/50" @click="open = false" aria-hidden="true"></div>
    <div class="v-palette relative w-full max-w-lg rounded-v-lg bg-v-surface border border-v-border overflow-hidden"
         x-dialog="open" tabindex="-1"
         role="dialog" aria-modal="true" aria-label="Command palette">
        <div class="flex items-center gap-2 border-b border-v-border px-4">
            <x-admin.icon name="search" class="w-4 h-4 text-v-muted shrink-0" />
            <input x-ref="input" x-model="q" @input="search()" data-v-dialog-initial
                   @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="go()"
                   type="text" placeholder="Jump to a page, or search products, orders, customers&hellip;"
                   aria-label="Search the admin"
                   role="combobox" aria-expanded="true" aria-controls="v-palette-results" aria-autocomplete="list"
                   :aria-activedescendant="results.length ? 'v-palette-option-' + selected : null"
                   class="w-full bg-transparent py-3 text-[13px] text-v-text placeholder:text-v-muted border-0 focus:ring-0">
        </div>
        <ul id="v-palette-results" role="listbox" aria-label="Results" class="max-h-72 overflow-y-auto py-1">
            <template x-for="(r, i) in results" :key="i">
                <li role="option" :id="'v-palette-option-' + i" :aria-selected="selected === i">
                    <a :href="r.url" @mouseenter="selected = i" tabindex="-1"
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
