/*
 * The editor's page structure: the top bar with the Board and Preview
 * switch in its middle, the two views of the page (the layout board, and
 * Preview, the store itself in a frame), and the settings panel beside
 * them, always open (a sheet from the bottom on a phone). The server's
 * shell only provides the boot data and a mount point; everything inside
 * is drawn here, so the markup and the code that drives it change
 * together.
 */

import { $, h, icon, dotsIcon } from './dom.js';
import { S, ctx } from './state.js';
import { pageHeading } from './page-picker.js';

const DEVICES = [
  { id: 'computer', label: 'Computer', iconName: 'monitor' },
  { id: 'tablet', label: 'Tablet', iconName: 'tablet' },
  { id: 'phone', label: 'Phone', iconName: 'smartphone' },
];

export function buildShell(mount, { storeName = '', exitUrl = '/' } = {}) {
  const topbar = h('header', { class: 'topbar' },
    h('div', { class: 'topbar__start' },
      h('a', { class: 'icon-btn', href: exitUrl, id: 'exit-link', 'aria-label': 'Exit the editor', 'data-tip': 'Exit' }, icon('arrow-left')),
      h('span', { class: 'topbar__rule', 'aria-hidden': 'true' }),
      h('span', { class: 'picker-btn picker-btn--static', id: 'store-btn' },
        h('span', { class: 'picker-btn__label', text: 'Store' }),
        h('span', { class: 'picker-btn__value', id: 'store-name', text: storeName }))),
    // Two views of the same page; switching keeps the place on the page.
    h('div', { class: 'viewswitch', role: 'tablist', 'aria-label': 'View' },
      h('button', { type: 'button', class: 'viewswitch__tab', role: 'tab', id: 'tab-board', dataset: { mode: 'board' }, 'aria-controls': 'board', 'aria-selected': 'true' },
        icon('layout', 'icon--sm'), h('span', { text: 'Board' })),
      h('button', { type: 'button', class: 'viewswitch__tab', role: 'tab', id: 'tab-preview', dataset: { mode: 'preview' }, 'aria-controls': 'preview', 'aria-selected': 'false', tabindex: -1 },
        icon('eye', 'icon--sm'), h('span', { text: 'Preview' }))),
    h('div', { class: 'topbar__end' },
      h('div', { class: 'btn-pair' },
        h('button', { type: 'button', class: 'icon-btn', id: 'undo-btn', 'aria-label': 'Undo', 'data-tip': 'Undo (Ctrl+Z)', disabled: true }, icon('undo')),
        h('button', { type: 'button', class: 'icon-btn', id: 'redo-btn', 'aria-label': 'Redo', 'data-tip': 'Redo (Ctrl+Shift+Z)', disabled: true }, icon('redo'))),
      h('p', { class: 'presence', id: 'presence', tabindex: 0, hidden: true },
        icon('user', 'icon--sm'), h('span', { class: 'presence__text' })),
      h('p', { class: 'status', id: 'status', role: 'status', 'aria-live': 'polite' },
        h('span', { class: 'status__dot', 'aria-hidden': 'true' }), h('span', { class: 'status__text', text: 'Loading store' })),
      h('button', { type: 'button', class: 'icon-btn', id: 'history-btn', 'aria-label': 'Earlier versions', 'data-tip': 'Earlier versions' }, icon('clock')),
      h('button', { type: 'button', class: 'btn btn--ghost btn--theme', id: 'theme-btn', 'aria-pressed': 'false', 'aria-label': 'Theme', 'data-tip': 'Colors, fonts and buttons for the whole store' },
        icon('palette', 'icon--sm'), h('span', { text: 'Theme' })),
      h('button', { type: 'button', class: 'icon-btn', id: 'more-btn', 'aria-label': 'More actions', 'aria-haspopup': 'true', 'aria-expanded': 'false', 'data-tip': 'More' }, dotsIcon()),
      h('button', { type: 'button', class: 'btn btn--secondary', id: 'save-btn', disabled: true }, h('span', { class: 'btn__label', text: 'Save draft' })),
      h('button', { type: 'button', class: 'btn btn--primary', id: 'publish-btn', disabled: true }, h('span', { class: 'btn__label', text: 'Publish' }))));

  // After a starting store is applied: what was applied, with its one-step undo.
  const applyNote = h('div', { class: 'apply-note', id: 'apply-note', role: 'status', hidden: true },
    icon('check', 'icon--sm'),
    h('span', { id: 'apply-note-text' }),
    h('button', { type: 'button', class: 'apply-note__undo', id: 'apply-undo' }, 'Undo'),
    h('button', { type: 'button', class: 'icon-btn icon-btn--sm apply-note__close', id: 'apply-close', 'aria-label': 'Dismiss' }, icon('close', 'icon--sm')));

  // Preview: the draft store page in a frame, at the width of a computer, a tablet or a phone.
  const devices = h('div', { class: 'seg preview__devices', id: 'preview-devices', role: 'radiogroup', 'aria-label': 'Preview width' },
    ...DEVICES.map((d) => h('button', { type: 'button', role: 'radio', dataset: { value: d.id }, 'aria-checked': String(d.id === 'computer'), tabindex: d.id === 'computer' ? 0 : -1 },
      icon(d.iconName, 'icon--sm'), h('span', { text: d.label }))));
  const preview = h('div', { class: 'preview', id: 'preview', role: 'tabpanel', 'aria-labelledby': 'tab-preview', hidden: true },
    h('div', { class: 'preview__bar' },
      pageHeading('preview'),
      h('div', { class: 'preview__width' }, devices, h('p', { class: 'preview__size', id: 'preview-size', 'aria-live': 'polite' }))),
    h('div', { class: 'preview__stage', id: 'preview-stage' },
      h('div', { class: 'preview__wrap', id: 'preview-wrap' },
        h('iframe', { class: 'preview__frame', id: 'preview-frame', title: 'Store preview', src: 'about:blank' })),
      h('p', { class: 'preview__state', id: 'preview-state', role: 'status', hidden: true })));

  const canvas = h('main', { class: 'canvas', id: 'canvas' },
    h('div', { class: 'board', id: 'board', role: 'tabpanel', 'aria-labelledby': 'tab-board', 'aria-label': 'Page layout' }),
    preview,
    applyNote,
    h('div', { class: 'canvas-state', id: 'canvas-state', hidden: true }));

  // The settings of what is selected, or of the page itself; always open.
  // On a phone it is a sheet that rests as one line at the bottom; the
  // page's name is in the heading above the board.
  const sheetLine = h('button', { type: 'button', class: 'sheetline', id: 'sheet-line', 'aria-expanded': 'false', hidden: true },
    icon('settings', 'sheetline__icon'),
    h('span', { class: 'sheetline__name', text: 'Page settings' }),
    icon('chevron-up', 'icon--sm sheetline__open'));
  const drawer = h('aside', { class: 'drawer', id: 'drawer', 'aria-label': 'Settings', dataset: { sheet: 'rest', kind: 'loading' } },
    sheetLine,
    h('div', { class: 'inspector', id: 'drawer-body' }));

  const app = h('div', {
    class: 'app', id: 'app',
    dataset: { state: 'loading', loaded: 'false', drawer: 'open', mode: 'board' },
  }, topbar, canvas, drawer);

  mount.replaceChildren(app,
    h('div', { class: 'popover-layer', id: 'popover-layer' }),
    h('div', { class: 'toasts', id: 'toasts', 'aria-live': 'polite' }),
    h('p', { class: 'visually-hidden', id: 'announcer', 'aria-live': 'assertive' }));
  return app;
}

/* ------------------------------------------------------------------------
   The editor's state, and what shows instead of the board when it cannot
   be drawn: the documents did not load, or the editor cannot go on.
   ------------------------------------------------------------------------ */

/** 'loading', 'ready', 'error' (the documents did not load) or 'fatal'. */
export function setAppState(state) {
  $('#app').dataset.state = state;
}

export function canvasState({ iconName, title, text, actions = [] }) {
  const box = $('#canvas-state');
  box.replaceChildren(h('div', { class: 'canvas-state__inner' },
    h('div', { class: 'canvas-state__icon' }, icon(iconName)),
    h('h2', { text: title }),
    h('p', { text }),
    actions.length ? h('div', { class: 'canvas-state__actions' }, ...actions) : null));
  box.hidden = false;
}

export function hideCanvasState() {
  $('#canvas-state').hidden = true;
}

/** Signed out, or no access: the editor stops and says what to do. */
export function showFatal() {
  const f = S.fatal;
  if (!f) return;
  setAppState('fatal');
  const signedOut = f.kind === 'unauthenticated';
  canvasState({
    iconName: signedOut ? 'user' : 'shield-check',
    title: signedOut ? 'Your session has ended' : 'You do not have access to the theme editor',
    text: signedOut
      ? 'Sign in again to keep editing. Changes that were not saved yet are kept in this tab until you leave it.'
      : 'Your account cannot change themes. Ask an admin to give your user group the Themes edit permission.',
    actions: signedOut
      ? [h('a', { class: 'btn btn--primary', href: ctx.boot.routes?.login || window.location.href, target: '_blank', rel: 'noopener' }, 'Sign in again'),
        h('button', { type: 'button', class: 'btn btn--secondary', onclick: () => window.location.reload() }, 'Reload the editor')]
      : [h('a', { class: 'btn btn--secondary', href: ctx.boot.routes?.exit || '/' }, 'Back to admin')],
  });
}
