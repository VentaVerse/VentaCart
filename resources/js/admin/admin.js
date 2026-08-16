// Admin v2 behavior. Registered on alpine:init so this file can load
// before app.js (which calls Alpine.start()).

// The day sheet's plot reads its own figures out under the pointer. It is a
// module rather than Alpine because it moves three elements on pointermove
// and reads layout while it does, which is a job for one requestAnimationFrame
// and not for reactivity.
import './day-sheet-plot.js';

// Returning from Settings without a view transition (hard entry, or
// a browser without the feature): the slab plays its own expansion
// entrance (.v-arrive keyframes in layout/shell.css). When the rail
// morph ran, it owns the crossing and this stays out of the way.
(() => {
    const arrive = () => {
        if (!document.referrer.includes('/settings')) return;
        document.documentElement.classList.add('v-arrive');
    };
    if ('onpagereveal' in window) {
        addEventListener('pagereveal', (e) => { if (!e.viewTransition) arrive(); });
    } else {
        arrive();
    }
})();

// Shared with the storefront helper of the same name (resources/themes/*/layouts/store.blade.php)
// and the legacy layouts/admin.blade.php: turns a stored relative path into a
// public /storage/... URL. Needed by any v-admin page that embeds the image
// picker/manager partials (single_image_picker.blade.php, media/index.blade.php),
// which call storageUrl() directly — those partials assumed the old admin
// layout's inline <script> defined it, which the v-admin shell never did.
window.storageUrl = function (path) {
    if (!path) return '';
    return '/storage/' + path.split('/').map(encodeURIComponent).join('/');
};

// ------------------------------------------------------------------
// One copy mechanism for every copy button in the admin.
//
// `navigator.clipboard` only exists in a secure context. The admin is
// browsed over plain http on a LAN address as often as it is over
// https, and on http the property is undefined: every one of the six
// copy buttons in the build read `navigator.clipboard.writeText(...)`
// with no guard and no catch, so the expression threw before it copied
// anything and Alpine swallowed the error. The button did nothing, said
// nothing, and worked perfectly on the live site, which is why it went
// unreported for so long.
//
// So: try the async API where it exists, fall back to a selection and
// document.execCommand('copy') where it does not, and when neither
// path works say so. A copy button that cannot copy has to tell the
// person holding the value they still have to take it themselves.
// Returns a promise for a boolean, so a caller can flip its own
// "Copied" state only when the value really did land.
window.vCopy = async function (text) {
    const value = String(text ?? '');
    if (!value) return false;

    if (window.navigator?.clipboard?.writeText) {
        try {
            await window.navigator.clipboard.writeText(value);
            return true;
        } catch (e) {
            // Denied permission, or a non-secure context that still
            // exposes the object. Fall through to the selection path.
        }
    }

    // The fallback every browser has had since long before the async
    // API: a throwaway textarea, selected, copied, removed. It has to
    // be on-screen and non-readonly for iOS to select it at all, so it
    // is parked outside the viewport rather than hidden.
    try {
        const ta = document.createElement('textarea');
        ta.value = value;
        ta.setAttribute('readonly', '');
        ta.style.cssText = 'position:fixed;top:0;left:-9999px;opacity:0';
        document.body.appendChild(ta);
        ta.select();
        ta.setSelectionRange(0, value.length);
        const ok = document.execCommand('copy');
        ta.remove();
        if (ok) return true;
    } catch (e) {
        // Nothing left to try.
    }

    window.Alpine?.store('toasts')?.push('Could not reach the clipboard. Select the value and copy it.', 'danger');
    return false;
};

// ------------------------------------------------------------------
// One dialog mechanism for every overlay in the admin.
//
// The admin has four surfaces that behave as dialogs and used to have
// four different amounts of nothing: x-admin.modal, x-admin.confirm-dialog
// (which is a modal), the command palette, and the hand-rolled booking
// modal and detail drawer in the courier-fulfillment extension. Rather
// than four implementations, `x-dialog="<open expression>"` on the panel
// gives all of them the same contract:
//
//   open  -> remember what had focus, move focus into the panel
//            (its [data-v-dialog-initial] if it names one, otherwise its
//            first focusable control), lock the page behind it
//   while open -> Tab and Shift+Tab cycle inside the panel
//   close -> unlock, return focus to what had it
//
// Escape stays with each surface, because each already owns the state
// the key has to clear, and every one of them already handled it.
//
// Stacked dialogs (the fulfillment drawer opening the booking modal, a
// confirm opening over a page) are handled by a stack: only the topmost
// panel answers Tab, and the lock lifts when the last one closes.
// ------------------------------------------------------------------
const V_FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    'summary',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

const vDialogStack = [];
let vDialogSeq = 0;

// Rendered and reachable. Anything an ancestor has hidden, collapsed or
// marked inert is not a tab stop, so it is not a trap boundary either.
function vIsVisible(el) {
    return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
}

// tabIndex is the check that matters last: the command palette's results
// are anchors carrying tabindex="-1" so the whole list is one tab stop,
// and a trap that treated the final anchor as its boundary would let Tab
// walk straight out of the panel.
function vFocusable(root) {
    return Array.from(root.querySelectorAll(V_FOCUSABLE))
        .filter((el) => el.tabIndex >= 0 && !el.closest('[inert]') && vIsVisible(el));
}

document.addEventListener('alpine:init', () => {
    window.Alpine.directive('dialog', (el, { expression }, { effect, evaluateLater, cleanup }) => {
        const readOpen = evaluateLater(expression || 'true');
        let active = false;
        let restoreTo = null;
        let openedAt = 0;

        const onKeydown = (e) => {
            if (e.key !== 'Tab' || !active) return;
            if (vDialogStack[vDialogStack.length - 1] !== el) return;

            const items = vFocusable(el);
            if (!items.length) {
                e.preventDefault();
                el.focus();
                return;
            }
            const first = items[0];
            const last = items[items.length - 1];
            const current = document.activeElement;

            if (!el.contains(current)) {
                e.preventDefault();
                (e.shiftKey ? last : first).focus();
            } else if (e.shiftKey && current === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && current === last) {
                e.preventDefault();
                first.focus();
            }
        };

        const open = () => {
            if (active) return;
            active = true;
            openedAt = ++vDialogSeq;

            // Only a control the person can actually see is worth coming
            // back to. Opening the booking modal from the detail drawer
            // closes the drawer first, so the trigger is already gone.
            const was = document.activeElement;
            restoreTo = was && was !== document.body && vIsVisible(was) ? was : null;

            vDialogStack.push(el);
            document.documentElement.classList.add('v-scroll-lock');
            if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '-1');

            // After the frame x-show has painted, or the panel is still
            // display:none and nothing in it can take focus.
            requestAnimationFrame(() => {
                if (!active) return;
                const named = el.querySelector('[data-v-dialog-initial]');
                const target = named && vIsVisible(named) ? named : (vFocusable(el)[0] || el);
                target.focus();
            });
        };

        const close = () => {
            if (!active) return;
            active = false;

            const at = vDialogStack.indexOf(el);
            if (at > -1) vDialogStack.splice(at, 1);
            if (!vDialogStack.length) document.documentElement.classList.remove('v-scroll-lock');

            const target = restoreTo;
            restoreTo = null;
            // Something opened on top of us and owns focus now.
            if (!target || vDialogSeq !== openedAt) return;
            requestAnimationFrame(() => {
                if (document.contains(target) && vIsVisible(target)) target.focus();
            });
        };

        effect(() => readOpen((value) => (value ? open() : close())));

        document.addEventListener('keydown', onKeydown, true);
        cleanup(() => {
            document.removeEventListener('keydown', onKeydown, true);
            close();
        });
    });

    // The admin shell's own state. The off-canvas slab is a drawer below
    // lg and permanent navigation above it, so whether it is a dialog at
    // all depends on the viewport: `lgUp` is what x-dialog and :inert
    // both read, and it is a media query rather than a resize listener.
    window.Alpine.data('vShell', () => ({
        mobileNav: false,
        lgUp: true,
        init() {
            const mq = window.matchMedia('(min-width: 1024px)');
            this.lgUp = mq.matches;
            mq.addEventListener('change', (e) => {
                this.lgUp = e.matches;
                if (e.matches) this.mobileNav = false;
            });
        },
        // Closed and off-screen below lg: 27 controls that must not be
        // tab stops. Above lg the slab is the page's navigation and is
        // always live.
        get navClosed() {
            return !this.lgUp && !this.mobileNav;
        },
        closeNav() {
            this.mobileNav = false;
        },
    }));

    window.Alpine.store('toasts', {
        items: [],
        counter: 0,
        push(message, tone = 'success') {
            const id = ++this.counter;
            this.items.push({ id, message, tone });
            setTimeout(() => this.dismiss(id), 4500);
        },
        dismiss(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    });

    window.Alpine.data('vPalette', (destinations, searchUrl) => ({
        open: false,
        q: '',
        results: [],
        selected: 0,
        searchTimer: null,
        controller: null,
        init() {
            window.addEventListener('open-palette', () => this.show());
            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    this.show();
                }
            });
        },
        show() {
            this.open = true;
            this.q = '';
            this.results = this.pageMatches('');
            this.selected = 0;
            this.$nextTick(() => this.$refs.input.focus());
        },
        pageMatches(q) {
            const needle = q.toLowerCase();
            return destinations
                .filter((d) => !needle
                    || d.label.toLowerCase().includes(needle)
                    || d.keywords.some((k) => k.toLowerCase().includes(needle)))
                .slice(0, 8)
                .map((d) => ({ label: d.label, sub: d.group, url: d.url, group: 'Pages' }));
        },
        search() {
            this.results = this.pageMatches(this.q);
            this.selected = 0;
            clearTimeout(this.searchTimer);
            if (this.q.length < 2) return;
            this.searchTimer = setTimeout(async () => {
                this.controller?.abort();
                this.controller = new AbortController();
                try {
                    const res = await fetch(`${searchUrl}?q=${encodeURIComponent(this.q)}`, {
                        headers: { Accept: 'application/json' },
                        signal: this.controller.signal,
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    this.results = [...this.pageMatches(this.q), ...data.results];
                } catch (e) {
                    // Aborted (superseded by a newer request) or network error:
                    // keep the current results (page matches) silently.
                    return;
                }
            }, 200);
        },
        go() {
            const hit = this.results[this.selected];
            if (hit) window.location.assign(hit.url);
        },
        move(delta) {
            const max = this.results.length - 1;
            this.selected = Math.min(Math.max(this.selected + delta, 0), Math.max(max, 0));
        },
    }));
});

// Any form marked data-v-form: on submit, flip its submit buttons into the
// loading state (spinner + no double-submit). Guard against re-entry.
document.addEventListener('submit', (e) => {
    const form = e.target.closest('form[data-v-form]');
    if (!form) return;
    if (form.dataset.vSubmitting === 'true') { e.preventDefault(); return; }
    form.dataset.vSubmitting = 'true';
    form.querySelectorAll('button[type="submit"]').forEach((b) => b.setAttribute('data-loading', 'true'));
});

// bfcache restore (browser Back after a submit) leaves the frozen DOM with
// the submit-guard still latched: buttons stuck spinning and the form
// inert. pageshow fires on bfcache restore (and on a normal load), so use
// it to clear the guard for every data-v-form on the page. Mirrors the
// storefront fix in commit 5e0fa9b.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-v-form]').forEach((form) => {
        delete form.dataset.vSubmitting;
        form.querySelectorAll('button[type="submit"]').forEach((b) => b.removeAttribute('data-loading'));
    });
});

// Rows with data-href navigate on click, unless the click hit an
// interactive element inside the row (link, button, input).
// Supports modifier clicks (ctrl/cmd) to open in new tab.
document.addEventListener('click', (e) => {
    const row = e.target.closest('tr[data-href]');
    if (!row || e.target.closest('a, button, input, select, textarea, label')) return;
    if (e.ctrlKey || e.metaKey) {
        window.open(row.dataset.href, '_blank');
    } else {
        window.location.assign(row.dataset.href);
    }
});

// Keyboard navigation: Enter on focused row with data-href navigates it.
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const row = e.target.closest('tr[data-href]');
    if (!row || e.target.closest('a, button, input, select, textarea, label')) return;
    if (e.ctrlKey || e.metaKey) {
        window.open(row.dataset.href, '_blank');
    } else {
        window.location.assign(row.dataset.href);
    }
});
