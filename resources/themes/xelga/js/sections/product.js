/*
 * The product page. Every price shown comes from the page data core worked
 * out on the server: for each variant (or the product) the amount the cart
 * charges at each quantity where a special starts. Choosing a variant or a
 * quantity only picks the matching row; nothing is calculated here. Adding
 * to the cart posts the chosen variant to core's cart.add.
 */
import { $, $$, reduceMotion, inEditorPreview } from '../util.js';
import { addItem, send, messageOf, toast } from '../cart.js';

const scroll = () => (reduceMotion.matches ? 'auto' : 'smooth');

/* ------------------------------------------------------------ gallery and full screen photos */
function initGallery(section, signal) {
  const root = $('[data-gallery]', section);
  const track = root && $('[data-gallery-track]', root);
  if (!track) return { go: () => {} };
  const slides = $$('.gallery__slide', track);
  const thumbs = $$('[data-gallery-go]', root);
  const now = $('[data-gallery-now]', root);
  const live = $('[data-gallery-live]', root);
  const prev = $('[data-gallery-prev]', root);
  const next = $('[data-gallery-next]', root);
  let index = 0;
  const isSlider = () => track.scrollWidth > track.clientWidth + 4;

  const mark = (i, speak) => {
    index = i;
    thumbs.forEach((t, n) => t.setAttribute('aria-current', String(n === i)));
    if (now) now.textContent = String(i + 1);
    if (speak && live) live.textContent = `Photo ${i + 1} of ${slides.length}`;
    if (prev) prev.disabled = i === 0;
    if (next) next.disabled = i === slides.length - 1;
  };
  const go = (i, speak = true) => {
    if (!slides.length) return;
    const target = Math.max(0, Math.min(slides.length - 1, i));
    if (isSlider()) track.scrollTo({ left: slides[target].offsetLeft - slides[0].offsetLeft, behavior: scroll() });
    // Stacked photos: only a shopper's own request scrolls the page.
    else if (speak) slides[target].scrollIntoView({ block: 'nearest', behavior: scroll() });
    mark(target, speak);
  };

  let raf = 0;
  track.addEventListener('scroll', () => {
    cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => {
      if (!isSlider()) return;
      const i = Math.round(track.scrollLeft / track.clientWidth);
      if (i !== index) mark(i, false);
    });
  }, { passive: true, signal });
  thumbs.forEach((t) => t.addEventListener('click', () => go(Number(t.dataset.galleryGo)), { signal }));
  prev?.addEventListener('click', () => go(index - 1), { signal });
  next?.addEventListener('click', () => go(index + 1), { signal });
  track.addEventListener('keydown', (e) => {
    const keys = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: slides.length - 1 };
    if (e.key in keys) {
      e.preventDefault();
      go(keys[e.key]);
    }
  }, { signal });
  mark(0, false);

  const box = $('[data-lightbox]', section);
  const zoom = $('[data-gallery-zoom]', root);
  if (box && zoom) {
    const img = $('[data-lightbox-img]', box);
    const count = $('[data-lightbox-now]', box);
    let li = 0;
    let opener = null;
    const show = (i) => {
      li = (i + slides.length) % slides.length;
      const src = $('img', slides[li]);
      img.src = src.currentSrc || src.src;
      img.alt = src.alt;
      count.textContent = String(li + 1);
    };
    const open = (i, trigger) => {
      show(i);
      opener = trigger;
      box.showModal();
    };
    zoom.addEventListener('click', (e) => open(index, e.currentTarget), { signal });
    slides.forEach((s, i) => s.addEventListener('click', () => open(i, zoom), { signal }));
    $('[data-lightbox-prev]', box)?.addEventListener('click', () => show(li - 1), { signal });
    $('[data-lightbox-next]', box)?.addEventListener('click', () => show(li + 1), { signal });
    $('[data-lightbox-close]', box)?.addEventListener('click', () => box.close(), { signal });
    box.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight') show(li + 1);
      if (e.key === 'ArrowLeft') show(li - 1);
    }, { signal });
    box.addEventListener('close', () => {
      go(li, false);
      opener?.focus();
    }, { signal });
  }

  return { go };
}

/* ------------------------------------------------------------ prices, variants, stock and buying */
const pointFor = (points, qty) => {
  let best = points?.[0] || null;
  for (const p of points || []) if (p.qty <= qty) best = p;
  return best;
};

function initBuying(section, data, gallery, signal) {
  const qtyInput = $('[data-product-qty]', section);
  const buy = $('[data-buy-main]', section);
  const msg = $('[data-buy-msg]', section);
  const stockNote = $('[data-stock-note]', section);
  const notify = $('[data-notify]', section);
  const groups = $$('[data-opts]', section);
  const priceNow = $('[data-price-now]', section);
  const priceWas = $('[data-price-was]', section);
  const wasLabel = $('[data-price-label-was]', section);
  const nowLabel = $('[data-price-label-now]', section);
  const badge = $('[data-price-badge]', section);
  const each = $('[data-price-each]', section);
  const buybar = $('[data-buybar]', section);
  const units = data.units || {};
  const unitList = Object.values(units);
  const selected = {};

  const chosenValue = (group) => {
    const select = $('[data-opt-select]', group);
    if (select) return select.value ? Number(select.value) : null;
    const radio = $('input[type="radio"]:checked', group);
    return radio ? Number(radio.value) : null;
  };

  const currentUnit = () => {
    if (!data.has_variants) return null;
    const ids = groups.map((g) => selected[g.dataset.opts]).filter((v) => v !== null && v !== undefined);
    if (ids.length !== groups.length) return null;
    return units[ids.slice().sort((a, b) => a - b).join('_')] || null;
  };

  const stockOf = (unit) => (unit ? unit.stock : data.stock);
  const soldOut = (unit) => data.track_stock && stockOf(unit) <= 0;

  const markOptions = () => {
    groups.forEach((group) => {
      const others = groups.filter((g) => g !== group).map((g) => selected[g.dataset.opts]).filter((v) => v);
      const choices = $$('input[type="radio"], option', group).filter((c) => c.value);
      choices.forEach((choice) => {
        const value = Number(choice.value);
        const matching = unitList.filter((u) => u.values.includes(value) && others.every((o) => u.values.includes(o)));
        const exists = matching.length > 0;
        const inStock = matching.some((u) => !data.track_stock || u.stock > 0);
        const state = !exists ? 'not available' : (inStock ? '' : 'sold out');
        if (choice.tagName === 'OPTION') {
          choice.textContent = choice.textContent.replace(/ \((sold out|not available)\)$/, '') + (state ? ` (${state})` : '');
        } else {
          const label = choice.closest('.opt');
          label?.classList.toggle('is-unavailable', state !== '');
          const note = label && $('[data-opt-state]', label);
          if (note) note.textContent = state ? `, ${state}` : '';
        }
      });
    });
  };

  const showStock = (unit) => {
    if (!stockNote) return;
    const count = stockOf(unit);
    const showLow = stockNote.dataset.showLow !== 'false';
    let level = 'in';
    let text = 'In stock, ready to ship';
    if (data.track_stock && (unit || !data.has_variants)) {
      if (count <= 0) {
        level = 'out';
        text = 'Sold out';
      } else if (showLow && count <= data.low_stock) {
        level = 'low';
        text = `Only ${count} left`;
      }
    } else if (data.track_stock && data.has_variants && unitList.length && unitList.every((u) => u.stock <= 0)) {
      level = 'out';
      text = 'Sold out';
    }
    stockNote.dataset.level = level;
    stockNote.textContent = text;
  };

  const showPrice = (unit) => {
    const qty = Math.max(1, Math.floor(Number(qtyInput?.value) || 1));
    const points = unit ? unit.points : data.points;
    const one = pointFor(points, 1);
    const atQty = pointFor(points, qty);
    if (priceNow && one) priceNow.textContent = one.price;
    const was = unit ? unit.was : data.was;
    const sale = Boolean(data.sale) && one && was !== one.price;
    if (priceWas) {
      priceWas.textContent = was;
      priceWas.hidden = !sale;
    }
    if (wasLabel) wasLabel.hidden = !sale;
    if (nowLabel) nowLabel.textContent = sale ? 'Sale price' : 'Price';
    priceNow?.closest('[data-price]')?.classList.toggle('price--sale', sale);
    if (badge) {
      // The percentage is core's, for the product; a variant priced on its own shows the word instead.
      badge.textContent = unit ? 'Sale' : `-${data.saving}%`;
      badge.hidden = !sale || (!unit && !(data.saving > 0));
    }
    if (each) {
      const tiered = atQty && one && atQty.qty > 1 && atQty.price !== one.price;
      each.hidden = !tiered;
      each.textContent = tiered ? `${atQty.price} each when you buy ${qty}` : '';
    }
    $$('[data-tier]', section).forEach((row) => {
      const tierQty = Number(row.dataset.tier);
      const point = (points || []).find((p) => p.qty === tierQty);
      const cell = $('[data-tier-price]', row);
      if (point && cell) cell.textContent = `${point.price} each`;
      row.classList.toggle('is-active', Boolean(atQty) && atQty.qty === tierQty && tierQty > 1);
    });
    if (buybar) {
      const priceText = $('[data-buybar-price]', buybar);
      if (priceText && one) priceText.textContent = one.price;
    }
  };

  const update = (fromChoice = false) => {
    groups.forEach((g) => { selected[g.dataset.opts] = chosenValue(g); });
    groups.forEach((g) => {
      const chosen = $('[data-opt-chosen]', g);
      if (!chosen) return;
      const radio = $('input[type="radio"]:checked', g);
      chosen.textContent = radio ? radio.dataset.label : 'Choose one';
    });
    markOptions();
    const unit = currentUnit();
    showStock(unit);
    showPrice(unit);
    if (qtyInput) {
      const stock = stockOf(unit);
      if (data.track_stock && (unit || !data.has_variants) && stock > 0) qtyInput.max = String(stock);
      else qtyInput.removeAttribute('max');
    }
    if (buy) {
      const allOut = data.track_stock && data.has_variants && unitList.length > 0 && unitList.every((u) => u.stock <= 0);
      const disabled = (data.has_variants ? (unit ? soldOut(unit) : allOut) : soldOut(null)) || (data.has_variants && unitList.length === 0);
      buy.disabled = disabled || Boolean(unit && soldOut(unit));
      buy.textContent = disabled || (unit && soldOut(unit)) ? 'Sold out' : 'Add to cart';
    }
    if (notify) notify.hidden = !(unit ? soldOut(unit) : (!data.has_variants && soldOut(null)));
    if (fromChoice && unit && unit.image !== null && unit.image !== undefined) gallery.go(unit.image, false);
    if (buybar) {
      const label = $('[data-buybar-variant]', buybar);
      if (label) label.textContent = unit ? unit.label : '';
    }
    if (fromChoice && msg) msg.textContent = '';
  };

  section.addEventListener('change', (e) => {
    if (e.target.closest('[data-opts]')) update(true);
  }, { signal });
  qtyInput?.addEventListener('input', () => update(false), { signal });
  qtyInput?.addEventListener('change', () => {
    const min = Number(qtyInput.min) || 1;
    const max = Number(qtyInput.max) || Infinity;
    qtyInput.value = String(Math.min(max, Math.max(min, Math.floor(Number(qtyInput.value) || min))));
    update(false);
  }, { signal });

  const say = (text, tone) => {
    if (!msg) return;
    msg.textContent = text;
    msg.dataset.tone = tone;
  };

  const add = async () => {
    if (inEditorPreview() || !buy || buy.disabled) return;
    const unit = currentUnit();
    if (data.has_variants && !unit) {
      const missing = groups.find((g) => !selected[g.dataset.opts]);
      say(missing ? 'Choose an option for each variant first.' : 'That combination is not available. Choose another.', 'error');
      (missing ? $('input, select', missing) : null)?.focus();
      $('[data-block-type="variants"]', section)?.scrollIntoView({ block: 'center', behavior: scroll() });
      return;
    }
    const quantity = Math.max(1, Math.floor(Number(qtyInput?.value) || 1));
    buy.disabled = true;
    buy.classList.add('is-loading');
    buy.setAttribute('aria-busy', 'true');
    say('', 'ok');
    const ok = await addItem({ productId: data.id, variantId: unit ? unit.id : null, quantity, name: unit ? `${data.name}, ${unit.label}` : data.name });
    buy.classList.remove('is-loading');
    buy.removeAttribute('aria-busy');
    buy.disabled = false;
    if (ok && document.documentElement.dataset.cartType === 'notification') say('Added to your cart.', 'ok');
  };
  buy?.addEventListener('click', add, { signal });
  $('[data-buybar-add]', section)?.addEventListener('click', add, { signal });

  // Back in stock email: the stock alerts extension's own route and rules.
  const form = $('[data-notify-form]', section);
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (inEditorPreview()) return;
    const out = $('[data-notify-msg]', form);
    const name = form.elements.name.value.trim();
    const email = form.elements.email.value.trim();
    if (!name || !email || !form.elements.email.checkValidity()) {
      out.textContent = 'Enter your name and a valid email address.';
      out.dataset.tone = 'error';
      (!name ? form.elements.name : form.elements.email).focus();
      return;
    }
    const button = $('button[type="submit"]', form);
    button.disabled = true;
    const unit = currentUnit();
    const result = await send(form.action, 'POST', {
      product_id: data.id,
      variant_id: unit ? unit.id : null,
      name,
      email,
      'cf-turnstile-response': $('[name="cf-turnstile-response"]', form)?.value || '',
    }).catch(() => ({ ok: false, status: 0, data: {} }));
    button.disabled = false;
    out.textContent = result.ok ? (result.data.message || 'The store will email you when it is back.') : messageOf(result, 'That could not be saved. Try again.');
    out.dataset.tone = result.ok ? 'ok' : 'error';
  }, { signal });

  // The buy bar shows once the main Add to cart button has scrolled out of view.
  if (buybar && buy && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(([entry]) => {
      const past = !entry.isIntersecting && entry.boundingClientRect.top < 0;
      buybar.classList.toggle('is-visible', past);
      buybar.inert = !past;
    });
    observer.observe(buy);
    signal.addEventListener('abort', () => observer.disconnect());
  }

  update(false);
}

/* ------------------------------------------------------------ sale countdown, share, size guide */
function initSaleEnd(section) {
  const box = $('[data-sale-end]', section);
  if (!box) return null;
  const end = new Date(box.dataset.saleEnd).getTime();
  const left = $('[data-sale-left]', box);
  const tick = () => {
    const minutes = Math.max(0, Math.floor((end - Date.now()) / 60000));
    if (Number.isNaN(end) || end <= Date.now()) {
      $('[data-sale-running]', box).hidden = true;
      $('[data-sale-ended]', box).hidden = false;
      return false;
    }
    const d = Math.floor(minutes / 1440);
    const h = Math.floor((minutes % 1440) / 60);
    const m = minutes % 60;
    const parts = [];
    if (d) parts.push(`${d} ${d === 1 ? 'day' : 'days'}`);
    if (h || d) parts.push(`${h} ${h === 1 ? 'hour' : 'hours'}`);
    if (!d) parts.push(`${m} ${m === 1 ? 'minute' : 'minutes'}`);
    left.textContent = parts.join(', ');
    return true;
  };
  if (!tick()) return null;
  const id = window.setInterval(() => { if (!tick()) window.clearInterval(id); }, 30000);
  return () => window.clearInterval(id);
}

function initExtras(section, signal) {
  $('[data-copy-link]', section)?.addEventListener('click', async (e) => {
    try {
      await navigator.clipboard.writeText(e.currentTarget.dataset.copyLink || window.location.href);
      toast('Link copied');
    } catch {
      toast('Copy the address from the address bar instead.', 'error');
    }
  }, { signal });

  // A details panel the merchant linked from the variants (a size guide).
  const panel = $('[data-variant-link]');
  const legend = $('[data-block-type="variants"] .opts:not(.opts--color) .opts__legend', section);
  if (panel && legend && !$('.opts__guide', legend)) {
    const link = document.createElement('button');
    link.type = 'button';
    link.className = 'opts__guide';
    link.textContent = panel.dataset.variantLink;
    link.addEventListener('click', () => {
      const id = panel.dataset.blockId;
      document.getElementById(`${id}-tab`)?.click();
      const acc = $(`[data-acc="${CSS.escape(id)}"]`);
      if (acc) acc.open = true;
      panel.closest('[data-section-type]')?.scrollIntoView({ block: 'start', behavior: scroll() });
    }, { signal });
    legend.append(link);
  }
}

export function initProduct(section) {
  const raw = $('[data-product-data]', section);
  if (!raw) return null;
  let data;
  try {
    data = JSON.parse(raw.textContent);
  } catch {
    return null;
  }
  const controller = new AbortController();
  const gallery = initGallery(section, controller.signal);
  initBuying(section, data, gallery, controller.signal);
  initExtras(section, controller.signal);
  const stopSale = initSaleEnd(section);
  return () => {
    controller.abort();
    stopSale?.();
  };
}

/* ------------------------------------------------------------ product details: tabs */
export function initProductDetails(section) {
  const controller = new AbortController();
  const { signal } = controller;
  $$('[role=tablist]', section).forEach((list) => {
    const tabs = () => $$('[role=tab]', list).filter((t) => !t.hidden);
    const select = (tab, focus) => {
      tabs().forEach((t) => {
        const on = t === tab;
        t.setAttribute('aria-selected', String(on));
        t.tabIndex = on ? 0 : -1;
        const panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) panel.hidden = !on;
      });
      if (focus) tab.focus();
    };
    list.addEventListener('click', (e) => {
      const tab = e.target.closest('[role=tab]');
      if (tab) select(tab, false);
    }, { signal });
    list.addEventListener('keydown', (e) => {
      const all = tabs();
      const i = all.indexOf(e.target.closest('[role=tab]'));
      const map = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: all.length - 1 };
      if (i < 0 || !(e.key in map)) return;
      e.preventDefault();
      select(all[(map[e.key] + all.length) % all.length], true);
    }, { signal });
  });
  return () => controller.abort();
}

/* ------------------------------------------------------------ reviews: show more */
export function initReviews(section) {
  const button = $('[data-reviews-more]', section);
  if (!button) return null;
  const onClick = () => {
    const hidden = $$('[data-review-more][hidden]', section);
    const step = Number(button.dataset.step) || 5;
    hidden.slice(0, step).forEach((li, i) => {
      li.hidden = false;
      if (i === 0) {
        li.tabIndex = -1;
        li.focus();
      }
    });
    if (hidden.length <= step) button.hidden = true;
  };
  button.addEventListener('click', onClick);
  return () => button.removeEventListener('click', onClick);
}
