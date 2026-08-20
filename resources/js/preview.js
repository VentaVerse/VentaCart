/**
 * The storefront's half of the theme editor's live preview.
 *
 * Loaded ONLY when an authenticated admin frames the storefront with
 * ?__preview=1 (see the guard in the store layout), so a shopper never
 * downloads it and no visitor can drive it.
 *
 * Two jobs:
 *
 * 1. Apply unsaved token values instantly, by writing CSS variables onto the
 *    document root. The storefront already reads every colour from those
 *    variables, so this is the same mechanism the page ships with rather than
 *    an imitation of it.
 *
 * 2. Answer "which control owns this?" when the merchant points at something.
 *    Matching a computed colour to a token by value alone is ambiguous:
 *    #FFFFFF is nav_bg, dropdown_bg and btn_text at once. So a value match only
 *    nominates candidates, and the real answer comes from PERTURBING each one
 *    and seeing which actually moves the element. That resolves by experiment,
 *    and stays correct when markup changes, which a hand-maintained map of
 *    selectors to tokens would not.
 */

const PROBE_VALUE = 'rgb(1, 2, 3)';

/** Properties worth reporting, in the order a person would name them. */
const PROPS = [
    ['color', 'Text'],
    ['backgroundColor', 'Background'],
    ['borderTopColor', 'Border'],
    ['borderBottomColor', 'Border'],
];

let tokens = [];        // token names the editor knows about, e.g. ['primary', ...]
let picking = false;
let halo = null;

const root = document.documentElement;

/* ------------------------------------------------------------------ */
/* helpers                                                             */
/* ------------------------------------------------------------------ */

/** Normalise any CSS colour to the browser's own rgb() form for comparison. */
const normalise = (() => {
    const probe = document.createElement('span');
    probe.style.display = 'none';
    document.documentElement.appendChild(probe);

    return (value) => {
        if (!value) return '';
        probe.style.color = '';
        probe.style.color = value;
        return getComputedStyle(probe).color;
    };
})();

const varName = (token) => '--color-' + token.replace(/_/g, '-');

const currentValue = (token) =>
    getComputedStyle(root).getPropertyValue(varName(token)).trim();

/** Is this colour actually painted, or fully transparent? */
const visible = (rgb) => rgb && !/rgba?\([^)]*,\s*0\s*\)$/.test(rgb);

/* ------------------------------------------------------------------ */
/* 1. applying unsaved values                                          */
/* ------------------------------------------------------------------ */

function applyVars(vars) {
    Object.entries(vars || {}).forEach(([name, value]) => {
        const prop = name.startsWith('--') ? name : varName(name);
        if (value === null || value === '') {
            root.style.removeProperty(prop);
        } else {
            root.style.setProperty(prop, value);
        }
    });
}

/* ------------------------------------------------------------------ */
/* 2. answering "which control owns this?"                             */
/* ------------------------------------------------------------------ */

/**
 * Tokens whose CURRENT value matches this painted colour. Cheap, and usually
 * narrows 46 tokens to one or two.
 */
function candidatesFor(rgb) {
    return tokens.filter((token) => normalise(currentValue(token)) === rgb);
}

/**
 * Which candidate actually paints this property on this element.
 *
 * Set it to a colour nothing else uses, read the element back, put it away.
 * The whole probe happens inside one frame, so the merchant never sees it.
 */
function whichOwns(el, prop, candidates) {
    if (candidates.length <= 1) return candidates[0] || null;

    for (const token of candidates) {
        const name = varName(token);
        const had = root.style.getPropertyValue(name);

        root.style.setProperty(name, PROBE_VALUE);
        const moved = normalise(getComputedStyle(el)[prop]) === normalise(PROBE_VALUE);

        if (had) root.style.setProperty(name, had);
        else root.style.removeProperty(name);

        if (moved) return token;
    }

    return null;
}

function inspect(el) {
    const computed = getComputedStyle(el);
    const found = [];
    const seen = new Set();

    for (const [prop, label] of PROPS) {
        const rgb = normalise(computed[prop]);
        if (!visible(rgb)) continue;

        const token = whichOwns(el, prop, candidatesFor(rgb));
        if (!token || seen.has(token)) continue;

        seen.add(token);
        found.push({ token, label, value: currentValue(token) });
    }

    return found;
}

/* ------------------------------------------------------------------ */
/* picking                                                             */
/* ------------------------------------------------------------------ */

function ensureHalo() {
    if (halo) return halo;

    halo = document.createElement('div');
    halo.setAttribute('data-preview-halo', '');
    Object.assign(halo.style, {
        position: 'fixed',
        pointerEvents: 'none',
        zIndex: '2147483647',
        border: '2px solid #C0873A',
        borderRadius: '3px',
        background: 'rgba(192, 135, 58, .10)',
        transition: 'all 90ms cubic-bezier(.2,.7,.3,1)',
        display: 'none',
    });
    document.body.appendChild(halo);

    return halo;
}

function drawHalo(el) {
    const r = el.getBoundingClientRect();
    const h = ensureHalo();

    Object.assign(h.style, {
        display: 'block',
        top: r.top - 2 + 'px',
        left: r.left - 2 + 'px',
        width: r.width + 4 + 'px',
        height: r.height + 4 + 'px',
    });
}

function hideHalo() {
    if (halo) halo.style.display = 'none';
}

function onMove(e) {
    if (!picking) return;
    const el = e.target;
    if (el && el.nodeType === 1 && !el.hasAttribute('data-preview-halo')) drawHalo(el);
}

function onPick(e) {
    if (!picking) return;

    e.preventDefault();
    e.stopPropagation();

    const el = e.target;
    if (!el || el.nodeType !== 1) return;

    send('picked', {
        found: inspect(el),
        // A short description of what was clicked, so the panel can say WHAT it
        // is looking at rather than only which control it landed on.
        tag: el.tagName.toLowerCase(),
        text: (el.textContent || '').trim().slice(0, 40),
    });
}

function setPicking(on) {
    picking = !!on;
    document.body.style.cursor = picking ? 'crosshair' : '';
    if (!picking) hideHalo();
}

/* ------------------------------------------------------------------ */
/* the channel                                                         */
/* ------------------------------------------------------------------ */

function send(type, payload = {}) {
    if (window.parent === window) return;
    window.parent.postMessage({ source: 'venta-preview', type, ...payload }, window.location.origin);
}

window.addEventListener('message', (e) => {
    // Same origin only. The frame and the editor are the same application.
    if (e.origin !== window.location.origin) return;
    const msg = e.data;
    if (!msg || msg.source !== 'venta-editor') return;

    switch (msg.type) {
        case 'tokens':
            tokens = msg.tokens || [];
            // Answered once, with a message that ends the exchange.
            send('used', { used: usedTokens() });
            break;
        case 'vars': applyVars(msg.vars); break;
        case 'pick': setPicking(msg.on); break;
    }
});

document.addEventListener('mousemove', onMove, true);
document.addEventListener('click', onPick, true);
window.addEventListener('scroll', hideHalo, true);

/**
 * Which tokens this page actually paints with.
 *
 * One pass over the document collecting every painted colour, then the tokens
 * whose value is among them. It is a filter, not a proof: two tokens sharing a
 * value both survive, and the picker settles that when the merchant points at
 * something. The point is to turn 46 controls into the dozen this page can
 * possibly be affected by.
 */
function usedTokens() {
    const painted = new Set();
    const all = document.body ? document.body.getElementsByTagName('*') : [];
    const cap = Math.min(all.length, 4000);

    for (let i = 0; i < cap; i++) {
        const c = getComputedStyle(all[i]);
        for (const [prop] of PROPS) {
            const rgb = c[prop];
            if (visible(rgb)) painted.add(normalise(rgb));
        }
    }

    return tokens.filter((t) => painted.has(normalise(currentValue(t))));
}

/**
 * Announce this page ONCE.
 *
 * `used` is deliberately not sent from here. It needs the token vocabulary,
 * which only arrives in the editor's reply, and answering that reply with
 * another `ready` is a feedback loop: ready, tokens, ready, tokens, forever,
 * walking the whole document each turn. That shipped, and it ran eight round
 * trips in three seconds on an idle page. The reply is a separate message the
 * editor does not answer.
 */
function announce() {
    send('ready', {
        url: window.location.pathname + window.location.search,
        pageType: root.getAttribute('data-page-type') || null,
        title: document.title,
    });
}

announce();
