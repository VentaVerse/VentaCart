/*
 * The product page. Every price shown comes from the page data core worked
 * out on the server: for each variant (or the product) the amount the cart
 * charges at each quantity where a special starts. Choosing a variant or a
 * quantity only picks the matching row; nothing is calculated here. Adding
 * to the cart posts the chosen variant to core's cart.add.
 */
import { $, $$, reduceMotion, finePointer, inEditorPreview } from '../util.js';
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
  mark(0, false);

  const box = $('[data-lightbox]', section);
  const zoom = $('[data-gallery-zoom]', root);
  const lightbox = box ? initLightbox(box, {
    openers: [zoom, ...slides],
    current: () => index,
    // Back on the page: show the photo last viewed and put focus on it.
    closed: (i, opener) => {
      go(i, false);
      if (opener === zoom || opener === track) opener.focus();
      else if (slides[i]) {
        slides[i].tabIndex = -1;
        slides[i].focus({ preventScroll: true });
      }
    },
  }, signal) : null;

  // Arrow keys change photo; Enter or Space on the slider opens the photo in
  // view full screen.
  track.addEventListener('keydown', (e) => {
    const keys = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: slides.length - 1 };
    if (e.key in keys) {
      e.preventDefault();
      go(keys[e.key]);
    } else if ((e.key === 'Enter' || e.key === ' ') && e.target === track && lightbox) {
      e.preventDefault();
      lightbox.open(index, track);
    }
  }, { signal });

  if (section.dataset.zoom === 'hover') initHoverZoom(section, slides, signal);

  return { go };
}

/*
 * Zoom on hover, in place: with a mouse or trackpad, the photo under the
 * pointer is magnified inside its own frame, centered on the spot under the
 * pointer, and follows it. The magnified photo is a background on a layer
 * over the frame, read from the photo's original file. It is at least 1.6
 * times the photo shown and at most what that file can give without
 * blurring; a photo too small to magnify that far keeps only the click to
 * full screen. Touch and pen input never magnify: a tap opens full screen.
 */
const MIN_ZOOM = 1.6;
const MAX_ZOOM = 2.5;

function initHoverZoom(section, slides, signal) {
  const on = () => section.dataset.zoom === 'hover' && finePointer.matches;

  // The photo's drawn box inside the frame, for contain or cover.
  const contentBox = (slide, img) => {
    const fw = slide.clientWidth;
    const fh = slide.clientHeight;
    const nw = img.naturalWidth;
    const nh = img.naturalHeight;
    if (!fw || !fh || !nw || !nh) return null;
    const cover = getComputedStyle(img).objectFit === 'cover';
    const s = cover ? Math.max(fw / nw, fh / nh) : Math.min(fw / nw, fh / nh);
    const cw = nw * s;
    const ch = nh * s;
    return { fw, fh, nw, cw, ch, ox: (fw - cw) / 2, oy: (fh - ch) / 2 };
  };

  const layerFor = (slide, img) => {
    let layer = $('.gallery__magnify', slide);
    if (!layer) {
      const src = String(img.dataset.zoomSrc || img.currentSrc || img.src).replace(/["\\\n]/g, (c) => encodeURIComponent(c));
      layer = document.createElement('span');
      layer.className = 'gallery__magnify';
      layer.setAttribute('aria-hidden', 'true');
      layer.style.backgroundImage = `url("${src}")`;
      slide.append(layer);
    }
    return layer;
  };

  const unmagnify = (slide) => slide.classList.remove('is-magnified');

  const place = (slide, e) => {
    const img = $('img', slide);
    const b = img && contentBox(slide, img);
    if (!b) return unmagnify(slide);
    const most = Math.min(MAX_ZOOM, b.nw / b.cw);
    if (most < MIN_ZOOM * 0.85) return unmagnify(slide);
    const z = Math.max(MIN_ZOOM, most);
    const rect = slide.getBoundingClientRect();
    const px = e.clientX - rect.left;
    const py = e.clientY - rect.top;
    // The point of the photo under the pointer, from 0 to 1 each way.
    const u = Math.min(1, Math.max(0, (px - b.ox) / b.cw));
    const v = Math.min(1, Math.max(0, (py - b.oy) / b.ch));
    const zw = b.cw * z;
    const zh = b.ch * z;
    // Keep that point under the pointer, but never pull an edge of the
    // magnified photo inside the frame when it is bigger than the frame.
    const clamp = (pos, size, frame) => (size >= frame ? Math.min(0, Math.max(frame - size, pos)) : (frame - size) / 2);
    const layer = layerFor(slide, img);
    layer.style.backgroundSize = `${zw}px ${zh}px`;
    layer.style.backgroundPosition = `${clamp(px - u * zw, zw, b.fw)}px ${clamp(py - v * zh, zh, b.fh)}px`;
    slide.classList.add('is-magnified');
  };

  slides.forEach((slide) => {
    slide.addEventListener('pointermove', (e) => {
      if (e.pointerType !== 'mouse' || !on()) return unmagnify(slide);
      place(slide, e);
    }, { signal });
    slide.addEventListener('pointerleave', () => unmagnify(slide), { signal });
    // The full screen photos open on the same click; leave nothing magnified behind.
    slide.addEventListener('click', () => unmagnify(slide), { signal });
  });
}

/*
 * Full screen photos. The track is a row of photos, one screen wide each,
 * moved by a transform rather than by scrolling, so every input ends the same
 * way: a photo let go past the threshold carries straight on to the next one
 * from where it was let go, and never springs back to the middle first.
 * Inputs: the arrow buttons and keys, Home and End, a mouse wheel or trackpad
 * (one photo per turn, up and down or sideways), and dragging with a mouse,
 * finger or pen. The viewer never moves on its own.
 *
 * With two or more photos it loops. The track holds a copy of the last photo
 * before the first and a copy of the first after the last, so stepping past
 * either end is an ordinary one-photo move; once the track comes to rest on
 * a copy it is put, unseen, on the real photo it copies. Positions below are
 * counted in "slots": slot 0 is the copy of the last photo, slots 1 to n the
 * real photos, slot n + 1 the copy of the first. Between the copies, slot s
 * and slot s + n look the same, which is what lets the track jump between
 * them without a visible change.
 */
function initLightbox(box, { openers, current, closed }, signal) {
  const track = $('[data-lightbox-track]', box);
  const items = $$('[data-lightbox-slide]', box);
  const prev = $('[data-lightbox-prev]', box);
  const next = $('[data-lightbox-next]', box);
  const now = $('[data-lightbox-now]', box);
  const live = $('[data-lightbox-live]', box);
  if (!track || !items.length) return null;
  const n = items.length;
  const loops = n > 1;

  if (loops) {
    // The copies are for the eye only: screen readers, Tab and Find skip them.
    const copy = (slide) => {
      const c = slide.cloneNode(true);
      c.removeAttribute('data-lightbox-slide');
      c.removeAttribute('role');
      c.removeAttribute('aria-roledescription');
      c.removeAttribute('aria-label');
      c.setAttribute('aria-hidden', 'true');
      c.inert = true;
      $('img', c).alt = '';
      return c;
    };
    track.prepend(copy(items[n - 1]));
    track.append(copy(items[0]));
  }
  const offset = loops ? 1 : 0;
  const maxSlot = loops ? n + 1 : 0;

  let pos = offset; // the slot in view; between two slots while moving
  let aim = offset; // the slot the track is heading for
  let at = 0; // the real photo counted as shown
  let frame = 0;
  let opener = null;
  let drag = null;
  let moved = 0;

  const rtl = () => getComputedStyle(track).direction === 'rtl';
  const width = () => track.getBoundingClientRect().width || 1;
  const realOf = (slot) => (((Math.round(slot) - offset) % n) + n) % n;
  // The track is as wide as the viewer, so a percentage moves whole photos.
  const paint = () => {
    track.style.transform = `translate3d(${(rtl() ? pos : -pos) * 100}%, 0, 0)`;
  };

  const mark = (i, speak) => {
    at = i;
    if (now) now.textContent = String(i + 1);
    if (speak && live) live.textContent = `Photo ${i + 1} of ${n}`;
  };

  // Resting on a copy: swap to the real photo it copies, which looks the same.
  const rest = () => {
    frame = 0;
    if (loops && (aim === 0 || aim === maxSlot)) {
      aim = aim === 0 ? n : 1;
      pos = aim;
      paint();
    }
    if (realOf(aim) !== at) mark(realOf(aim), false);
  };

  // Eases from wherever the track is now to a slot, so a photo let go mid-drag
  // or caught mid-move keeps going the way it was going.
  const glide = (slot, speak = true) => {
    cancelAnimationFrame(frame);
    aim = slot;
    mark(realOf(slot), speak);
    const from = pos;
    const distance = slot - from;
    if (reduceMotion.matches || Math.abs(distance) < 0.001) {
      pos = slot;
      paint();
      rest();
      return;
    }
    const duration = Math.min(480, 260 + 120 * Math.abs(distance));
    const start = performance.now();
    const tick = (time) => {
      const p = Math.min(1, (time - start) / duration);
      pos = from + distance * (1 - (1 - p) ** 3);
      paint();
      if (p < 1) frame = requestAnimationFrame(tick);
      else rest();
    };
    frame = requestAnimationFrame(tick);
  };

  // Keeps a moving track between the half-way points of the two copies by
  // jumping one whole set of photos, which shows exactly the same picture.
  const wrap = () => {
    if (!loops) return 0;
    if (pos < 0.5) { pos += n; return n; }
    if (pos > n + 0.5) { pos -= n; return -n; }
    return 0;
  };

  // One photo forward (1) or back (-1). If the next slot would be past a copy
  // (quick presses while the track is still moving), the track first jumps a
  // whole set of photos to the same picture, so the move stays one photo long.
  const step = (by) => {
    if (!loops) return;
    let target = aim + by;
    if (target > maxSlot || target < 0) {
      const shift = target > maxSlot ? -n : n;
      pos = pos + shift >= 0 && pos + shift <= maxSlot ? pos + shift : aim + shift;
      target += shift;
      paint();
    }
    glide(target);
  };
  const go = (i) => glide(i + offset);

  const show = (start, from) => {
    if (inEditorPreview() || box.open) return;
    opener = from;
    // Photos off to the side are never in view until they slide in, so a lazy
    // photo would arrive blank; load them all once the viewer is opened.
    $$('img', track).forEach((img) => { img.loading = 'eager'; });
    box.showModal();
    const i = Number.isFinite(start) ? Math.max(0, Math.min(n - 1, start)) : 0;
    cancelAnimationFrame(frame);
    pos = aim = i + offset;
    paint();
    mark(i, false);
  };
  const open = (e) => {
    const from = e.currentTarget;
    show(from.matches('.gallery__slide') ? Number(from.dataset.index) : current(), from);
  };
  openers.filter(Boolean).forEach((el) => el.addEventListener('click', open, { signal }));
  box.addEventListener('close', () => {
    cancelAnimationFrame(frame);
    if (drag) endDrag();
    closed(at, opener);
  }, { signal });
  $('[data-lightbox-close]', box)?.addEventListener('click', () => box.close(), { signal });
  prev?.addEventListener('click', () => step(-1), { signal });
  next?.addEventListener('click', () => step(1), { signal });

  box.addEventListener('keydown', (e) => {
    if (!loops) return;
    const back = rtl() ? 1 : -1;
    const keys = { ArrowRight: () => step(-back), ArrowLeft: () => step(back), Home: () => go(0), End: () => go(n - 1) };
    if (!(e.key in keys)) return;
    e.preventDefault();
    keys[e.key]();
  }, { signal });

  // A wheel or trackpad moves one photo per turn, up and down or sideways.
  // Further wheel events (a trackpad's glide) are ignored until they pause.
  // Pinch zoom (wheel with Ctrl) is left to the browser.
  let wheelHeld = false;
  let wheelSum = 0;
  let wheelTimer = 0;
  box.addEventListener('wheel', (e) => {
    if (e.ctrlKey) return;
    e.preventDefault();
    if (!loops) return;
    const sideways = Math.abs(e.deltaX) > Math.abs(e.deltaY);
    const delta = sideways ? (rtl() ? -e.deltaX : e.deltaX) : e.deltaY;
    if (!wheelHeld) {
      wheelSum += delta * (e.deltaMode === 1 ? 16 : 1);
      if (Math.abs(wheelSum) >= 12) {
        wheelHeld = true;
        step(wheelSum > 0 ? 1 : -1);
      }
    }
    clearTimeout(wheelTimer);
    wheelTimer = setTimeout(() => { wheelHeld = false; wheelSum = 0; }, 200);
  }, { passive: false, signal });

  // Dragging with a mouse, finger or pen. The photo follows the pointer; let
  // go past a short pull (or with a quick flick) it carries on to the next
  // photo in that direction, otherwise it settles back where it started.
  const endDrag = () => {
    if (!drag) return;
    const { from, speed } = drag;
    drag = null;
    track.classList.remove('is-dragging');
    const travelled = (pos - from) * width();
    const pulled = Math.abs(travelled) > Math.min(80, width() * 0.12)
      || (Math.abs(speed) > 0.35 && Math.abs(travelled) > 12);
    if (!pulled) {
      glide(Math.round(pos), false);
      return;
    }
    const target = travelled > 0 ? Math.ceil(pos - 0.001) : Math.floor(pos + 0.001);
    glide(Math.max(0, Math.min(maxSlot, target)));
  };
  track.addEventListener('pointerdown', (e) => {
    moved = 0;
    if (!loops || drag || (e.pointerType === 'mouse' && e.button !== 0)) return;
    // Catch a moving photo where it is.
    cancelAnimationFrame(frame);
    wrap();
    drag = { id: e.pointerId, x: e.clientX, from: pos, lastX: e.clientX, lastTime: e.timeStamp, speed: 0 };
    track.setPointerCapture(e.pointerId);
    track.classList.add('is-dragging');
  }, { signal });
  track.addEventListener('pointermove', (e) => {
    if (!drag || e.pointerId !== drag.id) return;
    moved = e.clientX - drag.x;
    const elapsed = e.timeStamp - drag.lastTime;
    if (elapsed > 0) drag.speed = 0.6 * ((e.clientX - drag.lastX) / elapsed) + 0.4 * drag.speed;
    drag.lastX = e.clientX;
    drag.lastTime = e.timeStamp;
    pos = drag.from - (rtl() ? -moved : moved) / width();
    const shift = wrap();
    drag.from += shift;
    paint();
  }, { signal });
  track.addEventListener('pointerup', endDrag, { signal });
  track.addEventListener('pointercancel', endDrag, { signal });
  track.addEventListener('lostpointercapture', endDrag, { signal });

  // A click anywhere but the photo closes the viewer, unless it ended a drag.
  track.addEventListener('click', (e) => {
    if (Math.abs(moved) > 6) return;
    if (!e.target.closest('.lightbox__img')) box.close();
  }, { signal });

  return { open: show };
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
