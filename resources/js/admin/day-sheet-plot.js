/**
 * An instant reading for the day sheet's sales plot, and the period picker
 * that swaps what the plot holds.
 *
 * Every figure the plot draws is already reachable through the SVG's own
 * <title>, which is correct for assistive technology and useless for a
 * person with a pointer: the browser holds a native tooltip for roughly a
 * second before it appears, and no CSS or attribute shortens that. A chart
 * that takes a second to answer reads as broken rather than as quiet.
 *
 * So the readout does the smallest possible thing. On pointer move it works
 * out which point is nearest, moves a guide line and two dots onto it, and
 * fills a readout with that point's revenue and the same ground a year
 * earlier. No animation, no delay, no library.
 *
 * THE PICKER is progressive enhancement over real links: without script the
 * seven controls reload the page with ?range= and the server renders the
 * period; with script the click is intercepted, the series is fetched from
 * the sales-series endpoint, and the paths, key, axis and readings are
 * rebuilt in place. The trough shows its active state the instant it is
 * clicked and the plot swaps when the data lands; fetched ranges are held
 * for the visit so revisiting one costs nothing.
 *
 * Every figure, every path string and every y position is computed on the
 * server (SalesPlot) and arrives finished, because currency formatting
 * belongs to the store's own configuration and not to a reimplementation of
 * it here that would drift the first time a second currency appeared.
 */

const SVG_NS = 'http://www.w3.org/2000/svg';
const RANGE_KEY = 'vSalesRange';

/** Redraw every node the payload owns: paths, hit targets, key, axis. */
function render(card, p) {
    const svg = card.querySelector('.v3-plot');

    // A reading taken on the old series is a reading of nothing once the
    // series is gone, so the swap clears it rather than letting a stale
    // date sit over the new plot until the pointer moves again.
    card.classList.remove('is-reading');
    card.querySelectorAll('[data-plot-guide], [data-plot-dot], [data-plot-readout]').forEach((el) => {
        el.style.transform = '';
    });

    svg.setAttribute('aria-label', p.aria);
    card.querySelector('.v3-plot__area').setAttribute('d', p.dArea || '');
    card.querySelector('.v3-plot__then').setAttribute('d', p.hasEarlier ? p.dThen : '');
    card.querySelector('.v3-plot__now').setAttribute('d', p.dNow || '');

    const hits = svg.querySelector('[data-plot-hits]');

    while (hits.firstChild) {
        hits.removeChild(hits.firstChild);
    }

    const n = p.x.length;
    const w = n < 2 ? p.width : p.width / (n - 1);

    for (let i = 0; i < n; i++) {
        const rect = document.createElementNS(SVG_NS, 'rect');

        rect.setAttribute('class', 'v3-plot__hit');
        rect.setAttribute('x', String(Math.max(0, p.x[i] - w / 2)));
        rect.setAttribute('y', '0');
        rect.setAttribute('width', String(w));
        rect.setAttribute('height', String(p.height));

        const title = document.createElementNS(SVG_NS, 'title');
        title.textContent = p.titles[i];
        rect.appendChild(title);

        hits.appendChild(rect);
    }

    card.querySelector('[data-plot-nowname]').textContent = p.nowName;
    card.querySelector('[data-plot-nowpeak]').textContent = 'peak ' + p.peakNow;
    card.querySelector('[data-plot-thenkey]').hidden = !p.hasEarlier;
    card.querySelector('[data-plot-thenname]').textContent = p.thenName || '';
    card.querySelector('[data-plot-thenpeak]').textContent = p.hasEarlier ? 'peak ' + p.peakThen : '';
    card.querySelector('[data-plot-nowread-label]').textContent = p.nowRead.label;
    card.querySelector('[data-plot-nowread-value]').textContent = p.nowRead.value;
    card.querySelector('.v3-plot__dot--then').hidden = !p.hasEarlier;
    card.querySelector('[data-plot-earlier-row]').hidden = !p.hasEarlier;

    const axis = card.querySelector('[data-plot-axis]');

    while (axis.firstChild) {
        axis.removeChild(axis.firstChild);
    }

    p.axis.forEach((label) => {
        const span = document.createElement('span');
        span.textContent = label;
        axis.appendChild(span);
    });
}

function mount(card) {
    let series;

    try {
        series = JSON.parse(card.dataset.plot || 'null');
    } catch (e) {
        return;
    }

    if (!series || !Array.isArray(series.x)) {
        return;
    }

    const svg = card.querySelector('.v3-plot');
    const guide = card.querySelector('[data-plot-guide]');
    const readout = card.querySelector('[data-plot-readout]');

    if (!svg || !guide || !readout) {
        return;
    }

    const frame = svg.parentElement;
    const dateEl = readout.querySelector('[data-plot-date]');
    const currentEl = readout.querySelector('[data-plot-current]');
    const earlierEl = readout.querySelector('[data-plot-earlier]');
    const dots = card.querySelectorAll('[data-plot-dot]');

    let frameId = null;

    // The swap replaces the series under the same listeners, so everything
    // below reads the current one from here rather than from a closure over
    // the first.
    card.__plot = series;
    card.__setPlot = (p) => {
        card.__plot = p;
        render(card, p);
    };

    function paint(clientX) {
        const p = card.__plot;
        const n = p.x.length;

        if (n < 2) {
            return;
        }

        const box = svg.getBoundingClientRect();

        if (box.width <= 0) {
            return;
        }

        // Nearest point, not the one to the left: a pointer sitting between
        // two points should read the one it is closest to, which is what a
        // person means by "hovering over March".
        const ratio = Math.min(1, Math.max(0, (clientX - box.left) / box.width));
        const i = Math.round(ratio * (n - 1));

        const frameBox = frame.getBoundingClientRect();
        const x = box.left - frameBox.left + (i / (n - 1)) * box.width;

        guide.style.transform = `translateX(${x}px)`;

        dots.forEach((dot) => {
            // The server already solved the geometry in viewBox units; the
            // only thing left is the vertical stretch to the rendered box.
            const units = p[dot.dataset.plotDot];

            if (!units) {
                return;
            }

            const y = (Number(units[i]) || 0) / p.height * box.height;

            dot.style.transform = `translate(${x}px, ${y}px)`;
        });

        dateEl.textContent = p.full[i];
        currentEl.textContent = p.currentFmt[i];
        earlierEl.textContent = p.hasEarlier ? `${p.earlierLabels[i]} ${p.earlierFmt[i]}` : '';

        // The readout follows the pointer but never leaves the card. Its own
        // width is read each time because the longest reading is not always
        // the first one shown.
        const half = readout.offsetWidth / 2;
        const left = Math.min(Math.max(x, half), frameBox.width - half);

        readout.style.transform = `translateX(${left}px)`;
        card.classList.add('is-reading');
    }

    function onMove(event) {
        const clientX = event.clientX;

        if (frameId !== null) {
            cancelAnimationFrame(frameId);
        }

        // One paint per frame. A pointermove can fire far more often than
        // the screen refreshes, and every one of these reads layout.
        frameId = requestAnimationFrame(() => {
            frameId = null;
            paint(clientX);
        });
    }

    function onLeave() {
        if (frameId !== null) {
            cancelAnimationFrame(frameId);
            frameId = null;
        }

        card.classList.remove('is-reading');

        // The transforms are cleared, not merely hidden. An invisible
        // readout parked 400px to the right is still 400px of document,
        // so a reading taken on a wide window and then carried into a
        // narrow one left the page scrolling sideways.
        readout.style.transform = '';
        guide.style.transform = '';
        dots.forEach((dot) => { dot.style.transform = ''; });
    }

    svg.addEventListener('pointermove', onMove);
    svg.addEventListener('pointerleave', onLeave);

    // A touch that ends should clear the reading rather than leave the last
    // one frozen on the plot.
    svg.addEventListener('pointercancel', onLeave);
    svg.addEventListener('pointerup', onLeave);
}

/**
 * The period picker. The active segment moves the instant it is chosen;
 * the plot follows when its series lands (these answers are milliseconds,
 * so no spinner earns its place). The last choice is remembered under
 * Venta's own key and restored on the next visit.
 */
function mountRange(nav) {
    const card = document.querySelector('[data-plot]');

    if (!card || !card.__plot) {
        return;
    }

    const endpoint = nav.dataset.rangeEndpoint;
    const note = document.querySelector('[data-sales-period]');
    const links = Array.from(nav.querySelectorAll('[data-range]'));
    const cache = new Map();

    cache.set(card.__plot.range, card.__plot);

    let applied = card.__plot.range;
    let want = applied;

    function activate(range) {
        links.forEach((link) => {
            const on = link.dataset.range === range;

            link.classList.toggle('is-active', on);

            if (on) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }

    function remember(range) {
        try { localStorage.setItem(RANGE_KEY, range); } catch (e) { /* private mode */ }
    }

    function address(range) {
        try {
            const url = new URL(window.location.href);
            url.searchParams.set('range', range);
            window.history.replaceState(null, '', url);
        } catch (e) { /* leave the address as it is */ }
    }

    function apply(range, p) {
        applied = range;
        card.__setPlot(p);

        if (note) {
            note.textContent = p.title;
        }
    }

    function show(range) {
        if (!links.some((link) => link.dataset.range === range)) {
            return;
        }

        want = range;
        activate(range);
        remember(range);
        address(range);

        const held = cache.get(range);

        if (held) {
            apply(range, held);
            return;
        }

        fetch(endpoint + '?range=' + encodeURIComponent(range), { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error(String(response.status)))))
            .then((p) => {
                cache.set(range, p);

                if (want === range) {
                    apply(range, p);
                }
            })
            .catch(() => {
                // The fetch failed, so the picker returns to the period the
                // plot actually holds rather than claiming one it does not.
                if (want === range) {
                    want = applied;
                    activate(applied);
                    remember(applied);
                    address(applied);
                }
            });
    }

    links.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            show(link.dataset.range);
        });
    });

    // An explicit ?range= in the address is the operator's statement and is
    // already rendered; it becomes the remembered choice. Otherwise the
    // remembered choice is restored.
    if (new URLSearchParams(window.location.search).get('range')) {
        remember(applied);
    } else {
        let stored = null;

        try { stored = localStorage.getItem(RANGE_KEY); } catch (e) { /* private mode */ }

        if (stored && stored !== applied) {
            show(stored);
        }
    }
}

/**
 * The share ring, the same problem and a better place to put the answer.
 *
 * Its middle already prints the total, so pointing at a segment replaces
 * that with the segment's own figures rather than opening a panel that
 * would then have to be positioned and clamped. Nothing moves, nothing
 * waits, and the <title> elements stay exactly where they are for
 * assistive technology.
 */
function mountRing(svg) {
    const value = svg.querySelector('[data-ring-v]');
    const key = svg.querySelector('[data-ring-k]');
    const share = svg.querySelector('[data-ring-s]');

    if (!value || !key || !share) {
        return;
    }

    const idle = { value: value.textContent, key: key.textContent };

    function read(seg) {
        value.textContent = seg.dataset.ringCount;
        key.textContent = seg.dataset.ringLabel;
        share.textContent = seg.dataset.ringShare;
        svg.classList.add('is-reading');
    }

    function rest() {
        value.textContent = idle.value;
        key.textContent = idle.key;
        share.textContent = '';
        svg.classList.remove('is-reading');
    }

    svg.querySelectorAll('[data-ring-seg]').forEach((seg) => {
        // pointerover, not pointerenter: these are stroked arcs, and moving
        // from one to the next fires over/out on each rather than a single
        // enter on the parent.
        seg.addEventListener('pointerover', () => read(seg));
    });

    svg.addEventListener('pointerleave', rest);
}

function init() {
    document.querySelectorAll('[data-plot]').forEach(mount);
    document.querySelectorAll('[data-range-picker]').forEach(mountRange);
    document.querySelectorAll('[data-ring]').forEach(mountRing);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
