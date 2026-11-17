/** Header: sticky state, search toggle, menu panels, currency choice and the phone drawer. */
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

  initCurrency(section, signal);

  return () => {
    ac.abort();
    resizeObserver.disconnect();
    window.clearTimeout(hoverTimer);
  };
}

/**
 * Remember the shopper's display currency and show the page again in it.
 * Core reads the `currency` cookie (left unencrypted for this) and falls back
 * to the store's default for a code it does not offer.
 */
function chooseCurrency(code) {
  if (!/^[A-Za-z0-9]{3}$/.test(code)) return;
  const secure = window.location.protocol === 'https:' ? '; Secure' : '';
  document.cookie = `currency=${code}; path=/; max-age=31536000; SameSite=Lax${secure}`;
  window.location.reload();
}

/** The currency list: a button that opens a listbox, used with mouse or keys. */
function initCurrency(section, signal) {
  $$('.drawer__currency-choice', section).forEach((choice) => {
    choice.addEventListener('click', () => {
      if (choice.getAttribute('aria-pressed') !== 'true') chooseCurrency(choice.dataset.currency || '');
    }, { signal });
  });

  const picker = $('[data-currency-picker]', section);
  if (!picker) return;
  const button = $('.currency-picker__button', picker);
  const list = $('.currency-picker__list', picker);
  const options = $$('[role="option"]', list);
  if (!button || !list || options.length === 0) return;

  let active = 0;
  const selectedIndex = () => Math.max(0, options.findIndex((o) => o.getAttribute('aria-selected') === 'true'));

  const highlight = (index) => {
    active = (index + options.length) % options.length;
    options.forEach((option, i) => option.classList.toggle('is-active', i === active));
    list.setAttribute('aria-activedescendant', options[active].id);
    options[active].scrollIntoView({ block: 'nearest' });
  };

  const open = (index = selectedIndex()) => {
    list.hidden = false;
    button.setAttribute('aria-expanded', 'true');
    highlight(index);
    list.focus();
  };

  const close = (refocus = true) => {
    if (list.hidden) return;
    list.hidden = true;
    button.setAttribute('aria-expanded', 'false');
    list.removeAttribute('aria-activedescendant');
    if (refocus) button.focus();
  };

  const pick = (index) => {
    const option = options[index];
    if (!option) return;
    if (option.getAttribute('aria-selected') === 'true') {
      close();
      return;
    }
    chooseCurrency(option.dataset.currency || '');
  };

  button.addEventListener('click', () => (list.hidden ? open() : close()), { signal });
  button.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      open(event.key === 'ArrowUp' ? options.length - 1 : selectedIndex());
    }
  }, { signal });

  list.addEventListener('keydown', (event) => {
    switch (event.key) {
      case 'ArrowDown': event.preventDefault(); highlight(active + 1); break;
      case 'ArrowUp': event.preventDefault(); highlight(active - 1); break;
      case 'Home': event.preventDefault(); highlight(0); break;
      case 'End': event.preventDefault(); highlight(options.length - 1); break;
      case 'Enter':
      case ' ': event.preventDefault(); pick(active); break;
      case 'Escape': event.preventDefault(); close(); break;
      case 'Tab': close(false); break;
      default: {
        // Type a letter to jump to the next code that starts with it.
        if (event.key.length !== 1 || event.ctrlKey || event.metaKey || event.altKey) return;
        const letter = event.key.toUpperCase();
        for (let step = 1; step <= options.length; step++) {
          const i = (active + step) % options.length;
          if ((options[i].dataset.currency || '').startsWith(letter)) { highlight(i); break; }
        }
      }
    }
  }, { signal });

  options.forEach((option, index) => {
    option.addEventListener('click', () => pick(index), { signal });
    option.addEventListener('pointermove', () => { if (active !== index) highlight(index); }, { signal });
  });

  picker.addEventListener('focusout', (event) => {
    if (!picker.contains(event.relatedTarget)) close(false);
  }, { signal });
  document.addEventListener('click', (event) => {
    if (!picker.contains(event.target)) close(false);
  }, { signal });
}
