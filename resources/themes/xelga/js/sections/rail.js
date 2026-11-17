/**
 * Product lists and reviews: a swipe row with arrows for the carousel and
 * large variants, and the rows limit for the product grid.
 */
import { $, reduceMotion } from '../util.js';

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

  const update = () => {
    applyRowLimit(section);
    const scrollable = isRail() && track.scrollWidth > track.clientWidth + 2;
    track.tabIndex = isRail() ? 0 : -1;
    if (isRail()) {
      track.setAttribute('role', 'region');
      track.setAttribute('aria-roledescription', 'carousel');
    } else {
      track.removeAttribute('role');
      track.removeAttribute('aria-roledescription');
    }
    if (prev) prev.disabled = !scrollable || track.scrollLeft <= 2;
    if (next) next.disabled = !scrollable || track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
  };

  const step = () => {
    const item = track.firstElementChild;
    if (!item) return track.clientWidth;
    const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
    const perView = Math.max(1, Math.floor((track.clientWidth + gap) / (item.offsetWidth + gap)));
    return perView * (item.offsetWidth + gap);
  };
  const behavior = () => (reduceMotion.matches ? 'auto' : 'smooth');

  prev?.addEventListener('click', () => track.scrollBy({ left: -step(), behavior: behavior() }), { signal });
  next?.addEventListener('click', () => track.scrollBy({ left: step(), behavior: behavior() }), { signal });
  track.addEventListener('keydown', (event) => {
    if (!isRail() || event.target !== track) return;
    const moves = { ArrowRight: step(), ArrowLeft: -step() };
    if (event.key in moves) {
      event.preventDefault();
      track.scrollBy({ left: moves[event.key], behavior: behavior() });
    } else if (event.key === 'Home' || event.key === 'End') {
      event.preventDefault();
      track.scrollTo({ left: event.key === 'Home' ? 0 : track.scrollWidth, behavior: behavior() });
    }
  }, { signal });

  let frame = 0;
  track.addEventListener('scroll', () => {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(update);
  }, { passive: true, signal });

  const observer = new ResizeObserver(update);
  observer.observe(track);
  update();

  return () => {
    ac.abort();
    observer.disconnect();
    cancelAnimationFrame(frame);
  };
}
