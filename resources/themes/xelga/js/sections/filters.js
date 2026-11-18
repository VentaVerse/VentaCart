/*
 * The Filters section: the category page's filter form in a section of
 * its own, in a page side or a Content column.
 *
 * From 990px the section stands where it was put and a change is sent at
 * once, as in the listing's own filter column. Below 990px the same
 * section is the listing's filter drawer: closed until the listing's
 * Filter button opens it, a dialog while open (focus moves in and stays
 * in, Escape or the close button shut it and give focus back to the
 * button). Nothing is copied or moved, so the page holds one form at
 * every width. Without the script the section stands open and the form
 * sends with its own button.
 */
import { $, $$ } from '../util.js';
import { initFilterForm } from './filter-form.js';

const WIDE = '(min-width: 990px)';
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), summary';

/**
 * The drawer below 990px: opens from the listing's Filter button, closes
 * again, and keeps focus inside while open. From 990px it does nothing.
 * The button is looked up each time, because the listing (and its button)
 * can be drawn again while the page stays.
 *
 * @param {HTMLElement} section the Filters section
 * @returns {() => void} undoes everything this added
 */
export function initFiltersDrawer(section) {
  const buttonSelector = `[data-filter-open][aria-controls="${section.id}"]`;
  const findButton = () => $(buttonSelector);
  const wide = window.matchMedia(WIDE);
  const heading = $('[data-filters-title]', section);
  const closeButton = $('[data-filters-close]', section);
  const cleanups = [];
  const on = (target, type, fn) => {
    if (!target) return;
    target.addEventListener(type, fn);
    cleanups.push(() => target.removeEventListener(type, fn));
  };
  let isOpen = false;

  const open = () => {
    if (wide.matches || isOpen) return;
    isOpen = true;
    section.dataset.open = 'true';
    section.setAttribute('role', 'dialog');
    section.setAttribute('aria-modal', 'true');
    findButton()?.setAttribute('aria-expanded', 'true');
    document.documentElement.dataset.filtersOpen = 'true';
    heading?.focus();
  };

  const close = ({ restoreFocus = true } = {}) => {
    if (!isOpen) return;
    isOpen = false;
    delete section.dataset.open;
    section.removeAttribute('role');
    section.removeAttribute('aria-modal');
    findButton()?.setAttribute('aria-expanded', 'false');
    delete document.documentElement.dataset.filtersOpen;
    if (restoreFocus) findButton()?.focus();
  };

  on(document, 'click', (event) => {
    if (event.target.closest?.(buttonSelector)) (isOpen ? close() : open());
  });
  on(closeButton, 'click', () => close());

  on(document, 'keydown', (event) => {
    if (!isOpen) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
      return;
    }
    if (event.key !== 'Tab') return;
    const stops = $$(FOCUSABLE, section).filter((el) => !el.hidden);
    const first = stops[0] || heading;
    const last = stops[stops.length - 1] || heading;
    const active = document.activeElement;
    // The heading holds focus on opening and is not a Tab stop, so it counts as the start.
    if (event.shiftKey && (active === first || active === heading || !section.contains(active))) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && (active === last || !section.contains(active))) {
      event.preventDefault();
      first.focus();
    }
  });

  // A tap on the page behind the drawer closes it and does nothing else.
  const onOutside = (event) => {
    if (!isOpen) return;
    const target = event.target;
    if (section.contains(target) || target.closest?.(buttonSelector)) return;
    event.preventDefault();
    event.stopPropagation();
    close({ restoreFocus: false });
  };
  document.addEventListener('click', onOutside, true);
  cleanups.push(() => document.removeEventListener('click', onOutside, true));

  // A window growing past the line: the drawer is no longer a drawer.
  const onWide = () => { if (wide.matches) close({ restoreFocus: false }); };
  wide.addEventListener('change', onWide);
  cleanups.push(() => wide.removeEventListener('change', onWide));

  cleanups.push(() => close({ restoreFocus: false }));
  return () => cleanups.forEach((fn) => fn());
}

export function initFilters(section) {
  const form = $('[data-filters]', section);
  if (!form) return () => {};

  const wide = window.matchMedia(WIDE);
  const cleanups = [];

  // The styles draw the drawer only once the script is here, so a page
  // without it keeps the form in the page.
  section.dataset.drawer = 'true';
  section.dataset.liveFilters = 'true';
  cleanups.push(() => { delete section.dataset.drawer; delete section.dataset.liveFilters; });
  cleanups.push(initFilterForm(form, { instant: () => wide.matches }));

  cleanups.push(initFiltersDrawer(section));

  return () => cleanups.forEach((fn) => fn());
}
