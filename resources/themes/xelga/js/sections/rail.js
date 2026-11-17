/**
 * Product lists and reviews: a swipe row with arrows for the carousel and
 * large variants, and the rows limit for the product grid.
 *
 * A swipe row that does not fit loops. It holds a copy of every item before
 * the real ones and another copy after them, so moving past the last item
 * carries on into the copy of the first, and moving back from the first goes
 * into the copy of the last, with no rewind and no arrow ever greyed out.
 * Once the row comes to rest inside a copy it is moved, unseen, by exactly
 * one set of items onto the real ones, which look the same. The row stays a
 * native scroller, so fingers, trackpads and momentum work as everywhere else.
 */
import { $, reduceMotion } from '../util.js';

/** Moves a looping row by a number of pixels; set up by initRail. */
const movers = new WeakMap();

/**
 * Moves a swipe row forward (positive) or back by a number of pixels, past
 * either end when it loops. Used by the row's own arrows and by autoplay.
 */
export function moveRail(track, by) {
  movers.get(track)?.(by);
}

/** Attributes a copy must not repeat: ids, and the hooks the editor finds blocks and settings by. */
const COPY_DROP = ['id', 'data-block-id', 'data-block-type', 'data-setting', 'data-setting-href', 'data-setting-attr', 'data-index'];

function copyOf(item) {
  const copy = item.cloneNode(true);
  [copy, ...copy.querySelectorAll('*')].forEach((el) => COPY_DROP.forEach((name) => el.removeAttribute(name)));
  copy.setAttribute('aria-hidden', 'true');
  copy.setAttribute('data-rail-copy', '');
  copy.inert = true;
  return copy;
}

/** Grid variant only: show columns x rows products, hide the rest. */
function applyRowLimit(section) {
  const track = $('.product-grid', section);
  if (!track) return;
  const cols = Number(getComputedStyle(section).getPropertyValue('--cols')) || 4;
  const rows = Number(section.dataset.rows) || 0;
  const wide = window.matchMedia('(min-width: 750px)').matches;
  const perRow = wide ? cols : (section.dataset.mobileCols === '1' ? 1 : 2);
  const limit = section.dataset.variant === 'grid' && rows > 0 ? perRow * rows : Infinity;
  Array.from(track.children).forEach((item, i) => item.classList.toggle('is-over-limit', i >= limit));
}

export function initRail(section) {
  const ac = new AbortController();
  const { signal } = ac;
  const track = $('.rail-track', section);
  if (!track) return () => ac.abort();
  const prev = $('[data-rail-prev]', section);
  const next = $('[data-rail-next]', section);
  const isRail = () => ['carousel', 'large'].includes(section.dataset.variant);
  const behavior = () => (reduceMotion.matches ? 'auto' : 'smooth');

  const realItems = () => Array.from(track.children).filter((el) => !el.hasAttribute('data-rail-copy'));
  const copies = () => Array.from(track.children).filter((el) => el.hasAttribute('data-rail-copy'));
  let looping = false;
  let lead = null; // the copy of the first real item, at the start of the trailing set
  let aim = null; // where a smooth move started by this script is heading
  let pressed = false;

  /** The width of one full set of items, gaps included. */
  const period = () => {
    const first = realItems().find((el) => !el.hidden);
    return first && lead ? lead.getBoundingClientRect().left - first.getBoundingClientRect().left : 0;
  };

  const unloop = () => {
    if (!looping) return;
    const set = period();
    const left = track.scrollLeft;
    copies().forEach((el) => el.remove());
    looping = false;
    lead = null;
    track.scrollTo({ left: Math.max(0, left - set), behavior: 'instant' });
  };

  const loop = () => {
    if (looping) return;
    const items = realItems();
    const visible = items.filter((el) => !el.hidden);
    if (visible.length < 2) return;
    const left = track.scrollLeft;
    const before = document.createDocumentFragment();
    const after = document.createDocumentFragment();
    items.forEach((el) => {
      before.append(copyOf(el));
      const copy = copyOf(el);
      if (el === visible[0]) lead = copy;
      after.append(copy);
    });
    track.prepend(before);
    track.append(after);
    looping = true;
    track.scrollTo({ left: left + period(), behavior: 'instant' });
  };

  // Resting inside a copy: move by one whole set onto the real items.
  const settle = () => {
    aim = null;
    if (!looping || pressed) return;
    const set = period();
    if (set <= 0) return;
    const left = track.scrollLeft;
    if (left < set - 1) track.scrollTo({ left: left + set, behavior: 'instant' });
    else if (left >= 2 * set - 1) track.scrollTo({ left: left - set, behavior: 'instant' });
  };

  const move = (by) => {
    if (!looping) {
      track.scrollBy({ left: by, behavior: behavior() });
      return;
    }
    const set = period();
    const max = track.scrollWidth - track.clientWidth;
    let from = aim ?? track.scrollLeft;
    // Quick presses can aim past the copies: first jump one set back (or
    // forward) to the same picture, so the move stays as long as asked.
    if (from + by > max || from + by < 0) {
      const shift = from + by > max ? -set : set;
      track.scrollTo({ left: track.scrollLeft + shift, behavior: 'instant' });
      from += shift;
    }
    aim = from + by;
    track.scrollTo({ left: aim, behavior: behavior() });
    if (behavior() === 'auto') settle();
  };
  movers.set(track, move);

  const update = () => {
    applyRowLimit(section);
    track.tabIndex = isRail() ? 0 : -1;
    if (isRail()) {
      track.setAttribute('role', 'region');
      track.setAttribute('aria-roledescription', 'carousel');
    } else {
      track.removeAttribute('role');
      track.removeAttribute('aria-roledescription');
    }
    // Loop only a row whose real items do not all fit.
    const overflows = isRail() && (looping ? period() > track.clientWidth + 2 : track.scrollWidth > track.clientWidth + 2);
    if (overflows) loop();
    else unloop();
    if (prev) prev.disabled = !overflows;
    if (next) next.disabled = !overflows;
  };

  const step = () => {
    const item = realItems().find((el) => !el.hidden);
    if (!item) return track.clientWidth;
    const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
    const perView = Math.max(1, Math.floor((track.clientWidth + gap) / (item.offsetWidth + gap)));
    return perView * (item.offsetWidth + gap);
  };

  prev?.addEventListener('click', () => move(-step()), { signal });
  next?.addEventListener('click', () => move(step()), { signal });
  track.addEventListener('keydown', (event) => {
    if (!isRail() || event.target !== track) return;
    const moves = { ArrowRight: step(), ArrowLeft: -step() };
    if (event.key in moves) {
      event.preventDefault();
      move(moves[event.key]);
    } else if (event.key === 'Home' || event.key === 'End') {
      event.preventDefault();
      // The first real item sits at the row's start one set in (or at 0
      // without copies); End brings the last page of real items into view.
      const set = looping ? period() : 0;
      const items = realItems().filter((el) => !el.hidden);
      const perView = Math.max(1, Math.round(step() / ((items[0]?.offsetWidth ?? 1) + (parseFloat(getComputedStyle(track).columnGap) || 0))));
      const last = items[Math.max(0, items.length - perView)];
      const end = last ? set + last.getBoundingClientRect().left - items[0].getBoundingClientRect().left : set;
      aim = null;
      track.scrollTo({ left: event.key === 'Home' ? set : end, behavior: behavior() });
    }
  }, { signal });

  // A finger or mouse still on the row holds the unseen move until it lets go.
  // A pause in scrolling counts as resting, for browsers without scrollend.
  let rest = 0;
  const restSoon = () => {
    window.clearTimeout(rest);
    rest = window.setTimeout(settle, 150);
  };
  // Touch has its own events: a pointer is cancelled as soon as the page
  // takes over a finger's swipe, while the finger is still down.
  let mouseDown = false;
  let fingerDown = false;
  const press = (mouse, finger) => {
    mouseDown = mouse ?? mouseDown;
    fingerDown = finger ?? fingerDown;
    pressed = mouseDown || fingerDown;
    if (pressed) aim = null;
    else restSoon();
  };
  track.addEventListener('pointerdown', (e) => { if (e.pointerType !== 'touch') press(true, null); }, { signal });
  ['pointerup', 'pointercancel'].forEach((type) => track.addEventListener(type, (e) => {
    if (e.pointerType !== 'touch') press(false, null);
  }, { signal }));
  track.addEventListener('touchstart', () => press(null, true), { passive: true, signal });
  ['touchend', 'touchcancel'].forEach((type) => track.addEventListener(type, (e) => {
    if (e.touches.length === 0) press(null, false);
  }, { passive: true, signal }));
  track.addEventListener('scroll', restSoon, { passive: true, signal });
  track.addEventListener('scrollend', () => {
    window.clearTimeout(rest);
    settle();
  }, { signal });

  const observer = new ResizeObserver(() => {
    update();
    settle();
  });
  observer.observe(track);
  update();

  return () => {
    ac.abort();
    observer.disconnect();
    window.clearTimeout(rest);
    movers.delete(track);
    unloop();
  };
}
