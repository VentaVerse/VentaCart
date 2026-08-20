/**
 * The storefront's half of the theme editor's live preview.
 *
 * Loaded ONLY when an authenticated admin frames the storefront in preview
 * mode (see App\Support\ThemePreview), so a shopper never downloads it and no
 * visitor can drive it.
 *
 * Three jobs:
 *
 * 1. Apply unsaved token values instantly, by writing CSS variables onto the
 *    document root. The storefront already reads every colour from those
 *    variables, so this is the same mechanism the page ships with rather than
 *    an imitation of it.
 *
 * 2. Report which tokens this page ACTUALLY paints with. See stainSweep().
 *
 * 3. Answer "which control owns this?" when the merchant points at something,
 *    by perturbing each candidate and seeing which one moves the element.
 */

/** Green and blue channels nothing in a real palette is likely to hold. */
const STAIN_G = 7;
const STAIN_B = 199;
const STAIN_RE = /^rgba?\((\d+),\s*7,\s*199/;

const PROBE_VALUE = 'rgb(1, 2, 3)';

/** Properties worth reporting, in the order a person would name them. */
const PROPS = [
    ['color', 'Text'],
    ['backgroundColor', 'Background'],
    ['borderTopColor', 'Border'],
    ['borderBottomColor', 'Border'],
];

/** A page with more elements than this is sampled rather than swept whole. */
const ELEMENT_CAP = 6000;

let tokens = [];
let picking = false;
let halo = null;
let haloColor = '#C0873A';

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

/** Elements a person can actually see. Hidden nodes are not "on this page". */
function paintedElements() {
    const all = document.body ? document.body.getElementsByTagName('*') : [];
    const out = [];

    for (let i = 0, n = Math.min(all.length, ELEMENT_CAP); i < n; i++) {
        const el = all[i];
        if (el.hasAttribute('data-preview-halo')) continue;
        if (el.getClientRects().length === 0) continue;
        out.push(el);
    }

    return out;
}

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
/* 2. which tokens this page really uses                               */
/* ------------------------------------------------------------------ */

/**
 * Stain every token a unique colour, read the document once, put it back.
 *
 * The first version matched tokens by VALUE: collect the colours the page
 * paints, then keep tokens whose value is in that set. That cannot work, and
 * shipped anyway. A theme reuses the same hex across many tokens by design, so
 * a token was listed whenever ANY other token sharing its value was painted.
 * Measured on the real store it narrowed 46 controls to 41, against a stated
 * goal of about a dozen, and editing a token could break the coincidence that
 * listed it and make the control vanish mid-edit.
 *
 * Staining answers the actual question. Each token is set to rgb(i, 7, 199),
 * an identity no palette holds, so ONE pass over the document says exactly
 * which tokens paint it. Ownership by experiment, the same principle the
 * picker uses, applied to the whole page at once. Nothing paints in between,
 * because JavaScript does not yield here: the cost is one style recalculation,
 * not 46 of them.
 *
 * Blind spots, stated honestly: a token used only in a gradient, a shadow, an
 * outline or an SVG fill is not seen, because only the four colour properties
 * above are read. Those tokens stay available in the full list.
 */
function stainSweep() {
    if (!tokens.length) return [];

    const saved = tokens.map((t) => root.style.getPropertyValue(varName(t)));

    tokens.forEach((t, i) => {
        root.style.setProperty(varName(t), 'rgb(' + i + ', ' + STAIN_G + ', ' + STAIN_B + ')');
    });

    const used = new Set();

    for (const el of paintedElements()) {
        const c = getComputedStyle(el);
        for (const [prop] of PROPS) {
            const m = STAIN_RE.exec(c[prop]);
            if (m) used.add(tokens[+m[1]]);
        }
    }

    tokens.forEach((t, i) => {
        if (saved[i]) root.style.setProperty(varName(t), saved[i]);
        else root.style.removeProperty(varName(t));
    });

    return [...used].filter(Boolean);
}

/* ------------------------------------------------------------------ */
/* 3. answering "which control owns this?"                             */
/* ------------------------------------------------------------------ */

/** Tokens whose CURRENT value matches this painted colour. */
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

/** A short name for what was clicked, so the panel can say what it is looking at. */
function describe(el) {
    const text = (el.textContent || '').trim().replace(/\s+/g, ' ');
    if (text && text.length <= 32) return text;
    if (text) return text.slice(0, 30) + '...';

    const tag = el.tagName.toLowerCase();
    if (tag === 'img') return el.getAttribute('alt') || 'image';

    return tag;
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
        border: '2px solid ' + haloColor,
        borderRadius: '3px',
        transition: 'all 90ms cubic-bezier(.2,.7,.3,1)',
        display: 'none',
    });
    document.body.appendChild(halo);

    return halo;
}

function drawHalo(el) {
    const r = el.getBoundingClientRect();
    const h = ensureHalo();

    h.style.borderColor = haloColor;
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

    send('picked', { found: inspect(el), what: describe(el) });
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
            if (msg.accent) haloColor = msg.accent;
            // Answered once, with a message that ends the exchange.
            send('used', { used: stainSweep() });
            break;
        case 'vars': applyVars(msg.vars); break;
        case 'pick': setPicking(msg.on); break;
    }
});

document.addEventListener('mousemove', onMove, true);
document.addEventListener('click', onPick, true);
window.addEventListener('scroll', hideHalo, true);

/**
 * Announce this page ONCE.
 *
 * `used` is deliberately not sent from here. It needs the token vocabulary,
 * which only arrives in the editor's reply, and answering that reply with
 * another `ready` is a feedback loop: ready, tokens, ready, tokens, forever,
 * sweeping the whole document each turn. That shipped, and it ran eight round
 * trips in three seconds on an idle page. The reply is a separate message the
 * editor does not answer.
 */
send('ready', {
    url: window.location.pathname + window.location.search,
    pageType: root.getAttribute('data-page-type') || null,
    title: document.title,
});
