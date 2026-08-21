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
    </div>

    <x-admin.form :action="route('admin.theme.layout.update')">
      <div class="v-lay" x-data="layoutEditor(window.__layout)">
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
                        <template x-if="cols.left > 0">
                            <div class="v-lay__col v-lay__col--side" :style="'width:' + (cols.left / grid * 100) + '%'">
                                <x-theme::layout-slot region="column_left" label="Left column" side="true" />
                            </div>
                        </template>
                        <template x-if="cols.left > 0">
                            <div class="v-lay__handle" role="separator" tabindex="0"
                                 aria-label="Left column width"
                                 @pointerdown="grab('left', $event)"
                                 @keydown.arrow-left.prevent="nudge('left', -1)"
                                 @keydown.arrow-right.prevent="nudge('left', 1)"></div>
                        </template>
                    @endif

                    <div class="v-lay__col v-lay__col--center">
                        @if (isset($regions['content_top']))
                            <x-theme::layout-slot region="content_top" label="Above the content" />
                        @endif

                        <div class="v-lay__pagebody">
                            <b>The page itself</b>
                            <span>rendered by the theme, not placed here</span>
                        </div>

                        @if (isset($regions['content_bottom']))
                            <x-theme::layout-slot region="content_bottom" label="Below the content" />
                        @endif
                    </div>

                    @if (isset($regions['column_right']))
                        <template x-if="cols.right > 0">
                            <div class="v-lay__handle" role="separator" tabindex="0"
                                 aria-label="Right column width"
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
                    <span x-show="cols.left > 0" @click="cols.left = 0; touch()" class="v-lay__drop">Remove left column</span>
                    <span x-show="cols.right > 0" @click="cols.right = 0; touch()" class="v-lay__drop">Remove right column</span>
                    <span>Content never goes below {{ $minContent }}/{{ $grid }}</span>
                </div>
            </div>
        </div>

        {{-- settings for the selected widget, from its own schema --}}
        <div class="v-lay__pick v-lay__pick--wide" x-show="editing" x-cloak
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
                This widget has nothing to configure.
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
                Nothing can go here. Every widget declares which positions suit it.
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
                return {
                    pageType: seed.pageType,
                    regions: seed.regions,
                    rows: seed.placements,        // region -> [placement]
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

                    /** Named `placed`, never `in`: `in` is a JS operator and Alpine
                     *  could not parse the expression, so every slot threw. */
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
                        this.adding = null;
                        this.touch();
                    },

                    /* ---- settings ---- */

                    edit(region, i) { this.editing = this.rows[region][i]; },

                    schemaFor(widget) { return widget ? (this.schemas[widget] || []) : []; },

                    cfg(name) { return (this.editing?.config ?? {})[name] ?? ''; },

                    setCfg(name, value) {
                        if (!this.editing.config) this.editing.config = {};
                        this.editing.config[name] = value;
                        this.touch();
                    },

                    remove(region, i) {
                        this.rows[region].splice(i, 1);
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
                        if (spec && !spec.regions.includes(region)) return;

                        this.rows[d.region].splice(d.i, 1);
                        if (!this.rows[region]) this.rows[region] = [];

                        const target = at === null ? this.rows[region].length : at;
                        this.rows[region].splice(target, 0, widget);
                        this.touch();
                    },

                    /* ---- widths ---- */

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

                    touch() { this.$el.dispatchEvent(new Event('change', { bubbles: true })); },

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
