/*
 * Every edit is an operation with a forward and a backward step. The list of
 * operations is the undo history for this visit; the documents themselves
 * are what gets saved, so history never has to be replayed on load.
 *
 * Applying an operation changes the document in memory and answers the
 * effects the preview needs: bridge messages it can apply at once, or a
 * server re-render (insert, duplicate, replace, block-insert) that the
 * preview module runs after the draft is saved.
 *
 * Operations are applied defensively: a target that no longer exists (after
 * loading someone else's changes, say) makes the step a no-op, never an error.
 */

import { S, doc, sec, ctx, labelOf } from './state.js';
import { sectionDef, blockDef, liveMode, themeSetting } from './catalog.js';
import { clone } from './util.js';

const listeners = new Set();

/** Called after every change with (op, { dir, effects, source }), source being commit, undo or redo. */
export function onChange(fn) {
  listeners.add(fn);
  return () => listeners.delete(fn);
}

/* ------------------------------------------------------------------------
   Low-level edits of a document's content
   ------------------------------------------------------------------------ */

function contentOf(handle) {
  return doc(handle)?.content || null;
}

function sectionIn(handle, sid) {
  return contentOf(handle)?.sections?.[sid] || null;
}

function clampIndex(index, length) {
  return Math.max(0, Math.min(Number.isFinite(index) ? index : length, length));
}

function moveInList(list, id, to) {
  const from = list.indexOf(id);
  if (from < 0) return false;
  list.splice(from, 1);
  list.splice(clampIndex(to, list.length), 0, id);
  return true;
}

/** The message that applies one section or block setting in the preview. */
function settingEffect(handle, sid, bid, key, value) {
  const s = sectionIn(handle, sid);
  if (!s) return [];
  const def = sectionDef(s.type);
  const list = bid ? blockDef(s.type, s.blocks[bid]?.type)?.settings || [] : def?.settings || [];
  const setting = list.find((d) => d.key === key);
  // A link to a product, category or page becomes an address only on the
  // server, so the preview draws the section again to get it right.
  const recordLink = setting?.type === 'url' && value && typeof value === 'object' && value.type !== 'url';
  if (setting && (liveMode(setting, def) === 'render' || recordLink)) {
    return [{ render: 'replace', handle, sectionId: sid }];
  }
  const message = { type: 'setting', sectionId: sid, blockId: bid || null, key, value };
  // An image is stored as a storage path; the preview also gets its address.
  if (setting?.type === 'image') message.url = value ? mediaUrl(value) : '';
  return [message];
}

/** The address of a stored image, as the API adapter knows the server serves it. */
export function mediaUrl(path) {
  if (!path) return '';
  return ctx.api?.mediaUrl ? ctx.api.mediaUrl(path) : String(path);
}

/* ------------------------------------------------------------------------
   Operation types
   ------------------------------------------------------------------------ */

const TYPES = {
  /* { doc, sid, bid, key, from, to } */
  set: {
    apply(op, fwd) {
      const s = sectionIn(op.doc, op.sid);
      const target = op.bid ? s?.blocks?.[op.bid] : s;
      if (!target) return [];
      const value = fwd ? op.to : op.from;
      if (value === undefined) delete target.settings[op.key];
      else target.settings[op.key] = clone(value);
      return settingEffect(op.doc, op.sid, op.bid, op.key, value);
    },
    describe: (op) => `change ${op.name || 'a setting'}`,
  },

  /* { doc, sid, bid, from, to } where to is true when shown */
  visible: {
    apply(op, fwd) {
      const s = sectionIn(op.doc, op.sid);
      const target = op.bid ? s?.blocks?.[op.bid] : s;
      if (!target) return [];
      const shown = fwd ? op.to : op.from;
      target.disabled = !shown;
      return [{ type: 'setting', sectionId: op.sid, blockId: op.bid || null, key: 'disabled', value: !shown }];
    },
    describe: (op) => `${op.to ? 'show' : 'hide'} ${op.name || 'section'}`,
  },

  /* { key, from, to } on the settings document */
  theme: {
    apply(op, fwd) {
      const c = contentOf('settings');
      if (!c) return [];
      const value = fwd ? op.to : op.from;
      if (value === undefined) delete c.values[op.key];
      else c.values[op.key] = clone(value);
      // Undoing back to "never set" shows the default, not the word undefined.
      return [{ type: 'theme-setting', key: op.key, value: value === undefined ? clone(themeSetting(op.key)?.default) : value }];
    },
    describe: () => 'change theme settings',
  },

  /* { scheme, role, from, to } on the settings document */
  color: {
    apply(op, fwd) {
      const scheme = contentOf('settings')?.schemes?.find((s) => s.id === op.scheme);
      if (!scheme) return [];
      const color = fwd ? op.to : op.from;
      scheme.colors[op.role] = color;
      return [{ type: 'theme-setting', key: 'schemes', value: { scheme: op.scheme, role: op.role, color } }];
    },
    describe: () => 'change scheme colors',
  },

  /* { doc, sid, from, to } index within the document's order */
  move: {
    apply(op, fwd) {
      const c = contentOf(op.doc);
      if (!c || !moveInList(c.order, op.sid, fwd ? op.to : op.from)) return [];
      return [{ type: 'move', sectionId: op.sid, toIndex: c.order.indexOf(op.sid) }];
    },
    describe: (op) => `move ${op.name || 'section'}`,
  },

  /* { doc, sid, bid, from, to } */
  rename: {
    apply(op, fwd) {
      const s = sectionIn(op.doc, op.sid);
      const target = op.bid ? s?.blocks?.[op.bid] : s;
      if (!target) return [];
      target.label = fwd ? op.to : op.from;
      return [{ type: 'rename', sectionId: op.sid, blockId: op.bid || null, label: target.label }];
    },
    describe: (op) => `rename to ${op.to}`,
  },

  /* { doc, sid, group, index, snapshot } */
  remove: {
    apply(op, fwd) {
      const c = contentOf(op.doc);
      if (!c) return [];
      if (fwd) {
        if (!c.sections[op.sid]) return [];
        op.snapshot = clone(c.sections[op.sid]);
        op.index = c.order.indexOf(op.sid);
        delete c.sections[op.sid];
        c.order = c.order.filter((id) => id !== op.sid);
        return [{ type: 'remove', sectionId: op.sid }];
      }
      if (c.sections[op.sid] || !op.snapshot) return [];
      c.sections[op.sid] = clone(op.snapshot);
      c.order.splice(clampIndex(op.index, c.order.length), 0, op.sid);
      return [{ type: 'restore', sectionId: op.sid, toIndex: c.order.indexOf(op.sid) }];
    },
    describe: (op) => `remove ${op.name || 'section'}`,
  },

  /*
   * { doc, sid, group, index, snapshot, kind: 'add' | 'duplicate', source }
   * The first time forward asks the server for the new section's markup;
   * after an undo the preview still holds it, so redo restores it.
   */
  insert: {
    apply(op, fwd) {
      const c = contentOf(op.doc);
      if (!c) return [];
      if (fwd) {
        if (c.sections[op.sid]) return [];
        c.sections[op.sid] = clone(op.snapshot);
        c.order.splice(clampIndex(op.index, c.order.length), 0, op.sid);
        if (op.shown) return [{ type: 'restore', sectionId: op.sid, toIndex: c.order.indexOf(op.sid) }];
        return [{ render: op.kind === 'duplicate' ? 'duplicate' : 'insert', handle: op.doc, sectionId: op.sid, source: op.source, group: op.group, index: c.order.indexOf(op.sid), op }];
      }
      if (!c.sections[op.sid]) return [];
      op.snapshot = clone(c.sections[op.sid]);
      delete c.sections[op.sid];
      c.order = c.order.filter((id) => id !== op.sid);
      return [{ type: 'remove', sectionId: op.sid }];
    },
    describe: (op) => `${op.kind === 'duplicate' ? 'duplicate' : 'add'} ${op.name || 'section'}`,
  },

  /* { doc, sid, bid, index, snapshot } */
  'block-add': {
    apply(op, fwd) {
      const s = sectionIn(op.doc, op.sid);
      if (!s) return [];
      if (fwd) {
        if (s.blocks[op.bid]) return [];
        s.blocks[op.bid] = clone(op.snapshot);
        s.block_order.splice(clampIndex(op.index, s.block_order.length), 0, op.bid);
        if (op.shown) return [{ type: 'block-restore', sectionId: op.sid, blockId: op.bid, index: s.block_order.indexOf(op.bid) }];
        return [{ render: 'block-insert', handle: op.doc, sectionId: op.sid, blockId: op.bid, index: s.block_order.indexOf(op.bid), op }];
      }
      if (!s.blocks[op.bid]) return [];
      op.snapshot = clone(s.blocks[op.bid]);
      delete s.blocks[op.bid];
      s.block_order = s.block_order.filter((id) => id !== op.bid);
      return [{ type: 'block-remove', sectionId: op.sid, blockId: op.bid }];
    },
    describe: (op) => `add ${op.name || 'block'}`,
  },

  /* { doc, sid, bid, index, snapshot } */
  'block-remove': {
    apply(op, fwd) {
      const s = sectionIn(op.doc, op.sid);
      if (!s) return [];
      if (fwd) {
        if (!s.blocks[op.bid]) return [];
        op.snapshot = clone(s.blocks[op.bid]);
        op.index = s.block_order.indexOf(op.bid);
        delete s.blocks[op.bid];
        s.block_order = s.block_order.filter((id) => id !== op.bid);
        return [{ type: 'block-remove', sectionId: op.sid, blockId: op.bid }];
      }
      if (s.blocks[op.bid] || !op.snapshot) return [];
      s.blocks[op.bid] = clone(op.snapshot);
      s.block_order.splice(clampIndex(op.index, s.block_order.length), 0, op.bid);
      return [{ type: 'block-restore', sectionId: op.sid, blockId: op.bid, index: s.block_order.indexOf(op.bid) }];
    },
    describe: (op) => `remove ${op.name || 'block'}`,
  },

  /* { doc, sid, bid, from, to } */
  'block-move': {
    apply(op, fwd) {
      const s = sectionIn(op.doc, op.sid);
      if (!s || !moveInList(s.block_order, op.bid, fwd ? op.to : op.from)) return [];
      return [{ type: 'block-move', sectionId: op.sid, blockId: op.bid, toIndex: s.block_order.indexOf(op.bid) }];
    },
    describe: (op) => `move ${op.name || 'block'}`,
  },
};

/** The documents an operation changes. */
export const handlesOf = (op) => (op.t === 'theme' || op.t === 'color' ? ['settings'] : [op.doc]);

export const describe = (op) => TYPES[op.t]?.describe(op) || 'change';

function run(op, dir) {
  const type = TYPES[op.t];
  if (!type) return [];
  try {
    return type.apply(op, dir > 0) || [];
  } catch (error) {
    console.warn('A change could not be applied and was skipped.', op, error);
    return [];
  }
}

function notify(op, dir, effects, source) {
  // A selection whose target is gone is cleared before anything redraws.
  if (S.sel?.kind === 'section') {
    const s = sec(S.sel.sid);
    if (!s || (S.sel.bid && !s.blocks[S.sel.bid])) S.sel = null;
  }
  listeners.forEach((fn) => fn(op, { dir, effects, source }));
}

/**
 * Records and applies a change. With `merge`, a change to the same field as
 * the previous step replaces that step's target value, so typing a heading
 * is one undo step rather than one per letter.
 */
export function commit(op, { merge = false } = {}) {
  if (!S.loaded || S.fatal) return;
  if (!op.name && op.sid) op.name = labelOf(op.sid, op.bid) || undefined;
  const last = S.history[S.pointer - 1];
  const mergeable = merge && op.mergeKey && last && S.pointer === S.history.length
    && last.mergeKey === op.mergeKey && last.seq > Math.max(...handlesOf(last).map((h) => doc(h)?.savedSeq ?? 0));
  if (mergeable) {
    last.to = op.to;
    notify(last, 1, run(last, 1), 'commit');
    return;
  }
  op.seq = ++S.seq;
  S.history.splice(S.pointer);
  S.history.push(op);
  S.pointer = S.history.length;
  notify(op, 1, run(op, 1), 'commit');
}

export function undo() {
  if (S.pointer === 0 || !S.loaded) return null;
  const op = S.history[S.pointer - 1];
  S.pointer -= 1;
  notify(op, -1, run(op, -1), 'undo');
  return op;
}

export function redo() {
  if (S.pointer >= S.history.length || !S.loaded) return null;
  const op = S.history[S.pointer];
  S.pointer += 1;
  notify(op, 1, run(op, 1), 'redo');
  return op;
}

export const canUndo = () => S.loaded && S.pointer > 0;
export const canRedo = () => S.loaded && S.pointer < S.history.length;

/** Clears history, for a fresh start after discard, restore or a page switch. */
export function resetHistory() {
  S.history = [];
  S.pointer = 0;
}

/**
 * Takes this visit's operations on one document out of the history and
 * answers those made since its last save, so they can be offered again after
 * someone else's version replaced the document.
 */
export function detachDocument(handle, savedSeq) {
  const applied = S.history.slice(0, S.pointer);
  const unsaved = applied.filter((op) => handlesOf(op).includes(handle) && op.seq > savedSeq);
  const keep = S.history.filter((op) => !handlesOf(op).includes(handle));
  const keptApplied = applied.filter((op) => !handlesOf(op).includes(handle)).length;
  S.history = keep;
  S.pointer = keptApplied;
  return unsaved;
}

/**
 * Applies earlier operations again as new steps, reading each one's current
 * starting value so undo goes back to the state they were applied on.
 */
export function reapply(ops) {
  let count = 0;
  ops.forEach((old) => {
    const op = clone({ ...old, snapshot: old.snapshot, op: undefined });
    delete op.seq;
    delete op.mergeKey;
    delete op.shown;
    const c = doc(op.doc)?.content;
    if (op.t === 'set') {
      const s = c?.sections?.[op.sid];
      const target = op.bid ? s?.blocks?.[op.bid] : s;
      if (!target) return;
      op.from = clone(target.settings[op.key]);
    } else if (op.t === 'visible') {
      const s = c?.sections?.[op.sid];
      const target = op.bid ? s?.blocks?.[op.bid] : s;
      if (!target) return;
      op.from = !target.disabled;
    } else if (op.t === 'rename') {
      const s = c?.sections?.[op.sid];
      const target = op.bid ? s?.blocks?.[op.bid] : s;
      if (!target) return;
      op.from = target.label;
    } else if (op.t === 'theme') {
      op.from = clone(doc('settings')?.content?.values?.[op.key]);
    } else if (op.t === 'color') {
      const scheme = doc('settings')?.content?.schemes?.find((s) => s.id === op.scheme);
      if (!scheme) return;
      op.from = scheme.colors[op.role];
    } else if (op.t === 'move') {
      if (!c?.order?.includes(op.sid)) return;
      op.from = c.order.indexOf(op.sid);
    } else if (op.t === 'block-move') {
      const order = c?.sections?.[op.sid]?.block_order;
      if (!order?.includes(op.bid)) return;
      op.from = order.indexOf(op.bid);
    } else if (op.t === 'insert' && c?.sections?.[op.sid]) {
      return;
    } else if (op.t === 'remove' && !c?.sections?.[op.sid]) {
      return;
    }
    commit(op);
    count += 1;
  });
  return count;
}
