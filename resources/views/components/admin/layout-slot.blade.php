@props(['region', 'label', 'description' => null, 'side' => null])

{{-- One position on the page, drawn as a list of widget lines. Everything
     inside is Alpine state from layoutEditor(), so a position is markup and
     holds no logic of its own.

     A position holds rows; a row holds columns; a column holds widgets in
     order. A row with one column has no chrome of its own: its widgets read
     as plain lines, and its columns and style sit behind the widget's Edit.
     A row split into columns shows them, with its own Columns and style. --}}
@php($vR = "'" . $region . "'")
<section class="v-slot {{ $side ? 'v-slot--side' : '' }}"
         :class="{ 'is-target': dragging }"
         aria-labelledby="v-slot-{{ $region }}"
         @dragover.prevent
         @drop.prevent="dropIntoRegion({{ $vR }})">

    <header class="v-slot__head">
        <div class="v-slot__titles">
            <h2 class="v-slot__name" id="v-slot-{{ $region }}">{{ $label }}</h2>
            @if ($description)
                <p class="v-slot__hint" @if ($side) x-show="cols.{{ $side }} > 0" @endif>{{ $description }}</p>
            @endif
            @if ($side)
                <p class="v-slot__hint" x-show="cols.{{ $side }} === 0" x-cloak>Off on this page. Adding a widget turns it on.</p>
            @endif
        </div>

        {{-- Templates: keep this position's widgets under a name, bring a
             kept set back, or carry it to another store as a file. Nothing
             here writes the page; Save does. --}}
        <div class="v-slot__menuwrap">
            <button type="button" class="v-btn v-btn--ghost v-btn--sm v-slot__menubtn"
                    id="v-menubtn-{{ $region }}"
                    aria-haspopup="menu" aria-controls="v-menu-{{ $region }}"
                    :aria-expanded="menuFor === {{ $vR }} ? 'true' : 'false'"
                    @click="toggleMenu({{ $vR }}, $el)"
                    @keydown.arrow-down.prevent="openMenu({{ $vR }}, $el)">
                Templates
                <x-admin.icon name="chevron-down" />
            </button>
            <div class="v-slot__menu" id="v-menu-{{ $region }}" role="menu"
                 aria-labelledby="v-menubtn-{{ $region }}"
                 x-show="menuFor === {{ $vR }}" x-cloak
                 @keydown="menuKey($event)"
                 @click.outside="if (menuFor === {{ $vR }}) closeMenu(false)">
                <button type="button" role="menuitem" tabindex="-1" class="v-slot__item"
                        :aria-disabled="rowsIn({{ $vR }}).length === 0 ? 'true' : 'false'"
                        @click="startTemplate({{ $vR }}, 'save', $event)">Save this position as a template</button>
                <button type="button" role="menuitem" tabindex="-1" class="v-slot__item"
                        :aria-disabled="templates.length === 0 ? 'true' : 'false'"
                        @click="startTemplate({{ $vR }}, 'insert', $event)">Insert a template</button>
                <button type="button" role="menuitem" tabindex="-1" class="v-slot__item"
                        :aria-disabled="templates.length === 0 ? 'true' : 'false'"
                        @click="startTemplate({{ $vR }}, 'replace', $event)">Replace with a template</button>
                <button type="button" role="menuitem" tabindex="-1" class="v-slot__item"
                        :aria-disabled="savedTemplates().length === 0 ? 'true' : 'false'"
                        @click="startTemplate({{ $vR }}, 'delete', $event)">Delete a template</button>
                <div class="v-slot__sep" role="separator"></div>
                <button type="button" role="menuitem" tabindex="-1" class="v-slot__item"
                        :aria-disabled="rowsIn({{ $vR }}).length === 0 ? 'true' : 'false'"
                        @click="menuExport({{ $vR }}, $event)">Export to a file</button>
                <button type="button" role="menuitem" tabindex="-1" class="v-slot__item"
                        @click="menuImport({{ $vR }})">Import from a file</button>
            </div>
            <input type="file" class="v-slot__file" id="v-import-{{ $region }}" tabindex="-1" aria-hidden="true"
                   accept="application/json,.json" @change="importRegion({{ $vR }}, $event)">
        </div>
    </header>

    {{-- The template form the menu opened, in the position it acts on. --}}
    <div class="v-slot__tpl" x-show="tplFor === {{ $vR }}" x-cloak
         role="group" :aria-label="tplTitle()"
         @keydown.escape.stop="closeTemplate()">
        <p class="v-slot__tplhead" x-text="tplTitle()"></p>
        <template x-if="tplFor === {{ $vR }} && tplMode === 'save'">
            <div class="v-slot__tplrow">
                <label class="v-slot__tplfield">
                    <span>Template name</span>
                    <input type="text" class="v-control" x-model="templateName" maxlength="80" data-tpl-first>
                </label>
                <button type="button" class="v-btn v-btn--secondary v-btn--sm"
                        @click="saveTemplate({{ $vR }})" :disabled="!templateName.trim()">Save template</button>
                <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="closeTemplate()">Cancel</button>
            </div>
        </template>
        <template x-if="tplFor === {{ $vR }} && tplMode !== 'save'">
            <div class="v-slot__tplrow">
                <label class="v-slot__tplfield">
                    <span>Template</span>
                    <select class="v-control" @change="tplChoice = $event.target.value" data-tpl-first>
                        <option value="">Choose a template&hellip;</option>
                        <template x-for="t in tplChoices()" :key="t.id">
                            <option :value="t.id" :selected="String(t.id) === String(tplChoice)"
                                    x-text="t.name + (t.region ? ' (' + regionLabel(t.region) + ')' : '')"></option>
                        </template>
                    </select>
                </label>
                <button type="button" class="v-btn v-btn--sm"
                        :class="tplMode === 'delete' ? 'v-btn--danger' : 'v-btn--secondary'"
                        @click="runTemplate({{ $vR }})" :disabled="!tplChoice" x-text="tplAction()"></button>
                <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="closeTemplate()">Cancel</button>
            </div>
        </template>
    </div>

    {{-- What just changed here, shown beside the change. Screen readers hear
         it once, from the save bar's live region. --}}
    <p class="v-slot__said" aria-hidden="true" x-show="announcement && saidIn === {{ $vR }}" x-cloak x-text="announcement"></p>

    {{-- Inherited from Every page: shown so nothing appears on a page
         unexplained, and edited only where it belongs. --}}
    <template x-for="(row, k) in inheritedCards({{ $vR }})" :key="'i' + k">
        <div class="v-line v-line--inherit">
            <span class="v-line__text">
                <span class="v-line__nm" x-text="lineName(row)"></span>
                <span class="v-line__meta"><span x-text="row.label"></span><span>from Every page</span></span>
            </span>
            <span class="v-line__acts">
                <a class="v-textbtn" :href="everyPageUrl">Edit on Every page</a>
            </span>
        </div>
    </template>

    <template x-for="(unit, u) in rowsIn({{ $vR }})" :key="unit.key">
        <div class="v-lrow"
             :class="{ 'is-split': unit.columns.length > 1, 'is-group': unit.columns.length === 1 && unit.cols[0].length > 1, 'is-off': !unit.enabled }">

            <template x-if="unit.columns.length > 1">
                <div class="v-lrow__head">
                    <span class="v-lrow__what" x-text="unit.columns.length + ' columns side by side'"></span>
                    <span class="v-tag v-tag--off" x-show="!unit.enabled">Hidden</span>
                    <span class="v-tag v-tag--off" x-show="styledRow(unit)">Styled</span>
                    <span class="v-tag v-tag--off" x-show="unit.shipped">Theme default</span>
                    <button type="button" class="v-textbtn v-lrow__tools"
                            :aria-expanded="rowTools === unit ? 'true' : 'false'"
                            @click="rowTools = (rowTools === unit ? null : unit)">Columns and style</button>
                </div>
            </template>

            <template x-if="unit.columns.length > 1 && rowTools === unit">
                <div class="v-lrow__panel">
                    @include('admin.layout.partials.row-tools', ['unitExpr' => 'unit', 'regionExpr' => $vR])
                </div>
            </template>

            <div class="v-lrow__cols" :class="'v-lrow__cols--' + unit.columns.length">
                <template x-for="(col, c) in unit.cols" :key="c">
                    <div class="v-lrowcol" :class="{ 'is-target': dragging }" :style="'flex-grow:' + unit.columns[c]"
                         @dragover.prevent @drop.stop.prevent="drop({{ $vR }}, u, c)">
                        <p class="v-lrowcol__lbl" x-show="unit.columns.length > 1" x-text="'Column ' + (c + 1)"></p>

                        <template x-for="(row, i) in col" :key="row.key">
                            <div class="v-line" :data-key="row.key"
                                 :class="{ 'is-off': !row.enabled || !unit.enabled, 'is-gone': !row.available, 'is-editing': editing === row }"
                                 draggable="true" @dragstart.stop="start({{ $vR }}, u, c, i)"
                                 @drop.stop.prevent="drop({{ $vR }}, u, c, i)">
                                <span class="v-line__grip" aria-hidden="true"><x-admin.icon name="drag" /></span>
                                <span class="v-line__text">
                                    <span class="v-line__nm" x-text="lineName(row)"></span>
                                    <span class="v-line__meta">
                                        <span x-text="lineType(row)"></span>
                                        <span class="v-tag v-tag--off" x-show="!row.enabled || !unit.enabled">Hidden</span>
                                        <span class="v-tag v-tag--off" x-show="styledCard(row) || (unit.columns.length === 1 && styledRow(unit))">Styled</span>
                                        <span class="v-tag v-tag--off" x-show="unit.shipped">Theme default</span>
                                        <span class="v-tag v-tag--off" x-show="!row.available"
                                              title="The extension behind this widget is switched off, so it shows nothing">Switched off</span>
                                    </span>
                                </span>

                                <span class="v-line__acts">
                                    <button type="button" class="v-iconbtn" @click="lineMove({{ $vR }}, u, c, i, -1)"
                                            :disabled="!canMove({{ $vR }}, u, c, i, -1)"
                                            :aria-label="'Move ' + lineName(row) + ' up'" title="Move up">
                                        <x-admin.icon name="chevron-up" />
                                    </button>
                                    <button type="button" class="v-iconbtn" @click="lineMove({{ $vR }}, u, c, i, 1)"
                                            :disabled="!canMove({{ $vR }}, u, c, i, 1)"
                                            :aria-label="'Move ' + lineName(row) + ' down'" title="Move down">
                                        <x-admin.icon name="chevron-down" />
                                    </button>
                                    <button type="button" class="v-textbtn" x-show="subjectOf(row)" x-cloak
                                            @click="openSubject(row)"
                                            x-text="subjectOf(row)?.browse_label || 'Choose'"></button>
                                    <button type="button" class="v-textbtn v-line__edit" @click="edit(row, {{ $vR }}, unit)"
                                            :aria-label="'Edit ' + lineName(row)">Edit</button>
                                    <button type="button" class="v-textbtn" @click="toggle(row, {{ $vR }})"
                                            :aria-label="(row.enabled ? 'Hide ' : 'Show ') + lineName(row)"
                                            x-text="row.enabled ? 'Hide' : 'Show'"></button>
                                    <button type="button" class="v-textbtn v-textbtn--danger" @click="remove({{ $vR }}, u, c, i)"
                                            :aria-label="'Remove ' + lineName(row)">Remove</button>
                                </span>
                            </div>
                        </template>

                        <template x-if="unit.columns.length > 1">
                            <button type="button" class="v-textbtn v-lrowcol__add"
                                    @click="openPicker({{ $vR }}, $el, u, c)"
                                    :aria-expanded="isAdding({{ $vR }}, u, c) ? 'true' : 'false'"
                                    :aria-label="'Add a widget to column ' + (c + 1)">
                                <x-admin.icon name="plus" /> Add widget
                            </button>
                        </template>
                        <template x-if="isAdding({{ $vR }}, u, c)">
                            @include('admin.layout.partials.widget-picker', ['label' => $label])
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <p class="v-slot__empty" x-show="rowsIn({{ $vR }}).length === 0 && inheritedIn({{ $vR }}).length === 0">
        No widgets here yet.
    </p>

    <button type="button" class="v-slot__add" @click="openPicker({{ $vR }}, $el)"
            :aria-expanded="isAdding({{ $vR }}, null, null) ? 'true' : 'false'"
            aria-label="Add widget to {{ $label }}">
        <x-admin.icon name="plus" />
        <span>Add widget</span>
    </button>
    <template x-if="isAdding({{ $vR }}, null, null)">
        @include('admin.layout.partials.widget-picker', ['label' => $label])
    </template>
</section>
