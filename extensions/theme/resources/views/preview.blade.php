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
      <div x-data="themeBench(window.__bench)" @keydown.window.prevent.p="togglePick()">
        <input type="hidden" name="payload" :value="payload()">

        {{-- The STAGE comes first in the DOM and is placed right by the grid.
             Panel-first put ~115 colour inputs between a keyboard user and the
             two controls they reach for most, the picker and the address. --}}
        <div class="v-bench" :class="{ 'is-wide': wide }">

            <div class="v-bench__stage">
                <div class="v-bench__bar">
                    <button type="button" class="v-bench__tool" @click="back()" aria-label="Back">
                        <x-admin.icon name="chevron-left" />
                    </button>
                    <button type="button" class="v-bench__tool" :aria-pressed="picking" @click="togglePick()"
                            aria-label="Pick an element (P)" title="Pick an element (P)">
                        <x-admin.icon name="cursor" />
                    </button>
                    <input type="text" class="v-bench__url" x-model="url" @keydown.enter.prevent="go()"
                           aria-label="Storefront address" spellcheck="false">
                    <span class="v-bench__where" x-show="pageType" x-text="pageLabel()"></span>
                    <button type="button" class="v-bench__tool v-bench__wide" :aria-pressed="wide"
                            @click="wide = ! wide"
                            :aria-label="wide ? 'Show the admin menu' : 'Hide the admin menu for more width'"
                            :title="wide ? 'Show menu' : 'More width'">
                        <x-admin.icon name="monitor" />
                    </button>
                </div>

                {{-- State, not instruction: the frame went somewhere other than
                     where it was sent. It used to swap the page type in silence,
                     so a merchant could pin colours to a page they never saw. --}}
                <p class="v-bench__redirected" x-show="redirected" x-cloak>
                    <x-admin.icon name="info" />
                    <span>Sent to <b x-text="redirected"></b>, the storefront answered with <b x-text="url"></b>.</span>
                </p>

                <iframe class="v-bench__frame" x-ref="frame" :src="startSrc" title="Storefront preview"></iframe>
            </div>

            <div class="v-bench__panel">
                {{-- Fixed head. The scope switch governs every edit below it and
                     used to scroll out of sight the moment a picked row was
                     centred, so a merchant edited without seeing whether they
                     were painting one page type or the whole shop. --}}
                <div class="v-bench__head">
                    <div class="v-bench__scope" role="group" aria-label="What this change affects">
                        <button type="button" :aria-pressed="scope === 'global'" @click="scope = 'global'">
                            Whole store
                        </button>
                        <button type="button" :aria-pressed="scope === 'page'" @click="scope = 'page'"
                                :disabled="! pageType" x-text="pageOnlyLabel()"></button>
                    </div>

                    <div class="v-bench__find">
                        <x-admin.icon name="search" />
                        <input type="search" class="v-bench__search" x-model="query"
                               placeholder="Find a colour" aria-label="Find a colour">
                        <button type="button" class="v-bench__filter" :aria-pressed="onlyUsed"
                                @click="onlyUsed = ! onlyUsed"
                                :aria-label="onlyUsed ? 'Showing colours this page uses. Show all.' : 'Showing all colours. Show only this page.'"
                                :title="onlyUsed ? 'On this page' : 'All colours'">
                            <x-admin.icon name="filter" />
                        </button>
                    </div>

                    <p class="v-bench__picked" x-show="what" x-cloak>
                        <span x-text="what"></span>
                    </p>
                </div>

                <div class="v-bench__list">
                    <template x-for="(fields, label) in visibleGroups()" :key="label">
                        <div>
                            <div class="v-bench__grouplabel" x-text="label"></div>
                            <template x-for="(name, token) in fields" :key="token">
                                <div class="v-bench__row" :class="{ 'is-hit': hit === token }"
                                     @mouseenter="reveal(token)" @mouseleave="reveal(null)"
                                     @focusin="reveal(token)" @focusout="reveal(null)">
                                    <input type="color" class="v-bench__swatch"
                                           :value="shown(token)"
                                           @input="edit(token, $event.target.value)"
                                           :aria-label="name">
                                    <span class="v-bench__name" x-text="name"></span>
                                    <span class="v-bench__pin" x-show="pinned(token)"
                                          :title="pageLabel() + ' overrides this'"></span>
                                    <button type="button" class="v-bench__hits"
                                            x-show="revealed === token && hits > 0" x-cloak
                                            @click="release()"
                                            :title="hits + ' places on this page. Click to stop marking them.'"
                                            :aria-label="hits + ' places on this page'"
                                            x-text="hits"></button>
                                    <input type="text" class="v-bench__hex" maxlength="7"
                                           :value="shown(token)"
                                           @change="edit(token, $event.target.value)"
                                           :aria-label="name + ' hex value'">
                                    <button type="button" class="v-bench__reset" x-show="pinned(token)"
                                            @click="unpin(token)"
                                            :aria-label="'Reset ' + name + ' to the store colour'"
                                            :title="'Reset to the store colour'">
                                        <x-admin.icon name="back" />
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>

                    <p class="v-bench__empty" x-show="! ready" x-cloak>Reading the page&hellip;</p>
                    <p class="v-bench__empty" x-show="ready && noMatches()" x-cloak
                       x-text="query ? 'No colour matches that name.' : 'Nothing on this page uses a colour you can set.'"></p>
                </div>

                {{-- The ledger. A pin only shows on the page type you are
                     standing on, so without this a store drifts apart with
                     nobody able to say where. --}}
                <div class="v-bench__ledger" x-show="ledger().length" x-cloak>
                    <template x-for="row in ledger()" :key="row.type">
                        <button type="button" class="v-bench__ledgerrow" @click="visit(row.type)">
                            <span x-text="row.label"></span>
                            <span class="v-bench__count" x-text="row.count"></span>
                        </button>
                    </template>
                </div>
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
                counts: @js($overrideCounts),
                start: @js('/'),
                homes: @js([
                    'home' => '/', 'cart' => '/cart', 'checkout' => '/checkout',
                    'account' => '/account', 'auth' => '/account/login', 'search' => '/search?q=a',
                    'blog' => '/blog', 'brand' => '/brands',
                ]),
            };

            function themeBench(seed) {
                return {
                    groups: seed.groups,
                    values: seed.values,               // the store's own colours
                    overrides: seed.overrides,         // pageType -> token -> value (null = clear it)
                    pageTypes: seed.pageTypes,
                    counts: seed.counts,
                    homes: seed.homes,
                    scope: 'global',
                    pageType: null,
                    url: seed.start,
                    startSrc: seed.start + '?__preview=1',
                    picking: false,
                    onlyUsed: true,
                    query: '',
                    used: [],
                    touched: {},
                    hit: null,
                    what: '',
                    ready: false,
                    redirected: '',
                    sent: '',
                    wide: true,
                    revealed: null,
                    hits: 0,
                    revealTimer: null,
                    holding: null,      // the control being edited: its reveal stays up

                    init() {
                        window.addEventListener('message', (e) => this.fromFrame(e));
                    },

                    frame() { return this.$refs.frame; },

                    accent() {
                        return getComputedStyle(document.documentElement)
                            .getPropertyValue('--v-act-fill').trim() || '#C0873A';
                    },

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
                            this.redirected = (this.sent && this.sent !== this.url) ? this.sent : '';
                            this.sent = '';
                            this.toFrame('tokens', { tokens: Object.keys(this.values), accent: this.accent() });
                            this.paint();
                            if (this.scope === 'page' && !this.pageType) this.scope = 'global';
                        }

                        // The frame's answer to `tokens`. Never answered back: that
                        // exchange used to loop, re-sweeping the document each turn.
                        if (m.type === 'used') {
                            this.used = m.used || [];
                            this.ready = true;
                        }

                        if (m.type === 'revealed') {
                            if (m.token === this.revealed) this.hits = m.count;
                        }

                        if (m.type === 'picked') {
                            const first = (m.found || [])[0];
                            this.what = first
                                ? m.what
                                : 'No colour you can set paints "' + m.what + '".';
                            this.hit = first ? first.token : null;
                            if (first) {
                                this.query = '';
                                this.$nextTick(() => this.revealHit());
                            }
                            this.holding = null;
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

                    /** What this token paints AS SEEN on the page in front of us. */
                    shown(token) {
                        const set = this.pageType ? this.overrides[this.pageType] : null;
                        return (set && set[token]) || this.values[token] || '#000000';
                    },

                    pinned(token) {
                        const set = this.pageType ? this.overrides[this.pageType] : null;
                        return !!(set && set[token]);
                    },

                    edit(token, value) {
                        if (!/^#[0-9a-fA-F]{6}$/.test(value)) return;

                        if (this.scope === 'page' && this.pageType) {
                            this.pageOverrides()[token] = value;
                        } else {
                            this.values[token] = value;
                        }

                        // A control you have used never leaves the list.
                        this.touched[token] = true;
                        this.holding = token;
                        this.reveal(token);
                        this.markDirty();
                        this.paint();
                    },

                    unpin(token) {
                        if (!this.pageType) return;

                        // Marked null, NOT deleted. A key absent from the payload
                        // reaches setPageOverrides() as "no instruction", and it
                        // only ever writes the keys it is given, so an unpinned
                        // override quietly survived every save: the control
                        // confirmed an action it had not taken. null is the
                        // instruction to delete, and the server honours it.
                        this.pageOverrides()[token] = null;
                        this.touched[token] = true;
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

                    /** The scope button states its own blast radius, so no caption has to. */
                    pageOnlyLabel() {
                        return this.pageType ? this.pageTypes[this.pageType] + ' only' : 'This page only';
                    },

                    visibleGroups() {
                        const q = this.query.trim().toLowerCase();
                        const out = {};

                        Object.entries(this.groups).forEach(([label, fields]) => {
                            const kept = {};
                            Object.entries(fields).forEach(([token, name]) => {
                                if (q) {
                                    if (!name.toLowerCase().includes(q) && !token.includes(q)) return;
                                } else if (this.onlyUsed
                                    && !this.used.includes(token)
                                    && this.hit !== token
                                    && !this.touched[token]) {
                                    return;
                                }
                                kept[token] = name;
                            });
                            if (Object.keys(kept).length) out[label] = kept;
                        });

                        return out;
                    },

                    noMatches() {
                        return Object.keys(this.visibleGroups()).length === 0;
                    },

                    /** Which page types carry overrides, most first. */
                    ledger() {
                        return Object.entries(this.counts)
                            .map(([type, count]) => ({ type, count, label: this.pageTypes[type] || type }))
                            .sort((a, b) => b.count - a.count);
                    },

                    visit(type) {
                        const path = this.homes[type];
                        if (path) { this.url = path; this.go(); }
                    },

                    /**
                     * Show every place this control lands, and say how many.
                     *
                     * The picker answers "what owns this element". Reading down
                     * the list you want the opposite: point at a control and see
                     * where it goes. Debounced, because sweeping the mouse down
                     * 19 rows would otherwise ask the frame 19 questions.
                     */
                    reveal(token) {
                        clearTimeout(this.revealTimer);

                        // Leaving a row does not put the marks away while that
                        // control is the one being edited. They vanished on
                        // mouseleave, which is the moment you reach for the
                        // colour: you edited with no sight of what else it
                        // touched, and found out by scrolling afterwards.
                        if (!token) token = this.holding;

                        if (!token) {
                            this.revealed = null;
                            this.hits = 0;
                            this.toFrame('reveal', { token: null });
                            return;
                        }

                        this.revealTimer = setTimeout(() => {
                            this.revealed = token;
                            this.hits = 0;
                            this.toFrame('reveal', { token });
                        }, 120);
                    },

                    /** Stop holding a control's marks up. */
                    release() {
                        this.holding = null;
                        this.reveal(null);
                    },

                    togglePick() {
                        this.picking = !this.picking;
                        this.toFrame('pick', { on: this.picking });
                    },

                    back() {
                        try { this.frame().contentWindow.history.back(); } catch (e) { /* cross-origin */ }
                    },

                    go() {
                        // Same-origin only. A protocol-relative or absolute address
                        // loads a third party in the stage, never announces itself,
                        // and leaves the panel describing the page you just left.
                        let path = this.url.trim();
                        if (/^[a-z]+:/i.test(path) || path.startsWith('//')) {
                            try { path = new URL(path, window.location.origin).pathname; }
                            catch (e) { path = '/'; }
                        }
                        if (!path.startsWith('/')) path = '/' + path;

                        this.url = path;
                        this.sent = path;
                        this.ready = false;
                        this.used = [];      // never describe this page with the last one's tokens
                        this.hit = null;
                        this.what = '';
                        this.holding = null;
                        this.revealed = null;
                        this.hits = 0;
                        this.frame().src = path + (path.includes('?') ? '&' : '?') + '__preview=1';
                    },

                    /**
                     * Bring the picked control into view WITHIN the list.
                     *
                     * scrollIntoView scrolls every scrollable ancestor, which
                     * dragged the whole admin page and took the header and Save
                     * off screen: you found your control and lost the button
                     * that keeps it.
                     */
                    revealHit() {
                        const list = this.$el.querySelector('.v-bench__list');
                        const row = this.$el.querySelector('.v-bench__row.is-hit');
                        if (!list || !row) return;

                        const delta = row.getBoundingClientRect().top
                            - list.getBoundingClientRect().top
                            - list.clientHeight / 2;

                        list.scrollTo({ top: list.scrollTop + delta, behavior: 'smooth' });
                    },

                    /**
                     * Arm the save bar.
                     *
                     * vForm's touch() is NOT reachable through `this` from a plain
                     * method: `this` is this component's own data object, not
                     * Alpine's merged scope proxy, so the earlier guarded call was
                     * a permanent no-op and dirty state survived only by vForm's
                     * MutationObserver fallback. A bubbled change event is what
                     * vForm actually listens for.
                     */
                    markDirty() {
                        this.$el.dispatchEvent(new Event('change', { bubbles: true }));
                    },

                    payload() {
                        const pages = {};

                        // Every page type that carries anything, INCLUDING sets
                        // that are now nothing but nulls: those nulls are the
                        // removals, and dropping them is what made reset a lie.
                        Object.entries(this.overrides).forEach(([type, set]) => {
                            if (set && Object.keys(set).length) pages[type] = set;
                        });

                        return JSON.stringify({ global: this.values, pages });
                    },
                };
            }
        </script>
    @endpush
</x-admin.shell>
