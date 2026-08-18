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
        <span class="v-field__label" id="{{ $dlbId }}-label">{{ $label }}</span>
        @if ($hint)
            <x-admin.help :text="$hint" />
        @endif
    </div>

    {{-- Toggle --}}
    <button type="button" class="v-btn v-btn--ghost v-btn--sm" id="{{ $dlbId }}-toggle"
        aria-controls="{{ $dlbId }}-panel"
        :aria-expanded="showList ? 'true' : 'false'"
        @click="showList = !showList">
        <x-admin.icon name="chevron" class="v-dlb-ic" ::class="showList && 'v-picker-rot'" />
        <span x-text="selectedIds.length > 0 ? selectedIds.length + ' selected' : 'Select items'"></span>
    </button>

    {{-- Selected tags when collapsed --}}
    <div class="v-gap-sm" x-show="selectedIds.length > 0 && !showList">
        <template x-for="item in selectedItems" :key="'tag-' + item.id">
            <span class="v-badge v-badge--info v-chip--inline">
                <span x-text="item.name"></span>
                <button type="button" class="v-row__x"
                    :aria-label="'Remove ' + item.name"
                    @click="selectedIds = selectedIds.filter(i => i !== item.id)">
                    <x-admin.icon name="x" />
                </button>
            </span>
        </template>
    </div>

    {{-- The picker: two bounded lists and the traffic between them. --}}
    <div id="{{ $dlbId }}-panel" x-show="showList" x-transition
        class="v-picker">

        {{-- Available --}}
        <div class="v-picker__pane v-picker__pane--in">
            <div class="v-picker__head">
                <span class="v-field__label">Available</span>
                <input type="search" x-model="search"
                    aria-label="Search available {{ $label }}"
                    class="v-control v-control--sm">
            </div>
            <div class="v-picker__list">
                <div role="listbox" aria-multiselectable="true" aria-label="Available {{ $label }}">
                    <template x-for="item in available" :key="item.id">
                        <button type="button" class="v-picker__opt" role="option"
                            :aria-selected="highlightedAvailable.includes(item.id) ? 'true' : 'false'"
                            @click="toggleHighlightAvailable(item.id, $event)"
                            x-text="item.name"></button>
                    </template>
                </div>
                <p x-show="available.length === 0" class="v-picker__none">
                    <span x-show="search">No matches</span>
                    <span x-show="!search">All items selected</span>
                </p>
            </div>
        </div>

        {{-- The traffic --}}
        <div class="v-picker__mid">
            <button type="button" class="v-btn v-btn--secondary v-btn--sm" aria-label="Add all {{ $label }}" @click="addAll()">
                <x-admin.icon name="chevron" class="v-dlb-ic" /><x-admin.icon name="chevron" class="v-dlb-ic" />
            </button>
            <button type="button" class="v-btn v-btn--secondary v-btn--sm" aria-label="Add highlighted {{ $label }}"
                :disabled="highlightedAvailable.length === 0" @click="addHighlighted()">
                <x-admin.icon name="chevron" class="v-dlb-ic" />
            </button>
            <button type="button" class="v-btn v-btn--secondary v-btn--sm" aria-label="Remove highlighted {{ $label }}"
                :disabled="highlightedSelected.length === 0" @click="removeHighlighted()">
                <x-admin.icon name="back" class="v-dlb-ic" />
            </button>
            <button type="button" class="v-btn v-btn--secondary v-btn--sm" aria-label="Remove all {{ $label }}"
                x-show="selectedIds.length > 0" @click="removeAll()">
                <x-admin.icon name="back" class="v-dlb-ic" /><x-admin.icon name="back" class="v-dlb-ic" />
            </button>
        </div>

        {{-- Selected --}}
        <div class="v-picker__pane v-picker__pane--out">
            <div class="v-picker__head">
                <span class="v-field__label">Selected <span class="v-tone-act" x-text="'(' + selectedIds.length + ')'"></span></span>
            </div>
            <div class="v-picker__list">
                <div role="listbox" aria-multiselectable="true" aria-label="Selected {{ $label }}">
                    <template x-for="item in selectedItems" :key="item.id">
                        <button type="button" class="v-picker__opt" role="option"
                            :aria-selected="highlightedSelected.includes(item.id) ? 'true' : 'false'"
                            @click="toggleHighlightSelected(item.id, $event)"
                            x-text="item.name"></button>
                    </template>
                </div>
                <p x-show="selectedIds.length === 0" class="v-picker__none">None selected</p>
            </div>
        </div>
    </div>

    {{-- Hidden inputs --}}
    <template x-for="id in selectedIds" :key="'hidden-' + id">
        <input type="hidden" :name="'{{ $name }}[]'" :value="id">
    </template>
</div>
