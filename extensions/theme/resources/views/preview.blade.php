<x-admin.shell title="Live preview">
    @push('head')
        @vite('extensions/theme/resources/css/theme-bench.css')
    @endpush

    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.theme.index') }}">Theme</a>
                <span aria-hidden="true">/</span>
                <span>Live preview</span>
            </div>
            <h1 class="v-head__title">Live preview</h1>
        </div>
    </div>

    {{-- The doctrine form owns x-data="vForm()", so the bench is an inner
         Alpine canvas inside it (the idiom the banners slide editor set).
         Putting x-data on the component tag loses to the component's own. --}}
    <x-admin.form :action="route('admin.theme.preview.save')">
      <div x-data="themeBench(window.__bench)">
        <input type="hidden" name="payload" :value="payload()">

        <div class="v-bench">

            {{-- ---------------- controls ---------------- --}}
            <div class="v-bench__panel">
                <div class="v-bench__scope" role="group" aria-label="What this change affects">
                    <button type="button" :aria-pressed="scope === 'global'" @click="scope = 'global'">Whole store</button>
                    <button type="button" :aria-pressed="scope === 'page'" @click="scope = 'page'"
                            :disabled="! pageType" x-text="pageLabel()"></button>
                </div>

                <p class="v-bench__note" x-show="scope === 'page' && pageType">
                    Changes here apply to <b x-text="pageLabel()"></b> only.
                </p>
                <p class="v-bench__note" x-show="scope === 'global'">
                    Changes here apply everywhere a page has not overridden them.
                </p>

                <template x-for="(fields, label) in visibleGroups()" :key="label">
                    <div>
                        <div class="v-bench__grouplabel" x-text="label"></div>
                        <template x-for="(name, token) in fields" :key="token">
                            <div class="v-bench__row" :class="{ 'is-hit': hit === token }">
                                <input type="color" class="v-bench__swatch"
                                       :value="shown(token)"
                                       @input="edit(token, $event.target.value)"
                                       :aria-label="name">
                                <span class="v-bench__name" x-text="name"></span>
                                <span class="v-bench__pin" x-show="pinned(token)"
                                      :title="pageLabel() + ' overrides this'"></span>
                                <input type="text" class="v-bench__hex" maxlength="7"
                                       :value="shown(token)"
                                       @change="edit(token, $event.target.value)"
                                       :aria-label="name + ' hex value'">
                                <button type="button" class="v-bench__reset" x-show="pinned(token)"
                                        @click="unpin(token)" :aria-label="'Reset ' + name + ' to the store colour'">
                                    <x-admin.icon name="back" />
                                </button>
                            </div>
                        </template>
                    </div>
                </template>

                <div class="v-bench__empty" x-show="Object.keys(visibleGroups()).length === 0">
                    Nothing on this page uses a colour you can set.
                </div>
            </div>

            {{-- ---------------- stage ---------------- --}}
            <div class="v-bench__stage">
                <div class="v-bench__bar">
                    <button type="button" class="v-bench__tool" @click="frame().contentWindow.history.back()"
                            aria-label="Back">
                        <x-admin.icon name="chevron-left" />
                    </button>
                    <button type="button" class="v-bench__tool" :aria-pressed="picking" @click="togglePick()"
                            aria-label="Pick an element">
                        <x-admin.icon name="cursor" />
                    </button>
                    <input type="text" class="v-bench__url" x-model="url" @keydown.enter.prevent="go()"
                           aria-label="Storefront address">
                    <span class="v-bench__chip" x-show="pageType" x-text="pageLabel()"></span>
                    <button type="button" class="v-bench__chip v-bench__chip--act" x-show="onlyUsed"
                            @click="onlyUsed = false" title="Show every colour">On this page</button>
                    <button type="button" class="v-bench__tool" x-show="! onlyUsed" @click="onlyUsed = true"
                            aria-label="Show only colours this page uses">
                        <x-admin.icon name="filter" />
                    </button>
                </div>

                <iframe class="v-bench__frame" x-ref="frame" :src="startSrc" title="Storefront preview"></iframe>
            </div>
        </div>
      </div>
    </x-admin.form>

    @push('scripts')
        <script>
            window.__bench = {
                groups: @js($groups),
                values: @js($values),
                overrides: @js($overrides),
                pageTypes: @js($pageTypes),
                start: @js('/'),
            };

            function themeBench(seed) {
                return {
                    groups: seed.groups,
                    values: seed.values,               // the store's own colours
                    overrides: seed.overrides,         // pageType -> token -> value
                    pageTypes: seed.pageTypes,
                    scope: 'global',
                    pageType: null,
                    url: seed.start,
                    startSrc: seed.start + '?__preview=1',
                    picking: false,
                    onlyUsed: true,
                    used: [],
                    hit: null,

                    init() {
                        window.addEventListener('message', (e) => this.fromFrame(e));
                    },

                    frame() { return this.$refs.frame; },

                    toFrame(type, extra = {}) {
                        const w = this.frame()?.contentWindow;
                        if (!w) return;

                        // Serialised, not passed by reference. Alpine hands out
                        // reactive Proxies, and structured clone refuses them:
                        // postMessage threw DataCloneError on every repaint.
                        w.postMessage(
                            JSON.parse(JSON.stringify({ source: 'venta-editor', type, ...extra })),
                            window.location.origin
                        );
                    },

                    fromFrame(e) {
                        if (e.origin !== window.location.origin) return;
                        const m = e.data;
                        if (!m || m.source !== 'venta-preview') return;

                        if (m.type === 'ready') {
                            this.pageType = m.pageType;
                            this.url = m.url.replace(/[?&]__preview=1/, '') || '/';
                            this.used = m.used || [];
                            // The frame announces before it knows the vocabulary, then
                            // again once told, so the second announcement carries `used`.
                            this.toFrame('tokens', { tokens: Object.keys(this.values) });
                            this.paint();
                            if (this.scope === 'page' && !this.pageType) this.scope = 'global';
                        }

                        if (m.type === 'picked') {
                            const first = (m.found || [])[0];
                            this.hit = first ? first.token : null;
                            if (first) {
                                this.onlyUsed = false;
                                this.$nextTick(() => this.revealHit());
                            }
                            this.picking = false;
                            this.toFrame('pick', { on: false });
                        }
                    },

                    /* ---- values ---- */

                    pageOverrides() {
                        if (!this.pageType) return {};
                        if (!this.overrides[this.pageType]) this.overrides[this.pageType] = {};
                        return this.overrides[this.pageType];
                    },

                    /**
                     * What this token paints AS SEEN on the page in front of us.
                     * Named `shown`, never `valueOf`: that one shadows
                     * Object.prototype.valueOf on the component object and gets
                     * called by any coercion, with no argument.
                     */
                    shown(token) {
                        const pinned = this.pageType && this.overrides[this.pageType]?.[token];
                        return pinned || this.values[token] || '#000000';
                    },

                    pinned(token) {
                        return !!(this.pageType && this.overrides[this.pageType]?.[token]);
                    },

                    edit(token, value) {
                        if (!/^#[0-9a-fA-F]{6}$/.test(value)) return;

                        if (this.scope === 'page' && this.pageType) {
                            this.pageOverrides()[token] = value;
                        } else {
                            this.values[token] = value;
                        }

                        this.markDirty();
                        this.paint();
                    },

                    unpin(token) {
                        if (!this.pageType) return;
                        delete this.overrides[this.pageType][token];
                        this.markDirty();
                        this.paint();
                    },

                    /** Push what the merchant is looking at into the frame, unsaved. */
                    paint() {
                        const vars = {};
                        Object.keys(this.values).forEach((t) => { vars[t] = this.shown(t); });
                        this.toFrame('vars', { vars });
                    },

                    /* ---- ui ---- */

                    pageLabel() {
                        return this.pageType ? this.pageTypes[this.pageType] : 'This page';
                    },

                    visibleGroups() {
                        const out = {};
                        Object.entries(this.groups).forEach(([label, fields]) => {
                            const kept = {};
                            Object.entries(fields).forEach(([token, name]) => {
                                if (!this.onlyUsed || this.used.includes(token) || this.hit === token) {
                                    kept[token] = name;
                                }
                            });
                            if (Object.keys(kept).length) out[label] = kept;
                        });
                        return out;
                    },

                    togglePick() {
                        this.picking = !this.picking;
                        this.toFrame('pick', { on: this.picking });
                    },

                    go() {
                        const path = this.url.startsWith('/') ? this.url : '/' + this.url;
                        this.frame().src = path + (path.includes('?') ? '&' : '?') + '__preview=1';
                    },

                    /**
                     * Bring the picked control into view WITHIN the panel.
                     *
                     * scrollIntoView scrolls every scrollable ancestor, which
                     * dragged the whole admin page and took the header and Save
                     * off screen: you found your control and lost the button
                     * that keeps it.
                     */
                    revealHit() {
                        const panel = this.$el.querySelector('.v-bench__panel');
                        const row = this.$el.querySelector('.v-bench__row.is-hit');
                        if (!panel || !row) return;

                        const delta = row.getBoundingClientRect().top
                            - panel.getBoundingClientRect().top
                            - panel.clientHeight / 2;

                        panel.scrollTo({ top: panel.scrollTop + delta, behavior: 'smooth' });
                    },

                    /**
                     * Arm the save bar. touch() belongs to the parent vForm and is
                     * reached through Alpine's scope chain; a colour input's edits
                     * arrive through Alpine, not through events vForm watches.
                     */
                    markDirty() {
                        if (typeof this.touch === 'function') this.touch();
                    },

                    payload() {
                        const pages = {};
                        Object.entries(this.overrides).forEach(([type, set]) => {
                            if (Object.keys(set).length) pages[type] = set;
                        });
                        return JSON.stringify({ global: this.values, pages });
                    },
                };
            }
        </script>
    @endpush
</x-admin.shell>
