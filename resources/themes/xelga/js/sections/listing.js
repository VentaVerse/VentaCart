/*
 * Listing pages (category, brand, search, all brands).
 *
 * The server draws everything: filters are a GET form, sorting is a GET
 * form, page numbers and Load more are links. This script only makes them
 * quicker to use: a filter change is sent at once on large screens, the
 * filters move into a drawer on phones, the sort is sent on change, the
 * grid or list choice is remembered for this shopper, and Load more adds
 * the next page's cards in place. Without it every part still works.
 */
import { $, $$ } from '../util.js';

const VIEW_KEY = 'xelga.listing-view';

function readView() {
  try {
    const value = window.localStorage.getItem(VIEW_KEY);
    return value === 'grid' || value === 'list' ? value : null;
  } catch {
    return null;
  }
}

function saveView(value) {
  try {
    window.localStorage.setItem(VIEW_KEY, value);
  } catch {
    // Private windows may refuse storage; the choice then lasts for this page only.
  }
}

/** Opens a dialog as a modal and gives focus back to the button that opened it. */
function openDialog(dialog, opener) {
  if (dialog.open) return;
  dialog.showModal();
  dialog.addEventListener('close', () => opener?.focus?.(), { once: true });
}

export function initListing(section) {
  const listing = $('[data-listing]', section);
  const cleanups = [];
  const on = (target, type, fn, options) => {
    if (!target) return;
    target.addEventListener(type, fn, options);
    cleanups.push(() => target.removeEventListener(type, fn, options));
  };

  // The sort and filters send themselves from here on, so their own buttons can go.
  section.dataset.enhanced = 'true';
  cleanups.push(() => { delete section.dataset.enhanced; });

  // Grid or list: the shopper's last choice wins over the section default.
  const toggle = $('[data-view-toggle]', section);
  const setView = (value, remember) => {
    section.dataset.variant = value;
    $$('[data-view]', section).forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.view === value)));
    if (remember) saveView(value);
  };
  if (toggle && section.dataset.switchView === 'true') {
    const saved = readView();
    if (saved) setView(saved, false);
  }
  on(toggle, 'click', (event) => {
    const button = event.target.closest('[data-view]');
    if (button) setView(button.dataset.view, true);
  });

  // Sort: send the form as soon as the choice changes.
  const sortForm = $('[data-sort-form]', section);
  on(sortForm, 'change', (event) => {
    if (event.target.matches('[data-sort]')) sortForm.requestSubmit();
  });

  // Filters: in the column where the styles draw it (a large screen with
  // room beside the products), in the drawer everywhere else. Whether the
  // column is drawn depends on the section's own width as well as the
  // screen's (sides may stand beside the content), so the script asks the
  // column itself, again whenever the section's width changes.
  const form = $('[data-filters]', section);
  const drawer = $('[data-filter-drawer]', section);
  const home = $('[data-filter-home]', section);
  const slot = $('[data-filter-slot]', section);
  if (form) {
    section.dataset.liveFilters = 'true';
    const inDrawer = () => !home || section.dataset.filters === 'drawer' || getComputedStyle(home).display === 'none';
    const place = () => {
      if (!drawer || !slot || !home) return;
      const target = inDrawer() ? slot : home;
      if (form.parentElement !== target) target.append(form);
      if (!inDrawer() && drawer.open) drawer.close();
    };
    place();
    if (typeof ResizeObserver === 'function') {
      const observer = new ResizeObserver(place);
      observer.observe(section);
      cleanups.push(() => observer.disconnect());
    }

    // In the column a change is sent at once; in the drawer the shopper
    // picks several and sends them with the drawer's button.
    let timer = null;
    const send = () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => form.requestSubmit(), 350);
    };
    on(form, 'change', (event) => {
      if (inDrawer()) return;
      if (event.target.matches('input[type=checkbox]')) send();
    });
    on(form, 'input', (event) => {
      if (inDrawer() || event.target.type !== 'number') return;
      window.clearTimeout(timer);
      timer = window.setTimeout(() => form.requestSubmit(), 900);
    });
    on(form, 'submit', () => {
      // Empty price boxes would otherwise reach the address as min_price=.
      $$('input[type=number]', form).forEach((input) => { input.disabled = input.value.trim() === ''; });
    });
    cleanups.push(() => window.clearTimeout(timer));

    if (drawer) {
      on($('[data-filter-open]', section), 'click', (event) => openDialog(drawer, event.currentTarget));
      on(drawer, 'click', (event) => {
        if (event.target.closest('[data-filter-close]') || event.target === drawer) drawer.close();
      });
    }
    cleanups.push(() => {
      if (home && form.parentElement !== home) home.append(form);
      delete section.dataset.liveFilters;
    });
  }

  // Load more: fetch the next page and add its cards to this list.
  on(section, 'click', async (event) => {
    const button = event.target.closest('[data-load-more]');
    if (!button || !listing) return;
    event.preventDefault();
    if (button.getAttribute('aria-busy') === 'true') return;
    const grid = $('[data-grid]', listing);
    const url = new URL(button.href, window.location.href);
    if (url.origin !== window.location.origin || !grid) return;

    button.setAttribute('aria-busy', 'true');
    button.textContent = 'Loading';
    try {
      const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
      if (!response.ok) throw new Error(String(response.status));
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      const next = page.getElementById(section.id);
      const items = next ? $$('[data-grid] > li', next) : [];
      items.forEach((li) => grid.append(document.importNode(li, true)));
      const nextLink = next ? $('[data-load-more]', next) : null;
      const note = next ? $('[data-load-note]', next) : null;
      const ownNote = $('[data-load-note]', section);
      if (note && ownNote) ownNote.textContent = note.textContent;
      if (nextLink) {
        button.href = nextLink.getAttribute('href');
        button.textContent = 'Load more';
        button.removeAttribute('aria-busy');
      } else {
        button.remove();
      }
      // The first new card takes focus, so a keyboard shopper lands on what was added.
      items.length && $('a', grid.children[grid.children.length - items.length])?.focus({ preventScroll: false });
    } catch {
      // The link still goes to the next page when fetching it here fails.
      window.location.assign(button.href);
    }
  });

  return () => cleanups.forEach((fn) => fn());
}
