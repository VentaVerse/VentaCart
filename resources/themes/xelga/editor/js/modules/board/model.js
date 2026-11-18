/*
 * Rows, sides and areas of a page document, as pure functions of the document.
 *
 * A template document may hold `rows` (each `{id, split, columns, settings}`,
 * a column being a list of section ids) and `sides` (`{left, right}`, lists
 * of section ids).
 *
 * A page with page content (one that may have sides and holds its main
 * section) also has areas: each row is in Top, Content or Bottom (its
 * `area`), in that order down the page. Top and Bottom run the whole page
 * width; the sides stand beside Content. `order` stays the reading order:
 * Top rows, left side, Content rows (each row's columns left to right),
 * right side, Bottom rows. A page without areas (Home, the cart, a page
 * without its main section) is just rows, and has no sides.
 *
 * normalize() is the server's layout normalizer written again for the
 * editor, so the editor can show the result of a move before it is saved.
 * Both read the shared cases in tests/fixtures/layout and must agree on
 * every one of them, row ids included. Every other change here ends in
 * normalize(), so what the editor saves is what the server would store.
 *
 * Nothing here touches the DOM or the editor's state. `rules` is
 * `{layout, minSpan(section), isMain(section), sidesAllowed, isLocked?,
 * ownRow?, contentOnly?, limitPerArea?, known?}` (see catalogRules()),
 * `layout` being the boot payload's `layout` (splits, row defaults, limits).
 *
 * Sides narrow Content: 12 twelfths without sides, 9 with one, 6 with
 * both. A column of part `p` in Content so really draws `p x content / 12`
 * twelfths of the page, and a section fits it only when its span is no
 * more than that, as on the server. Content measures its one-column rows
 * too, except for the main section; Top and Bottom are measured at 12.
 *
 * A spot of the board, where a section or row may go, is one of:
 * `{drop: 'gap', area, before: rowId | 'end'}` a row of its own between
 * rows of an area (`'end'` is the end of that area; `area` is null on a
 * page without areas), `{drop: 'column', rowId, column, before: sid | null}`,
 * `{drop: 'beside', rowId, side}` next to a full-width section,
 * `{drop: 'side', side, before: sid | null}`, and
 * `{drop: 'band', group: 'header' | 'footer', before: sid | null}` a place
 * in a band.
 */

import { isLocked } from '../catalog.js';

const SIDES = ['left', 'right'];
const ID = /^[a-z0-9-]{1,40}$/;

/** The areas of a page with page content, top to bottom. */
export const AREAS = ['top', 'content', 'bottom'];
const RANK = { top: 0, content: 1, bottom: 2 };

/** Why normalize() moved a section. */
export const MOVE_REASONS = {
  'too-wide': 'A section too wide for its place moved to a row of its own, or out of Content.',
  'in-order': 'A row moved with a section too wide for the content beside the sides, to keep the page in order.',
  'main-section': 'The page\'s main section moved to a row of its own.',
  'own-row': 'A section that needs a row of its own moved to one.',
  'content-only': 'A section that goes only beside the page content moved into Content.',
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

/** Whether a value is a rules object rather than the layout payload alone. */
const isRules = (value) => Boolean(value) && typeof value.isMain === 'function';

/* ------------------------------------------------------------------------
   Reading values the way the server reads decoded JSON
   ------------------------------------------------------------------------ */

/** A list or a map: what PHP calls an array once JSON is decoded. */
const isList = (value) => value !== null && typeof value === 'object';
const isMap = (value) => isList(value) && !Array.isArray(value);
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
   What the rules say about a section
   ------------------------------------------------------------------------ */

const ask = (rules, name, section) => typeof rules?.[name] === 'function' && Boolean(rules[name](section));
const lockedBy = (rules, section) => ask(rules, 'isLocked', section);
const ownRowBy = (rules, section) => ask(rules, 'ownRow', section);
const contentOnlyBy = (rules, section) => ask(rules, 'contentOnly', section);
const sidesAllowedBy = (rules) => (typeof rules.sidesAllowed === 'function' ? Boolean(rules.sidesAllowed()) : Boolean(rules.sidesAllowed));
const pageTypeKnownBy = (rules) => typeof rules?.pageTypeKnown !== 'function' || Boolean(rules.pageTypeKnown());

/** Whether the page has areas: it may have them, and it holds its main section. */
function hasAreasIn(sections, rules) {
  if (!rules || !sidesAllowedBy(rules)) return false;
  return [...sections.values()].some((section) => rules.isMain(section));
}

/** Whether the document has areas (Top, Content, Bottom) and so may have sides. */
export function hasAreas(content, rules) {
  return hasAreasIn(sectionMap(content), rules);
}

/** A row's stored area when it is one of the areas, else null. */
const storedArea = (r) => (isMap(r) && AREAS.includes(r.area) ? r.area : null);

/**
 * The area of each row, top to bottom (as the server's Layout::areasOf).
 *
 * 1. A row keeps a valid stored area.
 * 2. On a page stored with sides before rows had areas (`beside`), the
 *    sides ran beside every row, so a row without one is Content.
 * 3. Otherwise a row takes its place on the page: Top above the main
 *    section's row, Content for that row, Bottom below it. A row split
 *    off another takes the area of the row it left.
 * 4. Then the main row is Content, a row above it at most Content, a row
 *    below it at least Content, and down the page the area never goes back.
 *
 * `main` is the index of the main row: between two rows (x.5) or past the
 * end while it is still to be placed.
 */
function areaList(rows, main, beside) {
  const out = [];
  let least = 0;
  rows.forEach((r, index) => {
    let area = r._area ?? null;
    if (area === null && r._inherit && index > 0) area = out[index - 1];
    if (area === null) {
      if (beside) area = 'content';
      else if (index < main) area = 'top';
      else if (index === main) area = 'content';
      else area = 'bottom';
    }
    let rank = RANK[area];
    if (index < main) rank = Math.min(rank, RANK.content);
    else if (index === main) rank = RANK.content;
    else rank = Math.max(rank, RANK.content);
    least = Math.max(least, rank);
    out.push(AREAS[least]);
  });
  return out;
}

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

/** A row with its area, the key the server stores last; a page without areas has none. */
const withArea = (r, area) => (area ? { ...makeRow(r.id, r.split, r.columns, r.settings), area } : makeRow(r.id, r.split, r.columns, r.settings));

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
  const sides = isMap(content?.sides) ? content.sides : {};
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

/**
 * The document's rows: its own, else one full-width row per section in
 * `order` that is not in a side. Given the page's rules, on a page with
 * areas each row also has its `area`: the one it is stored with, else the
 * one its place on the page gives it (as the server's Layout::rows).
 */
export function rowsOf(content, layout, rules = null) {
  const defaults = layoutOf(layout).row_defaults;
  const explicit = isList(content?.rows);
  const given = explicit ? valuesOf(content.rows).filter(isList) : [];
  const rows = explicit
    ? given.map((r) => {
      const columns = valuesOf(isList(r.columns) ? r.columns : []).map((column) => valuesOf(isList(column) ? column : []).filter(isString));
      return makeRow(asString(r.id ?? ''), asString(r.split ?? '12'), columns, settingsOf(r.settings, defaults));
    })
    : impliedRows(content, defaults);

  const sections = sectionMap(content);
  if (!isRules(rules) || !pageTypeKnownBy(rules) || !hasAreasIn(sections, rules)) return rows;

  const sides = sidesOf(content);
  const inSides = SIDES.some((which) => sides[which].some((id) => sections.has(id)));
  const stored = explicit && given.some((r) => storedArea(r) !== null);
  let main = Infinity;
  rows.forEach((r, index) => {
    if (r.columns.flat().some((sid) => sections.has(sid) && rules.isMain(sections.get(sid)))) main = Math.min(main, index);
  });
  const areas = areaList(rows.map((r, index) => ({ _area: explicit ? storedArea(given[index]) : null })), main, inSides && !stored);
  return rows.map((r, index) => withArea(r, areas[index]));
}

/** The document's sides, each a list of section ids. */
function sidesOf(content) {
  const given = isMap(content?.sides) ? content.sides : {};
  const out = {};
  SIDES.forEach((which) => { out[which] = valuesOf(isList(given[which]) ? given[which] : []).filter(isString); });
  return out;
}

/**
 * The rows of each area, `{top, content, bottom}`, or null on a page
 * without areas.
 */
export function areasOf(content, rules) {
  const rows = rowsOf(content, rules?.layout, rules);
  if (!rows.some((r) => r.area) && !hasAreas(content, rules)) return null;
  const out = { top: [], content: [], bottom: [] };
  rows.forEach((r) => out[r.area].push(r));
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

/**
 * Why a section cannot sit in a column or side `span` twelfths wide, or
 * null. A column's span is its part of an area `content` twelfths wide; a
 * side is measured on the whole page.
 */
function misfit(section, span, rules, content = 12) {
  if (rules.isMain(section)) return 'main-section';
  if (ownRowBy(rules, section)) return 'own-row';
  return rules.minSpan(section) * 12 > span * content ? 'too-wide' : null;
}

/** Whether a section `need` twelfths wide fits a column of part `part` in an area `content` wide. */
const fitsPart = (need, part, content) => need * 12 <= part * content;

/** The content area left beside `count` sides, in twelfths of the page. */
function contentFor(count, layout) {
  return Math.max(1, 12 - layout.side_span * Math.max(0, Math.min(SIDES.length, count)));
}

/**
 * The width Content is measured in: every side that holds a section
 * counts, hidden ones included, since a hidden section can be shown again
 * without a save. `without` is a section about to leave its side. A page
 * without areas has no sides and is measured at 12.
 */
function measuredContent(content, rules, without = null) {
  const layout = layoutOf(rules.layout);
  const sections = sectionMap(content);
  if (!hasAreasIn(sections, rules)) return 12;
  const sides = sidesOf(content);
  const count = SIDES.filter((which) => sides[which].some((sid) => sid !== without && sections.has(sid))).length;
  return contentFor(count, layout);
}

/**
 * How wide the page's content area draws, in twelfths of the page: 12, 9
 * or 6. Counts the sides the store draws: those holding a section that is
 * not hidden and whose type is known. A side whose sections draw nothing
 * (an empty list, say) still counts here, though the store leaves it out:
 * only the drawn page can tell. The board says this width on its rows.
 */
export function contentSpan(content, rules) {
  const layout = layoutOf(rules.layout);
  const sections = sectionMap(content);
  if (!hasAreasIn(sections, rules)) return 12;
  const sides = sidesOf(content);
  const known = typeof rules.known === 'function' ? rules.known : () => true;
  const drawn = SIDES.filter((which) => sides[which].some((sid) => {
    const section = sections.get(sid);
    return Boolean(section) && !section.disabled && known(section);
  })).length;
  return contentFor(drawn, layout);
}

/** The sides a page type may have, from the boot payload's `sides_on`: both, or none. */
export function sideTargets(pageType, layout) {
  const l = layoutOf(layout);
  return Array.isArray(l.sides_on) && l.sides_on.includes(pageType) ? [...SIDES] : [];
}

const movedEntry = (sid, reason, value) => ({ path: `content.sections.${sid}`, reason, value });

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

/** The index of the first row holding a main section, or null. */
function mainRow(rows, sections, rules) {
  const index = rows.findIndex((r) => r.columns.some((column) => column.some((sid) => rules.isMain(sections.get(sid)))));
  return index < 0 ? null : index;
}

/**
 * Where the main section's row will be among the given rows: its row when
 * that is one column, just after it when it sits in a column (it moves out
 * to a row of its own), at the end when it sits in a side, and after the
 * row of the section before it when it sits nowhere.
 */
function mainPlace(given, sides, known, sections, rules, layout) {
  const isMain = (id) => isString(id) && sections.has(id) && rules.isMain(sections.get(id));
  if (SIDES.some((which) => sides[which].some(isMain))) return Infinity;

  const rowOf = new Map();
  for (let index = 0; index < given.length; index += 1) {
    const r = Array.isArray(given[index]) ? {} : given[index];
    const columns = valuesOf(isList(r.columns) ? r.columns : []);
    columns.forEach((column) => valuesOf(isList(column) ? column : []).forEach((id) => {
      if (isString(id) && !rowOf.has(id)) rowOf.set(id, index);
    }));
    for (const column of columns) {
      for (const id of valuesOf(isList(column) ? column : [])) {
        if (isMain(id)) {
          const split = r.split === undefined || r.split === null ? '12' : asString(r.split);
          return split === '12' || !partsOf(layout, split) ? index : index + 0.5;
        }
      }
    }
  }

  for (let k = 0; k < known.length; k += 1) {
    if (!isMain(known[k])) continue;
    for (let j = k - 1; j >= 0; j -= 1) {
      if (rowOf.has(known[j])) return rowOf.get(known[j]) + 0.5;
    }
    return -0.5;
  }
  return Infinity;
}

/**
 * Content holds what fits beside the sides. Each Content row is measured
 * at the content span, one-column rows too, leaving out the main section
 * and sections that belong in Content. The last row above the main row
 * that does not fit goes to Top with every Content row above it; the
 * first one below goes to Bottom with every Content row below it, so the
 * page keeps its reading order. Changes `rows` in place.
 */
function fitContent(rows, main, sections, rules, span, layout) {
  const misfits = new Map();
  rows.forEach((r, index) => {
    if (r._area !== 'content') return;
    const parts = partsOf(layout, r.split);
    r.columns.forEach((column, c) => column.forEach((sid) => {
      const section = sections.get(sid);
      if (!rules.isMain(section) && !contentOnlyBy(rules, section) && rules.minSpan(section) * 12 > parts[c] * span) {
        if (!misfits.has(index)) misfits.set(index, new Set());
        misfits.get(index).add(sid);
      }
    }));
  });

  const above = [...misfits.keys()].filter((index) => index < main);
  const below = [...misfits.keys()].filter((index) => index > main);
  const leave = new Map();
  if (above.length) {
    const last = Math.max(...above);
    rows.forEach((r, index) => { if (index <= last && r._area === 'content') leave.set(index, 'top'); });
  }
  if (below.length) {
    const first = Math.min(...below);
    rows.forEach((r, index) => { if (index >= first && r._area === 'content') leave.set(index, 'bottom'); });
  }

  const moved = [];
  leave.forEach((area, index) => {
    rows[index]._area = area;
    rows[index].columns.flat().forEach((sid) => {
      if (!contentOnlyBy(rules, sections.get(sid))) moved.push(movedEntry(sid, misfits.get(index)?.has(sid) ? 'too-wide' : 'in-order', area));
    });
  });
  return moved;
}

/**
 * A section that belongs in Content found in Top or Bottom moves to a
 * one-column row of its own: at the start of Content from Top, at the end
 * of Content from Bottom. A one-column row that holds only it moves as it
 * is. Answers the rows in their new order and what moved.
 */
function contentOnlyIntoContent(rows, emptied, sections, rules, taken, defaults) {
  const moved = [];
  const into = { top: [], bottom: [] };
  const kept = [];
  rows.forEach((r) => {
    const area = r._area;
    if (area === 'content') {
      kept.push(r);
      return;
    }
    const out = [];
    r.columns.forEach((column, c) => column.forEach((sid) => { if (contentOnlyBy(rules, sections.get(sid))) out.push([c, sid]); }));
    if (!out.length) {
      kept.push(r);
      return;
    }
    if (r.split === '12') {
      into[area].push({ ...r, _area: 'content' });
      moved.push(movedEntry(out[0][1], 'content-only', r.id));
      return;
    }
    out.forEach(([c, sid]) => {
      r.columns[c] = r.columns[c].filter((id) => id !== sid);
      const plain = { ...plainRow(sid, taken, defaults), _area: 'content', _inherit: false };
      into[area].push(plain);
      moved.push(movedEntry(sid, 'content-only', plain.id));
    });
    if (r.columns.every((column) => column.length === 0)) emptied.add(r.id);
    kept.push(r);
  });
  if (!moved.length) return { rows, moved };
  const byArea = { top: [], content: [], bottom: [] };
  kept.forEach((r) => byArea[r._area].push(r));
  return { rows: [...byArea.top, ...into.top, ...byArea.content, ...into.bottom, ...byArea.bottom], moved };
}

/** Whether each row's area is the one its place on the page gives it. */
function areasArePlaces(rows, main) {
  if (main === null) return false;
  return rows.every((r, index) => r._area === (index < main ? 'top' : index === main ? 'content' : 'bottom'));
}

/**
 * The document with every section placed once and `order` derived from
 * where it sits. Never refuses: what does not fit is moved and reported
 * in `moved` with one of the MOVE_REASONS, as the server does:
 *
 * - A section in no row and no side gets its own full-width row after the
 *   row of the nearest section before it in `order`, unreported.
 * - A section too wide for its column or side, a main section in a column
 *   or side, or a section that keeps a row of its own found in one, gets
 *   its own full-width row: after its row, or at the end from a side.
 * - On a page with areas each row gets its area; Content is measured at
 *   what the sides leave, and a row that does not fit there leaves
 *   Content with the rows between it and the edge of Content. A section
 *   that belongs in Content moves into it from Top or Bottom.
 * - A page without areas has no sides: they go to rows at the end.
 *
 * A document with neither `rows` nor `sides` comes back as it is. The
 * input is never changed.
 */
export function normalize(content, rules) {
  if (!Object.hasOwn(content, 'rows') && !Object.hasOwn(content, 'sides')) return { content, moved: [] };
  if (!pageTypeKnownBy(rules)) return { content, moved: [] };
  const layout = layoutOf(rules.layout);
  const defaults = layout.row_defaults;
  const sections = sectionMap(content);

  const known = new Set();
  valuesOf(isList(content.order) ? content.order : []).forEach((id) => { if (isString(id) && sections.has(id)) known.add(id); });
  sections.forEach((section, id) => known.add(id));
  const knownList = [...known];

  const placed = new Set();
  let moved = [];
  const taken = new Set(sections.keys());
  const claim = (id) => {
    if (!isString(id) || !sections.has(id) || placed.has(id)) return false;
    placed.add(id);
    return true;
  };

  const areas = hasAreasIn(sections, rules);

  const sides = { left: [], right: [] };
  const givenSides = isList(content.sides) ? content.sides : {};
  SIDES.forEach((which) => {
    const list = Array.isArray(givenSides) ? undefined : givenSides[which];
    valuesOf(isList(list) ? list : []).forEach((id) => { if (claim(id)) sides[which].push(id); });
  });
  const hadSides = sides.left.length > 0 || sides.right.length > 0;

  // What stays in the sides decides the content area Content is measured in.
  const sideMisfits = [];
  SIDES.forEach((which) => {
    sides[which] = sides[which].filter((sid) => {
      const reason = areas ? misfit(sections.get(sid), layout.side_span, rules) : 'no-sides';
      if (reason !== null) sideMisfits.push([sid, reason]);
      return reason === null;
    });
  });
  const span = contentFor(SIDES.filter((which) => sides[which].length).length, layout);

  // The rows as given, or one per section when only sides were given.
  let given;
  if (isList(content.rows)) {
    given = valuesOf(content.rows).filter(isList);
  } else {
    given = knownList.filter((id) => !placed.has(id)).map((id) => ({ split: '12', columns: [[id]] }));
  }

  // The rows stored before areas ran beside the sides from top to bottom.
  const beside = areas && hadSides && given.every((r) => storedArea(r) === null);

  // The area each given row will be in, so its columns are measured at the
  // width they draw: Top and Bottom at the whole page, Content at what the
  // sides leave.
  const spanOf = given.map(() => span);
  if (areas) {
    const first = given.map((r) => ({ _area: storedArea(r), _inherit: false }));
    areaList(first, mainPlace(given, sides, knownList, sections, rules, layout), beside).forEach((area, i) => {
      spanOf[i] = area === 'content' ? span : 12;
    });
  }

  // Row ids that can stay: well formed, used once, not a section's id.
  const ids = new Map();
  given.forEach((r, i) => {
    const id = Array.isArray(r) ? undefined : r.id;
    if (isString(id) && ID.test(id) && !taken.has(id)) {
      ids.set(i, id);
      taken.add(id);
    }
  });

  // Rows carry `_area` (stored, or null to be worked out) and `_inherit`
  // (split off the row before it) until the areas are known.
  const tag = (r, area, inherit) => Object.assign(r, { _area: area, _inherit: inherit });
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
    const area = areas ? storedArea(r) : null;
    // An empty row with no usable id gets one from a fixed seed, so its id
    // does not depend on where the row sits.
    const seed = [].concat(...columns)[0] ?? 'row';

    if (split === '12') {
      const column = columns[0];
      if (!column.length) return;
      const [first, ...rest] = column;
      rows.push(tag(makeRow(ids.get(i) ?? freshId(first, taken), '12', [[first]], settings), area, false));
      rest.forEach((id) => rows.push(tag(plainRow(id, taken, defaults), null, true)));
      return;
    }

    const id = ids.get(i) ?? freshId(seed, taken);
    const misfits = [];
    columns = columns.map((column, c) => column.filter((sid) => {
      const reason = misfit(sections.get(sid), parts[c], rules, spanOf[i]);
      if (reason !== null) misfits.push([sid, reason]);
      return reason === null;
    }));
    rows.push(tag(makeRow(id, split, columns, settings), area, false));
    if (misfits.length && columns.every((column) => column.length === 0)) emptied.add(id);
    misfits.forEach(([sid, reason]) => {
      const plain = tag(plainRow(sid, taken, defaults), null, true);
      rows.push(plain);
      moved.push(movedEntry(sid, reason, plain.id));
    });
  });

  // Sides: what cannot sit in one goes to its own row at the end.
  sideMisfits.forEach(([sid, reason]) => {
    const plain = tag(plainRow(sid, taken, defaults), null, false);
    rows.push(plain);
    moved.push(movedEntry(sid, reason, plain.id));
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
    rows.splice(at, 0, tag(plainRow(sid, taken, defaults), null, false));
    placed.add(sid);
  });

  if (areas) {
    const main = mainRow(rows, sections, rules);
    areaList(rows, main ?? Infinity, beside).forEach((area, index) => { rows[index]._area = area; });
    if (main !== null) moved = moved.concat(fitContent(rows, main, sections, rules, span, layout));
    const into = contentOnlyIntoContent(rows, emptied, sections, rules, taken, defaults);
    rows = into.rows;
    moved = moved.concat(into.moved);
  }

  rows = fitRowLimit(rows, emptied, layout.max_rows);

  // Top, then Content between the sides, then Bottom.
  const order = [];
  (areas ? AREAS : ['content']).forEach((area) => {
    if (area === 'content') order.push(...sides.left);
    rows.forEach((r) => { if (!areas || r._area === area) r.columns.forEach((column) => order.push(...column)); });
    if (area === 'content') order.push(...sides.right);
  });

  const keptSides = {};
  SIDES.forEach((which) => { if (sides[which].length) keptSides[which] = sides[which]; });

  const bare = rows.map((r) => makeRow(r.id, r.split, r.columns, r.settings));
  let plainOnly = bare.every((r) => r.split === '12' && same(r.settings, defaults))
    && same(bare, impliedRows({ order, sections: content.sections ?? [], sides: keptSides }, defaults));
  let out_ = bare;
  if (areas) {
    plainOnly = plainOnly && !Object.keys(keptSides).length && areasArePlaces(rows, mainRow(rows, sections, rules));
    out_ = rows.map((r) => withArea(r, r._area));
  }

  const out = { ...content };
  out.order = order;
  delete out.rows;
  delete out.sides;
  if (!plainOnly) out.rows = out_;
  if (Object.keys(keptSides).length) out.sides = keptSides;

  return { content: out, moved };
}


/**
 * Rules that accept every placement: for changes that move nothing out of
 * place. Given a page's rules, they keep its main section and its areas.
 */
function acceptingRules(given) {
  if (isRules(given)) return { ...given, minSpan: () => 0, ownRow: () => false, contentOnly: () => false };
  return { layout: layoutOf(given), minSpan: () => 0, isMain: () => false, sidesAllowed: true };
}

/**
 * The document normalized without moving anything: ids that name no
 * section are dropped, sections in no row get one after the row of the
 * section before them, `order` is derived again. Used after a section is
 * added or removed on a page with rows. Given the page's rules (not only
 * the layout), a page with areas keeps them.
 */
export function tidy(content, rulesOrLayout) {
  return normalize(content, acceptingRules(rulesOrLayout)).content;
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
   Spots of the board
   ------------------------------------------------------------------------ */

/**
 * The gaps between rows, in board order: on a page with areas a gap
 * before each row of an area and one at its end (an empty area has only
 * its end); on a page without, a gap before each row and one at the end.
 * `rows` carry their area (rowsOf with rules).
 */
export function gapTargets(rows) {
  if (!rows.some((r) => r.area)) return [...rows.map((r) => ({ drop: 'gap', area: null, before: r.id })), { drop: 'gap', area: null, before: 'end' }];
  const out = [];
  AREAS.forEach((area) => {
    rows.filter((r) => r.area === area).forEach((r) => out.push({ drop: 'gap', area, before: r.id }));
    out.push({ drop: 'gap', area, before: 'end' });
  });
  return out;
}

/**
 * Where a gap is among the rows: `{index, area}`, index -1 when its row is
 * gone. On a page with areas `'end'` is the end of the gap's area, and a
 * gap without an area takes the one of the row it is before (`'end'`: the
 * end of Bottom). `rows` carry their area (rowsOf with rules).
 */
export function gapAt(rows, spot) {
  const before = spot?.before ?? 'end';
  if (!rows.some((r) => r.area)) {
    return { index: before === 'end' ? rows.length : rows.findIndex((r) => r.id === before), area: null };
  }
  const own = before === 'end' ? null : rows.find((r) => r.id === before);
  const area = AREAS.includes(spot?.area) ? spot.area : (own?.area ?? (before === 'end' ? 'bottom' : null));
  if (!area) return { index: -1, area: null };
  if (before === 'end') return { index: rows.filter((r) => RANK[r.area] <= RANK[area]).length, area };
  return { index: own && own.area === area ? rows.indexOf(own) : -1, area };
}

/**
 * The area a spot is in: a gap's own, a column's or a section edge's row
 * area, Content for a side; null on a page without areas and for a band.
 */
export function areaAt(content, spot, rules) {
  if (!spot || spot.drop === 'band' || !hasAreas(content, rules)) return null;
  if (spot.drop === 'side') return 'content';
  const rows = rowsOf(content, rules.layout, rules);
  if (spot.drop === 'gap') return gapAt(rows, spot).area;
  return rows.find((r) => r.id === spot.rowId)?.area ?? null;
}

/**
 * A section's own place as a spot: its place in its side or column, or
 * the gap its full-width row stands in. Null when it is not on the page.
 */
export function placeOf(content, sid, rules) {
  const layout = layoutOf(rules.layout);
  const at = locate(content, sid, layout);
  if (!at) return null;
  if (at.where === 'side') return { drop: 'side', side: at.side, before: sidesOf(content)[at.side][at.index + 1] ?? null };
  const r = rowsOf(content, layout, rules).find((x) => x.id === at.rowId);
  if (!r) return null;
  if ((partsOf(layout, r.split)?.length || 1) > 1) return { drop: 'column', rowId: r.id, column: at.column, before: r.columns[at.column][at.index + 1] ?? null };
  return { drop: 'gap', area: r.area ?? null, before: r.id };
}

/**
 * How many sections of a type are counted against its limit at a spot:
 * for a type whose limit is per area, on a page with areas, those in the
 * spot's area (the sides count as Content); otherwise those in the whole
 * document, a band being a document of its own.
 */
export function countInPlace(content, type, spot, rules) {
  const sections = sectionMap(content);
  const ofType = valuesOf(isList(content?.order) ? content.order : []).filter((id) => isString(id) && sections.get(id)?.type === type);
  const area = ask(rules, 'limitPerArea', { type }) ? areaAt(content, spot, rules) : null;
  if (!area) return ofType.length;
  const where = new Map();
  rowsOf(content, rules.layout, rules).forEach((r) => r.columns.flat().forEach((sid) => where.set(sid, r.area)));
  SIDES.forEach((which) => sidesOf(content)[which].forEach((sid) => where.set(sid, 'content')));
  return ofType.filter((id) => where.get(id) === area).length;
}

/** How wide a row's columns are measured: its part of Content, or of the whole page in Top and Bottom. */
function widthOfRow(content, r, rules, sid) {
  return r?.area && r.area !== 'content' ? 12 : measuredContent(content, rules, sid);
}

/**
 * How wide a spot really is, in twelfths of the page: a column its part
 * of its area (so 8 beside one side is 6, and may be a fraction), a side
 * 3, a gap in Content what the sides leave, any other gap a whole row.
 * `sid`, when given, is the section that would move there: a side it
 * leaves empty no longer narrows the page.
 */
export function spanAt(content, target, rules, sid = null) {
  const layout = layoutOf(rules.layout);
  if (target?.drop === 'side') return layout.side_span;
  const rows = rowsOf(content, layout, rules);
  if (target?.drop === 'gap') return gapAt(rows, target).area === 'content' ? measuredContent(content, rules, sid) : 12;
  const r = rows.find((x) => x.id === target?.rowId);
  const width = widthOfRow(content, r, rules, sid);
  if (target?.drop === 'beside') return (6 * width) / 12;
  if (target?.drop === 'column') {
    const part = (r && partsOf(layout, r.split)?.[target.column]) || 0;
    return (part * width) / 12;
  }
  return 0;
}

/**
 * Whether the section may go there, as the server would keep it there:
 * - A locked section (the page content) fits nowhere: it never moves.
 * - A section that keeps a row of its own fits only a gap.
 * - A section that belongs in Content fits only Content's gaps, columns
 *   and section edges, and the sides.
 * - A section fits a Content gap only when its span fits the content width.
 * - A column, side or section edge must have room and be wide enough.
 * - Never onto its own place.
 */
export function fits(content, sid, target, rules) {
  const layout = layoutOf(rules.layout);
  const sections = sectionMap(content);
  const section = sections.get(sid);
  if (!section || !target || lockedBy(rules, section)) return false;
  const ownRow = ownRowBy(rules, section);
  const contentOnly = contentOnlyBy(rules, section);
  const areas = hasAreasIn(sections, rules);
  const span = rules.minSpan(section);
  const rows = rowsOf(content, layout, rules);
  const at = locate(content, sid, layout);
  const own = at?.where === 'row' ? rows.find((r) => r.id === at.rowId) : null;

  if (target.drop === 'gap') {
    const { index, area } = gapAt(rows, target);
    if (index < 0) return false;
    if (own && own.split === '12' && (own.area ?? null) === area) {
      const ownIndex = rows.indexOf(own);
      if (index === ownIndex || index === ownIndex + 1) return false;
    }
    if (!areas) return true;
    if (contentOnly) return area === 'content';
    return area !== 'content' || span <= measuredContent(content, rules, sid);
  }

  if (ownRow || target.drop === 'band') return false;

  if (target.drop === 'column') {
    const r = rows.find((x) => x.id === target.rowId);
    const parts = r ? partsOf(layout, r.split) : null;
    if (!parts || parts.length < 2 || !Number.isInteger(target.column) || target.column < 0 || target.column >= parts.length) return false;
    if (areas && contentOnly && r.area !== 'content') return false;
    const column = r.columns[target.column] || [];
    const before = target.before ?? null;
    if (before === sid || (before !== null && !column.includes(before))) return false;
    const here = at?.where === 'row' && at.rowId === r.id && at.column === target.column;
    if (here && (column[at.index + 1] ?? null) === before) return false;
    if (!here && column.length >= layout.max_per_column) return false;
    return fitsPart(span, parts[target.column], widthOfRow(content, r, rules, sid));
  }

  if (target.drop === 'beside') {
    const r = rows.find((x) => x.id === target.rowId);
    if (!r || r.split !== '12' || r.columns[0]?.length !== 1 || !SIDES.includes(target.side)) return false;
    if (areas && contentOnly && r.area !== 'content') return false;
    const other = r.columns[0][0];
    const otherSection = sections.get(other);
    if (other === sid || !otherSection || rules.isMain(otherSection) || lockedBy(rules, otherSection) || ownRowBy(rules, otherSection)) return false;
    const width = widthOfRow(content, r, rules, sid);
    return fitsPart(span, 6, width) && fitsPart(rules.minSpan(otherSection), 6, width) && partsOf(layout, '6-6') !== null;
  }

  if (target.drop === 'side') {
    if (!areas || !SIDES.includes(target.side)) return false;
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
 * Whether a row of columns may move to a gap, as the server would keep it
 * there: never with a locked section in it, never out of Content with a
 * section that belongs in Content, and into Content only when each of its
 * sections fits its column at the content width. Never onto its own place.
 */
export function rowFits(content, rowId, target, rules) {
  const layout = layoutOf(rules.layout);
  const sections = sectionMap(content);
  const rows = rowsOf(content, layout, rules);
  const index = rows.findIndex((r) => r.id === rowId);
  if (index < 0 || target?.drop !== 'gap') return false;
  const r = rows[index];
  const rest = rows.filter((x) => x.id !== rowId);
  const { index: to, area } = gapAt(rest, target);
  if (to < 0 || ((r.area ?? null) === area && to === index)) return false;
  const ids = r.columns.flat();
  if (ids.some((sid) => lockedBy(rules, sections.get(sid)))) return false;
  if (!hasAreasIn(sections, rules)) return true;
  if (area !== 'content') return !ids.some((sid) => contentOnlyBy(rules, sections.get(sid)));
  const parts = partsOf(layout, r.split) || [12];
  const width = measuredContent(content, rules);
  return r.columns.every((column, c) => column.every((sid) => {
    const section = sections.get(sid);
    return contentOnlyBy(rules, section) || fitsPart(rules.minSpan(section), parts[c] ?? 12, width);
  }));
}

/**
 * Whether a new section may be added at a spot of the board, checked
 * against the page as it is now: the board can change while the section
 * picker is open (an undo, a save from another tab). Answers `{ok: true}`,
 * or `{ok: false, reason}` with reason 'gone' (the spot is no longer on the
 * page), 'full' (its column or side holds as many as it can), 'narrow'
 * (the section needs a wider spot), 'own-row' (it keeps a row of its own)
 * or 'content-only' (it goes beside the page content only). A spot that is
 * gone is refused, never swapped for another place. For a band spot
 * `content` is the band's own document.
 */
export function checkAddSpot(content, target, section, rules) {
  const layout = layoutOf(rules.layout);
  const sections = sectionMap(content);
  const rows = rowsOf(content, layout, rules);
  const areas = hasAreasIn(sections, rules);
  const span = rules.minSpan(section);
  const ownRow = ownRowBy(rules, section);
  const contentOnly = areas && contentOnlyBy(rules, section);
  const no = (reason) => ({ ok: false, reason });
  if (target?.drop === 'band') {
    const order = valuesOf(isList(content?.order) ? content.order : []);
    return (target.before ?? null) === null || order.includes(target.before) ? { ok: true } : no('gone');
  }
  if (target?.drop === 'gap') {
    const { index, area } = gapAt(rows, target);
    if (index < 0) return no('gone');
    if (!areas) return { ok: true };
    if (contentOnly) return area === 'content' ? { ok: true } : no('content-only');
    return area !== 'content' || span <= measuredContent(content, rules) ? { ok: true } : no('narrow');
  }
  if (target?.drop === 'column') {
    const r = rows.find((x) => x.id === target.rowId);
    const parts = r ? partsOf(layout, r.split) : null;
    if (!parts || parts.length < 2 || !Number.isInteger(target.column) || target.column < 0 || target.column >= parts.length) return no('gone');
    const column = r.columns[target.column] || [];
    if ((target.before ?? null) !== null && !column.includes(target.before)) return no('gone');
    if (ownRow) return no('own-row');
    if (contentOnly && r.area !== 'content') return no('content-only');
    if (column.length >= layout.max_per_column) return no('full');
    return fitsPart(span, parts[target.column], widthOfRow(content, r, rules)) ? { ok: true } : no('narrow');
  }
  if (target?.drop === 'beside') {
    const r = rows.find((x) => x.id === target.rowId);
    const other = r?.split === '12' && r.columns[0]?.length === 1 ? sections.get(r.columns[0][0]) : null;
    if (!other || rules.isMain(other) || lockedBy(rules, other) || !SIDES.includes(target.side)) return no('gone');
    if (ownRow || ownRowBy(rules, other)) return no('own-row');
    if (contentOnly && r.area !== 'content') return no('content-only');
    const width = widthOfRow(content, r, rules);
    return fitsPart(span, 6, width) && fitsPart(rules.minSpan(other), 6, width) ? { ok: true } : no('narrow');
  }
  if (target?.drop === 'side') {
    if (!hasAreasIn(sections, rules) || !SIDES.includes(target.side)) return no('gone');
    const list = sidesOf(content)[target.side];
    if ((target.before ?? null) !== null && !list.includes(target.before)) return no('gone');
    if (ownRow) return no('own-row');
    if (list.length >= layout.max_per_side) return no('full');
    return span <= layout.side_span ? { ok: true } : no('narrow');
  }
  return no('gone');
}

/** The section ids of `moved` that went to rows of their own, leaving out `sid`; moves out of Content are named by effectsOf(). */
const rowMoves = (moved, sid = null) => moved
  .filter((m) => !AREAS.includes(m.value))
  .map((m) => m.path.replace(/^content\.sections\./, ''))
  .filter((id) => id !== sid);

/**
 * Moves a section to the target. Answers the document unchanged when the
 * section does not fit there, the ids of the rows the move made, and the
 * other sections it moved to rows of their own. A gap's row is stored in
 * the gap's area.
 */
export function place(content, sid, target, rules) {
  if (!fits(content, sid, target, rules)) return { content, created: [], moved: [] };
  const layout = layoutOf(rules.layout);
  const all = rowsOf(content, layout, rules);
  const rows = all.map((r) => ({ ...r, columns: r.columns.map((column) => column.filter((id) => id !== sid)) }));
  const sides = sidesOf(content);
  SIDES.forEach((which) => { sides[which] = sides[which].filter((id) => id !== sid); });

  if (target.drop === 'gap') {
    // A one-column row emptied by the move is gone after normalizing, so its
    // id is free again; an empty row of columns stays and keeps its id.
    const taken = takenIds(content, rows.filter((r) => r.split !== '12' || r.columns.some((column) => column.length)));
    const { index, area } = gapAt(all, target);
    rows.splice(index, 0, withArea(makeRow(freshId(sid, taken), '12', [[sid]], { ...layout.row_defaults }), area));
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

  // A side filled for the first time narrows Content: a section too wide
  // for its column then moves to a row of its own, and is named in `moved`;
  // a row too wide for Content leaves it, as effectsOf() tells.
  const result = withLayout(content, rows, sides, rules);
  return { content: result.content, created: createdBetween(content, result.content, layout), moved: rowMoves(result.moved, sid) };
}

/**
 * How wide a section's own place really is, in twelfths of the page: its
 * column's part of its area, its side, a full-width row in Content what
 * the sides leave (but 12 for the page content and a section that belongs
 * in Content, which Content never measures), any other full-width row 12;
 * null when it is not on the page.
 */
export function roomOf(content, sid, rules) {
  const layout = layoutOf(rules.layout);
  const at = locate(content, sid, layout);
  if (!at) return null;
  if (at.where === 'side') return layout.side_span;
  const r = rowsOf(content, layout, rules).find((x) => x.id === at.rowId);
  const parts = r ? partsOf(layout, r.split) : null;
  const width = widthOfRow(content, r, rules);
  if (!parts || parts.length < 2) {
    // The page content and a section that belongs in Content are never measured there.
    const section = sectionMap(content).get(sid);
    return r?.area === 'content' && !rules.isMain(section) && !contentOnlyBy(rules, section) ? width : 12;
  }
  return (parts[at.column] * width) / 12;
}

/**
 * Whether a section stands in a column of a row of two or more, or in a
 * side: a place it shares with others, where a choice that needs a row of
 * its own (Edge to edge) cannot be honored. False when it is not on the page.
 */
export function sharesRow(content, sid, rules) {
  const layout = layoutOf(rules.layout);
  const at = locate(content, sid, layout);
  if (!at) return false;
  if (at.where === 'side') return true;
  const r = rowsOf(content, layout).find((x) => x.id === at.rowId);
  const parts = r ? partsOf(layout, r.split) : null;
  return Boolean(parts && parts.length > 1);
}

/**
 * Puts another section in a section's place: the same row, column or
 * side and the same position in it, under the new id. Answers null, and
 * changes nothing, when the old one is not on the page or is locked, the
 * new id is taken, or the new section does not fit the place (too wide
 * for it, locked, one that keeps a row of its own in a shared place, or
 * one that belongs in Content in Top or Bottom). Rows, sides and order
 * keep their shape and key order, so nothing else moves.
 */
export function replaceIn(content, sid, newSid, section, rules) {
  const sections = sectionMap(content);
  if (!sections.has(sid) || !isString(newSid) || newSid === '' || sections.has(newSid)) return null;
  const old = sections.get(sid);
  if (rules.isMain(old) || rules.isMain(section) || lockedBy(rules, old) || lockedBy(rules, section)) return null;
  const room = roomOf(content, sid, rules);
  if (room === null || rules.minSpan(section) > room) return null;
  if (ownRowBy(rules, section) && sharesRow(content, sid, rules)) return null;
  if (contentOnlyBy(rules, section) && hasAreasIn(sections, rules) && areaAt(content, placeOf(content, sid, rules), rules) !== 'content') return null;

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
    } else if (key === 'sides' && isMap(content.sides)) {
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

/** A gap given as a spot, or as the id of the row it is before (`'end'`). */
const asGap = (before) => (isMap(before) ? before : { drop: 'gap', area: null, before: before ?? 'end' });

/**
 * Adds an empty row of columns at a gap (a spot, or the id of the row it
 * goes before, or 'end'), in the gap's area. An empty one-column row says
 * nothing, so it is not added. `rulesOrLayout`: the page's rules keep its
 * areas.
 */
export function addRow(content, split, before, rowId, rulesOrLayout) {
  const l = layoutOf(rulesOrLayout);
  const parts = partsOf(l, split);
  const rows = rowsOf(content, l, rulesOrLayout);
  const { index, area } = gapAt(rows, asGap(before));
  if (!parts || parts.length < 2 || index < 0 || rows.length >= l.max_rows) return content;
  if (!isString(rowId) || !ID.test(rowId) || takenIds(content, rows).has(rowId)) return content;
  rows.splice(index, 0, withArea(makeRow(rowId, split, parts.map(() => []), { ...l.row_defaults }), area));
  return withLayout(content, rows, sidesOf(content), acceptingRules(rulesOrLayout)).content;
}

/** Moves a row to a gap (a spot, or the id of the row it goes before, or 'end'), into the gap's area. */
export function moveRow(content, rowId, before, rulesOrLayout) {
  const l = layoutOf(rulesOrLayout);
  const rows = rowsOf(content, l, rulesOrLayout);
  const gap = asGap(before);
  const from = rows.findIndex((r) => r.id === rowId);
  if (from < 0 || gap.before === rowId) return content;
  const [moving] = rows.splice(from, 1);
  const { index, area } = gapAt(rows, gap);
  if (index < 0) return content;
  rows.splice(index, 0, withArea(moving, area));
  return withLayout(content, rows, sidesOf(content), acceptingRules(rulesOrLayout)).content;
}

/** Removes a row; each of its sections gets a full-width row where the row was, in reading order and its area. */
export function removeRow(content, rowId, rulesOrLayout) {
  const l = layoutOf(rulesOrLayout);
  const rows = rowsOf(content, l, rulesOrLayout);
  const index = rows.findIndex((r) => r.id === rowId);
  if (index < 0) return { content, created: [] };
  const [gone] = rows.splice(index, 1);
  const taken = takenIds(content, rows);
  const plain = [].concat(...gone.columns).map((sid) => withArea(plainRow(sid, taken, l.row_defaults), gone.area));
  rows.splice(index, 0, ...plain);
  const next = withLayout(content, rows, sidesOf(content), acceptingRules(rulesOrLayout)).content;
  return { content: next, created: createdBetween(content, next, l) };
}

/**
 * Gives a row another split. Extra columns pour into the last one; a
 * column over its limit and sections too wide for their new column move to
 * full-width rows below, in the row's area; one column (`12`) makes a
 * full-width row per section, with no row settings.
 */
export function changeSplit(content, rowId, split, rules) {
  const layout = layoutOf(rules.layout);
  const parts = partsOf(layout, split);
  const rows = rowsOf(content, layout, rules);
  const index = rows.findIndex((r) => r.id === rowId);
  if (!parts || index < 0 || rows[index].split === split) return { content, moved: [], created: [] };

  const r = rows[index];
  const columns = fitColumns(r.columns, parts.length);
  const overflow = [];
  if (parts.length > 1) {
    columns.forEach((column) => overflow.push(...column.splice(layout.max_per_column)));
  }
  rows[index] = withArea(makeRow(r.id, split, columns, parts.length > 1 ? r.settings : { ...layout.row_defaults }), r.area);
  const taken = takenIds(content, rows);
  rows.splice(index + 1, 0, ...overflow.map((sid) => withArea(plainRow(sid, taken, layout.row_defaults), r.area)));

  const result = withLayout(content, rows, sidesOf(content), rules);
  return { content: result.content, moved: [...rowMoves(result.moved), ...overflow], created: createdBetween(content, result.content, layout) };
}

/** Sets one of a row's settings (a key of the row defaults) to a text value. */
export function setRow(content, rowId, key, value, rulesOrLayout) {
  const l = layoutOf(rulesOrLayout);
  if (!Object.hasOwn(l.row_defaults, key) || !isString(value)) return content;
  const rows = rowsOf(content, l, rulesOrLayout);
  const r = rows.find((x) => x.id === rowId);
  if (!r || r.settings[key] === value) return content;
  r.settings = { ...r.settings, [key]: value };
  return withLayout(content, rows, sidesOf(content), acceptingRules(rulesOrLayout)).content;
}

/* ------------------------------------------------------------------------
   What a change redraws
   ------------------------------------------------------------------------ */

/**
 * Each section's width on the page, in twelfths: a side's 3, a column's
 * part of its area, a full-width row its area. Every side that holds a
 * section counts, as the editor's preview draws it; given the page's
 * rules, Top and Bottom are the whole page wide.
 */
function widths(content, layout, rules) {
  const out = new Map();
  const sides = sidesOf(content);
  const area = contentFor(SIDES.filter((which) => sides[which].length).length, layout);
  SIDES.forEach((which) => sides[which].forEach((sid) => out.set(sid, layout.side_span)));
  rowsOf(content, layout, rules).forEach((r) => {
    const parts = partsOf(layout, r.split) || [12];
    const width = r.area && r.area !== 'content' ? 12 : area;
    r.columns.forEach((column, c) => column.forEach((sid) => out.set(sid, ((parts[c] ?? 12) * width) / 12)));
  });
  return out;
}

/**
 * What changed between two versions of a document's layout: the board,
 * the multi-column rows whose content changed, the sections whose width
 * changed, and, given the page's rules, the sections that left Content
 * (`left`, `[{sid, area}]` with the area each went to): a side filled
 * for the first time takes the rows too wide for Content out of it, with
 * the rows between them and its edge, and the board says which.
 */
export function effectsOf(before, after, rulesOrLayout) {
  const l = layoutOf(rulesOrLayout);
  const rules = isRules(rulesOrLayout) ? rulesOrLayout : null;
  const was = new Map(rowsOf(before, l, rules).map((r) => [r.id, JSON.stringify(r)]));
  const now = rowsOf(after, l, rules);
  const rows = now
    .filter((r) => (partsOf(l, r.split)?.length || 1) > 1 && was.get(r.id) !== JSON.stringify(r))
    .map((r) => r.id);
  const wide = widths(before, l, rules);
  const sections = [...widths(after, l, rules)].filter(([sid, span]) => wide.get(sid) !== span).map(([sid]) => sid);

  const left = [];
  if (rules) {
    const areaNow = new Map();
    now.forEach((r) => r.columns.flat().forEach((sid) => areaNow.set(sid, r.area)));
    rowsOf(before, l, rules).forEach((r) => {
      if (r.area !== 'content') return;
      r.columns.flat().forEach((sid) => {
        const area = areaNow.get(sid);
        if (area === 'top' || area === 'bottom') left.push({ sid, area });
      });
    });
  }
  return { board: true, rows, sections, left };
}

/**
 * The layout rules for a document from the section catalog: a type the
 * catalog does not know counts as the narrowest span, so it keeps its
 * place; a variant may ask for its own span. Locked, own-row, content-only
 * and per-area limits are read from the boot payload's section entries.
 */
export function catalogRules(layout, sectionDef, sidesAllowed) {
  const def = (section) => sectionDef(section?.type);
  return {
    layout,
    minSpan(section) {
      const d = def(section);
      if (!d) return layout.side_span;
      const span = d.span || {};
      const variantSetting = (d.settings || []).find((s) => s.key === 'variant');
      const settings = isList(section?.settings) ? section.settings : {};
      const variant = settings.variant ?? variantSetting?.default;
      if (isString(variant) && span.variants && Object.hasOwn(span.variants, variant)) return span.variants[variant];
      return Number.isInteger(span.min) ? span.min : 12;
    },
    isMain: (section) => def(section)?.main === true,
    isLocked: (section) => isLocked(def(section)),
    ownRow: (section) => def(section)?.own_row === true,
    contentOnly: (section) => def(section)?.content_only === true,
    limitPerArea: (section) => def(section)?.limit_per_area === true,
    known: (section) => Boolean(def(section)),
    sidesAllowed: Boolean(sidesAllowed),
  };
}
