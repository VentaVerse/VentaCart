/*
 * Rows and sides of a page document, as pure functions of the document.
 *
 * A template document may hold `rows` (each `{id, split, columns, settings}`,
 * a column being a list of section ids) and `sides` (`{left, right}`, lists
 * of section ids). `order` stays the reading order: left side, each row's
 * columns left to right, right side.
 *
 * normalize() is the server's layout normalizer written again for the
 * editor, so the editor can show the result of a move before it is saved.
 * Both read the shared cases in tests/fixtures/layout and must agree on
 * every one of them, row ids included. Every other change here ends in
 * normalize(), so what the editor saves is what the server would store.
 *
 * Nothing here touches the DOM or the editor's state. `rules` is
 * `{layout, minSpan(section), isMain(section), sidesAllowed}`, `layout`
 * being the boot payload's `layout` (splits, row defaults, limits).
 */

const SIDES = ['left', 'right'];
const ID = /^[a-z0-9-]{1,40}$/;

/** Why normalize() moved a section to a row of its own. */
export const MOVE_REASONS = {
  'too-wide': 'A section too wide for its column moved to a row of its own.',
  'main-section': 'The page\'s main section moved to a row of its own.',
  'no-sides': 'This page cannot have sides, so a section in one moved to a row of its own.',
};

let shared = null;

/** The boot payload's layout, for the functions that take no rules. */
export function useLayout(layout) {
  shared = layout || null;
}

function layoutOf(given) {
  const layout = given?.layout || given || shared;
  if (!layout?.splits) throw new Error('The row model needs the layout payload (useLayout).');
  return layout;
}

/* ------------------------------------------------------------------------
   Reading values the way the server reads decoded JSON
   ------------------------------------------------------------------------ */

/** A list or a map: what PHP calls an array once JSON is decoded. */
const isList = (value) => value !== null && typeof value === 'object';
const valuesOf = (value) => (Array.isArray(value) ? value : isList(value) ? Object.values(value) : []);
const isString = (value) => typeof value === 'string';
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

function asString(value) {
  if (isString(value)) return value;
  if (typeof value === 'number') return String(value);
  if (value === true) return '1';
  return '';
}

/** The document's sections by id. */
function sectionMap(content) {
  const map = new Map();
  if (isList(content?.sections)) {
    Object.entries(content.sections).forEach(([id, section]) => map.set(String(id), isList(section) ? section : {}));
  }
  return map;
}

const partsOf = (layout, split) => (Object.hasOwn(layout.splits, split) ? layout.splits[split].parts : null);

/* ------------------------------------------------------------------------
   Row ids
   ------------------------------------------------------------------------ */

/** The SHA-1 of a text, in hex. Row ids are made from it, as on the server. */
function sha1(text) {
  const bytes = new TextEncoder().encode(text);
  const length = bytes.length;
  const words = new Uint32Array((((length + 8) >> 6) + 1) * 16);
  for (let i = 0; i < length; i += 1) words[i >> 2] |= bytes[i] << (24 - (i % 4) * 8);
  words[length >> 2] |= 0x80 << (24 - (length % 4) * 8);
  words[words.length - 1] = length * 8;

  const rotl = (x, n) => ((x << n) | (x >>> (32 - n))) >>> 0;
  const h = [0x67452301, 0xefcdab89, 0x98badcfe, 0x10325476, 0xc3d2e1f0];
  const w = new Uint32Array(80);
  for (let block = 0; block < words.length; block += 16) {
    for (let t = 0; t < 16; t += 1) w[t] = words[block + t];
    for (let t = 16; t < 80; t += 1) w[t] = rotl(w[t - 3] ^ w[t - 8] ^ w[t - 14] ^ w[t - 16], 1);
    let [a, b, c, d, e] = h;
    for (let t = 0; t < 80; t += 1) {
      let f;
      let k;
      if (t < 20) { f = (b & c) | (~b & d); k = 0x5a827999; } else if (t < 40) { f = b ^ c ^ d; k = 0x6ed9eba1; } else if (t < 60) { f = (b & c) | (b & d) | (c & d); k = 0x8f1bbcdc; } else { f = b ^ c ^ d; k = 0xca62c1d6; }
      const next = (rotl(a, 5) + (f >>> 0) + e + k + w[t]) >>> 0;
      e = d;
      d = c;
      c = rotl(b, 30);
      b = a;
      a = next;
    }
    h[0] = (h[0] + a) >>> 0;
    h[1] = (h[1] + b) >>> 0;
    h[2] = (h[2] + c) >>> 0;
    h[3] = (h[3] + d) >>> 0;
    h[4] = (h[4] + e) >>> 0;
  }
  return h.map((x) => x.toString(16).padStart(8, '0')).join('');
}

/**
 * `row-` and the shortest start (8 to 36 hex digits) of the seed's SHA-1
 * that is not taken; when every length is, the seed gets a number (`row`
 * becomes `row-2`, then `row-3`, ...). Adds the id to `taken`.
 */
function freshId(seed, taken) {
  for (let n = 1; n <= 1000; n += 1) {
    const hash = sha1(n === 1 ? seed : `${seed}-${n}`);
    for (let length = 8; length <= 36; length += 2) {
      const id = `row-${hash.slice(0, length)}`;
      if (!taken.has(id)) {
        taken.add(id);
        return id;
      }
    }
  }
  throw new Error(`No free row id for section ${seed}.`);
}

/** The id of a row made for one section, given the ids the document already uses. */
export function rowIdFor(sectionId, taken) {
  return freshId(String(sectionId), new Set(valuesOf(taken).filter(isString)));
}

/** An id for an empty row added to the document. */
export function freshRowId(content, layout) {
  return freshId('row', takenIds(content, rowsOf(content, layout)));
}

function takenIds(content, rows) {
  return new Set([...sectionMap(content).keys(), ...rows.map((r) => r.id)]);
}

/* ------------------------------------------------------------------------
   Rows as they are
   ------------------------------------------------------------------------ */

const makeRow = (id, split, columns, settings) => ({ id, split, columns, settings });

function settingsOf(given, defaults) {
  const source = isList(given) ? given : {};
  const out = { ...defaults };
  Object.keys(defaults).forEach((key) => {
    if (!Array.isArray(source) && Object.hasOwn(source, key) && isString(source[key])) out[key] = source[key];
  });
  return out;
}

function plainRow(sectionId, taken, defaults) {
  return makeRow(freshId(sectionId, taken), '12', [[sectionId]], { ...defaults });
}

function impliedRows(content, defaults) {
  const sections = sectionMap(content);
  const inSides = new Set();
  const sides = isList(content?.sides) && !Array.isArray(content.sides) ? content.sides : {};
  SIDES.forEach((which) => valuesOf(isList(sides[which]) ? sides[which] : []).forEach((id) => { if (isString(id)) inSides.add(id); }));

  const taken = new Set(sections.keys());
  const seen = new Set();
  const rows = [];
  valuesOf(isList(content?.order) ? content.order : []).forEach((id) => {
    if (!isString(id) || !sections.has(id) || inSides.has(id) || seen.has(id)) return;
    seen.add(id);
    rows.push(plainRow(id, taken, defaults));
  });
  return rows;
}

/** The document's rows: its own, else one full-width row per section in `order` that is not in a side. */
export function rowsOf(content, layout) {
  const defaults = layoutOf(layout).row_defaults;
  if (!isList(content?.rows)) return impliedRows(content, defaults);
  return valuesOf(content.rows).filter(isList).map((r) => {
    const columns = valuesOf(isList(r.columns) ? r.columns : []).map((column) => valuesOf(isList(column) ? column : []).filter(isString));
    return makeRow(asString(r.id ?? ''), asString(r.split ?? '12'), columns, settingsOf(r.settings, defaults));
  });
}

/** The document's sides, each a list of section ids. */
function sidesOf(content) {
  const given = isList(content?.sides) && !Array.isArray(content.sides) ? content.sides : {};
  const out = {};
  SIDES.forEach((which) => { out[which] = valuesOf(isList(given[which]) ? given[which] : []).filter(isString); });
  return out;
}

/** Where a section sits: in a row's column or in a side, or null. */
export function locate(content, id, layout) {
  const sides = sidesOf(content);
  for (const side of SIDES) {
    const index = sides[side].indexOf(id);
    if (index >= 0) return { where: 'side', side, index };
  }
  for (const r of rowsOf(content, layout)) {
    for (let column = 0; column < r.columns.length; column += 1) {
      const index = r.columns[column].indexOf(id);
      if (index >= 0) return { where: 'row', rowId: r.id, column, index };
    }
  }
  return null;
}

/* ------------------------------------------------------------------------
   The normalizer (mirrors the server's Layout::normalize)
   ------------------------------------------------------------------------ */

function fitColumns(columns, count) {
  const out = columns.map((c) => [...c]);
  while (out.length < count) out.push([]);
  if (out.length > count) {
    const extra = out.splice(count);
    out[count - 1] = out[count - 1].concat(...extra);
  }
  return out;
}

function misfit(section, span, rules) {
  if (rules.isMain(section)) return 'main-section';
  return rules.minSpan(section) > span ? 'too-wide' : null;
}

const sidesAllowedBy = (rules) => (typeof rules.sidesAllowed === 'function' ? Boolean(rules.sidesAllowed()) : Boolean(rules.sidesAllowed));

const movedEntry = (sid, reason, rowId) => ({ path: `content.sections.${sid}`, reason, value: rowId });

function rowIndexOf(rows, sid) {
  return rows.findIndex((r) => r.columns.some((column) => column.includes(sid)));
}

/**
 * At most `max_rows` rows: when rows made for moved or unplaced sections
 * take a page over, empty rows go, first those this run emptied, then other
 * empty rows from the end.
 */
function fitRowLimit(rows, emptied, max) {
  const isEmpty = (r) => r.columns.every((column) => column.length === 0);
  const drop = [];
  rows.forEach((r, index) => { if (emptied.has(r.id) && isEmpty(r)) drop.push(index); });
  for (let index = rows.length - 1; index >= 0; index -= 1) {
    if (!emptied.has(rows[index].id) && isEmpty(rows[index])) drop.push(index);
  }
  const gone = new Set(drop.slice(0, Math.max(0, rows.length - max)));
  return rows.filter((r, index) => !gone.has(index));
}

/**
 * The document with every section placed once and `order` derived from
 * where it sits. Never refuses: a section that does not fit where it is
 * gets its own full-width row and is reported in `moved` (reasons
 * too-wide, main-section, no-sides); a section in no row and no side is
 * placed after the row of the section before it in `order`, unreported.
 * A document with neither `rows` nor `sides` comes back as it is. The
 * input is never changed.
 */
export function normalize(content, rules) {
  if (!Object.hasOwn(content, 'rows') && !Object.hasOwn(content, 'sides')) return { content, moved: [] };
  const layout = layoutOf(rules.layout);
  const defaults = layout.row_defaults;
  const sections = sectionMap(content);

  const known = new Set();
  valuesOf(isList(content.order) ? content.order : []).forEach((id) => { if (isString(id) && sections.has(id)) known.add(id); });
  sections.forEach((section, id) => known.add(id));
  const knownList = [...known];

  const placed = new Set();
  const moved = [];
  const taken = new Set(sections.keys());
  const claim = (id) => {
    if (!isString(id) || !sections.has(id) || placed.has(id)) return false;
    placed.add(id);
    return true;
  };

  const sides = { left: [], right: [] };
  const givenSides = isList(content.sides) ? content.sides : {};
  SIDES.forEach((which) => {
    const list = Array.isArray(givenSides) ? undefined : givenSides[which];
    valuesOf(isList(list) ? list : []).forEach((id) => { if (claim(id)) sides[which].push(id); });
  });

  let given;
  if (isList(content.rows)) {
    given = valuesOf(content.rows).filter(isList);
  } else {
    given = knownList.filter((id) => !placed.has(id)).map((id) => ({ split: '12', columns: [[id]] }));
  }

  const ids = new Map();
  given.forEach((r, i) => {
    const id = Array.isArray(r) ? undefined : r.id;
    if (isString(id) && ID.test(id) && !taken.has(id)) {
      ids.set(i, id);
      taken.add(id);
    }
  });

  let rows = [];
  const emptied = new Set();
  given.forEach((given_, i) => {
    const r = Array.isArray(given_) ? {} : given_;
    let split = isString(r.split) ? r.split : Number.isInteger(r.split) ? String(r.split) : '';
    let columns = valuesOf(isList(r.columns) ? r.columns : []).map((column) => (isList(column) ? [...valuesOf(column)] : []));
    if (!partsOf(layout, split)) {
      split = '12';
      columns = [[].concat(...columns)];
    }
    const parts = partsOf(layout, split);
    columns = fitColumns(columns, parts.length).map((column) => column.filter(claim));
    const settings = settingsOf(r.settings, defaults);
    // An empty row with no usable id gets one from a fixed seed, so its id
    // does not depend on where the row sits.
    const seed = [].concat(...columns)[0] ?? 'row';

    if (split === '12') {
      const column = columns[0];
      if (!column.length) return;
      const [first, ...rest] = column;
      rows.push(makeRow(ids.get(i) ?? freshId(first, taken), '12', [[first]], settings));
      rest.forEach((id) => rows.push(plainRow(id, taken, defaults)));
      return;
    }

    const id = ids.get(i) ?? freshId(seed, taken);
    const misfits = [];
    columns = columns.map((column, c) => column.filter((sid) => {
      const reason = misfit(sections.get(sid), parts[c], rules);
      if (reason !== null) misfits.push([sid, reason]);
      return reason === null;
    }));
    rows.push(makeRow(id, split, columns, settings));
    if (misfits.length && columns.every((column) => column.length === 0)) emptied.add(id);
    misfits.forEach(([sid, reason]) => {
      const plain = plainRow(sid, taken, defaults);
      rows.push(plain);
      moved.push(movedEntry(sid, reason, plain.id));
    });
  });

  // Sides: what cannot sit in one goes to its own row at the end.
  const allowed = sidesAllowedBy(rules);
  SIDES.forEach((which) => {
    sides[which] = sides[which].filter((sid) => {
      const reason = allowed ? misfit(sections.get(sid), layout.side_span, rules) : 'no-sides';
      if (reason === null) return true;
      const plain = plainRow(sid, taken, defaults);
      rows.push(plain);
      moved.push(movedEntry(sid, reason, plain.id));
      return false;
    });
  });

  // Sections in no row and no side: after the row of the nearest one before them.
  knownList.forEach((sid, k) => {
    if (placed.has(sid)) return;
    let at = 0;
    for (let j = k - 1; j >= 0; j -= 1) {
      const index = rowIndexOf(rows, knownList[j]);
      if (index >= 0) {
        at = index + 1;
        break;
      }
    }
    rows.splice(at, 0, plainRow(sid, taken, defaults));
    placed.add(sid);
  });

  rows = fitRowLimit(rows, emptied, layout.max_rows);

  const order = [...sides.left];
  rows.forEach((r) => r.columns.forEach((column) => order.push(...column)));
  order.push(...sides.right);

  const keptSides = {};
  SIDES.forEach((which) => { if (sides[which].length) keptSides[which] = sides[which]; });

  const out = { ...content };
  out.order = order;
  delete out.rows;
  delete out.sides;
  const plainOnly = rows.every((r) => r.split === '12' && same(r.settings, defaults))
    && same(rows, impliedRows({ order, sections: content.sections ?? [], sides: keptSides }, defaults));
  if (!plainOnly) out.rows = rows;
  if (Object.keys(keptSides).length) out.sides = keptSides;

  return { content: out, moved };
}

/** Rules that accept every placement: for changes that move nothing out of place. */
function acceptingRules(layout) {
  return { layout: layoutOf(layout), minSpan: () => 0, isMain: () => false, sidesAllowed: true };
}

/**
 * The document normalized without moving anything: ids that name no
 * section are dropped, sections in no row get one after the row of the
 * section before them, `order` is derived again. Used after a section is
 * added or removed on a page with rows.
 */
export function tidy(content, layout) {
  return normalize(content, acceptingRules(layout)).content;
}

/** The document with these rows and sides, normalized. */
function withLayout(content, rows, sides, rules) {
  return normalize({ ...content, rows, sides }, rules);
}

const rowIds = (content, layout) => rowsOf(content, layout).map((r) => r.id);

function createdBetween(before, after, layout) {
  const had = new Set(rowIds(before, layout));
  return rowIds(after, layout).filter((id) => !had.has(id));
}

/* ------------------------------------------------------------------------
   Where a section can go
   ------------------------------------------------------------------------ */

/**
 * How wide a drop target is, in twelfths. Targets:
 * `{drop: 'gap', before: rowId | 'end'}` a row of its own between rows,
 * `{drop: 'column', rowId, column, before: sid | null}`,
 * `{drop: 'beside', rowId, side: 'left' | 'right'}` next to a full-width section,
 * `{drop: 'side', side, before: sid | null}`.
 */
export function spanAt(content, target, rules) {
  const layout = layoutOf(rules.layout);
  if (target?.drop === 'gap') return 12;
  if (target?.drop === 'beside') return 6;
  if (target?.drop === 'side') return layout.side_span;
  if (target?.drop === 'column') {
    const r = rowsOf(content, layout).find((x) => x.id === target.rowId);
    return (r && partsOf(layout, r.split)?.[target.column]) || 0;
  }
  return 0;
}

/**
 * Whether the section may go there: its span fits, a main section only
 * into a gap, a column or side with room, never onto its own place.
 */
export function fits(content, sid, target, rules) {
  const layout = layoutOf(rules.layout);
  const section = sectionMap(content).get(sid);
  if (!section || !target) return false;
  if (rules.isMain(section) && target.drop !== 'gap') return false;
  const span = rules.minSpan(section);
  const rows = rowsOf(content, layout);
  const at = locate(content, sid, layout);
  const own = at?.where === 'row' ? rows.find((r) => r.id === at.rowId) : null;

  if (target.drop === 'gap') {
    const index = target.before === 'end' ? rows.length : rows.findIndex((r) => r.id === target.before);
    if (index < 0) return false;
    if (own && own.split === '12') {
      const ownIndex = rows.indexOf(own);
      if (index === ownIndex || index === ownIndex + 1) return false;
    }
    return true;
  }

  if (target.drop === 'column') {
    const r = rows.find((x) => x.id === target.rowId);
    const parts = r ? partsOf(layout, r.split) : null;
    if (!parts || parts.length < 2 || !Number.isInteger(target.column) || target.column < 0 || target.column >= parts.length) return false;
    const column = r.columns[target.column] || [];
    const before = target.before ?? null;
    if (before === sid || (before !== null && !column.includes(before))) return false;
    const here = at?.where === 'row' && at.rowId === r.id && at.column === target.column;
    if (here && (column[at.index + 1] ?? null) === before) return false;
    if (!here && column.length >= layout.max_per_column) return false;
    return span <= parts[target.column];
  }

  if (target.drop === 'beside') {
    const r = rows.find((x) => x.id === target.rowId);
    if (!r || r.split !== '12' || r.columns[0]?.length !== 1 || !SIDES.includes(target.side)) return false;
    const other = r.columns[0][0];
    const otherSection = sectionMap(content).get(other);
    if (other === sid || !otherSection || rules.isMain(otherSection)) return false;
    return span <= 6 && rules.minSpan(otherSection) <= 6 && partsOf(layout, '6-6') !== null;
  }

  if (target.drop === 'side') {
    if (!sidesAllowedBy(rules) || !SIDES.includes(target.side)) return false;
    const list = sidesOf(content)[target.side];
    const before = target.before ?? null;
    if (before === sid || (before !== null && !list.includes(before))) return false;
    const here = at?.where === 'side' && at.side === target.side;
    if (here && (list[at.index + 1] ?? null) === before) return false;
    if (!here && list.length >= layout.max_per_side) return false;
    return span <= layout.side_span;
  }

  return false;
}

/**
 * Moves a section to the target. Answers the document unchanged when the
 * section does not fit there, and the ids of the rows the move made.
 */
export function place(content, sid, target, rules) {
  if (!fits(content, sid, target, rules)) return { content, created: [] };
  const layout = layoutOf(rules.layout);
  const rows = rowsOf(content, layout).map((r) => ({ ...r, columns: r.columns.map((column) => column.filter((id) => id !== sid)) }));
  const sides = sidesOf(content);
  SIDES.forEach((which) => { sides[which] = sides[which].filter((id) => id !== sid); });

  if (target.drop === 'gap') {
    // A one-column row emptied by the move is gone after normalizing, so its
    // id is free again; an empty row of columns stays and keeps its id.
    const taken = takenIds(content, rows.filter((r) => r.split !== '12' || r.columns.some((column) => column.length)));
    const index = target.before === 'end' ? rows.length : rows.findIndex((r) => r.id === target.before);
    rows.splice(index, 0, makeRow(freshId(sid, taken), '12', [[sid]], { ...layout.row_defaults }));
  } else if (target.drop === 'column') {
    const column = rows.find((r) => r.id === target.rowId).columns[target.column];
    const index = target.before == null ? column.length : column.indexOf(target.before);
    column.splice(index, 0, sid);
  } else if (target.drop === 'beside') {
    const r = rows.find((x) => x.id === target.rowId);
    const other = r.columns[0][0];
    r.split = '6-6';
    r.columns = target.side === 'left' ? [[sid], [other]] : [[other], [sid]];
  } else {
    const list = sides[target.side];
    const index = target.before == null ? list.length : list.indexOf(target.before);
    list.splice(index, 0, sid);
  }

  const next = withLayout(content, rows, sides, rules).content;
  return { content: next, created: createdBetween(content, next, layout) };
}

/** How wide a section's own place is, in twelfths: its column, its side or a full-width row; null when it is not on the page. */
export function roomOf(content, sid, rules) {
  const layout = layoutOf(rules.layout);
  const at = locate(content, sid, layout);
  if (!at) return null;
  if (at.where === 'side') return layout.side_span;
  const r = rowsOf(content, layout).find((x) => x.id === at.rowId);
  return (r && partsOf(layout, r.split)?.[at.column]) || 12;
}

/**
 * Puts another section in a section's place: the same row, column or
 * side and the same position in it, under the new id. Answers null, and
 * changes nothing, when the old one is not on the page or is the page's
 * main section, the new id is taken, or the new section does not fit the
 * place (too wide for it, or a main section). Rows, sides and order keep
 * their shape and key order, so nothing else moves.
 */
export function replaceIn(content, sid, newSid, section, rules) {
  const sections = sectionMap(content);
  if (!sections.has(sid) || !isString(newSid) || newSid === '' || sections.has(newSid)) return null;
  if (rules.isMain(sections.get(sid)) || rules.isMain(section)) return null;
  const room = roomOf(content, sid, rules);
  if (room === null || rules.minSpan(section) > room) return null;

  const swap = (id) => (id === sid ? newSid : id);
  const swapList = (list) => (Array.isArray(list) ? list.map(swap) : list);
  const out = {};
  Object.keys(content).forEach((key) => {
    if (key === 'sections') {
      out.sections = {};
      Object.keys(content.sections).forEach((id) => { out.sections[swap(id)] = id === sid ? section : content.sections[id]; });
    } else if (key === 'order') {
      out.order = swapList(content.order);
    } else if (key === 'rows' && Array.isArray(content.rows)) {
      out.rows = content.rows.map((r) => (isList(r) && Array.isArray(r.columns) ? { ...r, columns: r.columns.map(swapList) } : r));
    } else if (key === 'sides' && isList(content.sides) && !Array.isArray(content.sides)) {
      out.sides = {};
      Object.keys(content.sides).forEach((which) => { out.sides[which] = swapList(content.sides[which]); });
    } else {
      out[key] = content[key];
    }
  });
  return { content: out };
}

/* ------------------------------------------------------------------------
   Rows
   ------------------------------------------------------------------------ */

const gapIndex = (rows, before) => (before === 'end' || before == null ? rows.length : rows.findIndex((r) => r.id === before));

/** Adds an empty row of columns before a row (or at the end). An empty one-column row says nothing, so it is not added. */
export function addRow(content, split, before, rowId, layout) {
  const l = layoutOf(layout);
  const parts = partsOf(l, split);
  const rows = rowsOf(content, l);
  const index = gapIndex(rows, before);
  if (!parts || parts.length < 2 || index < 0 || rows.length >= l.max_rows) return content;
  if (!isString(rowId) || !ID.test(rowId) || takenIds(content, rows).has(rowId)) return content;
  rows.splice(index, 0, makeRow(rowId, split, parts.map(() => []), { ...l.row_defaults }));
  return withLayout(content, rows, sidesOf(content), acceptingRules(l)).content;
}

/** Moves a row before another row (or to the end). */
export function moveRow(content, rowId, before, layout) {
  const l = layoutOf(layout);
  const rows = rowsOf(content, l);
  const from = rows.findIndex((r) => r.id === rowId);
  if (from < 0 || before === rowId) return content;
  const [moving] = rows.splice(from, 1);
  const index = gapIndex(rows, before);
  if (index < 0) return content;
  rows.splice(index, 0, moving);
  return withLayout(content, rows, sidesOf(content), acceptingRules(l)).content;
}

/** Removes a row; each of its sections gets a full-width row where the row was, in reading order. */
export function removeRow(content, rowId, layout) {
  const l = layoutOf(layout);
  const rows = rowsOf(content, l);
  const index = rows.findIndex((r) => r.id === rowId);
  if (index < 0) return { content, created: [] };
  const [gone] = rows.splice(index, 1);
  const taken = takenIds(content, rows);
  const plain = [].concat(...gone.columns).map((sid) => plainRow(sid, taken, l.row_defaults));
  rows.splice(index, 0, ...plain);
  const next = withLayout(content, rows, sidesOf(content), acceptingRules(l)).content;
  return { content: next, created: createdBetween(content, next, l) };
}

/**
 * Gives a row another split. Extra columns pour into the last one; a
 * column over its limit and sections too wide for their new column move to
 * full-width rows below; one column (`12`) makes a full-width row per
 * section, with no row settings.
 */
export function changeSplit(content, rowId, split, rules) {
  const layout = layoutOf(rules.layout);
  const parts = partsOf(layout, split);
  const rows = rowsOf(content, layout);
  const index = rows.findIndex((r) => r.id === rowId);
  if (!parts || index < 0 || rows[index].split === split) return { content, moved: [], created: [] };

  const r = rows[index];
  const columns = fitColumns(r.columns, parts.length);
  const overflow = [];
  if (parts.length > 1) {
    columns.forEach((column) => overflow.push(...column.splice(layout.max_per_column)));
  }
  rows[index] = makeRow(r.id, split, columns, parts.length > 1 ? r.settings : { ...layout.row_defaults });
  const taken = takenIds(content, rows);
  rows.splice(index + 1, 0, ...overflow.map((sid) => plainRow(sid, taken, layout.row_defaults)));

  const result = withLayout(content, rows, sidesOf(content), rules);
  const moved = [...result.moved.map((m) => m.path.replace(/^content\.sections\./, '')), ...overflow];
  return { content: result.content, moved, created: createdBetween(content, result.content, layout) };
}

/** Sets one of a row's settings (a key of the row defaults) to a text value. */
export function setRow(content, rowId, key, value, layout) {
  const l = layoutOf(layout);
  if (!Object.hasOwn(l.row_defaults, key) || !isString(value)) return content;
  const rows = rowsOf(content, l);
  const r = rows.find((x) => x.id === rowId);
  if (!r || r.settings[key] === value) return content;
  r.settings = { ...r.settings, [key]: value };
  return withLayout(content, rows, sidesOf(content), acceptingRules(l)).content;
}

/* ------------------------------------------------------------------------
   What a change redraws
   ------------------------------------------------------------------------ */

/** Each section's width on the page, in twelfths. */
function widths(content, layout) {
  const out = new Map();
  const sides = sidesOf(content);
  SIDES.forEach((which) => sides[which].forEach((sid) => out.set(sid, layout.side_span)));
  rowsOf(content, layout).forEach((r) => {
    const parts = partsOf(layout, r.split) || [12];
    r.columns.forEach((column, c) => column.forEach((sid) => out.set(sid, parts[c] ?? 12)));
  });
  return out;
}

/**
 * What a change between two versions of a document redraws: the board,
 * the multi-column rows whose content changed (drawn again by the server),
 * and the sections whose width changed, so their picture is taken again.
 */
export function effectsOf(before, after, layout) {
  const l = layoutOf(layout);
  const was = new Map(rowsOf(before, l).map((r) => [r.id, JSON.stringify(r)]));
  const rows = rowsOf(after, l)
    .filter((r) => (partsOf(l, r.split)?.length || 1) > 1 && was.get(r.id) !== JSON.stringify(r))
    .map((r) => r.id);
  const wide = widths(before, l);
  const sections = [...widths(after, l)].filter(([sid, span]) => wide.get(sid) !== span).map(([sid]) => sid);
  return { board: true, rows, sections };
}

/**
 * The layout rules for a document from the section catalog: a type the
 * catalog does not know counts as the narrowest span, so it keeps its
 * place; a variant may ask for its own span.
 */
export function catalogRules(layout, sectionDef, sidesAllowed) {
  return {
    layout,
    minSpan(section) {
      const def = sectionDef(section?.type);
      if (!def) return layout.side_span;
      const span = def.span || {};
      const variantSetting = (def.settings || []).find((s) => s.key === 'variant');
      const settings = isList(section?.settings) ? section.settings : {};
      const variant = settings.variant ?? variantSetting?.default;
      if (isString(variant) && span.variants && Object.hasOwn(span.variants, variant)) return span.variants[variant];
      return Number.isInteger(span.min) ? span.min : 12;
    },
    isMain: (section) => sectionDef(section?.type)?.main === true,
    sidesAllowed: Boolean(sidesAllowed),
  };
}
