@props(['region', 'label', 'side' => false])

{{-- One position on the page. Everything inside is Alpine state from
     layoutEditor(), so a slot is markup and holds no logic of its own. --}}
<div class="v-slot {{ $side ? 'v-slot--side' : '' }}"
     :class="{ 'is-target': dragging }"
     @dragover.prevent
     @drop.prevent="drop('{{ $region }}')">

    <div class="v-slot__lbl">
        <span>{{ $label }}</span>
        <span class="mono">{{ $region }}</span>
        <button type="button" class="v-slot__add" @click="adding = '{{ $region }}'"
                aria-label="Add a widget to {{ $label }}">+</button>
    </div>

    {{-- Inherited from All pages: shown so nothing appears on a page
         unexplained, and not editable from here, because it does not
         belong to this page. --}}
    <template x-for="(row, i) in inheritedIn('{{ $region }}')" :key="'i' + row.id">
        <div class="v-card v-card--inherit">
            <span class="v-card__nm" x-text="row.title || row.widget"></span>
            <span class="v-tag">All pages</span>
        </div>
    </template>

    <template x-for="(row, i) in placed('{{ $region }}')" :key="row.id ?? ('n' + i)">
        <div class="v-card" :class="{ 'is-off': !row.enabled, 'is-gone': !row.available }"
             draggable="true" @dragstart="start('{{ $region }}', i)"
             @drop.stop.prevent="drop('{{ $region }}', i)">
            <span class="v-card__grip" aria-hidden="true">⣿</span>
            <span class="v-card__nm" x-text="row.title || row.label"></span>

            <span class="v-tag v-tag--off" x-show="!row.available">Module off</span>

            <span class="v-card__acts">
                <button type="button" @click="edit('{{ $region }}', i)"
                        aria-label="Settings" title="Settings">&#9881;</button>
                <button type="button" @click="toggle('{{ $region }}', i)"
                        :aria-label="row.enabled ? 'Hide this widget' : 'Show this widget'"
                        :title="row.enabled ? 'Visible' : 'Hidden'">
                    <span x-text="row.enabled ? '◉' : '○'"></span>
                </button>
                <button type="button" @click="remove('{{ $region }}', i)"
                        aria-label="Remove this widget" title="Remove">&times;</button>
            </span>
        </div>
    </template>

    <p class="v-slot__empty" x-show="placed('{{ $region }}').length === 0 && inheritedIn('{{ $region }}').length === 0">
        Nothing here yet
    </p>
</div>
