/*
 * The Filters section: the category page's filter form in a section of
 * its own, usually in a page side.
 *
 * Below 990px the sides stack above and below the page, so the form sits
 * in a box that starts closed and the listing's Filter button is a link
 * that opens it. From 990px the box is open and a change is sent at
 * once, as in the listing's own filter column. Without the script the
 * box opens by hand and the form sends with its own button.
 */
import { $ } from '../util.js';
import { initFilterForm } from './filter-form.js';

const WIDE = '(min-width: 990px)';

export function initFilters(section) {
  const box = $('[data-filters-box]', section);
  const form = $('[data-filters]', section);
  if (!box || !form) return () => {};

  const wide = window.matchMedia(WIDE);
  const cleanups = [];

  // Open beside the page, closed above it on a phone. A shopper who
  // opens or closes it keeps that until the screen crosses the line.
  const fit = () => { box.open = wide.matches; };
  fit();
  wide.addEventListener('change', fit);
  cleanups.push(() => wide.removeEventListener('change', fit));

  section.dataset.liveFilters = 'true';
  cleanups.push(() => { delete section.dataset.liveFilters; });
  cleanups.push(initFilterForm(form, { instant: () => wide.matches }));

  // Any link to this section (the listing's Filter button) opens the box
  // before the browser scrolls to it.
  const target = `#${section.id}`;
  const onClick = (event) => {
    const link = event.target.closest?.('a[href]');
    if (link && link.getAttribute('href') === target) box.open = true;
  };
  const onHash = () => {
    if (window.location.hash === target) box.open = true;
  };
  document.addEventListener('click', onClick);
  window.addEventListener('hashchange', onHash);
  onHash();
  cleanups.push(() => {
    document.removeEventListener('click', onClick);
    window.removeEventListener('hashchange', onHash);
  });

  return () => cleanups.forEach((fn) => fn());
}
