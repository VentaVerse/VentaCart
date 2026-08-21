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
        <button type="button" class="v-slot__add" @click="openPicker('{{ $region }}', $el)"
                :aria-expanded="adding === '{{ $region }}' ? 'true' : 'false'"
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
        {{-- Reachable, and movable without a mouse. It was a draggable div with
             no role and no tabindex, so reordering worked for exactly one input
             device: not touch, not the keyboard, not assistive tech. --}}
        <div class="v-card" :class="{ 'is-off': !row.enabled, 'is-gone': !row.available, 'is-editing': editing === row }"
             tabindex="0" role="group" :aria-label="(row.title || row.label) + ' in {{ $label }}'"
             draggable="true" @dragstart="start('{{ $region }}', i)"
             @drop.stop.prevent="drop('{{ $region }}', i)"
             @keydown.ctrl.arrow-up.prevent="nudgeCard('{{ $region }}', i, -1)"
             @keydown.ctrl.arrow-down.prevent="nudgeCard('{{ $region }}', i, 1)">
            <span class="v-card__grip" aria-hidden="true">⣿</span>
            <span class="v-card__nm" x-text="row.title || row.label"></span>
            {{-- The widget's own name, so two "Product grid" cards a merchant
                 renamed are still tellable apart. --}}
            <span class="v-card__kind mono" x-text="row.label" x-show="row.title && row.title !== row.label"></span>

            {{-- Which record it is showing. Two Banner group cards are
                 otherwise the same card twice. --}}
            <span class="v-card__subj" x-show="subjectOf(row)?.chosen" x-cloak
                  x-text="subjectOf(row)?.chosen?.label"></span>

            <span class="v-tag v-tag--off" x-show="!row.available">Module off</span>

            <span class="v-card__acts">
                {{-- Straight into what this card is showing. Only appears when
                     the widget points at a record something else owns, so a
                     Product grid never carries a control that leads nowhere. --}}
                <button type="button" class="v-card__open" x-show="subjectOf(row)" x-cloak
                        @click="openSubject(row)"
                        :aria-label="'Edit ' + (subjectOf(row)?.chosen?.label || (row.title || row.label))"
                        x-text="subjectOf(row)?.browse_label || 'Edit'"></button>

                <button type="button" @click="nudgeCard('{{ $region }}', i, -1)"
                        :disabled="i === 0" aria-label="Move up" title="Move up">&uarr;</button>
                <button type="button" @click="nudgeCard('{{ $region }}', i, 1)"
                        :disabled="i === placed('{{ $region }}').length - 1"
                        aria-label="Move down" title="Move down">&darr;</button>
                <button type="button" @click="moving = (moving === row ? null : row)"
                        aria-label="Move to another position" title="Move to&hellip;">&hellip;</button>
                <button type="button" @click="edit('{{ $region }}', i)"
                        aria-label="Settings" title="Settings">&#9881;</button>
                <button type="button" @click="toggle('{{ $region }}', i)"
                        :aria-pressed="row.enabled ? 'false' : 'true'"
                        :aria-label="row.enabled ? 'Hide this widget' : 'Show this widget'"
                        :title="row.enabled ? 'Visible' : 'Hidden'">
                    <span x-text="row.enabled ? '◉' : '○'"></span>
                </button>
                <button type="button" @click="remove('{{ $region }}', i)"
                        aria-label="Remove this widget" title="Remove">&times;</button>
            </span>
        </div>
    </template>

    {{-- Moving between positions without dragging. Offers only the positions
         this widget accepts and that are actually open. --}}
    <template x-for="(row, i) in placed('{{ $region }}')" :key="'m' + (row.id ?? i)">
        <div class="v-card__moves" x-show="moving === row" x-cloak>
            <template x-for="target in movesFor(row.widget)" :key="target">
                <button type="button" @click="moveTo('{{ $region }}', i, target)"
                        x-show="target !== '{{ $region }}'" x-text="regionLabel(target)"></button>
            </template>
        </div>
    </template>

    {{-- The choices open INSIDE the position they are for.

         The picker was one fixed panel in the viewport's bottom-right corner,
         so every plus on the page opened it in the same place: 307px below the
         one at the top, 418px ABOVE the one at the bottom, and on top of the
         cards in the very slot being filled. It was also the last element in
         the document, which put its first option 49 tab presses away from the
         button that opened it. Opening in place answers all three, and gives
         the room to draw each widget rather than name it. --}}
    <template x-if="adding === '{{ $region }}'">
        <div class="v-slot__pick" role="dialog" aria-modal="false"
             aria-label="Add a widget to {{ $label }}"
             @keydown.escape.stop="closePicker()">
            <template x-for="w in offerable('{{ $region }}')" :key="w.key">
                <button type="button" class="v-pickcard" @click="add(w)">
                    <span class="v-wire" :class="'v-wire--' + (w.shape || 'block')" aria-hidden="true">
                        <i></i><i></i><i></i><i></i><i></i><i></i>
                    </span>
                    <span class="v-pickcard__nm" x-text="w.title"></span>
                </button>
            </template>

            <p class="v-slot__empty" x-show="offerable('{{ $region }}').length === 0">
                No widget fits this position
            </p>

            <button type="button" class="v-slot__pickx" @click="closePicker()">Cancel</button>
        </div>
    </template>

    <p class="v-slot__empty" x-show="adding !== '{{ $region }}' && placed('{{ $region }}').length === 0 && inheritedIn('{{ $region }}').length === 0">
        Nothing here yet
    </p>
</div>
