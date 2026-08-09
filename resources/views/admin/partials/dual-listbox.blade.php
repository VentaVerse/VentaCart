{{--
    Dual Listbox Component
    @props: name (string), label (string), hint (string), items (array of ['id' => x, 'name' => y]), selected (array of ids)
--}}
@props(['name', 'label', 'hint' => '', 'items' => [], 'selected' => []])

@php
    // One stable id per include so the group can name itself and the
    // disclosure can point at the panel it opens. $name is a form key, so it
    // can carry brackets; strip anything an id may not hold.
    $dlbId = 'dlb-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $name);
@endphp

<div role="group" aria-labelledby="{{ $dlbId }}-label" x-data="{
    search: '',
    allItems: @js($items),
    // Coerce numeric ids to int (so old() string values match integer item ids),
    // but preserve non-numeric ids like currency codes ('USD') as strings.
    selectedIds: @js(array_values($selected)).map(v => /^-?\d+$/.test(String(v)) ? parseInt(v, 10) : v),
    showList: false,
    highlightedAvailable: [],
    highlightedSelected: [],

    get available() {
        return this.allItems.filter(item =>
            !this.selectedIds.includes(item.id) &&
            (!this.search || item.name.toLowerCase().includes(this.search.toLowerCase()))
        );
    },

    get selectedItems() {
        return this.allItems.filter(item => this.selectedIds.includes(item.id));
    },

    toggleHighlightAvailable(id, event) {
        if (event.ctrlKey || event.metaKey) {
            if (this.highlightedAvailable.includes(id)) {
                this.highlightedAvailable = this.highlightedAvailable.filter(i => i !== id);
            } else {
                this.highlightedAvailable.push(id);
            }
        } else {
            this.highlightedAvailable = [id];
        }
    },

    toggleHighlightSelected(id, event) {
        if (event.ctrlKey || event.metaKey) {
            if (this.highlightedSelected.includes(id)) {
                this.highlightedSelected = this.highlightedSelected.filter(i => i !== id);
            } else {
                this.highlightedSelected.push(id);
            }
        } else {
            this.highlightedSelected = [id];
        }
    },

    addHighlighted() {
        this.highlightedAvailable.forEach(id => {
            if (!this.selectedIds.includes(id)) {
                this.selectedIds.push(id);
            }
        });
        this.highlightedAvailable = [];
    },

    removeHighlighted() {
        this.highlightedSelected.forEach(id => {
            this.selectedIds = this.selectedIds.filter(i => i !== id);
        });
        this.highlightedSelected = [];
    },

    addAll() {
        this.available.forEach(item => {
            if (!this.selectedIds.includes(item.id)) {
                this.selectedIds.push(item.id);
            }
        });
        this.highlightedAvailable = [];
    },

    removeAll() {
        this.selectedIds = [];
        this.highlightedSelected = [];
    }
}">
    <div class="flex items-center gap-1.5 mb-1">
        <span class="v-label" id="{{ $dlbId }}-label">{{ $label }}</span>
        @if ($hint)
            <x-admin.hint label="About {{ $label }}">{{ $hint }}</x-admin.hint>
        @endif
    </div>

    {{-- Toggle --}}
    <x-admin.button variant="ghost" size="sm" class="mb-2" id="{{ $dlbId }}-toggle"
        aria-controls="{{ $dlbId }}-panel"
        x-bind:aria-expanded="showList ? 'true' : 'false'"
        @click="showList = !showList">
        <x-admin.icon name="chevron-right" class="w-3.5 h-3.5 transition-transform" x-bind:class="showList && 'rotate-90'" />
        <span x-text="selectedIds.length > 0 ? selectedIds.length + ' selected' : 'Select items'"></span>
    </x-admin.button>

    {{-- Selected tags when collapsed --}}
    <div class="flex flex-wrap gap-x-3 gap-y-1.5 mb-2" x-show="selectedIds.length > 0 && !showList">
        <template x-for="item in selectedItems" :key="'tag-' + item.id">
            <x-admin.badge tone="accent">
                <span x-text="item.name"></span>
                <button type="button" class="text-v-muted hover:text-v-danger align-middle"
                    x-bind:aria-label="'Remove ' + item.name"
                    @click="selectedIds = selectedIds.filter(i => i !== item.id)">
                    <x-admin.icon name="x" class="w-3 h-3" />
                </button>
            </x-admin.badge>
        </template>
    </div>

    {{-- Dual list --}}
    <div id="{{ $dlbId }}-panel" x-show="showList" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
        class="flex items-stretch gap-0">

        {{-- Available --}}
        <div class="flex-1 min-w-0 border border-v-border rounded-l-v-md overflow-hidden">
            <div class="bg-v-sunken px-3 py-2 border-b border-v-border">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="v-label">Available</span>
                </div>
                <input type="text" x-model="search" placeholder="Search..."
                    aria-label="Search available {{ $label }}"
                    class="v-control w-full h-[var(--control-h-sm)] px-2.5 text-xs bg-v-surface text-v-text border border-v-strong rounded-v-md placeholder:text-v-muted">
            </div>
            <div class="h-44 overflow-y-auto">
                <template x-for="item in available" :key="item.id">
                    <div @click="toggleHighlightAvailable(item.id, $event)"
                        :class="highlightedAvailable.includes(item.id) ? 'bg-v-accent-weak text-v-accent font-medium' : 'text-v-secondary hover:bg-v-hover'"
                        class="w-full text-left px-3 py-1.5 text-[13px] cursor-pointer select-none transition-colors">
                        <span x-text="item.name"></span>
                    </div>
                </template>
                <div x-show="available.length === 0" class="px-3 py-6 text-xs text-v-muted text-center">
                    <span x-show="search">No matches</span>
                    <span x-show="!search">All items selected</span>
                </div>
            </div>
        </div>

        {{-- Center buttons --}}
        <div class="flex flex-col items-center justify-center gap-2 px-2 bg-v-sunken border-y border-v-border">
            <x-admin.button variant="secondary" size="sm" class="w-8 shrink-0 !px-0"
                title="Add all" aria-label="Add all {{ $label }}" @click="addAll()">
                <x-admin.icon name="chevron-right" class="w-3.5 h-3.5 -mr-1.5" />
                <x-admin.icon name="chevron-right" class="w-3.5 h-3.5" />
            </x-admin.button>
            <x-admin.button variant="secondary" size="sm" class="w-8 shrink-0 !px-0"
                title="Add selected" aria-label="Add highlighted {{ $label }}"
                x-bind:disabled="highlightedAvailable.length === 0" @click="addHighlighted()">
                <x-admin.icon name="chevron-right" class="w-3.5 h-3.5" />
            </x-admin.button>
            <x-admin.button variant="secondary" size="sm" class="w-8 shrink-0 !px-0"
                title="Remove selected" aria-label="Remove highlighted {{ $label }}"
                x-bind:disabled="highlightedSelected.length === 0" @click="removeHighlighted()">
                <x-admin.icon name="chevron-left" class="w-3.5 h-3.5" />
            </x-admin.button>
            <x-admin.button variant="secondary" size="sm" class="w-8 shrink-0 !px-0"
                title="Remove all" aria-label="Remove all {{ $label }}"
                x-show="selectedIds.length > 0" @click="removeAll()">
                <x-admin.icon name="chevron-left" class="w-3.5 h-3.5 -mr-1.5" />
                <x-admin.icon name="chevron-left" class="w-3.5 h-3.5" />
            </x-admin.button>
        </div>

        {{-- Selected --}}
        <div class="flex-1 min-w-0 border border-v-border rounded-r-v-md overflow-hidden">
            <div class="bg-v-sunken px-3 py-2 border-b border-v-border">
                <div class="flex items-center justify-between">
                    <span class="v-label">Selected <span class="text-v-accent" x-text="'(' + selectedIds.length + ')'"></span></span>
                </div>
            </div>
            <div class="h-44 overflow-y-auto">
                <template x-for="item in selectedItems" :key="item.id">
                    <div @click="toggleHighlightSelected(item.id, $event)"
                        :class="highlightedSelected.includes(item.id) ? 'bg-[var(--bl-bad-wash)] text-v-danger font-medium' : 'text-v-secondary hover:bg-v-hover'"
                        class="w-full px-3 py-1.5 text-[13px] cursor-pointer select-none transition-colors">
                        <span x-text="item.name"></span>
                    </div>
                </template>
                <div x-show="selectedIds.length === 0" class="px-3 py-6 text-xs text-v-muted text-center">
                    None selected
                </div>
            </div>
        </div>
    </div>

    {{-- Hidden inputs --}}
    <template x-for="id in selectedIds" :key="'hidden-' + id">
        <input type="hidden" :name="'{{ $name }}[]'" :value="id">
    </template>
</div>
