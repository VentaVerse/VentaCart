/** Header: sticky state, search toggle, menu panels and the phone drawer. */
import { $, $$, root, finePointer } from '../util.js';

export function initHeader(section) {
  const ac = new AbortController();
  const { signal } = ac;

  // Height feeds full-height heroes and scroll padding.
  const measure = () => root.style.setProperty('--header-h', `${section.offsetHeight}px`);
  const resizeObserver = new ResizeObserver(measure);
  resizeObserver.observe(section);
  measure();

  const onScroll = () => {
    section.classList.toggle('is-stuck', section.dataset.sticky === 'true' && window.scrollY > 4);
  };
  window.addEventListener('scroll', onScroll, { passive: true, signal });
  onScroll();

  // Search toggle (classic and centered on small screens)
  const searchToggle = $('.header__search-toggle', section);
  searchToggle?.addEventListener('click', () => {
    const open = !section.classList.contains('is-search-open');
    section.classList.toggle('is-search-open', open);
    searchToggle.setAttribute('aria-expanded', String(open));
    if (open) $('.search-field__input', section)?.focus();
  }, { signal });

  // Menus with panels
  const triggers = $$('.menu__link[aria-controls]', section);
  let hoverTimer = null;

  const closeAll = (except = null) => {
    triggers.forEach((trigger) => {
      if (trigger === except) return;
      trigger.setAttribute('aria-expanded', 'false');
      const panel = document.getElementById(trigger.getAttribute('aria-controls'));
      if (panel) panel.hidden = true;
    });
  };

  const setOpen = (trigger, open) => {
    closeAll(trigger);
    trigger.setAttribute('aria-expanded', String(open));
    const panel = document.getElementById(trigger.getAttribute('aria-controls'));
    if (panel) panel.hidden = !open;
  };

  triggers.forEach((trigger) => {
    const item = trigger.closest('.menu__item');
    trigger.addEventListener('click', () => {
      setOpen(trigger, trigger.getAttribute('aria-expanded') !== 'true');
    }, { signal });

    item.addEventListener('mouseenter', () => {
      if (!finePointer.matches) return;
      window.clearTimeout(hoverTimer);
      hoverTimer = window.setTimeout(() => setOpen(trigger, true), 90);
    }, { signal });

    item.addEventListener('mouseleave', () => {
      if (!finePointer.matches) return;
      window.clearTimeout(hoverTimer);
      hoverTimer = window.setTimeout(() => setOpen(trigger, false), 220);
    }, { signal });

    item.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape' || trigger.getAttribute('aria-expanded') !== 'true') return;
      setOpen(trigger, false);
      trigger.focus();
    }, { signal });

    item.addEventListener('focusout', (event) => {
      if (!item.contains(event.relatedTarget)) setOpen(trigger, false);
    }, { signal });
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.menu__item')) closeAll();
  }, { signal });

  // Phone drawer
  const drawer = $('.drawer', section);
  const burger = $('[data-drawer-open]', section);
  if (drawer && burger) {
    const close = () => drawer.close();
    burger.addEventListener('click', () => {
      drawer.showModal();
      root.classList.add('is-locked');
      burger.setAttribute('aria-expanded', 'true');
    }, { signal });
    $$('[data-drawer-close]', drawer).forEach((btn) => btn.addEventListener('click', close, { signal }));
    drawer.addEventListener('click', (event) => {
      if (event.target === drawer) close();
    }, { signal });
    drawer.addEventListener('close', () => {
      root.classList.remove('is-locked');
      burger.setAttribute('aria-expanded', 'false');
    }, { signal });
    window.matchMedia('(min-width: 990px)').addEventListener('change', (event) => {
      if (event.matches && drawer.open) close();
    }, { signal });
  }

  return () => {
    ac.abort();
    resizeObserver.disconnect();
    window.clearTimeout(hoverTimer);
  };
}
