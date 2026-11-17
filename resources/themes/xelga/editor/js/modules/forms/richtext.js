/*
 * Rich text: bold, italic, links and bulleted lists, nothing more. The
 * server cleans the HTML on save with a narrow allowlist; pasting here takes
 * plain text only, so formatting from other programs never comes along.
 */

import { h, toast } from '../dom.js';
import { openPopover, closePopover } from '../popover.js';

/** The link forms the server accepts: a path, an anchor, http(s), mailto or tel. */
export const isSafeLink = (value) => /^(\/(?!\/)|#|https?:\/\/[^\s]+$|mailto:|tel:)/i.test(String(value || '').trim());

const ALLOWED = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'A', 'UL', 'OL', 'LI']);

/**
 * The same narrow allowlist the server applies, run here too before a value
 * is put into the editor's own page: the editor runs in the admin, so stored
 * text is never trusted to have been cleaned. Parsing happens in an inert
 * document, so nothing in the value runs or loads.
 */
export function cleanRichText(html) {
  const doc = new DOMParser().parseFromString(`<div>${String(html || '')}</div>`, 'text/html');
  const root = doc.body.firstElementChild;
  const walk = (node) => {
    Array.from(node.children).forEach((child) => {
      if (['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'TEMPLATE', 'NOSCRIPT', 'SVG', 'MATH'].includes(child.tagName.toUpperCase())) {
        child.remove();
        return;
      }
      walk(child);
      if (!ALLOWED.has(child.tagName)) {
        child.replaceWith(...child.childNodes);
        return;
      }
      Array.from(child.attributes).forEach((attr) => {
        const keep = child.tagName === 'A' && attr.name === 'href' && isSafeLink(attr.value);
        if (!keep) child.removeAttribute(attr.name);
      });
    });
  };
  if (root) walk(root);
  return root ? Array.from(root.childNodes).map((n) => document.importNode(n, true)) : [];
}

export function richText(value, change, id) {
  const area = h('div', {
    class: 'rte__area', id, contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true', 'aria-label': 'Text',
    oninput: () => change(area.innerHTML, { merge: true }),
  });
  area.replaceChildren(...cleanRichText(value));

  area.addEventListener('paste', (e) => {
    e.preventDefault();
    const text = e.clipboardData?.getData('text/plain') || '';
    document.execCommand('insertText', false, text);
  });

  const cmd = (name, arg) => (e) => {
    e.preventDefault();
    area.focus();
    document.execCommand(name, false, arg);
    change(area.innerHTML, { merge: true });
  };

  const linkBtn = h('button', {
    type: 'button', 'aria-label': 'Link', 'aria-haspopup': 'dialog',
    onmousedown: (e) => e.preventDefault(),
    onclick: (e) => {
      const sel = window.getSelection();
      if (!sel || sel.isCollapsed || !area.contains(sel.anchorNode)) {
        toast('Select some text first, then add the link.');
        return;
      }
      const range = sel.getRangeAt(0).cloneRange();
      const input = h('input', { class: 'input', type: 'text', inputmode: 'url', placeholder: '/pages/contact or https://', 'aria-label': 'Link address' });
      const error = h('p', { class: 'field__error', hidden: true, text: 'Use a page address starting with /, or a full web address starting with https://.' });
      const apply = () => {
        const href = input.value.trim();
        if (!isSafeLink(href)) {
          error.hidden = false;
          input.setAttribute('aria-invalid', 'true');
          return;
        }
        closePopover({ restoreFocus: false });
        area.focus();
        const s = window.getSelection();
        s.removeAllRanges();
        s.addRange(range);
        document.execCommand('createLink', false, href);
        change(area.innerHTML, { merge: false });
      };
      input.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter') {
          ev.preventDefault();
          apply();
        }
      });
      openPopover(e.currentTarget, h('div', { class: 'link-pop' },
        h('p', { class: 'pop-title', text: 'Add a link' }), input, error,
        h('div', { class: 'confirm__actions' },
          h('button', { type: 'button', class: 'btn btn--secondary btn--sm', onclick: () => closePopover() }, 'Cancel'),
          h('button', { type: 'button', class: 'btn btn--primary btn--sm', onclick: apply }, 'Add link'))), { label: 'Add a link' });
    },
  }, 'Link');

  const bar = h('div', { class: 'rte__bar', role: 'toolbar', 'aria-label': 'Text formatting' },
    h('button', { type: 'button', 'aria-label': 'Bold', onmousedown: cmd('bold') }, h('strong', { text: 'B' })),
    h('button', { type: 'button', 'aria-label': 'Italic', onmousedown: cmd('italic') }, h('em', { text: 'I' })),
    linkBtn,
    h('button', { type: 'button', 'aria-label': 'Bulleted list', onmousedown: cmd('insertUnorderedList') }, 'List'));
  return h('div', { class: 'rte' }, bar, area);
}
