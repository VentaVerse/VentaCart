/*
 * Every edit is an operation with a forward and a backward step. The list of
 * operations is the undo history for this visit; the documents themselves
 * are what gets saved, so history never has to be replayed on load.
 *
 * Applying an operation changes the document in memory and answers its
 * effects: what changed in the page's rows and sides ({board, rows,
 * sections}) when they did, else nothing.
 *
 * Operations are applied defensively: a target that no longer exists (after
 * loading someone else's changes, say) makes the step a no-op, never an error.
 *
 * Rows and sides change through the row model (board/model.js). Its
 * operations remember the page's rows, sides and order before the change,
 * so undo puts back exactly what was there; their effect names what the
 * layout board draws again ({board, rows, sections}).
 */

import { S, doc, sec, ctx, labelOf } from './state.js';
import { sectionDef } from './catalog.js';
import { clone, same } from './util.js';
import { place, locate, rowsOf, addRow, moveRow, removeRow, changeSplit, setRow, effectsOf, tidy, catalogRules, replaceIn, sideTargets } from './board/model.js';

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

/* ------------------------------------------------------------------------
   Rows and sides
   ------------------------------------------------------------------------ */

const LAYOUT_KEYS = ['order', 'rows', 'sides'];

/** The boot payload's rows, splits and limits. */
export const layoutPayload = () => ctx.boot?.layout || null;

/** The sides a document's page may have (both, or none), from the boot payload's `sides_on`. */
export function sidesFor(handle) {
  const layout = layoutPayload();
  const kind = handle === 'settings' ? 'settings' : handle === 'header' || handle === 'footer' ? 'group' : 'template';
  if (kind !== 'template' || !layout) return [];
  return sideTargets(String(handle).split('.')[0], layout);
}

/**
 * The layout rules of one document, from the section catalog: spans,
 * locked sections, sections that keep a row of their own or belong in
 * Content, limits per area. A band (header, footer) has no sides.
 */
export function rulesFor(handle) {
  return catalogRules(layoutPayload(), sectionDef, sidesFor(handle).length > 0);
}

/** Whether a document places its sections in rows or sides, not only in order. */
export const hasLayout = (c) => Boolean(c) && ('rows' in c || 'sides' in c);

/** What undo needs to put a document's rows, sides and order back, key order included. */
const layoutSnapshot = (c) => ({ keys: Object.keys(c), values: clone({ order: c.order, rows: c.rows, sides: c.sides }) });

/** A copy holding only what the row model reads, to compare before and after. */
const layoutView = (c) => ({ order: clone(c.order), rows: clone(c.rows), sides: clone(c.sides), sections: c.sections });

/**
 * Writes rows, sides and order into the document in place (other parts of
 * the editor hold it), keeping the key order the values came with.
 */
function writeLayout(c, keys, values) {
  const rest = {};
  Object.keys(c).forEach((key) => {
    rest[key] = c[key];
    delete c[key];
  });
  keys.forEach((key) => {
    if (LAYOUT_KEYS.includes(key)) {
      if (values[key] !== undefined) c[key] = clone(values[key]);
    } else if (key in rest) {
      c[key] = rest[key];
    }
  });
  Object.keys(rest).forEach((key) => {
    if (!LAYOUT_KEYS.includes(key) && !(key in c)) c[key] = rest[key];
  });
}

/**
 * An operation type that changes rows or sides through the model.
 * `change(op, content, rules)` answers `{content, created?, moved?}`.
 * Forward always works from the document as it is, so redo after undo
 * gives the same result; backward puts back the snapshot kept in `prior`.
 */
function layoutType(change, describe) {
  return {
    apply(op, fwd) {
      const c = contentOf(op.doc);
      if (!c) return [];
      const rules = rulesFor(op.doc);
      if (!fwd) {
        if (!op.prior) return [];
        const was = layoutView(c);
        writeLayout(c, op.prior.keys, op.prior.values);
        return [effectsOf(was, c, rules)];
      }
      op.prior = null;
      const result = change(op, c, rules);
      const next = result?.content;
      if (!next || same({ keys: Object.keys(next), order: next.order, rows: next.rows, sides: next.sides }, { keys: Object.keys(c), order: c.order, rows: c.rows, sides: c.sides })) return [];
      op.prior = layoutSnapshot(c);
      op.created = result.created || [];
      op.moved = result.moved || [];
      const was = layoutView(c);
      writeLayout(c, Object.keys(next), next);
      const effects = effectsOf(was, c, rules);
      // The sections the change took out of Content, for the message that confirms it.
      op.left = effects.left;
      return [effects];
    },
    describe,
  };
}

/** Gives a document's sections these entries, in this order, keeping the map itself (other parts of the editor hold it). */
function setSections(c, entries) {
  Object.keys(c.sections).forEach((id) => { delete c.sections[id]; });
  entries.forEach(([id, section]) => { c.sections[id] = section; });
}

/**
 * After a section is added or removed on a page with rows: drop its id
 * from the rows, or give it a row after the one of the section before it.
 */
function tidyLayout(c, handle) {
  if (!hasLayout(c)) return;
  const next = tidy(c, rulesFor(handle));
  writeLayout(c, Object.keys(next), next);
}

/**
 * A copy sits right under its original when that is in a column or a side
 * with room, as the server puts it; otherwise it keeps the row of its own
 * tidy() gave it.
 */
function placeUnder(handle, c, source, sid) {
  const layout = layoutPayload();
  const at = locate(c, source, layout);
  if (!at) return;
  let target;
  if (at.where === 'side') {
    target = { drop: 'side', side: at.side, before: c.sides?.[at.side]?.[at.index + 1] ?? null };
  } else {
    const row = rowsOf(c, layout).find((r) => r.id === at.rowId);
    if (!row || row.split === '12') return;
    target = { drop: 'column', rowId: at.rowId, column: at.column, before: row.columns[at.column][at.index + 1] ?? null };
  }
  const next = place(c, sid, target, rulesFor(handle)).content;
  if (next !== c) writeLayout(c, Object.keys(next), next);
}

/**
 * A section added at a spot of the board moves there from the row of its
 * own tidy() gave it. When it is already there, or the spot no longer
 * takes it, it keeps that row. Answers the other sections the move pushed
 * out of their columns (a side filled for the first time narrows the page).
 */
function placeAt(handle, c, sid, target) {
  const { content: next, moved } = place(c, sid, target, rulesFor(handle));
  if (next !== c) writeLayout(c, Object.keys(next), next);
  return moved;
}

/** The gap a row operation goes to; an operation without an area takes the one of the row it is before. */
const gapOf = (op) => ({ drop: 'gap', area: op.area ?? null, before: op.before ?? 'end' });

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
      return [];
    },
    describe: (op) => `change ${op.name || 'a setting'}`,
  },

  /* { doc, sid, bid, from, to } where to is true when shown */
  visible: {
    apply(op, fwd) {
      const s = sectionIn(op.doc, op.sid);
      const target = op.bid ? s?.blocks?.[op.bid] : s;
      if (!target) return [];
      target.disabled = !(fwd ? op.to : op.from);
      return [];
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
      return [];
    },
    describe: () => 'change theme settings',
  },

  /* { scheme, role, from, to } on the settings document */
  color: {
    apply(op, fwd) {
      const scheme = contentOf('settings')?.schemes?.find((s) => s.id === op.scheme);
      if (!scheme) return [];
      scheme.colors[op.role] = fwd ? op.to : op.from;
      return [];
    },
    describe: () => 'change scheme colors',
  },

  /* { doc, sid, from, to } index within the document's order */
  move: {
    apply(op, fwd) {
      const c = contentOf(op.doc);
      if (c) moveInList(c.order, op.sid, fwd ? op.to : op.from);
      return [];
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
      return [];
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
        op.layout = hasLayout(c) ? layoutSnapshot(c) : null;
        const was = op.layout ? layoutView(c) : null;
        delete c.sections[op.sid];
        c.order = c.order.filter((id) => id !== op.sid);
        tidyLayout(c, op.doc);
        return was ? [effectsOf(was, c, rulesFor(op.doc))] : [];
      }
      if (c.sections[op.sid] || !op.snapshot) return [];
      c.sections[op.sid] = clone(op.snapshot);
      if (op.layout) {
        const was = layoutView(c);
        writeLayout(c, op.layout.keys, op.layout.values);
        return [effectsOf(was, c, rulesFor(op.doc))];
      }
      c.order.splice(clampIndex(op.index, c.order.length), 0, op.sid);
      return [];
    },
    describe: (op) => `remove ${op.name || 'section'}`,
  },

  /*
   * { doc, sid, group, index, snapshot, kind: 'add' | 'duplicate', source, target }
   * `target`, on a page with rows, is the spot of the board it is added at.
   */
  insert: {
    apply(op, fwd) {
      const c = contentOf(op.doc);
      if (!c) return [];
      if (fwd) {
        if (c.sections[op.sid]) return [];
        // A spot of the board (a side, say) can give a page of full-width
        // sections its first rows or sides.
        op.layout = hasLayout(c) || op.target ? layoutSnapshot(c) : null;
        const was = op.layout ? layoutView(c) : null;
        c.sections[op.sid] = clone(op.snapshot);
        c.order.splice(clampIndex(op.index, c.order.length), 0, op.sid);
        // On a page with rows the new section gets its own row after the
        // row of the section before it, then moves to its spot.
        if (!was) return [];
        tidyLayout(c, op.doc);
        if (op.kind === 'duplicate' && op.source) placeUnder(op.doc, c, op.source, op.sid);
        if (op.target) op.moved = placeAt(op.doc, c, op.sid, op.target);
        const effects = effectsOf(was, c, rulesFor(op.doc));
        // The sections the new one took out of Content (a first side narrows it).
        op.left = effects.left;
        return [effects];
      }
      if (!c.sections[op.sid]) return [];
      op.snapshot = clone(c.sections[op.sid]);
      const was = op.layout ? layoutView(c) : null;
      delete c.sections[op.sid];
      c.order = c.order.filter((id) => id !== op.sid);
      if (op.layout) writeLayout(c, op.layout.keys, op.layout.values);
      return was ? [effectsOf(was, c, rulesFor(op.doc))] : [];
    },
    describe: (op) => `${op.kind === 'duplicate' ? 'duplicate' : 'add'} ${op.name || 'section'}`,
  },

  /*
   * { doc, sid, newSid, snapshot } a section of another type in the place
   * of `sid`: the same row, column or side, under `newSid`. Refused (no
   * change) when the new section does not fit that place. Undo puts the
   * old section back exactly, its id, settings, blocks and place.
   */
  replace: {
    apply(op, fwd) {
      const c = contentOf(op.doc);
      if (!c) return [];
      const rules = rulesFor(op.doc);
      // The row model reads the sections too: compare against a copy of the map.
      const before = () => ({ ...layoutView(c), sections: { ...c.sections } });
      if (fwd) {
        if (!c.sections[op.sid] || c.sections[op.newSid]) return [];
        const result = replaceIn(c, op.sid, op.newSid, clone(op.snapshot), rulesFor(op.doc));
        if (!result) return [];
        op.old = clone(c.sections[op.sid]);
        op.prior = { ...layoutSnapshot(c), sectionKeys: Object.keys(c.sections) };
        const was = before();
        setSections(c, Object.entries(result.content.sections));
        writeLayout(c, Object.keys(result.content), result.content);
        return [effectsOf(was, c, rules)];
      }
      if (!c.sections[op.newSid] || c.sections[op.sid] || !op.old || !op.prior) return [];
      op.snapshot = clone(c.sections[op.newSid]);
      const was = before();
      const keys = op.prior.sectionKeys.filter((id) => id === op.sid || c.sections[id]);
      Object.keys(c.sections).forEach((id) => { if (id !== op.newSid && !keys.includes(id)) keys.push(id); });
      setSections(c, keys.map((id) => [id, id === op.sid ? clone(op.old) : c.sections[id]]));
      writeLayout(c, op.prior.keys, op.prior.values);
      return [effectsOf(was, c, rules)];
    },
    describe: (op) => `replace ${op.name || 'section'}`,
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
        return [];
      }
      if (!s.blocks[op.bid]) return [];
      op.snapshot = clone(s.blocks[op.bid]);
      delete s.blocks[op.bid];
      s.block_order = s.block_order.filter((id) => id !== op.bid);
      return [];
    },
    describe: (op) => `add ${op.name || 'block'}`,
  },

  /* { doc, sid, index, items: [{ bid, snapshot }] } */
  'blocks-add': {
    apply(op, fwd) {
      const s = sectionIn(op.doc, op.sid);
      if (!s) return [];
      if (fwd) {
        const fresh = op.items.filter((item) => !s.blocks[item.bid]);
        const at = clampIndex(op.index, s.block_order.length);
        fresh.forEach((item) => { s.blocks[item.bid] = clone(item.snapshot); });
        s.block_order.splice(at, 0, ...fresh.map((item) => item.bid));
        return [];
      }
      op.items.forEach((item) => {
        if (!s.blocks[item.bid]) return;
        item.snapshot = clone(s.blocks[item.bid]);
        delete s.blocks[item.bid];
      });
      s.block_order = s.block_order.filter((id) => !op.items.some((item) => item.bid === id));
      return [];
    },
    describe: (op) => `add ${op.name || 'blocks'}`,
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
        return [];
      }
      if (s.blocks[op.bid] || !op.snapshot) return [];
      s.blocks[op.bid] = clone(op.snapshot);
      s.block_order.splice(clampIndex(op.index, s.block_order.length), 0, op.bid);
      return [];
    },
    describe: (op) => `remove ${op.name || 'block'}`,
  },

  /* { doc, sid, bid, from, to } */
  'block-move': {
    apply(op, fwd) {
      const s = sectionIn(op.doc, op.sid);
      if (s) moveInList(s.block_order, op.bid, fwd ? op.to : op.from);
      return [];
    },
    describe: (op) => `move ${op.name || 'block'}`,
  },

  /* { doc, sid, target } a drop target of the row model */
  place: layoutType((op, c, rules) => place(c, op.sid, op.target, rules), (op) => `move ${op.name || 'section'}`),

  /* { doc, split, area, before, rowId } an empty row of columns at a gap of an area */
  'row-add': layoutType((op, c, rules) => ({ content: addRow(c, op.split, gapOf(op), op.rowId, rules) }), () => 'add a row'),

  /* { doc, rowId, area, before } to a gap of an area */
  'row-move': layoutType((op, c, rules) => ({ content: moveRow(c, op.rowId, gapOf(op), rules) }), () => 'move a row'),

  /* { doc, rowId } its sections stay, each in a row of its own */
  'row-remove': layoutType((op, c, rules) => removeRow(c, op.rowId, rules), () => 'remove a row'),

  /* { doc, rowId, split } */
  'row-split': layoutType((op, c, rules) => changeSplit(c, op.rowId, op.split, rules), () => 'change the columns'),

  /* { doc, rowId, key, to } */
  'row-set': layoutType((op, c, rules) => ({ content: setRow(c, op.rowId, op.key, op.to, rules) }), () => 'change row settings'),
};

/** Operation types that change rows or sides. */
export const LAYOUT_TYPES = new Set(['place', 'row-add', 'row-move', 'row-remove', 'row-split', 'row-set']);

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
  // A block that is gone leaves its section selected, so the panel stays on
  // the section's settings rather than going back to the page.
  if (S.sel?.kind === 'section') {
    const s = sec(S.sel.sid);
    if (!s) S.sel = null;
    else if (S.sel.bid && !s.blocks[S.sel.bid]) S.sel = { ...S.sel, bid: null };
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
    const op = clone({ ...old, snapshot: old.snapshot });
    delete op.seq;
    delete op.mergeKey;
    // Rows and sides are worked out again from the document as it is now.
    delete op.prior;
    delete op.layout;
    delete op.old;
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
    } else if (op.t === 'place' && !c?.sections?.[op.sid]) {
      return;
    } else if (op.t === 'replace' && (!c?.sections?.[op.sid] || c.sections[op.newSid])) {
      return;
    }
    commit(op);
    count += 1;
  });
  return count;
}
