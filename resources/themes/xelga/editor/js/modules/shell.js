/*
 * The editor's page structure: top bar, the two side panels, the canvas
 * (the layout board, or the store preview) and the phone dock. The server's shell only provides the boot data
 * and a mount point; everything inside is drawn here, so the markup and the
 * code that drives it change together.
 */

import { h, icon, dotsIcon } from './dom.js';

const deviceButton = (device, label, iconName, checked) => h('button', {
  type: 'button', role: 'radio', 'aria-checked': String(checked), tabindex: checked ? 0 : -1,
  dataset: { device }, 'aria-label': label, 'data-tip': label,
}, icon(iconName));

export function buildShell(mount, { storeName = '', exitUrl = '/', storeUrl = '/' } = {}) {
  const topbar = h('header', { class: 'topbar' },
    h('div', { class: 'topbar__start' },
      h('a', { class: 'icon-btn', href: exitUrl, id: 'exit-link', 'aria-label': 'Exit the editor', 'data-tip': 'Exit' }, icon('arrow-left')),
      h('span', { class: 'topbar__rule', 'aria-hidden': 'true' }),
      h('span', { class: 'picker-btn picker-btn--static', id: 'store-btn' },
        h('span', { class: 'picker-btn__label', text: 'Store' }),
        h('span', { class: 'picker-btn__value', id: 'store-name', text: storeName })),
      h('button', { type: 'button', class: 'picker-btn picker-btn--page', id: 'page-btn', 'aria-haspopup': 'true', 'aria-expanded': 'false' },
        h('span', { class: 'picker-btn__label', text: 'Page' }),
        h('span', { class: 'picker-btn__value', id: 'page-name', text: 'Home page' }),
        icon('chevron-down', 'icon--sm'))),
    h('div', { class: 'topbar__center' },
      h('div', { class: 'seg seg--icons', role: 'radiogroup', 'aria-label': 'Preview size', id: 'device-toggle' },
        deviceButton('desktop', 'Desktop', 'monitor', true),
        deviceButton('tablet', 'Tablet', 'tablet', false),
        deviceButton('phone', 'Phone', 'smartphone', false))),
    h('div', { class: 'topbar__end' },
      h('div', { class: 'btn-pair' },
        h('button', { type: 'button', class: 'icon-btn', id: 'undo-btn', 'aria-label': 'Undo', 'data-tip': 'Undo (Ctrl+Z)', disabled: true }, icon('undo')),
        h('button', { type: 'button', class: 'icon-btn', id: 'redo-btn', 'aria-label': 'Redo', 'data-tip': 'Redo (Ctrl+Shift+Z)', disabled: true }, icon('redo'))),
      h('p', { class: 'presence', id: 'presence', tabindex: 0, hidden: true },
        icon('user', 'icon--sm'), h('span', { class: 'presence__text' })),
      h('p', { class: 'status', id: 'status', role: 'status', 'aria-live': 'polite' },
        h('span', { class: 'status__dot', 'aria-hidden': 'true' }), h('span', { class: 'status__text', text: 'Loading store' })),
      h('a', {
        class: 'btn btn--ghost btn--preview', id: 'preview-link', href: storeUrl, target: '_blank', rel: 'noopener',
        'aria-disabled': 'true', 'aria-label': 'Open store preview in a new tab', 'data-tip': 'Your draft, as shoppers will see it',
      }, icon('external', 'icon--sm'), h('span', { text: 'Open store preview' })),
      h('button', { type: 'button', class: 'icon-btn', id: 'more-btn', 'aria-label': 'More actions', 'aria-haspopup': 'true', 'aria-expanded': 'false', 'data-tip': 'More' }, dotsIcon()),
      h('button', { type: 'button', class: 'btn btn--secondary', id: 'save-btn', disabled: true }, h('span', { class: 'btn__label', text: 'Save draft' })),
      h('button', { type: 'button', class: 'btn btn--primary', id: 'publish-btn', disabled: true }, h('span', { class: 'btn__label', text: 'Publish' }))));

  const nav = h('aside', { class: 'panel panel--nav', id: 'nav-panel', 'aria-label': 'Page sections and theme settings' },
    h('div', { class: 'sheet-handle', 'aria-hidden': 'true' }),
    h('div', { class: 'panel__tabs', role: 'tablist', 'aria-label': 'Edit' },
      h('button', { type: 'button', role: 'tab', id: 'tab-sections', 'aria-selected': 'true', 'aria-controls': 'nav-sections' }, 'Sections'),
      h('button', { type: 'button', role: 'tab', id: 'tab-theme', 'aria-selected': 'false', 'aria-controls': 'nav-theme', tabindex: -1 }, 'Theme settings'),
      h('button', { type: 'button', class: 'icon-btn sheet-close', 'data-sheet-close': true, 'aria-label': 'Close panel' }, icon('close'))),
    h('div', { class: 'panel__body', id: 'nav-sections', role: 'tabpanel', 'aria-labelledby': 'tab-sections' }),
    h('div', { class: 'panel__body', id: 'nav-theme', role: 'tabpanel', 'aria-labelledby': 'tab-theme', hidden: true }),
    h('div', { class: 'panel__body', id: 'nav-add', hidden: true }),
    h('div', { class: 'panel__body', id: 'nav-revisions', hidden: true }));

  const skeleton = h('div', { class: 'frame-skeleton', id: 'frame-skeleton', 'aria-hidden': 'true' },
    h('div', { class: 'sk sk--bar' }), h('div', { class: 'sk sk--nav' }), h('div', { class: 'sk sk--hero' }),
    h('div', { class: 'sk-row' }, h('div', { class: 'sk sk--card' }), h('div', { class: 'sk sk--card' }), h('div', { class: 'sk sk--card' }), h('div', { class: 'sk sk--card' })));

  // After a starting store is applied: what was applied, with its one-step undo.
  const applyNote = h('div', { class: 'apply-note', id: 'apply-note', role: 'status', hidden: true },
    icon('check', 'icon--sm'),
    h('span', { id: 'apply-note-text' }),
    h('button', { type: 'button', class: 'apply-note__undo', id: 'apply-undo' }, 'Undo'),
    h('button', { type: 'button', class: 'icon-btn icon-btn--sm apply-note__close', id: 'apply-close', 'aria-label': 'Dismiss' }, icon('close', 'icon--sm')));

  // The layout board, and the store preview beside it until the board does everything the preview did.
  const canvasSwitch = h('div', { class: 'seg canvas-switch', id: 'canvas-switch', role: 'radiogroup', 'aria-label': 'Show' },
    h('button', { type: 'button', role: 'radio', 'aria-checked': 'true', tabindex: 0, dataset: { canvas: 'board' } }, icon('layout', 'icon--sm'), h('span', { text: 'Board' })),
    h('button', { type: 'button', role: 'radio', 'aria-checked': 'false', tabindex: -1, dataset: { canvas: 'preview' } }, icon('monitor', 'icon--sm'), h('span', { text: 'Preview' })));

  const canvas = h('main', { class: 'canvas', id: 'canvas' },
    canvasSwitch,
    h('div', { class: 'board', id: 'board', role: 'region', 'aria-label': 'Page layout' }),
    applyNote,
    h('div', { class: 'frame-wrap', id: 'frame-wrap' },
      h('iframe', { id: 'frame', title: 'Store preview', src: 'about:blank' }),
      skeleton,
      h('div', { class: 'canvas-state', id: 'canvas-state', hidden: true })),
    h('p', { class: 'frame-size', id: 'frame-size', 'aria-live': 'polite' }));

  const inspector = h('aside', { class: 'panel panel--inspector', id: 'inspector', 'aria-label': 'Settings' },
    h('div', { class: 'sheet-handle', 'aria-hidden': 'true' }),
    h('div', { class: 'inspector', id: 'inspector-body' }));

  const dock = h('nav', { class: 'dock', 'aria-label': 'Editor panels' },
    h('button', { type: 'button', class: 'dock__btn', dataset: { dock: 'sections' } }, icon('layers'), h('span', { text: 'Sections' })),
    h('button', { type: 'button', class: 'dock__btn', dataset: { dock: 'theme' } }, icon('palette'), h('span', { text: 'Theme' })),
    h('button', { type: 'button', class: 'dock__btn', dataset: { dock: 'selected' }, id: 'dock-selected', disabled: true },
      icon('settings'), h('span', { id: 'dock-selected-label', text: 'Tap the store to edit' })));

  const app = h('div', {
    class: 'app', id: 'app',
    dataset: { device: 'desktop', nav: 'sections', panel: 'nav', state: 'loading', sheet: 'closed', canvas: 'board', loaded: 'false' },
  }, topbar, nav, canvas, inspector, dock);

  mount.replaceChildren(app,
    h('div', { class: 'popover-layer', id: 'popover-layer' }),
    h('div', { class: 'toasts', id: 'toasts', 'aria-live': 'polite' }),
    h('p', { class: 'visually-hidden', id: 'announcer', 'aria-live': 'assertive' }));
  return app;
}

/** Skeleton rows in both panels while the documents load. */
export function panelsLoading() {
  const rows = () => h('div', { class: 'sk-rows', 'aria-hidden': 'true' }, ...Array.from({ length: 9 }, () => h('div', { class: 'sk' })));
  document.getElementById('nav-sections').replaceChildren(rows());
  document.getElementById('inspector-body').replaceChildren(rows());
}
