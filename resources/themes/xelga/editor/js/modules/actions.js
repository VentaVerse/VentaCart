/*
 * Edits of sections and blocks from the board and the drawer: hide,
 * rename, add, replace, duplicate, remove, and the same for blocks. Each
 * one is a single operation, so each one is a single undo step.
 */

import { announce, toast } from './dom.js';
import { S, ctx, ui, sec, blk, doc, locate, groupList, blockList, usedIds, labelOf, valueOf, ensureShape } from './state.js';
import { sectionDef, blockDef, blockTypes, allowedIn, isLocked } from './catalog.js';
import { commit, undo, hasLayout, layoutPayload, rulesFor } from './operations.js';
import { rowsOf, checkAddSpot, freshRowId, replaceIn, countInPlace, placeOf, areaAt, gapAt, hasAreas } from './board/model.js';
import { fullWords } from './board/words.js';
import { schemeSetting } from './board/choices.js';
import { blockLabel } from './board/labels.js';
import { clone, humanize } from './util.js';
import { select } from './selection.js';
import { flush, resolveConflict, setFatal } from './drafts.js';
import { normalizeDocument } from './api.js';

const isMain = (sid) => Boolean(sectionDef(sec(sid)?.type)?.main);
const lockedSid = (sid) => isLocked(sectionDef(sec(sid)?.type));

export function toggleVisible(sid, bid = null) {
  const at = locate(sid);
  if (!at || (!bid && isMain(sid))) return;
  const now = valueOf(sid, bid, 'visible');
  commit({ t: 'visible', doc: at.handle, sid, bid, from: now, to: !now });
  announce(`${labelOf(sid, bid)} ${now ? 'hidden' : 'shown'}`);
  ui.renderInspector();
}

/** Turns the name in the drawer's head into a field; Enter or leaving it keeps the new name. */
export function startRename(sid, bid = null) {
  S.renaming = { sid, bid };
  ui.renderInspector();
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

/**
 * How many sections of a type count against its limit at a spot of a
 * document: in the spot's area for a type limited per area on a page with
 * areas, else in the whole document (a band is a document of its own).
 */
function countHere(handle, type, spot) {
  const c = S.docs[handle]?.content;
  return c ? countInPlace(c, type, spot, rulesFor(handle)) : 0;
}

/**
 * Whether a type is at its limit at a spot, with the words that say so:
 * the area that has one already, else the page or band. Null when it is not.
 */
function limitReached(handle, def, type, spot) {
  if (!def.limit || countHere(handle, type, spot) < def.limit) return null;
  const c = S.docs[handle]?.content;
  const area = def.limit_per_area && c ? areaAt(c, spot, rulesFor(handle)) : null;
  return fullWords(def, { area, band: spot?.drop === 'band' ? spot.group : null });
}

/**
 * Why a section cannot be copied, for its disabled Duplicate: its area
 * already holds as many of a type limited per area (the copy would sit
 * just below it, in that area), else the page. Null when it can be.
 */
export function duplicateHint(sid) {
  if (canDuplicate(sid)) return null;
  const at = locate(sid);
  const def = sectionDef(at?.data?.type);
  const area = def?.limit_per_area && at ? areaAt(at.doc.content, placeOf(at.doc.content, sid, rulesFor(at.handle)), rulesFor(at.handle)) : null;
  return area ? 'This area already has one. Use Add section in another area.' : 'This page has as many as it can hold';
}

/** A section can be copied when its type has room where the copy goes: just below it, in its own area. */
export function canDuplicate(sid) {
  const at = locate(sid);
  const def = sectionDef(at?.data?.type);
  if (!at || !def || isLocked(def)) return false;
  if (!def.limit) return true;
  return countHere(at.handle, at.data.type, placeOf(at.doc.content, sid, rulesFor(at.handle))) < def.limit;
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
  if (!at || lockedSid(sid)) return;
  const name = labelOf(sid);
  commit({ t: 'remove', doc: at.handle, sid, group: at.group });
  toast(`${name} removed.`, { action: { label: 'Undo', run: undo } });
}

/* What a refused spot of the board is told, by checkAddSpot's reason. */
const SPOT_WORDS = {
  gone: 'The page changed while you were choosing, and that spot is no longer on it. Nothing was added; choose where it goes again.',
  full: 'That column holds as many sections as it can. Nothing was added.',
  'full-side': 'That side holds as many sections as it can. Nothing was added.',
  'own-row': 'That section needs a row of its own. Nothing was added.',
  'content-only': 'That section goes only beside the page content. Nothing was added.',
};

/**
 * Adds a section from the section picker at a spot of the board
 * (`target`, a spot of the row model, or a band spot, which adds to the
 * header or footer band): a preset (settings and blocks) or a variant with
 * the schema's defaults. The spot is checked against the page as it is
 * now; one that is gone, full or too narrow, or an area or band that
 * already has as many of the type as it may, is refused with the reason,
 * and nothing is added somewhere else instead. Answers `{sid, moved,
 * left}`: the names of sections the add pushed out of their columns, and
 * `[{name, area}]` for those it took out of Content (a first side narrows
 * it); or null.
 */
export function addSection(type, { variant = null, preset = null, target = null } = {}) {
  const def = sectionDef(type);
  const band = target?.drop === 'band' ? target.group : null;
  const handle = band || S.page?.handle;
  const c = doc(handle)?.content;
  if (!def || !handle || !c || !target) return null;
  if (band && !allowedIn(def, null, false, false, band)) return null;
  const full = limitReached(handle, def, type, target);
  if (full) {
    toast(full, { tone: 'error' });
    return null;
  }
  const { sid, snapshot } = buildSection(type, def, { variant, preset }, usedIds());
  const rules = rulesFor(handle);
  const spot = checkAddSpot(c, target, snapshot, rules);
  if (!spot.ok) {
    const reason = spot.reason === 'full' && target.drop === 'side' ? 'full-side' : spot.reason;
    toast(SPOT_WORDS[reason] || `${def.label} needs a wider spot than that one.`, { tone: 'error', timeout: 8000 });
    return null;
  }
  if (band) {
    // A band is a list of full-width sections: the spot is a place in its order.
    const index = target.before == null ? c.order.length : c.order.indexOf(target.before);
    commit({ t: 'insert', kind: 'add', doc: handle, sid, group: band, index, snapshot, name: def.label, target: null });
    return { sid, moved: [], left: [] };
  }
  const count = groupList('template').length;
  // A page of full-width sections only, without areas: the gap is a place in its order.
  const plain = !hasLayout(c) && target.drop === 'gap' && !hasAreas(c, rules);
  const index = plain ? orderIndexOfGap(c, target) : count;
  const op = { t: 'insert', kind: 'add', doc: handle, sid, group: 'template', index, snapshot, name: def.label, target: plain ? null : target };
  commit(op);
  // The first section of a side narrows the page: what no longer fits its
  // column moves to a row of its own, and a row too wide for Content leaves it.
  return {
    sid,
    moved: (op.moved || []).filter((id) => c.sections[id]).map((id) => labelOf(id)),
    left: (op.left || []).filter((m) => c.sections[m.sid]).map((m) => ({ name: labelOf(m.sid), area: m.area })),
  };
}

/**
 * A new section of a type with fresh ids for it and its blocks: a preset
 * (settings and blocks) or a variant with the schema's defaults. Adds the
 * ids to `used`.
 */
export function buildSection(type, def, { variant = null, preset = null } = {}, used = new Set()) {
  const sid = newId(type, used);
  used.add(sid);
  const settings = clone(preset?.settings || {});
  if (variant && !preset) settings.variant = variant;
  const snapshot = { type, label: def.label, disabled: false, settings, block_order: [], blocks: {} };
  const taken = {};
  presetBlocks(def, preset).forEach((b) => {
    const bid = uniqueBlockId(b.type, used);
    snapshot.block_order.push(bid);
    const label = b.label || blockLabel(blockDef(type, b.type)?.label || humanize(b.type), blockLimits(def, b.type, taken[b.type] || 0));
    taken[b.type] = (taken[b.type] || 0) + 1;
    snapshot.blocks[bid] = { type: b.type, label, disabled: false, settings: clone(b.settings || {}) };
  });
  return { sid, snapshot };
}

/**
 * Replaces a section of the page with a new one of another type, in the
 * same spot: a preset or a variant with the new type's defaults, keeping
 * only the old section's color scheme. Refused, with a message, when the
 * new type is at its limit on the page or does not fit the spot. One
 * operation, so one undo puts the old section back exactly. Answers the
 * new section's id, or null.
 */
export function replaceSection(sid, type, { variant = null, preset = null } = {}) {
  const at = locate(sid);
  const def = sectionDef(type);
  if (!at || at.group !== 'template' || !def || isLocked(def) || lockedSid(sid) || at.data.type === type) return null;
  const full = limitReached(at.handle, def, type, placeOf(at.doc.content, sid, rulesFor(at.handle)));
  if (full) {
    toast(full, { tone: 'error' });
    return null;
  }
  const { sid: newSid, snapshot } = buildSection(type, def, { variant, preset }, usedIds());
  keepScheme(snapshot, def, sid);
  if (!replaceIn(at.doc.content, sid, newSid, snapshot, rulesFor(at.handle))) {
    toast(`${def.label} needs a wider spot than that one.`, { tone: 'error' });
    return null;
  }
  const op = { t: 'replace', doc: at.handle, sid, newSid, snapshot, name: labelOf(sid) };
  commit(op);
  return op.prior ? newSid : null;
}

/* The new section takes the color scheme the old one shows, so the page keeps its colors. */
function keepScheme(snapshot, def, sid) {
  const to = schemeSetting(def);
  const from = schemeSetting(sectionDef(sec(sid)?.type));
  if (!to || !from) return;
  const value = valueOf(sid, null, from.key);
  if (value !== undefined && value !== null && value !== '') snapshot.settings[to.key] = clone(value);
}

function orderIndexOfGap(c, target) {
  const rows = rowsOf(c, layoutPayload());
  const { index: at } = gapAt(rows, target);
  const r = at >= 0 ? rows[at] : null;
  const index = r ? c.order.indexOf(r.columns[0]?.[0]) : -1;
  return index < 0 ? c.order.length : index;
}

/** Adds an empty row of columns at a gap of the board. Answers the new row's id, or null. */
export function addRowAt(split, target) {
  const handle = S.page?.handle;
  const c = doc(handle)?.content;
  const layout = layoutPayload();
  if (!c || !layout?.splits?.[split]) return null;
  const before = target?.before ?? 'end';
  const rows = rowsOf(c, layout, rulesFor(handle));
  if (gapAt(rows, { drop: 'gap', area: target?.area ?? null, before }).index < 0) {
    toast(SPOT_WORDS.gone, { tone: 'error', timeout: 8000 });
    return null;
  }
  if (rows.length >= layout.max_rows) {
    toast(`This page has ${layout.max_rows} rows, the most it can have.`, { tone: 'error' });
    return null;
  }
  const op = { t: 'row-add', doc: handle, split, area: target?.area ?? null, before, rowId: freshRowId(c, layout) };
  commit(op);
  if (!op.prior) return null;
  const text = `Row added: ${layout.splits[split].label.toLowerCase()}. Add sections to its columns.`;
  toast(text, { action: { label: 'Undo', run: undo } });
  announce(text);
  return op.rowId;
}

/* What decides whether a new block of a type is numbered: its limit, the section's, and how many it has. */
function blockLimits(def, blockType, taken) {
  const spec = (def?.blocks?.types || []).find((t) => t.type === blockType);
  return { limit: spec?.limit || 0, max: def?.blocks?.max || 0, taken };
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
  // Blocks of an extension that is away take no room.
  const list = blockList(sid).filter((b) => blockDef(s.type, b.type));
  const full = Boolean(def.blocks.max) && list.length >= def.blocks.max;
  const types = blockTypes(s.type).filter((t) => !t.limit || list.filter((b) => b.type === t.type).length < t.limit);
  return { types: full ? [] : types, full, max: def.blocks.max };
}

/** A block can be copied while its section has room for one more of its kind. */
export function canDuplicateBlock(sid, bid) {
  const b = blk(sid, bid);
  return Boolean(b) && addableBlocks(sid).types.some((t) => t.type === b.type);
}

/* A block of an extension that is away can always be removed: it counts toward nothing. */
export function canRemoveBlock(sid, bid = null) {
  const type = sec(sid)?.type;
  const b = bid ? blk(sid, bid) : null;
  if (b && !blockDef(type, b.type)) return true;
  const def = sectionDef(type);
  return blockList(sid).filter((x) => blockDef(type, x.type)).length > (def?.blocks?.min || 0);
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
  const snapshot = { type, label: blockLabel(def.label || humanize(type), blockLimits(sectionDef(at.data.type), type, same)), disabled: false, settings: {} };
  const index = at.data.block_order.length;
  commit({ t: 'block-add', doc: at.handle, sid, bid, index, snapshot, name: snapshot.label });
  announce(`${snapshot.label} added to ${labelOf(sid)}`);
  select(sid, bid);
}

export function removeBlock(sid, bid) {
  const at = locate(sid);
  if (!at || !blk(sid, bid)) return;
  if (!canRemoveBlock(sid, bid)) {
    toast(`${labelOf(sid)} needs at least ${sectionDef(at.data.type)?.blocks?.min} of these.`, { tone: 'error' });
    return;
  }
  const name = labelOf(sid, bid);
  const order = at.data.block_order;
  // The focus goes on to the block that takes its place, or to Add block after the last.
  const next = order[order.indexOf(bid) + 1] || null;
  commit({ t: 'block-remove', doc: at.handle, sid, bid });
  if (S.sel?.kind === 'section' && S.sel.sid === sid) select(sid);
  toast(`${name} removed.`, { action: { label: 'Undo', run: undo } });
  window.requestAnimationFrame(() => focusInBlocks(next));
}

/** Puts the focus on a block of the open section's list, or on its Add button, and shows it. */
function focusInBlocks(bid) {
  const list = document.querySelector('#drawer .blist');
  const target = (bid && list?.querySelector(`.blist__item[data-bid="${CSS.escape(bid)}"] .blist__main`))
    || document.querySelector('#drawer .blist__add')
    || list?.querySelector('.blist__item:last-child .blist__main');
  if (!target) return;
  target.focus({ preventScroll: true });
  target.scrollIntoView({ block: 'nearest' });
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
