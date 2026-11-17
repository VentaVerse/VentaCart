/*
 * Font pairs: each card is set in the pair itself, so the merchant chooses
 * by how it looks. A pair's faces come with the boot payload as files the
 * theme ships (`faces`), loaded with the FontFace API so no stylesheet is
 * written into the page; a pair may instead name a stylesheet (`href`).
 */

import { h } from '../dom.js';
import { radioItems, radioKeys } from '../selection.js';

const loaded = new Set();

function loadPair(pair) {
  if (loaded.has(pair.value)) return;
  loaded.add(pair.value);
  if (Array.isArray(pair.faces) && 'FontFace' in window) {
    pair.faces.forEach((face) => {
      if (!face?.family || !face?.url) return;
      const font = new FontFace(face.family, `url("${encodeURI(face.url)}")`, {
        weight: String(face.weight || '400'),
        style: face.style || 'normal',
        display: 'swap',
      });
      font.load().then((f) => document.fonts.add(f)).catch(() => { /* the card falls back to the next family */ });
    });
  } else if (pair.href) {
    document.head.append(h('link', { rel: 'stylesheet', href: pair.href, dataset: { font: pair.value } }));
  }
}

export function fontPairPicker(setting, options, value, change) {
  const group = h('div', { class: 'font-pairs', role: 'radiogroup', 'aria-label': setting.label });
  options.forEach((o) => {
    loadPair(o);
    const [headName, bodyName] = String(o.label || '').split(' and ');
    const sample = h('span', { class: 'font-pair__sample', text: o.sample || 'New arrivals this week' });
    sample.style.fontFamily = o.heading || '';
    sample.style.fontWeight = String(o.heading_weight || 700);
    const body = h('span', { class: 'font-pair__body', text: o.description || '' });
    body.style.fontFamily = o.body || '';
    group.append(h('button', {
      type: 'button', role: 'radio', class: 'font-pair', dataset: { value: o.value },
      onclick: () => {
        radioItems(group, o.value);
        change(o.value);
      },
    }, sample, body,
    h('span', { class: 'font-pair__meta' }, h('span', { text: `Headings: ${headName}` }), h('span', { text: `Text: ${bodyName || headName}` }))));
  });
  radioItems(group, value);
  radioKeys(group);
  return group;
}
