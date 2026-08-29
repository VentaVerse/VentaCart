<x-admin.shell title="Layout">
    @push('head')
        @vite('extensions/theme/resources/css/theme-layout.css')
    @endpush

    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.theme.index') }}">Themes</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.theme.customise') }}">{{ $theme }}</a>
                <span aria-hidden="true">/</span>
                <span>Layout</span>
            </div>
            <h1 class="v-head__title">Layout</h1>
        </div>
        <div class="v-head__actions">
            {{-- The screen changes what every shopper sees and had no path to
                 the storefront it edits. --}}
            <a href="{{ $sampleUrl }}" target="_blank" rel="noopener" class="v-btn v-btn--sm">View store</a>
        </div>
    </div>

    <x-admin.form :action="route('admin.theme.layout.update')">
      <div class="v-lay" :class="{ 'is-inspecting': editing }" x-data="layoutEditor(window.__layout)">
        <input type="hidden" name="payload" :value="payload()">

        {{-- On a phone the 12-link rail filled the whole first viewport before
             the editor was reachable, so there it collapses to one control. --}}
        <label class="v-lay__pagepick">
            <span>Page</span>
            <select onchange="window.location = this.value">
                <option value="{{ route('admin.theme.layout', ['page' => '*']) }}" @selected($pageType === '*')>All pages</option>
                @foreach ($pageTypes as $vKey => $vLabel)
                    <option value="{{ route('admin.theme.layout', ['page' => $vKey]) }}" @selected($pageType === $vKey)>{{ $vLabel }}</option>
                @endforeach
            </select>
        </label>

        {{-- which page --}}
        <nav class="v-lay__rail" aria-label="Pages">
            <div class="v-lay__raillbl">Everywhere</div>
            <a href="{{ route('admin.theme.layout', ['page' => '*']) }}"
               class="{{ $pageType === '*' ? 'is-on' : '' }}">
                <span>All pages</span>
                @if (($counts['*'] ?? 0) > 0)<span class="n">{{ $counts['*'] }}</span>@endif
            </a>

            <div class="v-lay__raillbl">Pages</div>
            @foreach ($pageTypes as $key => $label)
                <a href="{{ route('admin.theme.layout', ['page' => $key]) }}"
                   class="{{ $pageType === $key ? 'is-on' : '' }}">
                    <span>{{ $label }}</span>
                    @if (($counts[$key] ?? 0) > 0)<span class="n">{{ $counts[$key] }}</span>@endif
                </a>
            @endforeach
        </nav>

        {{-- the page, drawn as a page --}}
        <div class="v-lay__stage">
            {{-- Inside the stage, not beside it.

                 It was a direct child of the .v-lay grid carrying
                 grid-column: 1 / -1, so the moment it appeared it claimed a
                 whole row, and the auto-placed stage below it fell into the
                 rail's 176px column: the entire editor collapsed to a
                 narrow strip on every announcement. Latent while the live
                 region only spoke for moves and rescues, and immediate once
                 adding a widget started announcing too. It belongs in here
                 anyway, because everything it reports happened in here. --}}
            <p class="v-lay__said" role="status" aria-live="polite" x-text="announcement" x-show="announcement" x-cloak></p>

            {{-- Placements in positions this theme no longer has. Kept, never
                 deleted, and now actually shown: they were computed since the
                 layout system landed and no view ever drew them, so a theme
                 update that dropped a region hid the work in silence. --}}
            <div class="v-lay__orphans" x-show="orphans.length" x-cloak>
                <p>
                    <x-admin.icon name="warning" />
                    <span x-text="orphans.length + (orphans.length === 1 ? ' widget sits' : ' widgets sit') + ' in a position this theme does not have. They render nothing until you move them.'"></span>
                </p>
                <template x-for="o in orphans" :key="o.id">
                    <div class="v-lay__orphan">
                        <span x-text="o.title || o.widget"></span>
                        <span class="mono" x-text="o.page + ' / ' + o.region"></span>
                        <button type="button" @click="rescue(o)">Move to Below the content</button>
                    </div>
                </template>
            </div>

            <div class="v-lay__page">
                {{-- top --}}
                @if (isset($regions['header_top']))
                    <div class="v-lay__strip">
                        <x-theme::layout-slot region="header_top" label="Above the header" />
                    </div>
                @endif

                <div class="v-lay__chrome" :class="{ 'is-off': !partOn('header') }">
                    <span class="v-lay__logo"></span>
                    <span class="v-lay__line"></span>
                    <span>header</span>
                    <template x-for="f in partsIn('header')" :key="f.name">
                        <x-theme::part-switch />
                    </template>
                </div>

                {{-- the row --}}
                <div class="v-lay__middle">
                    @if (isset($regions['column_left']))
                        {{-- The right column had a stub and the left never did, so a
                             filter rail, the commonest sidebar in commerce, could not
                             be made from this screen at any width. --}}
                        <template x-if="cols.left === 0">
                            <button type="button" class="v-lay__stub" @click="addColumn('left')"
                                    title="Add a left column" aria-label="Add a left column">+</button>
                        </template>
                        <template x-if="cols.left > 0">
                            <div class="v-lay__col v-lay__col--side" :style="'width:' + (cols.left / grid * 100) + '%'">
                                <x-theme::layout-slot region="column_left" label="Left column" side="true" />
                            </div>
                        </template>
                        <template x-if="cols.left > 0">
                            <div class="v-lay__handle" role="separator" tabindex="0"
                                 aria-label="Left column width"
                                 :aria-valuenow="cols.left" aria-valuemin="0" :aria-valuemax="grid - minContent"
                                 aria-orientation="vertical"
                                 @pointerdown="grab('left', $event)"
                                 @keydown.arrow-left.prevent="nudge('left', -1)"
                                 @keydown.arrow-right.prevent="nudge('left', 1)"></div>
                        </template>
                    @endif

                    <div class="v-lay__col v-lay__col--center">
                        @if (isset($regions['content_top']))
                            <x-theme::layout-slot region="content_top" label="Above the content" />
                        @endif

                        {{-- The parts of the hardcoded page this theme lets a
                             merchant switch off, drawn where they actually sit.
                             The control on the thing it governs answers "on this
                             page" by standing on the page, and switching one off
                             dims the part rather than changing nothing anyone
                             can see. --}}
                        <div class="v-lay__pagebody">
                            <template x-for="f in partsIn('body')" :key="f.name">
                                <div class="v-lay__part" :class="{ 'is-off': !f.value }">
                                    <span class="v-lay__partbar" aria-hidden="true"></span>
                                    <x-theme::part-switch />
                                </div>
                            </template>
                            <b>The page itself</b>
                        </div>

                        @if (isset($regions['content_bottom']))
                            <x-theme::layout-slot region="content_bottom" label="Below the content" />
                        @endif
                    </div>

                    @if (isset($regions['column_right']))
                        <template x-if="cols.right > 0">
                            <div class="v-lay__handle" role="separator" tabindex="0"
                                 aria-label="Right column width"
                                 :aria-valuenow="cols.right" aria-valuemin="0" :aria-valuemax="grid - minContent"
                                 aria-orientation="vertical"
                                 @pointerdown="grab('right', $event)"
                                 @keydown.arrow-left.prevent="nudge('right', 1)"
                                 @keydown.arrow-right.prevent="nudge('right', -1)"></div>
                        </template>
                        <template x-if="cols.right > 0">
                            <div class="v-lay__col v-lay__col--side" :style="'width:' + (cols.right / grid * 100) + '%'">
                                <x-theme::layout-slot region="column_right" label="Right column" side="true" />
                            </div>
                        </template>
                        <template x-if="cols.right === 0">
                            <button type="button" class="v-lay__stub" @click="cols.right = 2; touch()"
                                    title="Add a right column" aria-label="Add a right column">+</button>
                        </template>
                    @endif
                </div>

                <div class="v-lay__chrome" :class="{ 'is-off': !partOn('footer') }">
                    <span class="v-lay__line"></span>
                    <span>footer</span>
                    <template x-for="f in partsIn('footer')" :key="f.name">
                        <x-theme::part-switch />
                    </template>
                </div>

                @if (isset($regions['footer_top']))
                    <div class="v-lay__strip">
                        <x-theme::layout-slot region="footer_top" label="Above the footer" />
                    </div>
                @endif

                <div class="v-lay__ruler">
                    <span>Columns</span>
                    <span class="mono" x-text="cols.left + ' / ' + (grid - cols.left - cols.right) + ' / ' + cols.right"></span>
                    <span class="v-lay__sp"></span>
                    <button type="button" class="v-lay__drop" x-show="cols.left > 0" @click="dropColumn('left')"
                            :aria-label="dropLabel('left')" x-text="dropLabel('left')"></button>
                    <button type="button" class="v-lay__drop" x-show="cols.right > 0" @click="dropColumn('right')"
                            :aria-label="dropLabel('right')" x-text="dropLabel('right')"></button>
                </div>
            </div>
        </div>

        {{-- settings for the selected widget, from its own schema --}}
        <div class="v-lay__inspector" x-show="editing" x-cloak
             @keydown.escape.window="editing = null">
            <div class="v-lay__pickhead">
                <b x-text="editing ? (editing.title || editing.label) : ''"></b>
                <button type="button" @click="editing = null" aria-label="Close">&times;</button>
            </div>

            <label class="v-lay__field">
                <span>Heading shown on the page</span>
                <input type="text" :value="editing?.title ?? ''"
                       @input="editing.title = $event.target.value; touch()">
            </label>

            <template x-for="f in schemaFor(editing?.widget)" :key="f.name">
                <label class="v-lay__field">
                    <span x-text="f.label"></span>

                    <template x-if="f.type === 'select'">
                        <span class="v-lay__withnew">
                            {{-- :selected on the option, not :value on the select.
                                 A select's value cannot bind before x-for has
                                 added its options, so the saved choice showed as
                                 "Choose..." on every open. --}}
                            <select @change="setCfg(f.name, $event.target.value)">
                                <option value="">Choose&hellip;</option>
                                <template x-for="(label, value) in f.options" :key="value">
                                    <option :value="value" :selected="cfg(f.name) === value" x-text="label"></option>
                                </template>
                            </select>
                            {{-- Creating the thing you are placing, without leaving --}}
                            <a x-show="f.create_url" :href="f.create_url" target="_blank"
                               class="v-lay__new" x-text="f.create_label ?? 'New'"></a>
                        </span>
                    </template>

                    <template x-if="f.type === 'number'">
                        <input type="number" :value="cfg(f.name)" @input="setCfg(f.name, $event.target.value)">
                    </template>

                    <template x-if="f.type !== 'select' && f.type !== 'number'">
                        <input type="text" :value="cfg(f.name)" @input="setCfg(f.name, $event.target.value)">
                    </template>
                </label>
            </template>

            <p class="v-lay__pickempty" x-show="schemaFor(editing?.widget).length === 0">
                Nothing to configure
            </p>
        </div>

        {{-- Parts this theme declares but does not say where to draw. They
             have nowhere to stand on the stage, so they stand under it. A
             theme that names every part's home never renders this at all. --}}
        <fieldset class="v-lay__page-settings" x-show="partsIn(null).length" x-cloak>
            <legend>Elsewhere on this page</legend>
            <template x-for="f in partsIn(null)" :key="f.name">
                <x-theme::part-switch />
            </template>
        </fieldset>

        {{-- The widget's own subject, edited without leaving the layout.

             Two levels, and they belong to different owners. The first is
             ours: which record sits in this position is layout state, so
             choosing one arms this page's Save and writes nothing. Below
             that is the module's own page in a frame, saving itself the way
             every record editor opened by an explicit Edit already does. The
             two save scopes alternate by level and never overlap, which is
             what makes the nesting safe. --}}
        <div class="v-lay__panel" x-show="panel" x-cloak
             x-dialog="!!panel"
             @keydown.escape.window="closeSubject()"
             role="dialog" aria-modal="true" aria-labelledby="v-panel-title">
            {{-- The chooser is a short list and the editor is a whole page,
                 so the sheet is sized for what it is holding rather than
                 stretched to the tallest thing it might hold. --}}
            <div class="v-lay__sheet" :class="{ 'is-framed': frameUrl }" @click.outside="closeSubject()">
                <div class="v-lay__phead">
                    <button type="button" class="v-lay__pback" x-show="frameUrl" x-cloak
                            @click="panelBack()" aria-label="Back">&larr;</button>

                    <div class="v-lay__ptrail">
                        <template x-if="frameUrl">
                            <button type="button" @click="frameUrl = null" x-text="panel?.field?.label"></button>
                        </template>
                        <span aria-hidden="true" x-show="frameUrl" x-cloak>/</span>
                        <b id="v-panel-title" x-text="frameUrl ? (frameTitle || '&hellip;') : panel?.field?.label"></b>
                        <span class="v-lay__powner" :class="{ 'is-module': frameUrl }"
                              x-text="frameUrl ? ownerOf(panel?.row) : 'Layout'"></span>
                    </div>

                    <button type="button" class="v-lay__pclose" @click="closeSubject()"
                            aria-label="Close" data-v-dialog-initial>&times;</button>
                </div>

                <div class="v-lay__pbody">
                    {{-- level one: the choices, drawn from rows the module handed
                         over. No module code renders here, so a widget pointing at
                         a category gets this panel the day it declares one. --}}
                    <div class="v-lay__browse" x-show="!frameUrl">
                        <template x-for="choice in panel?.field?.browse ?? []" :key="choice.value">
                            <div class="v-lay__choice" :class="{ 'is-on': choice.value === panelValue() }">
                                <button type="button" class="v-lay__choicepick"
                                        @click="chooseSubject(choice)"
                                        :aria-pressed="choice.value === panelValue() ? 'true' : 'false'">
                                    <span class="v-lay__thumbs" aria-hidden="true">
                                        <template x-for="thumb in choice.thumbs" :key="thumb">
                                            <img :src="thumb" alt="" loading="lazy">
                                        </template>
                                    </span>
                                    <span class="v-lay__choicetxt">
                                        <span class="v-lay__choicenm" x-text="choice.label"></span>
                                        <span class="mono" x-text="choice.meta"></span>
                                    </span>
                                    <span class="v-tag" x-show="choice.value === panelValue()" x-cloak>In this position</span>
                                </button>
                                <button type="button" class="v-lay__choicego" x-show="choice.url"
                                        @click="enterSubject(choice)"
                                        :aria-label="'Open ' + choice.label">Open</button>
                            </div>
                        </template>

                        <p class="v-lay__pickempty" x-show="(panel?.field?.browse ?? []).length === 0">
                            Nothing saved yet
                        </p>

                        <a class="v-lay__pnew" x-show="panel?.field?.create_url" :href="panel?.field?.create_url"
                           target="_blank" rel="noopener" x-text="panel?.field?.create_label ?? 'New'"></a>
                    </div>

                    {{-- level two and below: the module's own page, same admin,
                         furniture off. Its Save is its own. --}}
                    {{-- x-if, not x-show: a bound src with nothing in it is
                         still a request, and an empty frame sat there asking
                         the server for /undefined every time the panel opened.
                         Interaction-gated, which is what x-if is still for. --}}
                    <template x-if="frameUrl">
                        <iframe class="v-lay__frame" :src="frameUrl"
                                :title="panel?.field?.label" x-ref="frame"></iframe>
                    </template>
                </div>
            </div>
        </div>

      </div>
    </x-admin.form>

    @push('scripts')
        <script>
            window.__layout = {
                pageType: @js($pageType),
                regions: @js($regions),
                rows: @js($rows),
                inherited: @js($inherited),
                layouts: @js($layouts),
                widgets: @js($widgets),
                schemas: @js($schemas),
                pageSettings: @js($pageSettings),
                orphans: @js($orphans),
                schemasUrl: @js(route('admin.theme.layout.schemas')),
                cols: @js($columns),
                grid: @js($grid),
                minContent: @js($minContent),
            };

            function layoutEditor(seed) {
                // The form element, held in a closure. Inside an x-for scope
                // $el is the control that fired, so touch() dispatched change
                // on the checkbox that called it and re-entered itself:
                // "Maximum call stack size exceeded" on every page-setting click.
                let root = null;
                let nextKey = 1;

                // A row as the editor holds it: its columns (widths in eighths),
                // its cards per column, and a stable key for x-for so a row
                // keeps its DOM while it is moved.
                const wrap = (unit) => ({
                    key: 'r' + (nextKey++),
                    id: unit.id ?? null,
                    columns: unit.columns && unit.columns.length ? unit.columns.slice() : [8],
                    enabled: unit.enabled !== false,
                    cols: (unit.cols || [[]]).map((c) => c.slice()),
                });

                const seedRows = {};
                const seeded = Array.isArray(seed.rows) ? {} : (seed.rows || {});
                Object.entries(seeded).forEach(([region, units]) => {
                    seedRows[region] = units.map(wrap);
                });

                return {
                    pageType: seed.pageType,
                    regions: seed.regions,
                    // An OBJECT even when the server sent nothing. A JS Array with
                    // string keys assigned to it is not reactive in Alpine, and
                    // the payload input silently kept its load-time value.
                    rows: seedRows,
                    inherited: seed.inherited,
                    layouts: seed.layouts,
                    widgets: seed.widgets,
                    cols: { left: seed.cols.left, right: seed.cols.right },
                    grid: seed.grid,
                    minContent: seed.minContent,
                    schemas: seed.schemas,
                    schemasUrl: seed.schemasUrl,
                    pageSettings: seed.pageSettings,
                    orphans: seed.orphans,
                    // Where the picker is open: { region, u, c }.
                    adding: null,
                    // The plus that opened the picker, so Escape can hand
                    // focus back to it rather than dropping it on the body.
                    addingFrom: null,
                    editing: null,
                    // The open subject panel: { row, field }. frameUrl null
                    // means level one, the choices; anything else is the
                    // module's own page inside the frame.
                    panel: null,
                    frameUrl: null,
                    frameTitle: '',
                    frameSteps: 0,
                    frameGoingBack: false,
                    dragging: null,
                    moving: null,
                    announcement: '',
                    sayTimer: null,

                    init() {
                        root = this.$root ?? this.$el;
                        window.addEventListener('focus', () => this.refreshChoices());
                        window.addEventListener('message', (e) => this.hearFrame(e));
                    },

                    /* ---- reading ---- */

                    rowsIn(region) { return this.rows[region] || []; },

                    /** Every card in a position, whatever row or column it is in. */
                    cardsIn(region) {
                        return this.rowsIn(region).flatMap((u) => u.cols.flat());
                    },

                    regionLabel(region) { return (this.regions[region] || {}).label || region; },

                    /** Rows a page inherits from All pages. Shown, never editable here. */
                    inheritedIn(region) {
                        return (this.inherited[region] || []);
                    },

                    sameShape(a, b) {
                        return a.length === b.length && a.every((w, i) => w === b[i]);
                    },

                    commit() { this.rows = { ...this.rows }; },

                    /* ---- rows ---- */

                    addRow(region, el) {
                        if (!this.rows[region]) this.rows[region] = [];
                        this.rows[region].push(wrap({ columns: [8], cols: [[]] }));
                        this.commit();
                        this.say('Row added to ' + this.regionLabel(region) + '. Save to keep it.');
                        this.touch();
                        this.$nextTick(() => {
                            const rows = root?.querySelectorAll('.v-slot .v-lrow');
                            const last = el?.closest('.v-slot')?.querySelectorAll('.v-lrow:not(.v-lrow--inherit)');
                            last?.[last.length - 1]?.querySelector('.v-lrowcol__add')?.focus();
                        });
                    },

                    /**
                     * Changing the shape keeps every card: columns that vanish
                     * hand their cards to the last column that remains, in
                     * order, rather than dropping them off the page.
                     */
                    setShape(region, u, shape) {
                        const unit = this.rows[region][u];
                        const cols = shape.map((_, i) => unit.cols[i] ? unit.cols[i].slice() : []);
                        if (unit.cols.length > shape.length) {
                            const spill = unit.cols.slice(shape.length).flat();
                            cols[shape.length - 1].push(...spill);
                        }
                        unit.columns = shape.slice();
                        unit.cols = cols;
                        this.commit();
                        this.say('Row set to ' + shape.join(' + ') + '. Save to keep it.');
                        this.touch();
                    },

                    nudgeRow(region, u, by) {
                        const list = this.rows[region];
                        const to = u + by;
                        if (!list || to < 0 || to >= list.length) return;
                        [list[u], list[to]] = [list[to], list[u]];
                        this.commit();
                        this.say('Row moved ' + (by < 0 ? 'up' : 'down'));
                        this.touch();
                    },

                    toggleRow(region, u) {
                        const unit = this.rows[region][u];
                        unit.enabled = !unit.enabled;
                        this.say('Row ' + (unit.enabled ? 'shown' : 'hidden') + '. Save to keep it.');
                        this.touch();
                    },

                    /** Removing a row removes what is in it, and says how much. */
                    removeRow(region, u) {
                        const unit = this.rows[region][u];
                        const n = unit.cols.flat().length;
                        if (unit.cols.flat().includes(this.editing)) this.editing = null;
                        this.rows[region].splice(u, 1);
                        this.commit();
                        this.say('Row removed from ' + this.regionLabel(region)
                            + (n ? ' with ' + n + (n === 1 ? ' widget' : ' widgets') : '') + '. Save to keep it.');
                        this.touch();
                    },

                    /* ---- adding widgets ---- */

                    offerable(region) {
                        if (!region) return [];
                        return this.widgets.filter((w) => w.regions.includes(region));
                    },

                    isAdding(region, u, c) {
                        return !!this.adding && this.adding.region === region && this.adding.u === u && this.adding.c === c;
                    },

                    /**
                     * Opening in place, and moving focus with it: focus in on
                     * open, focus back on close.
                     */
                    openPicker(region, u, c, el) {
                        this.adding = { region, u, c };
                        this.addingFrom = el;
                        this.$nextTick(() => {
                            root?.querySelector('.v-slot__pick .v-pickcard')?.focus();
                        });
                    },

                    closePicker() {
                        const back = this.addingFrom;
                        this.adding = null;
                        this.addingFrom = null;
                        back?.focus();
                    },

                    add(w) {
                        const { region, u, c } = this.adding;
                        const unit = this.rows[region]?.[u];
                        if (!unit) return;
                        unit.cols[c].push({
                            id: null, widget: w.key, title: w.title,
                            label: w.title, enabled: true, available: true, config: {},
                        });
                        this.commit();
                        this.say(w.title + ' added to ' + this.regionLabel(region) + '. Save to keep it.');
                        this.closePicker();
                        this.touch();
                    },

                    /* ---- settings ---- */

                    edit(row) { this.editing = row; },

                    schemaFor(widget) { return widget ? (this.schemas[widget] || []) : []; },

                    /**
                     * Falls back to the schema's declared default, so a freshly
                     * added Product grid does not show Show and How many blank.
                     */
                    cfg(name) {
                        const set = this.editing?.config ?? {};
                        if (set[name] !== undefined && set[name] !== '') return set[name];
                        const field = this.schemaFor(this.editing?.widget).find((f) => f.name === name);
                        return field?.default ?? '';
                    },

                    setCfg(name, value) {
                        if (!this.editing.config) this.editing.config = {};
                        this.editing.config[name] = value;
                        this.touch();
                    },

                    /* ---- page parts ---- */

                    partsIn(part) {
                        return this.pageSettings.filter((f) => (f.part ?? null) === part);
                    },

                    partOn(part) {
                        return this.partsIn(part).every((f) => f.value);
                    },

                    setPart(f, value) {
                        f.value = value;
                        this.say(f.label + (value ? ' shown' : ' hidden') + ' on this page. Save to keep it.');
                        this.touch();
                    },

                    /* ---- the subject panel ---- */

                    subjectOf(row) {
                        const field = this.schemaFor(row?.widget).find((f) => Array.isArray(f.browse));
                        if (!field) return null;
                        const value = row?.config?.[field.name] ?? field.default ?? '';
                        return {
                            field,
                            browse_label: field.browse_label,
                            chosen: field.browse.find((c) => c.value === value) || null,
                        };
                    },

                    openSubject(row) {
                        const subject = this.subjectOf(row);
                        if (!subject) return;
                        this.panel = { row, field: subject.field };
                        this.frameUrl = null;
                        this.frameTitle = '';
                        this.frameSteps = 0;
                    },

                    closeSubject() {
                        if (!this.panel) return;
                        this.panel = null;
                        this.frameUrl = null;
                        this.frameTitle = '';
                        this.frameSteps = 0;
                    },

                    panelValue() {
                        const name = this.panel?.field?.name;
                        if (!name) return '';
                        return this.panel.row?.config?.[name] ?? this.panel.field.default ?? '';
                    },

                    chooseSubject(choice) {
                        const name = this.panel?.field?.name;
                        if (!name || choice.value === this.panelValue()) return;
                        if (!this.panel.row.config) this.panel.row.config = {};
                        this.panel.row.config[name] = choice.value;
                        this.commit();
                        this.say(choice.label + ' placed. Save to keep it.');
                        this.touch();
                    },

                    ownerOf(row) {
                        const spec = this.widgets.find((w) => w.key === row?.widget);
                        return spec?.module || (row?.label ?? '');
                    },

                    enterSubject(choice) {
                        if (!choice.url) return;
                        this.frameTitle = choice.label;
                        this.frameSteps = 0;
                        this.frameUrl = choice.url;
                    },

                    panelBack() {
                        if (this.frameSteps > 1) {
                            this.frameSteps -= 1;
                            this.frameGoingBack = true;
                            setTimeout(() => { this.frameGoingBack = false; }, 2000);
                            this.$refs.frame?.contentWindow?.postMessage(
                                { v: 'venta-frame-host', type: 'back' }, window.location.origin
                            );
                            return;
                        }
                        this.frameUrl = null;
                        this.frameTitle = '';
                        this.frameSteps = 0;
                    },

                    hearFrame(e) {
                        if (e.origin !== window.location.origin) return;
                        if (!e.data || e.data.v !== 'venta-frame') return;
                        if (!this.panel) return;
                        if (e.data.type === 'ready') {
                            this.frameTitle = e.data.title || '';
                            if (this.frameGoingBack) {
                                this.frameGoingBack = false;
                            } else {
                                this.frameSteps += 1;
                            }
                        }
                        if (e.data.type === 'saved') {
                            this.refreshChoices().then(() => this.resyncPanel());
                            if (e.data.message) this.say(e.data.message);
                        }
                    },

                    resyncPanel() {
                        if (!this.panel) return;
                        const fresh = this.schemaFor(this.panel.row?.widget)
                            .find((f) => f.name === this.panel.field.name);
                        if (fresh) this.panel = { row: this.panel.row, field: fresh };
                    },

                    /* ---- cards ---- */

                    remove(region, u, c, i) {
                        const col = this.rows[region][u].cols[c];
                        const row = col[i];
                        if (this.editing === row) this.editing = null;
                        col.splice(i, 1);
                        this.commit();
                        this.say((row.title || row.label) + ' removed from ' + this.regionLabel(region) + '. Save to keep it.');
                        this.touch();
                    },

                    toggle(row) {
                        row.enabled = !row.enabled;
                        this.say((row.title || row.label) + (row.enabled ? ' shown' : ' hidden') + '. Save to keep it.');
                        this.touch();
                    },

                    /* ---- moving ---- */

                    start(region, u, c, i) { this.dragging = { region, u, c, i }; },

                    /** The dragged card, lifted out of where it was. */
                    lift() {
                        const d = this.dragging;
                        this.dragging = null;
                        if (!d) return null;
                        const col = this.rows[d.region]?.[d.u]?.cols?.[d.c];
                        if (!col) return null;
                        return { card: col[d.i], from: d, col };
                    },

                    fits(card, region) {
                        const spec = this.widgets.find((w) => w.key === card.widget);
                        if (spec && !spec.regions.includes(region)) {
                            this.say((card.title || card.label) + ' cannot go in ' + this.regionLabel(region));
                            return false;
                        }
                        return true;
                    },

                    /** Drop onto a column, or onto a card in it (before that card). */
                    drop(region, u, c, at = null) {
                        const d = this.dragging;
                        if (!d) return;
                        const card = this.rows[d.region]?.[d.u]?.cols?.[d.c]?.[d.i];
                        if (!card || !this.fits(card, region)) { this.dragging = null; return; }

                        const lifted = this.lift();
                        lifted.col.splice(d.i, 1);

                        const target = this.rows[region][u].cols[c];
                        let index = at === null ? target.length : at;
                        // Dropping onto a later card in the same column: the
                        // removal above shifted it up by one.
                        if (target === lifted.col && at !== null && at > d.i) index -= 1;
                        target.splice(index, 0, card);
                        this.commit();
                        this.touch();
                    },

                    /** Drop on the position itself, outside any row: a new row at the end. */
                    dropIntoRegion(region) {
                        const d = this.dragging;
                        if (!d) return;
                        const card = this.rows[d.region]?.[d.u]?.cols?.[d.c]?.[d.i];
                        if (!card || !this.fits(card, region)) { this.dragging = null; return; }

                        const lifted = this.lift();
                        lifted.col.splice(d.i, 1);
                        if (!this.rows[region]) this.rows[region] = [];
                        this.rows[region].push(wrap({ columns: [8], cols: [[card]] }));
                        this.commit();
                        this.touch();
                    },

                    /** Reorder inside a column with buttons, so touch and keyboard both work. */
                    nudgeCard(region, u, c, i, by) {
                        const list = this.rows[region][u].cols[c];
                        const to = i + by;
                        if (!list || to < 0 || to >= list.length) return;
                        [list[i], list[to]] = [list[to], list[i]];
                        this.commit();
                        this.say((list[to].title || list[to].label) + ' moved ' + (by < 0 ? 'up' : 'down'));
                        this.touch();
                    },

                    /** Where else this widget is allowed to go. */
                    movesFor(widget) {
                        const spec = this.widgets.find((w) => w.key === widget);
                        const allowed = spec ? spec.regions : Object.keys(this.regions);
                        return allowed.filter((r) => this.regionOpen(r));
                    },

                    regionOpen(region) {
                        if (region === 'column_left') return this.cols.left > 0;
                        if (region === 'column_right') return this.cols.right > 0;
                        return true;
                    },

                    /** Into a new full-width row at the end of the target position. */
                    moveTo(region, u, c, i, target) {
                        if (region === target) { this.moving = null; return; }
                        const col = this.rows[region][u].cols[c];
                        const card = col[i];
                        col.splice(i, 1);
                        if (!this.rows[target]) this.rows[target] = [];
                        this.rows[target].push(wrap({ columns: [8], cols: [[card]] }));
                        this.commit();
                        this.say((card.title || card.label) + ' moved to ' + this.regionLabel(target));
                        this.moving = null;
                        this.touch();
                    },

                    /**
                     * Bring a stranded placement back into a position that exists.
                     * Only offered for the page being edited; an orphan on another
                     * page is listed so it can be found, not silently moved.
                     */
                    rescue(orphan) {
                        if (orphan.page_type !== this.pageType) {
                            window.location = '{{ route('admin.theme.layout') }}?page=' + encodeURIComponent(orphan.page_type);
                            return;
                        }
                        if (!this.rows.content_bottom) this.rows.content_bottom = [];
                        this.rows.content_bottom.push(wrap({ columns: [8], cols: [[{
                            id: orphan.id, widget: orphan.widget, title: orphan.title,
                            label: orphan.title || orphan.widget, enabled: true, available: true, config: {},
                        }]] }));
                        this.orphans = this.orphans.filter((o) => o.id !== orphan.id);
                        this.commit();
                        this.say((orphan.title || orphan.widget) + ' moved to Below the content');
                        this.touch();
                    },

                    async refreshChoices() {
                        try {
                            const res = await fetch(this.schemasUrl, { headers: { Accept: 'application/json' } });
                            if (res.ok) this.schemas = await res.json();
                        } catch (e) { /* the merchant loses nothing if this fails */ }
                    },

                    /** Announced to a screen reader, and shown to everyone else. */
                    say(message) {
                        this.announcement = message;
                        clearTimeout(this.sayTimer);
                        this.sayTimer = setTimeout(() => { this.announcement = ''; }, 4000);
                    },

                    /* ---- the page rails ---- */

                    addColumn(side) { this.setWidth(side, 2); this.touch(); },

                    dropLabel(side) {
                        const n = this.cardsIn(side === 'left' ? 'column_left' : 'column_right').length;
                        return n === 0
                            ? 'Remove ' + side + ' column'
                            : 'Remove ' + side + ' column (' + n + ' inside)';
                    },

                    /**
                     * Removing a rail moves its rows to Below the content and
                     * says so, rather than hiding them and saving them anyway.
                     */
                    dropColumn(side) {
                        const region = side === 'left' ? 'column_left' : 'column_right';
                        const stranded = this.rowsIn(region);
                        const n = this.cardsIn(region).length;
                        if (stranded.length) {
                            if (!this.rows.content_bottom) this.rows.content_bottom = [];
                            this.rows.content_bottom.push(...stranded);
                            this.rows[region] = [];
                            this.say(n + (n === 1 ? ' widget' : ' widgets') + ' moved to Below the content');
                        }
                        this.cols[side] = 0;
                        this.commit();
                        this.touch();
                    },

                    grab(side, e) {
                        e.preventDefault();
                        const row = e.target.closest('.v-lay__middle');
                        const move = (ev) => {
                            const box = row.getBoundingClientRect();
                            const frac = (ev.clientX - box.left) / box.width;
                            let n = Math.round(frac * this.grid);
                            if (side === 'right') n = this.grid - n;
                            this.setWidth(side, n);
                        };
                        const up = () => {
                            window.removeEventListener('pointermove', move);
                            window.removeEventListener('pointerup', up);
                            this.touch();
                        };
                        window.addEventListener('pointermove', move);
                        window.addEventListener('pointerup', up);
                    },

                    nudge(side, by) { this.setWidth(side, this.cols[side] + by); this.touch(); },

                    /** The content column is never allowed to disappear. */
                    setWidth(side, n) {
                        const other = side === 'left' ? this.cols.right : this.cols.left;
                        const max = this.grid - this.minContent - other;
                        this.cols[side] = Math.max(0, Math.min(n, max));
                    },

                    touch() {
                        (root ?? this.$root).dispatchEvent(new Event('change', { bubbles: true }));
                    },

                    payload() {
                        const rows = [];
                        Object.entries(this.rows).forEach(([region, units]) => {
                            units.forEach((unit) => {
                                const placements = [];
                                unit.cols.forEach((col, c) => {
                                    col.forEach((p) => placements.push({
                                        id: p.id, column: c, widget: p.widget,
                                        title: p.title, enabled: p.enabled, config: p.config,
                                    }));
                                });
                                rows.push({
                                    id: unit.id, region, columns: unit.columns, enabled: unit.enabled, placements,
                                });
                            });
                        });
                        const settings = {};
                        this.pageSettings.forEach((f) => { settings[f.name] = f.value; });

                        return JSON.stringify({
                            page_type: this.pageType,
                            columns: this.cols,
                            settings,
                            rows,
                        });
                    },
                };
            }
        </script>
    @endpush
</x-admin.shell>
