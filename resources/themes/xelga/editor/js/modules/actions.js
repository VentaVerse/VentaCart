/*
 * Edits the outline and the inspector both offer: hide, rename, move,
 * duplicate, remove, and the same for blocks. Each one is a single
 * operation, so each one is a single undo step.
 */

import { announce, toast } from './dom.js';
import { S, ctx, ui, sec, blk, doc, locate, groupList, groupIndex, blockList, usedIds, labelOf, valueOf, ensureShape, openOnly } from './state.js';
import { sectionDef, blockDef, blockTypes } from './catalog.js';
import { commit, undo } from './operations.js';
import { clone, humanize } from './util.js';
import { select } from './selection.js';
import { flush, resolveConflict, setFatal } from './drafts.js';
import { normalizeDocument } from './api.js';

const isMain = (sid) => Boolean(sectionDef(sec(sid)?.type)?.main);

export function toggleVisible(sid, bid = null) {
  const at = locate(sid);
  if (!at || (!bid && isMain(sid))) return;
  const now = valueOf(sid, bid, 'visible');
  commit({ t: 'visible', doc: at.handle, sid, bid, from: now, to: !now });
  announce(`${labelOf(sid, bid)} ${now ? 'hidden' : 'shown'}`);
  ui.renderInspector();
}

export function startRename(sid, bid = null) {
  S.renaming = { sid, bid };
  if (bid) openOnly(sid);
  if (S.nav !== 'sections') ui.setNav?.('sections', { keepSelection: true });
  ui.renderOutline();
}

export function rename(sid, bid, value) {
  const at = locate(sid);
  const target = bid ? blk(sid, bid) : sec(sid);
  if (!at || !target) return;
  const to = String(value || '').trim().slice(0, 80);
  if (!to || to === labelOf(sid, bid)) return;
  commit({ t: 'rename', doc: at.handle, sid, bid, from: target.label, to });
  announce(`Renamed to ${to}`);
}

export function moveBy(sid, delta) {
  const at = locate(sid);
  if (!at) return false;
  const from = groupIndex(sid);
  const count = groupList(at.group).length;
  const to = from + delta;
  if (to < 0 || to >= count) return false;
  commit({ t: 'move', doc: at.handle, sid, from, to });
  announce(`${labelOf(sid)} moved to position ${to + 1} of ${count}`);
  return true;
}

export function moveTo(sid, to) {
  const at = locate(sid);
  const from = groupIndex(sid);
  if (!at || from === to) return;
  commit({ t: 'move', doc: at.handle, sid, from, to });
  announce(`${labelOf(sid)} moved to position ${to + 1}`);
}

/** How many sections of a type a document already has, for the schema's limit. */
export function countOfType(handle, type) {
  const c = S.docs[handle]?.content;
  return c ? c.order.filter((id) => c.sections[id]?.type === type).length : 0;
}

export function canDuplicate(sid) {
  const at = locate(sid);
  const def = sectionDef(at?.data?.type);
  if (!at || !def || def.main) return false;
  return !def.limit || countOfType(at.handle, at.data.type) < def.limit;
}

/** A copy with fresh ids for the section and every block in it. */
function copySection(data, used) {
  const copy = clone(data);
  const map = {};
  copy.block_order = (data.block_order || []).map((bid) => {
    const next = uniqueBlockId(data.blocks[bid]?.type || 'block', used);
    map[bid] = next;
    return next;
  });
  copy.blocks = {};
  Object.entries(data.blocks || {}).forEach(([bid, b]) => {
    if (map[bid]) copy.blocks[map[bid]] = clone(b);
  });
  return copy;
}

function uniqueBlockId(type, used) {
  const id = newId(type, used);
  used.add(id);
  return id;
}

function newId(base, used) {
  const stem = String(base || 'item').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 30) || 'item';
  let id;
  do {
    id = `${stem}-${Math.random().toString(36).slice(2, 6)}`;
  } while (used.has(id));
  return id;
}

/*
 * Copies are made on the server, which hands out ids no other section or
 * block uses anywhere in the store's documents. The draft is saved first, so
 * the server copies exactly what the merchant sees.
 */
let copying = false;

export async function duplicateSection(sid) {
  const at = locate(sid);
  if (!at || !canDuplicate(sid) || copying) return;
  if (!ctx.api.duplicate) {
    duplicateHere(sid);
    return;
  }
  const copy = await copyOnServer(at.handle, sid, null);
  if (!copy) return;
  const { content, newId } = copy;
  const snapshot = content.sections[newId];
  commit({ t: 'insert', kind: 'duplicate', doc: at.handle, sid: newId, source: sid, group: at.group, index: content.order.indexOf(newId), snapshot, name: snapshot.label });
  copy.settle();
  toast(`${labelOf(sid)} copied. The copy is just below it.`, { action: { label: 'Undo', run: undo } });
  select(newId);
}

export async function duplicateBlock(sid, bid) {
  const at = locate(sid);
  if (!at || !blk(sid, bid) || !canDuplicateBlock(sid, bid) || copying) return;
  let copy = null;
  if (ctx.api.duplicate) {
    copy = await copyOnServer(at.handle, sid, bid);
    if (!copy) return;
  }
  const newBid = copy?.newId || newId(blk(sid, bid).type, usedIds());
  const snapshot = copy ? copy.content.sections[sid]?.blocks?.[newBid] : { ...clone(blk(sid, bid)), label: `${labelOf(sid, bid)} (copy)` };
  if (!snapshot) return;
  const index = copy ? copy.content.sections[sid].block_order.indexOf(newBid) : at.data.block_order.indexOf(bid) + 1;
  commit({ t: 'block-add', doc: at.handle, sid, bid: newBid, index, snapshot, name: snapshot.label });
  copy?.settle();
  openOnly(sid);
  toast(`${labelOf(sid, bid)} copied.`, { action: { label: 'Undo', run: undo } });
  select(sid, newBid);
}

/**
 * Asks the server for the copy. Answers { content, newId, settle } or null
 * after telling the merchant why not. `settle` marks the document saved
 * when nothing else changed while the server worked.
 */
async function copyOnServer(handle, sid, bid) {
  copying = true;
  S.copying = sid;
  ui.renderOutline();
  ui.updateBusy?.();
  try {
    const saved = await flush({ handles: [handle] });
    const d = doc(handle);
    if (!saved || !d) {
      toast('The copy waits until your changes are saved. Check the status at the top and try again.', { tone: 'error' });
      return null;
    }
    const before = JSON.stringify(d.content);
    const res = await ctx.api.duplicate(handle, { section: sid, block: bid, draftVersion: d.draftVersion });
    const content = ensureShape(res.document.content, d.kind);
    if (!res.newId || (bid ? !content.sections[sid]?.blocks?.[res.newId] : !content.sections[res.newId])) {
      throw Object.assign(new Error('The server did not return the copy.'), { kind: 'server' });
    }
    d.draftVersion = res.draftVersion;
    d.hasDraft = true;
    d.lastSavedAt = res.updatedAt;
    d.lastSavedBy = S.user;
    const untouched = JSON.stringify(d.content) === before;
    return {
      content,
      newId: res.newId,
      settle() {
        // The draft on the server now holds the copy: so does this one.
        if (untouched) {
          d.savedJson = JSON.stringify(d.content);
          d.savedSeq = S.seq;
          ui.updateToolbar();
        }
      },
    };
  } catch (error) {
    copyFailed(handle, error);
    return null;
  } finally {
    copying = false;
    S.copying = null;
    ui.renderOutline();
    ui.updateBusy?.();
  }
}

function copyFailed(handle, error) {
  const d = doc(handle);
  if (error.kind === 'conflict' && d) {
    d.conflict = normalizeDocument(error.data?.current || error.data || {}, handle);
    d.error = { kind: 'conflict', message: error.message };
    resolveConflict(d);
    return;
  }
  if (error.kind === 'unauthenticated' || error.kind === 'forbidden') {
    setFatal(error);
    return;
  }
  const reason = error.kind === 'validation' ? Object.values(error.fields || {})[0]?.[0] || error.message : error.message;
  toast(`Nothing was copied. ${reason}`, { tone: 'error', timeout: 8000 });
}

/** Without a server that copies (the dev harness), the copy gets ids made here. */
function duplicateHere(sid) {
  const at = locate(sid);
  const used = usedIds();
  const newSid = newId(at.data.type, used);
  used.add(newSid);
  const snapshot = copySection(at.data, used);
  snapshot.label = `${labelOf(sid)} (copy)`;
  const index = at.doc.content.order.indexOf(sid) + 1;
  commit({ t: 'insert', kind: 'duplicate', doc: at.handle, sid: newSid, source: sid, group: at.group, index, snapshot, name: snapshot.label });
  toast(`${labelOf(sid)} copied.`);
  select(newSid);
}

export function removeSection(sid) {
  const at = locate(sid);
  if (!at || isMain(sid)) return;
  const name = labelOf(sid);
  commit({ t: 'remove', doc: at.handle, sid, group: at.group });
  toast(`${name} removed.`, { action: { label: 'Undo', run: undo } });
}

/**
 * Adds a section from the add panel: a preset (settings and blocks) or a
 * variant with the schema's defaults.
 */
export function addSection(type, { variant = null, preset = null, index = null } = {}) {
  const def = sectionDef(type);
  const handle = S.page?.handle;
  if (!def || !handle) return null;
  if (def.limit && countOfType(handle, type) >= def.limit) {
    toast(`This page already has ${def.limit === 1 ? 'a' : def.limit} ${def.label}${def.limit === 1 ? '' : ' sections'}, the most it can have.`, { tone: 'error' });
    return null;
  }
  const used = usedIds();
  const sid = newId(type, used);
  used.add(sid);
  const settings = clone(preset?.settings || {});
  if (variant && !preset) settings.variant = variant;
  const snapshot = { type, label: def.label, disabled: false, settings, block_order: [], blocks: {} };
  presetBlocks(def, preset).forEach((b, i) => {
    const bid = uniqueBlockId(b.type, used);
    snapshot.block_order.push(bid);
    snapshot.blocks[bid] = { type: b.type, label: b.label || `${blockDef(type, b.type).label} ${i + 1}`, disabled: false, settings: clone(b.settings || {}) };
  });
  const count = groupList('template').length;
  const at = Math.max(0, Math.min(index ?? count, count));
  commit({ t: 'insert', kind: 'add', doc: handle, sid, group: 'template', index: at, snapshot, name: def.label });
  return sid;
}

/**
 * The blocks a new section starts with: the preset's, kept within the
 * schema's limits, or the fewest the schema needs.
 */
function presetBlocks(def, preset) {
  const types = def.blocks?.types || [];
  const min = def.blocks?.min || 0;
  const max = def.blocks?.max ?? 0;
  const perType = {};
  const out = [];
  (preset?.blocks || []).forEach((b) => {
    const spec = types.find((t) => t.type === b?.type);
    if (!spec || out.length >= max) return;
    perType[b.type] = (perType[b.type] || 0) + 1;
    if (spec.limit && perType[b.type] > spec.limit) return;
    out.push(b);
  });
  const first = types[0];
  while (first && out.length < min) out.push({ type: first.type });
  return out;
}

/* ------------------------------------------------------------------------
   Blocks
   ------------------------------------------------------------------------ */

/** Block types that may still be added to a section, with the reason others may not. */
export function addableBlocks(sid) {
  const s = sec(sid);
  const def = sectionDef(s?.type);
  if (!def?.blocks) return { types: [], full: false };
  const list = blockList(sid);
  const full = Boolean(def.blocks.max) && list.length >= def.blocks.max;
  const types = blockTypes(s.type).filter((t) => !t.limit || list.filter((b) => b.type === t.type).length < t.limit);
  return { types: full ? [] : types, full, max: def.blocks.max };
}

/** A block can be copied while its section has room for one more of its kind. */
export function canDuplicateBlock(sid, bid) {
  const b = blk(sid, bid);
  return Boolean(b) && addableBlocks(sid).types.some((t) => t.type === b.type);
}

export function canRemoveBlock(sid) {
  const def = sectionDef(sec(sid)?.type);
  return blockList(sid).length > (def?.blocks?.min || 0);
}

export function addBlock(sid, type) {
  const at = locate(sid);
  const def = blockDef(at?.data?.type, type);
  if (!at || !def) return;
  const { types } = addableBlocks(sid);
  if (!types.some((t) => t.type === type)) return;
  const used = usedIds();
  const bid = newId(type, used);
  const same = blockList(sid).filter((b) => b.type === type).length;
  const snapshot = { type, label: `${def.label || humanize(type)} ${same + 1}`, disabled: false, settings: {} };
  const index = at.data.block_order.length;
  commit({ t: 'block-add', doc: at.handle, sid, bid, index, snapshot, name: snapshot.label });
  openOnly(sid);
  announce(`${snapshot.label} added to ${labelOf(sid)}`);
  select(sid, bid);
}

export function removeBlock(sid, bid) {
  const at = locate(sid);
  if (!at || !blk(sid, bid)) return;
  if (!canRemoveBlock(sid)) {
    toast(`${labelOf(sid)} needs at least ${sectionDef(at.data.type)?.blocks?.min} of these.`, { tone: 'error' });
    return;
  }
  const name = labelOf(sid, bid);
  commit({ t: 'block-remove', doc: at.handle, sid, bid });
  if (S.sel?.sid === sid && S.sel?.bid === bid) select(sid);
  toast(`${name} removed.`, { action: { label: 'Undo', run: undo } });
}

export function moveBlockBy(sid, bid, delta) {
  const at = locate(sid);
  if (!at) return false;
  const order = at.data.block_order;
  const from = order.indexOf(bid);
  const to = from + delta;
  if (from < 0 || to < 0 || to >= order.length) return false;
  commit({ t: 'block-move', doc: at.handle, sid, bid, from, to });
  announce(`${labelOf(sid, bid)} moved to position ${to + 1} of ${order.length}`);
  return true;
}

export function moveBlockTo(sid, bid, to) {
  const at = locate(sid);
  const from = at?.data?.block_order.indexOf(bid) ?? -1;
  if (!at || from < 0 || from === to) return;
  commit({ t: 'block-move', doc: at.handle, sid, bid, from, to });
  announce(`${labelOf(sid, bid)} moved to position ${to + 1}`);
}
