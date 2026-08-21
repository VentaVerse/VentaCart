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
            <a href="{{ url('/') }}" target="_blank" rel="noopener" class="v-btn v-btn--sm">View store</a>
        </div>
    </div>

    <x-admin.form :action="route('admin.theme.layout.update')">
      <div class="v-lay" :class="{ 'is-inspecting': editing }" x-data="layoutEditor(window.__layout)">
        <input type="hidden" name="payload" :value="payload()">

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
        <p class="v-lay__said" role="status" aria-live="polite" x-text="announcement" x-show="announcement" x-cloak></p>

        <div class="v-lay__stage">
            <div class="v-lay__page">
                {{-- top --}}
                @if (isset($regions['header_top']))
                    <div class="v-lay__strip">
                        <x-theme::layout-slot region="header_top" label="Above the header" />
                    </div>
                @endif

                <div class="v-lay__chrome"><span class="v-lay__logo"></span><span class="v-lay__line"></span><span>header</span></div>

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

                        <div class="v-lay__pagebody"><b>The page itself</b></div>

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

                <div class="v-lay__chrome"><span class="v-lay__line"></span><span>footer</span></div>

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

        {{-- what this theme lets you change on the page itself --}}
        <div class="v-lay__page-settings" x-show="pageSettings.length" x-cloak>
            <div class="v-lay__pslabel">On this page</div>
            <template x-for="f in pageSettings" :key="f.name">
                <label class="v-lay__ps">
                    <input type="checkbox" :checked="f.value" @change="f.value = $event.target.checked; touch()">
                    <span x-text="f.label"></span>
                </label>
            </template>
        </div>

        {{-- the picker --}}
        <div class="v-lay__pick" x-show="adding" x-cloak @keydown.escape.window="adding = null">
            <div class="v-lay__pickhead">
                <b>Add to <span x-text="regionLabel(adding)"></span></b>
                <button type="button" @click="adding = null" aria-label="Close">&times;</button>
            </div>
            <template x-for="w in offerable(adding)" :key="w.key">
                <button type="button" class="v-lay__pickrow" @click="add(w)">
                    <span x-text="w.title"></span>
                    <span class="mono" x-text="w.key"></span>
                </button>
            </template>
            <p class="v-lay__pickempty" x-show="offerable(adding).length === 0">
                No widget fits this position
            </p>
        </div>
      </div>
    </x-admin.form>

    @push('scripts')
        <script>
            window.__layout = {
                pageType: @js($pageType),
                regions: @js($regions),
                placements: @js($placements),
                inherited: @js($inherited),
                widgets: @js($widgets),
                schemas: @js($schemas),
                pageSettings: @js($pageSettings),
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

                return {
                    pageType: seed.pageType,
                    regions: seed.regions,
                    // An OBJECT even when the server sent nothing. A JS Array with
                    // string keys assigned to it is not reactive in Alpine, and
                    // the payload input silently kept its load-time value.
                    rows: Array.isArray(seed.placements) ? {} : seed.placements,
                    inherited: seed.inherited,
                    widgets: seed.widgets,
                    cols: { left: seed.cols.left, right: seed.cols.right },
                    grid: seed.grid,
                    minContent: seed.minContent,
                    schemas: seed.schemas,
                    pageSettings: seed.pageSettings,
                    adding: null,
                    editing: null,
                    dragging: null,
                    moving: null,
                    announcement: '',
                    sayTimer: null,

                    /** Named `placed`, never `in`: `in` is a JS operator and Alpine
                     *  could not parse the expression, so every slot threw. */
                    init() { root = this.$root ?? this.$el; },

                    placed(region) { return this.rows[region] || []; },

                    regionLabel(region) { return (this.regions[region] || {}).label || region; },

                    /** Rows a page inherits from All pages. Shown, never editable here. */
                    inheritedIn(region) {
                        return this.inherited.filter((r) => r.region === region);
                    },

                    /* ---- adding ---- */

                    offerable(region) {
                        if (!region) return [];
                        return this.widgets.filter((w) => w.regions.includes(region));
                    },

                    add(w) {
                        const region = this.adding;
                        if (!this.rows[region]) this.rows[region] = [];
                        this.rows[region].push({
                            id: null, widget: w.key, title: w.title,
                            label: w.title, enabled: true, available: true, config: {},
                        });
                        this.rows = { ...this.rows };
                        this.adding = null;
                        this.touch();
                    },

                    /* ---- settings ---- */

                    edit(region, i) { this.editing = this.rows[region][i]; },

                    schemaFor(widget) { return widget ? (this.schemas[widget] || []) : []; },

                    /**
                     * Falls back to the schema's declared default. A freshly added
                     * Product grid showed Show and How many both blank while it
                     * would in fact render the 12 newest, so the merchant had to
                     * remember what the blank meant.
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

                    remove(region, i) {
                        this.rows[region].splice(i, 1);
                        this.rows = { ...this.rows };
                        this.touch();
                    },

                    toggle(region, i) {
                        this.rows[region][i].enabled = !this.rows[region][i].enabled;
                        this.touch();
                    },

                    /* ---- moving ---- */

                    start(region, i) { this.dragging = { region, i }; },

                    /** Drop onto a slot: move between regions, or reorder within one. */
                    drop(region, at = null) {
                        const d = this.dragging;
                        if (!d) return;
                        this.dragging = null;

                        const widget = this.rows[d.region][d.i];
                        // A widget that does not accept this position is refused,
                        // rather than accepted and then rendered wrong.
                        const spec = this.widgets.find((w) => w.key === widget.widget);

                        // A silent return is indistinguishable from a bug: the card
                        // simply sprang back and nothing said why.
                        if (spec && !spec.regions.includes(region)) {
                            this.say((widget.title || widget.label) + ' cannot go in ' + this.regionLabel(region));
                            return;
                        }

                        this.rows[d.region].splice(d.i, 1);
                        if (!this.rows[region]) this.rows[region] = [];

                        const target = at === null ? this.rows[region].length : at;
                        this.rows[region].splice(target, 0, widget);
                        this.rows = { ...this.rows };
                        this.touch();
                    },

                    /* ---- widths ---- */

                    /* ---- columns ---- */

                    addColumn(side) { this.setWidth(side, 2); this.touch(); },

                    dropLabel(side) {
                        const n = this.placed(side === 'left' ? 'column_left' : 'column_right').length;
                        return n === 0
                            ? 'Remove ' + side + ' column'
                            : 'Remove ' + side + ' column (' + n + ' inside)';
                    },

                    /**
                     * Removing a column used to hide its widgets and keep saving
                     * them: the slot stopped rendering, the cards vanished, and
                     * the placements still went to the database where nothing
                     * would ever draw them. They move to Below the content now,
                     * and the merchant is told so.
                     */
                    dropColumn(side) {
                        const region = side === 'left' ? 'column_left' : 'column_right';
                        const stranded = this.placed(region);

                        if (stranded.length) {
                            if (!this.rows.content_bottom) this.rows.content_bottom = [];
                            this.rows.content_bottom.push(...stranded);
                            this.rows[region] = [];
                            this.say(stranded.length + (stranded.length === 1 ? ' widget' : ' widgets') + ' moved to Below the content');
                        }

                        this.cols[side] = 0;
                        this.rows = { ...this.rows };
                        this.touch();
                    },

                    /* ---- moving without a mouse ---- */

                    /** Reorder with buttons, so touch and keyboard both work. */
                    nudgeCard(region, i, by) {
                        const list = this.rows[region];
                        const to = i + by;
                        if (!list || to < 0 || to >= list.length) return;

                        [list[i], list[to]] = [list[to], list[i]];
                        this.rows = { ...this.rows };
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

                    moveTo(region, i, target) {
                        if (region === target) { this.moving = null; return; }

                        const widget = this.rows[region][i];
                        this.rows[region].splice(i, 1);
                        if (!this.rows[target]) this.rows[target] = [];
                        this.rows[target].push(widget);
                        this.rows = { ...this.rows };
                        this.say((widget.title || widget.label) + ' moved to ' + this.regionLabel(target));
                        this.moving = null;
                        this.touch();
                    },

                    /** Announced to a screen reader, and shown to everyone else. */
                    say(message) {
                        this.announcement = message;
                        clearTimeout(this.sayTimer);
                        this.sayTimer = setTimeout(() => { this.announcement = ''; }, 4000);
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
                        const out = [];
                        Object.entries(this.rows).forEach(([region, list]) => {
                            list.forEach((p) => out.push({
                                id: p.id, region, widget: p.widget,
                                title: p.title, enabled: p.enabled, config: p.config,
                            }));
                        });
                        const settings = {};
                        this.pageSettings.forEach((f) => { settings[f.name] = f.value; });

                        return JSON.stringify({
                            page_type: this.pageType,
                            columns: this.cols,
                            settings,
                            placements: out,
                        });
                    },
                };
            }
        </script>
    @endpush
</x-admin.shell>
