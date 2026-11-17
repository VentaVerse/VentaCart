/*
 * DOM helpers shared by every part of the editor: element building, icons
 * from the theme's sprite, layout drawings for option cards, toasts and the
 * screen reader announcer.
 */

export const $ = (sel, scope = document) => scope.querySelector(sel);
export const $$ = (sel, scope = document) => Array.from(scope.querySelectorAll(sel));

/** Builds an element: h('button', {class: 'x', onclick: fn}, child, 'text'). */
export function h(tag, attrs, ...children) {
  const el = document.createElement(tag);
  if (attrs) {
    for (const [key, value] of Object.entries(attrs)) {
      if (value === null || value === undefined || value === false) continue;
      if (key.startsWith('on') && typeof value === 'function') el.addEventListener(key.slice(2), value);
      else if (key === 'class') el.className = value;
      else if (key === 'text') el.textContent = value;
      else if (key === 'dataset') Object.assign(el.dataset, value);
      else if (key === 'style' && typeof value === 'object') Object.assign(el.style, value);
      else if (key === 'value' && (tag === 'input' || tag === 'textarea')) el.value = String(value);
      else if (value === true) el.setAttribute(key, '');
      else el.setAttribute(key, String(value));
    }
  }
  for (const child of children.flat()) {
    if (child === null || child === undefined || child === false) continue;
    el.append(child instanceof Node ? child : document.createTextNode(String(child)));
  }
  return el;
}

const SVG_NS = 'http://www.w3.org/2000/svg';

/* The sprite is either a file (its URL comes in the boot payload) or inlined
   into the shell, in which case references stay within the document. */
let spriteUrl = '';

export function setSprite(url) {
  spriteUrl = url || '';
}

export function iconHref(name) {
  return `${spriteUrl}#i-${name}`;
}

export function icon(name, cls = '') {
  const svg = document.createElementNS(SVG_NS, 'svg');
  svg.setAttribute('class', `icon ${cls}`.trim());
  svg.setAttribute('aria-hidden', 'true');
  const use = document.createElementNS(SVG_NS, 'use');
  use.setAttribute('href', iconHref(name));
  svg.append(use);
  return svg;
}

/** The three-dot "more" icon, drawn here because the sprite has none. */
export function dotsIcon() {
  const svg = document.createElementNS(SVG_NS, 'svg');
  svg.setAttribute('class', 'icon');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('aria-hidden', 'true');
  [5, 12, 19].forEach((cx) => {
    const c = document.createElementNS(SVG_NS, 'circle');
    c.setAttribute('cx', cx);
    c.setAttribute('cy', 12);
    c.setAttribute('r', 1.6);
    c.setAttribute('fill', 'currentColor');
    c.setAttribute('stroke', 'none');
    svg.append(c);
  });
  return svg;
}

/**
 * A small layout drawing in a 64 by 40 box. Each part is kind + x,y,w,h:
 * i photo, v light text on a photo, h heading, t text, b button, m tinted
 * panel, w white card, o outline, p pill, c round photo, d accent dot.
 */
export function schematic(spec) {
  const svg = document.createElementNS(SVG_NS, 'svg');
  svg.setAttribute('class', 'schematic');
  svg.setAttribute('viewBox', '0 0 64 40');
  svg.setAttribute('aria-hidden', 'true');
  String(spec || 'm0,0,64,40').split(/\s+/).filter(Boolean).forEach((part) => {
    const kind = part[0];
    const [x, y, w, hgt] = part.slice(1).split(',').map(Number);
    let node;
    if (kind === 'c') {
      node = document.createElementNS(SVG_NS, 'ellipse');
      node.setAttribute('cx', x + w / 2);
      node.setAttribute('cy', y + hgt / 2);
      node.setAttribute('rx', w / 2);
      node.setAttribute('ry', hgt / 2);
      node.setAttribute('class', 'sk-i');
    } else {
      node = document.createElementNS(SVG_NS, 'rect');
      node.setAttribute('x', x);
      node.setAttribute('y', y);
      node.setAttribute('width', w);
      node.setAttribute('height', hgt);
      node.setAttribute('rx', kind === 'p' ? hgt / 2 : kind === 'd' ? Math.min(w, hgt) / 2 : 1);
      node.setAttribute('class', `sk-${kind}`);
    }
    svg.append(node);
  });
  return svg;
}

export function announce(text) {
  const el = $('#announcer');
  if (!el) return;
  el.textContent = '';
  window.setTimeout(() => { el.textContent = text; }, 30);
}

/**
 * A short message at the bottom of the screen. Errors stay until closed when
 * they carry an action, so a "Try again" is never missed.
 */
export function toast(text, { tone = 'info', action = null, timeout = 4200 } = {}) {
  const holder = $('#toasts');
  if (!holder) return null;
  const iconName = tone === 'success' ? 'check' : tone === 'error' ? 'alert' : 'info';
  const el = h('div', { class: `toast toast--${tone}`, role: tone === 'error' ? 'alert' : 'status' },
    icon(iconName), h('span', { text }));
  if (action) {
    el.append(h('button', { type: 'button', onclick: () => { el.remove(); action.run(); } }, action.label));
  }
  holder.append(el);
  if (timeout) window.setTimeout(() => el.remove(), timeout);
  return el;
}

let tipSeq = 0;
let openTip = null;

/**
 * A small "?" beside a label that holds an explanation the control itself
 * cannot make obvious. Shown on hover, keyboard focus or a tap; Escape, a
 * tap elsewhere or leaving closes it. The text stays in the page as the
 * control's description, so screen readers hear it without opening it.
 */
export function helpTip(text, describedId = null) {
  if (!text) return null;
  tipSeq += 1;
  const id = describedId || `tip-${tipSeq}`;
  const bubble = h('span', { class: 'tip__bubble', id, role: 'tooltip', text, hidden: true });
  const btn = h('button', { type: 'button', class: 'tip__btn', 'aria-label': 'More about this', 'aria-describedby': id, 'aria-expanded': 'false' }, '?');
  const wrap = h('span', { class: 'tip' }, btn, bubble);
  const show = () => {
    if (openTip && openTip !== hide) openTip();
    bubble.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
    openTip = hide;
  };
  function hide() {
    bubble.hidden = true;
    btn.setAttribute('aria-expanded', 'false');
    if (openTip === hide) openTip = null;
  }
  btn.addEventListener('mouseenter', show);
  btn.addEventListener('mouseleave', hide);
  btn.addEventListener('focus', show);
  btn.addEventListener('blur', hide);
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (bubble.hidden) show(); else hide();
  });
  btn.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !bubble.hidden) {
      e.stopPropagation();
      hide();
    }
  });
  return wrap;
}

document.addEventListener('pointerdown', (e) => {
  if (openTip && !e.target.closest?.('.tip')) openTip();
});
