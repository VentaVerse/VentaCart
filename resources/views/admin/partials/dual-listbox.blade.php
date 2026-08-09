{{--
    Dual Listbox Component
    @props: name (string), label (string), hint (string), items (array of ['id' => x, 'name' => y]), selected (array of ids)
--}}
@props(['name', 'label', 'hint' => '', 'items' => [], 'selected' => []])

<div x-data="{
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
    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
    @if ($hint)
        <p class="text-xs text-gray-500 mb-2">{{ $hint }}</p>
    @endif

    {{-- Toggle --}}
    <button type="button" @click="showList = !showList"
        class="flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800 mb-2">
        <svg class="w-4 h-4 transition-transform" :class="showList && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span x-text="selectedIds.length > 0 ? selectedIds.length + ' selected' : 'Select items'"></span>
    </button>

    {{-- Selected tags when collapsed --}}
    <div class="flex flex-wrap gap-1.5 mb-2" x-show="selectedIds.length > 0 && !showList">
        <template x-for="item in selectedItems" :key="'tag-' + item.id">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-full border border-indigo-200">
                <span x-text="item.name"></span>
                <button type="button" @click="selectedIds = selectedIds.filter(i => i !== item.id)" class="text-indigo-400 hover:text-indigo-700">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </span>
        </template>
    </div>

    {{-- Dual list --}}
    <div x-show="showList" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
        class="flex items-stretch gap-0">

        {{-- Available --}}
        <div class="flex-1 border border-gray-200 rounded-l-lg overflow-hidden">
            <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="v-label">Available</span>
                </div>
                <input type="text" x-model="search" placeholder="Search..."
                    class="w-full text-xs border-gray-300 rounded px-2 py-1.5 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="h-44 overflow-y-auto">
                <template x-for="item in available" :key="item.id">
                    <div @click="toggleHighlightAvailable(item.id, $event)"
                        :class="highlightedAvailable.includes(item.id) ? 'bg-indigo-100 text-indigo-800' : 'text-gray-700 hover:bg-gray-50'"
                        class="w-full text-left px-3 py-1.5 text-sm cursor-pointer select-none transition-colors">
                        <span x-text="item.name"></span>
                    </div>
                </template>
                <div x-show="available.length === 0" class="px-3 py-6 text-xs text-gray-400 text-center">
                    <span x-show="search">No matches</span>
                    <span x-show="!search">All items selected</span>
                </div>
            </div>
        </div>

        {{-- Center buttons --}}
        <div class="flex flex-col items-center justify-center gap-2 px-2 bg-gray-50 border-y border-gray-200">
            <button type="button" @click="addAll()" title="Add all"
                class="w-8 h-8 flex items-center justify-center rounded bg-white border border-gray-300 text-gray-500 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
            </button>
            <button type="button" @click="addHighlighted()" title="Add selected" :disabled="highlightedAvailable.length === 0"
                class="w-8 h-8 flex items-center justify-center rounded bg-white border border-gray-300 text-gray-500 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
            <button type="button" @click="removeHighlighted()" title="Remove selected" :disabled="highlightedSelected.length === 0"
                class="w-8 h-8 flex items-center justify-center rounded bg-white border border-gray-300 text-gray-500 hover:bg-red-50 hover:text-red-600 hover:border-red-300 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button type="button" @click="removeAll()" title="Remove all" x-show="selectedIds.length > 0"
                class="w-8 h-8 flex items-center justify-center rounded bg-white border border-gray-300 text-gray-500 hover:bg-red-50 hover:text-red-600 hover:border-red-300 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7M19 19l-7-7 7-7"/></svg>
            </button>
        </div>

        {{-- Selected --}}
        <div class="flex-1 border border-gray-200 rounded-r-lg overflow-hidden">
            <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <span class="v-label">Selected <span class="text-indigo-600" x-text="'(' + selectedIds.length + ')'"></span></span>
                </div>
            </div>
            <div class="h-44 overflow-y-auto">
                <template x-for="item in selectedItems" :key="item.id">
                    <div @click="toggleHighlightSelected(item.id, $event)"
                        :class="highlightedSelected.includes(item.id) ? 'bg-red-100 text-red-800' : 'text-gray-700 hover:bg-gray-50'"
                        class="w-full px-3 py-1.5 text-sm cursor-pointer select-none transition-colors">
                        <span x-text="item.name"></span>
                    </div>
                </template>
                <div x-show="selectedIds.length === 0" class="px-3 py-6 text-xs text-gray-400 text-center">
                    None selected
                </div>
            </div>
        </div>
    </div>

    <p class="text-[10px] text-gray-400 mt-1" x-show="showList">Click to highlight, Ctrl+Click for multiple. Use arrows to move.</p>

    {{-- Hidden inputs --}}
    <template x-for="id in selectedIds" :key="'hidden-' + id">
        <input type="hidden" :name="'{{ $name }}[]'" :value="id">
    </template>
</div>
