@props(['region', 'label', 'side' => false])

{{-- One position on the page. Everything inside is Alpine state from
     layoutEditor(), so a slot is markup and holds no logic of its own.

     A position holds ROWS; a row holds columns on the eight-column grid;
     a column holds widgets in order. A position with one row of one column
     reads exactly as the flat list it used to be. --}}
<div class="v-slot {{ $side ? 'v-slot--side' : '' }}"
     :class="{ 'is-target': dragging }"
     @dragover.prevent
     @drop.prevent="dropIntoRegion('{{ $region }}')">

    <div class="v-slot__lbl">
        <span>{{ $label }}</span>
        <span class="mono">{{ $region }}</span>
        {{-- The position's own menu: keep, load, copy, carry. Every act here
             either changes editor state (and arms Save) or is a record on its
             own control; none writes this page behind the Save. --}}
        <button type="button" class="v-slot__menu" @click="tools = (tools === '{{ $region }}' ? null : '{{ $region }}')"
                :aria-expanded="tools === '{{ $region }}' ? 'true' : 'false'" aria-label="Templates for {{ $label }}" title="Templates">&#8943;</button>
        <button type="button" class="v-slot__add" @click="addRow('{{ $region }}', $el)"
                aria-label="Add a row to {{ $label }}" title="Add a row">+</button>
    </div>

    <div class="v-slot__tools" x-show="tools === '{{ $region }}'" x-cloak @keydown.escape.stop="tools = null">
        <div class="v-slot__toolrow">
            <input type="text" x-model="templateName" maxlength="80" aria-label="Template name" placeholder="">
            <button type="button" class="v-btn v-btn--sm" @click="saveTemplate('{{ $region }}')"
                    :disabled="!templateName.trim() || rowsIn('{{ $region }}').length === 0">Save as template</button>
        </div>
        <div class="v-slot__toolrow" x-show="templates.length" x-cloak>
            <select x-ref="tpl_{{ $region }}" aria-label="Load a template">
                <option value="">Load a template&hellip;</option>
                <template x-for="t in templates" :key="t.id">
                    <option :value="t.id" x-text="t.name + (t.region ? ' (' + regionLabel(t.region) + ')' : '')"></option>
                </template>
            </select>
            <button type="button" class="v-btn v-btn--sm" @click="loadTemplate('{{ $region }}', $refs.tpl_{{ $region }}.value, 'append')">Add</button>
            <button type="button" class="v-btn v-btn--sm" @click="loadTemplate('{{ $region }}', $refs.tpl_{{ $region }}.value, 'replace')">Replace</button>
            <button type="button" class="v-lay__new" @click="deleteTemplate($refs.tpl_{{ $region }}.value)">Delete</button>
        </div>
        <div class="v-slot__toolrow">
            <select x-ref="copy_{{ $region }}" aria-label="Copy to page">
                <option value="">Copy to&hellip;</option>
                <option value="*">All pages</option>
                @foreach (\App\Support\StorefrontPageType::TYPES as $vKey => $vLabel)
                    <option value="{{ $vKey }}">{{ $vLabel }}</option>
                @endforeach
            </select>
            <button type="button" class="v-btn v-btn--sm" @click="copyRegion('{{ $region }}', $refs.copy_{{ $region }}.value)"
                    :disabled="rowsIn('{{ $region }}').length === 0">Copy</button>
        </div>
        <div class="v-slot__toolrow">
            <button type="button" class="v-lay__new" @click="exportRegion('{{ $region }}')" :disabled="rowsIn('{{ $region }}').length === 0">Export</button>
            <label class="v-lay__new v-slot__import">Import
                <input type="file" accept="application/json,.json" @change="importRegion('{{ $region }}', $event)">
            </label>
        </div>
    </div>

    {{-- Inherited from All pages: shown so nothing appears on a page
         unexplained, and not editable from here, because it does not
         belong to this page. --}}
    <template x-for="(unit, u) in inheritedIn('{{ $region }}')" :key="'i' + u">
        <div class="v-lrow v-lrow--inherit">
            <div class="v-lrow__cols" :class="'v-lrow__cols--' + unit.columns.length">
                <template x-for="(col, c) in unit.cols" :key="c">
                    <div class="v-lrowcol" :style="'flex-grow:' + unit.columns[c]">
                        <template x-for="row in col" :key="'i' + row.id">
                            <div class="v-card v-card--inherit">
                                <span class="v-card__nm" x-text="row.title || row.widget"></span>
                                <span class="v-tag">All pages</span>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <template x-for="(unit, u) in rowsIn('{{ $region }}')" :key="unit.key">
        <div class="v-lrow" :class="{ 'is-off': !unit.enabled, 'is-split': unit.columns.length > 1, 'is-shipped': unit.shipped }"
             tabindex="0" role="group" :aria-label="'Row ' + (u + 1) + ' in {{ $label }}'"
             @keydown.ctrl.arrow-up.prevent="nudgeRow('{{ $region }}', u, -1)"
             @keydown.ctrl.arrow-down.prevent="nudgeRow('{{ $region }}', u, 1)">

            <div class="v-lrow__head">
                <span class="v-lrow__grip" aria-hidden="true">⣿</span>
                <span class="v-tag" x-show="unit.shipped" x-cloak>As shipped</span>

                {{-- The shape of the row, drawn: a merchant picks between
                     shapes, and the words for shapes are the worst available
                     description of them. --}}
                <span class="v-lrow__shapes" role="group" aria-label="Columns">
                    <template x-for="shape in layouts" :key="shape.join('-')">
                        <button type="button" class="v-lshape" :class="{ 'is-on': sameShape(unit.columns, shape) }"
                                @click="setShape('{{ $region }}', u, shape)"
                                :aria-pressed="sameShape(unit.columns, shape) ? 'true' : 'false'"
                                :aria-label="shape.join(' + ') + ' of 8'" :title="shape.join(' + ')">
                            <template x-for="(w, i) in shape" :key="i">
                                <i :style="'flex-grow:' + w"></i>
                            </template>
                        </button>
                    </template>
                </span>

                <span class="v-lrow__acts">
                    <button type="button" class="v-lrow__paint" :class="{ 'is-on': styled(unit), 'is-editing': editingRow === unit }"
                            @click="editRow('{{ $region }}', u)" aria-label="Row style" title="Row style">&#9679;</button>
                    <button type="button" @click="nudgeRow('{{ $region }}', u, -1)" :disabled="u === 0"
                            aria-label="Move row up" title="Move row up">&uarr;</button>
                    <button type="button" @click="nudgeRow('{{ $region }}', u, 1)"
                            :disabled="u === rowsIn('{{ $region }}').length - 1"
                            aria-label="Move row down" title="Move row down">&darr;</button>
                    <button type="button" @click="toggleRow('{{ $region }}', u)"
                            :aria-pressed="unit.enabled ? 'false' : 'true'"
                            :aria-label="unit.enabled ? 'Hide this row' : 'Show this row'"
                            :title="unit.enabled ? 'Visible' : 'Hidden'">
                        <span x-text="unit.enabled ? '◉' : '○'"></span>
                    </button>
                    <button type="button" @click="removeRow('{{ $region }}', u)"
                            aria-label="Remove this row" title="Remove row">&times;</button>
                </span>
            </div>

            <div class="v-lrow__cols" :class="'v-lrow__cols--' + unit.columns.length">
                <template x-for="(col, c) in unit.cols" :key="c">
                    <div class="v-lrowcol" :class="{ 'is-target': dragging }" :style="'flex-grow:' + unit.columns[c]"
                         @dragover.prevent @drop.stop.prevent="drop('{{ $region }}', u, c)">

                        <template x-for="(row, i) in col" :key="row.id ?? ('n' + u + '-' + c + '-' + i)">
                            {{-- Reachable, and movable without a mouse. --}}
                            <div class="v-card" :class="{ 'is-off': !row.enabled, 'is-gone': !row.available, 'is-editing': editing === row }"
                                 tabindex="0" role="group" :aria-label="(row.title || row.label) + ' in {{ $label }}'"
                                 draggable="true" @dragstart.stop="start('{{ $region }}', u, c, i)"
                                 @drop.stop.prevent="drop('{{ $region }}', u, c, i)"
                                 @keydown.ctrl.arrow-up.prevent="nudgeCard('{{ $region }}', u, c, i, -1)"
                                 @keydown.ctrl.arrow-down.prevent="nudgeCard('{{ $region }}', u, c, i, 1)">
                                <span class="v-card__grip" aria-hidden="true">⣿</span>
                                <span class="v-card__nm" x-text="row.title || row.label"></span>
                                <span class="v-card__kind mono" x-text="row.label" x-show="row.title && row.title !== row.label"></span>
                                <span class="v-card__subj" x-show="subjectOf(row)?.chosen" x-cloak
                                      x-text="subjectOf(row)?.chosen?.label"></span>
                                <span class="v-tag v-tag--off" x-show="!row.available">Module off</span>

                                <span class="v-card__acts">
                                    <button type="button" class="v-card__open" x-show="subjectOf(row)" x-cloak
                                            @click="openSubject(row)"
                                            :aria-label="'Edit ' + (subjectOf(row)?.chosen?.label || (row.title || row.label))"
                                            x-text="subjectOf(row)?.browse_label || 'Edit'"></button>
                                    <button type="button" @click="nudgeCard('{{ $region }}', u, c, i, -1)"
                                            :disabled="i === 0" aria-label="Move up" title="Move up">&uarr;</button>
                                    <button type="button" @click="nudgeCard('{{ $region }}', u, c, i, 1)"
                                            :disabled="i === col.length - 1" aria-label="Move down" title="Move down">&darr;</button>
                                    <button type="button" @click="moving = (moving === row ? null : row)"
                                            aria-label="Move to another position" title="Move to&hellip;">&hellip;</button>
                                    <button type="button" @click="edit(row)" aria-label="Settings" title="Settings">&#9881;</button>
                                    <button type="button" @click="toggle(row)"
                                            :aria-pressed="row.enabled ? 'false' : 'true'"
                                            :aria-label="row.enabled ? 'Hide this widget' : 'Show this widget'"
                                            :title="row.enabled ? 'Visible' : 'Hidden'">
                                        <span x-text="row.enabled ? '◉' : '○'"></span>
                                    </button>
                                    <button type="button" @click="remove('{{ $region }}', u, c, i)"
                                            aria-label="Remove this widget" title="Remove">&times;</button>
                                </span>
                            </div>
                        </template>

                        {{-- Moving between positions without dragging. Offers only
                             the positions this widget accepts and that are open. --}}
                        <template x-for="(row, i) in col" :key="'m' + (row.id ?? (u + '-' + c + '-' + i))">
                            <div class="v-card__moves" x-show="moving === row" x-cloak>
                                <template x-for="target in movesFor(row.widget)" :key="target">
                                    <button type="button" @click="moveTo('{{ $region }}', u, c, i, target)"
                                            x-show="target !== '{{ $region }}'" x-text="regionLabel(target)"></button>
                                </template>
                            </div>
                        </template>

                        <button type="button" class="v-lrowcol__add" @click="openPicker('{{ $region }}', u, c, $el)"
                                :aria-expanded="isAdding('{{ $region }}', u, c) ? 'true' : 'false'"
                                aria-label="Add a widget to this column">+ Widget</button>

                        {{-- The choices open INSIDE the column they are for. --}}
                        <template x-if="isAdding('{{ $region }}', u, c)">
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
                    </div>
                </template>
            </div>
        </div>
    </template>

    <p class="v-slot__empty" x-show="rowsIn('{{ $region }}').length === 0 && inheritedIn('{{ $region }}').length === 0">
        Nothing here yet
    </p>
</div>
