/**
 * An instant reading for the day sheet's revenue plot.
 *
 * Every figure the plot draws is already reachable through the SVG's own
 * <title>, which is correct for assistive technology and useless for a
 * person with a pointer: the browser holds a native tooltip for roughly a
 * second before it appears, and no CSS or attribute shortens that. A chart
 * that takes a second to answer reads as broken rather than as quiet.
 *
 * So this does the smallest possible thing. On pointer move it works out
 * which month is nearest, moves a guide line and two dots onto it, and
 * fills a readout with that month's revenue and the same month a year
 * earlier. No animation, no delay, no library.
 *
 * The <title> elements stay exactly where they are. They are what a screen
 * reader reads, and removing them to avoid a duplicate would trade a
 * cosmetic overlap for an accessibility regression.
 *
 * Every figure and every y position is computed on the server and rides on
 * data-plot as a finished string, because currency formatting belongs to
 * the store's own configuration and not to a reimplementation of it here
 * that would drift the first time a second currency appeared.
 */

function mount(card) {
    let series;

    try {
        series = JSON.parse(card.dataset.plot || 'null');
    } catch (e) {
        return;
    }

    if (!series || !Array.isArray(series.labels) || series.labels.length < 2) {
        return;
    }

    const svg = card.querySelector('.v-plot');
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
    const n = series.labels.length;

    let frameId = null;

    function paint(clientX) {
        const box = svg.getBoundingClientRect();

        if (box.width <= 0) {
            return;
        }

        // Nearest month, not the one to the left: a pointer sitting between
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
            const units = series[dot.dataset.plotDot];
            const y = (Number(units[i]) || 0) / series.height * box.height;

            dot.style.transform = `translate(${x}px, ${y}px)`;
        });

        dateEl.textContent = series.labels[i];
        currentEl.textContent = series.current[i];
        earlierEl.textContent = `${series.earlierLabels[i]} ${series.earlier[i]}`;

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
    document.querySelectorAll('[data-ring]').forEach(mountRing);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
