/*
 * Xelga storefront behavior.
 *
 * Plain JavaScript, no framework. Every section type with behavior has one
 * initializer that receives the section element and returns a cleanup
 * function, so a section can be started again (one added to the page after
 * it loaded) without leaking listeners or timers.
 */
import { $$ } from './util.js';
import { bindBuying } from './cart.js';
import { initAnnouncement } from './sections/announcement-bar.js';
import { initHeader } from './sections/header.js';
import { initHero } from './sections/hero.js';
import { initRail } from './sections/rail.js';
import { initRailAutoplay } from './sections/rail-autoplay.js';
import { initCountdowns } from './sections/countdown.js';
import { initHotspots } from './sections/shop-the-look.js';
import { initProduct, initProductDetails, initReviews } from './sections/product.js';
import { initCartPage } from './sections/cart-main.js';
import { initListing } from './sections/listing.js';
import { initPageParts } from './sections/page-parts.js';
import { bindCartDrawer } from './cart-drawer.js';

const INITIALIZERS = {
  'announcement-bar': [initAnnouncement],
  header: [initHeader],
  hero: [initHero],
  'featured-products': [initRail, initRailAutoplay],
  testimonials: [initRail],
  'product-spotlight': [initCountdowns],
  'shop-the-look': [initHotspots],
  'product-main': [initProduct],
  'product-details': [initProductDetails],
  'product-reviews': [initReviews],
  'related-products': [initRail],
  'recently-viewed': [initRail],
  'cart-main': [initCartPage],
  'category-main': [initListing],
  'search-main': [initListing],
  'brand-main': [initListing],
  'brand-list-main': [initListing],
  'blog-main': [initPageParts],
  'post-main': [initPageParts],
  'page-main': [initPageParts],
  'contact-main': [initPageParts],
  'not-found-main': [initPageParts],
};

const cleanups = new WeakMap();

export function initSection(section) {
  cleanups.get(section)?.();
  const fns = INITIALIZERS[section.dataset.sectionType] || [];
  const done = fns.map((fn) => fn(section));
  cleanups.set(section, () => done.forEach((cleanup) => cleanup && cleanup()));
}

export function cleanupSection(section) {
  cleanups.get(section)?.();
  cleanups.delete(section);
}

function boot() {
  $$('[data-section-type]').forEach(initSection);
  // Sign-in, account and checkout pages carry their own script, split out
  // of the bundle every other page loads.
  if (document.querySelector('[data-fixed-page]')) {
    import('./pages/fixed-pages.js').then((m) => m.initFixedPages());
  }
}

bindBuying();
bindCartDrawer();

// A section added to the page after it loaded is started (and stopped) through these.
window.XelgaTheme = Object.freeze({ initSection, cleanupSection });

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
  boot();
}
