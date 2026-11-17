/** Shop the look: one dot open at a time; Escape and a click outside close it. */
import { $, $$ } from '../util.js';

export function initHotspots(section) {
  const ac = new AbortController();
  const { signal } = ac;
  const spots = $$('.hotspot', section);

  // Opens the product card away from the photo's nearest edge.
  const place = (spot) => {
    const x = Number(getComputedStyle(spot).getPropertyValue('--x')) || 50;
    const y = Number(getComputedStyle(spot).getPropertyValue('--y')) || 50;
    spot.dataset.side = x > 62 ? 'start' : x < 38 ? 'end' : 'middle';
    spot.toggleAttribute('data-above', y > 62);
  };

  const closeAll = (except = null) => {
    spots.forEach((spot) => {
      const btn = $('.hotspot__btn', spot);
      if (!btn || btn === except) return;
      btn.setAttribute('aria-expanded', 'false');
      const pop = $('.hotspot__pop', spot);
      if (pop) pop.hidden = true;
    });
  };

  spots.forEach((spot) => {
    place(spot);
    const btn = $('.hotspot__btn', spot);
    const pop = $('.hotspot__pop', spot);
    if (!btn || !pop) return;
    btn.addEventListener('click', () => {
      const open = btn.getAttribute('aria-expanded') !== 'true';
      closeAll(btn);
      place(spot);
      btn.setAttribute('aria-expanded', String(open));
      pop.hidden = !open;
    }, { signal });
    spot.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      closeAll();
      btn.focus();
    }, { signal });
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.hotspot')) closeAll();
  }, { signal });

  return () => ac.abort();
}
