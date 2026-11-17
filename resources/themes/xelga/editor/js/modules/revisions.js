/*
 * Earlier versions, in the drawer: every publish of the parts this page is
 * drawn from (theme settings, header, the page, footer), newest first.
 * Restoring copies a version into the draft so it can be checked before it
 * goes live.
 */

import { h } from './dom.js';
import { S, ctx, ui, pageHandles, docLabel, isDirty, doc } from './state.js';
import { openPopover, confirmBox } from './popover.js';
import { restoreRevision } from './drafts.js';
import { formatTime } from './util.js';

const SOURCE_WORDS = {
  publish: null,
  restore: 'Restored',
  'store-template': 'From a starting store',
  import: 'Imported',
};

let token = 0;

export function openRevisions() {
  ui.openDrawer?.({ type: 'revisions' });
}

const shown = () => S.view.type === 'revisions';

/** Draws the list into the drawer's body, under the drawer's own head. */
export async function renderRevisions(body = ui.drawerBody?.(), drawerHead = ui.drawerHead) {
  if (!body || !drawerHead) return;
  const mine = ++token;
  const list = h('ul', { class: 'rev-list', role: 'list', 'aria-busy': 'true' },
    ...Array.from({ length: 5 }, () => h('li', { class: 'sk rev-sk', 'aria-hidden': 'true' })));
  body.replaceChildren(h('div', { class: 'inspector' },
    drawerHead('Earlier versions', 'Every publish is kept, the newest 30 of each part. Restoring puts a version into your draft; nothing goes live until you publish.'),
    h('div', { class: 'insp-body' }, list)));

  const handles = ['settings', ...pageHandles()];
  const results = await Promise.all(handles.map((handle) => ctx.api.revisions(handle)
    .then((items) => ({ handle, items }))
    .catch((error) => ({ handle, error }))));
  if (mine !== token || !shown()) return;

  const rows = results.flatMap((r) => (r.items || []).map((rev) => ({ ...rev, handle: r.handle })))
    .sort((a, b) => new Date(b.createdAt || 0) - new Date(a.createdAt || 0));
  const failed = results.filter((r) => r.error);

  list.setAttribute('aria-busy', 'false');
  list.replaceChildren(...rows.map((rev) => {
    const tag = rev.live ? 'Live now' : SOURCE_WORDS[rev.source];
    return h('li', { class: 'rev' },
      h('div', { class: 'rev__text' },
        h('span', { class: 'rev__title' }, docLabel(rev.handle), tag ? h('span', { class: 'rev__tag', text: tag }) : null),
        h('span', { class: 'rev__meta', text: [formatTime(rev.createdAt), rev.createdBy?.name].filter(Boolean).join(' · ') })),
      h('button', {
        type: 'button', class: 'btn btn--secondary btn--sm',
        onclick: (e) => confirmRestore(e.currentTarget, rev),
      }, 'Restore'));
  }));
  if (!rows.length && !failed.length) {
    list.append(h('li', { class: 'rev-empty' },
      h('p', { class: 'rev-empty__title', text: 'Nothing has been published yet' }),
      h('p', { text: 'Each time you publish, a copy is kept here so you can go back to it.' })));
  }
  failed.forEach((r) => list.append(h('li', { class: 'rev-error' },
    h('p', { text: `Versions of ${docLabel(r.handle)} could not be loaded. ${r.error.message}` }),
    h('button', { type: 'button', class: 'btn btn--secondary btn--sm', onclick: () => renderRevisions(body, drawerHead) }, 'Try again'))));
}

function confirmRestore(anchor, rev) {
  const label = docLabel(rev.handle);
  const unsaved = isDirty(doc(rev.handle)) || doc(rev.handle)?.hasDraft;
  openPopover(anchor, confirmBox({
    title: `Restore ${label}?`,
    text: `The version from ${formatTime(rev.createdAt)} becomes the draft of ${label}.${unsaved ? ` Your current draft of ${label} is replaced.` : ''} Nothing goes live until you publish.`,
    confirmLabel: 'Restore into draft',
    onConfirm: async () => {
      const ok = await restoreRevision(rev.handle, rev);
      if (ok) ui.closeDrawer?.();
    },
  }), { align: 'end' });
}

export function initRevisions() {
  ui.renderRevisions = renderRevisions;
}
