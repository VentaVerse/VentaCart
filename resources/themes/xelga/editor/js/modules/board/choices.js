/*
 * What the section picker offers at a spot of the board, as plain data:
 * the section types in their groups, each with its starting layouts, and
 * whether each one can go there and why not. Also what a section holds
 * that a replacement would lose. Nothing here touches the DOM or the
 * editor's state, so the board, the add panel and the tests read the same
 * answers.
 *
 * A type's layouts are its presets (named starting points), else its
 * variants, else the type itself. A type whose narrowest layout is wider
 * than the spot, or that is at its limit on the page, stays offered but
 * greyed, with the reason; so does each layout that is too wide.
 */

import { spanAt } from './model.js';

/** Why a type cannot be added at a spot. */
export const ALREADY_THERE = 'Already on this page';
export const TOO_WIDE = 'Need a wider spot';
export const NOW_HERE = 'Now here';

/** The picker's groups of the theme's own sections, in order; a schema names its group in `picker_group`. */
export const GROUPS = [
  { id: 'banners', name: 'Banners and photos' },
  { id: 'products', name: 'Products' },
  { id: 'categories', name: 'Categories and brands' },
  { id: 'text', name: 'Text and trust' },
];

/** The group of a type the schema does not place: text and trust. */
export const DEFAULT_GROUP = 'text';

/** The tile that adds an empty row of columns, offered at a gap between rows only. */
export const ROW_TILE = 'row';

const FULL_WIDTH = { drop: 'gap', before: 'end' };

/** The narrowest spot a span needs, in words. */
export function needsWords(span) {
  if (span >= 12) return 'Needs full width';
  if (span >= 8) return 'Needs two thirds';
  if (span >= 6) return 'Needs half or more';
  return 'Needs a third or more';
}

/**
 * The layouts a type is added with, each `{label, variant, preset}`: its
 * presets, else its variants, else the type alone.
 */
export function choicesOf(def) {
  if (def?.presets?.length) {
    return def.presets.map((p) => ({ label: p.label, variant: p.settings?.variant ?? null, preset: p }));
  }
  if (def?.variants?.length) return def.variants.map((v) => ({ label: v.label, variant: v.value, preset: null }));
  return [{ label: def?.label || '', variant: null, preset: null }];
}

const spanOf = (type, variant, rules) => rules.minSpan({ type, settings: variant == null ? {} : { variant } });

const countOf = (content, type) => (Array.isArray(content?.order) ? content.order : [])
  .filter((id) => content.sections?.[id]?.type === type).length;

/**
 * Each section type of `catalog` (type => definition, already limited to
 * the types this page may hold) as `{type, disabled, reason?, need?}` for
 * a spot: a type at its limit on the page is disabled as already there,
 * one whose narrowest layout is wider than the spot as too wide (`need`
 * is that layout's span). Main sections are never added, so they are left
 * out. `now`, in replace mode, is the type in the spot now.
 */
export function addableTypes(content, target, catalog, rules, { span = null, now = null } = {}) {
  const room = span ?? spanAt(content, target || FULL_WIDTH, rules);
  return Object.entries(catalog || {}).filter(([, def]) => def && !def.main).map(([type, def]) => {
    if (type === now) return { type, disabled: true, reason: NOW_HERE };
    if (def.limit && countOf(content, type) >= def.limit) return { type, disabled: true, reason: ALREADY_THERE };
    const narrowest = Math.min(...choicesOf(def).map((c) => spanOf(type, c.variant, rules)));
    if (narrowest > room) return { type, disabled: true, reason: TOO_WIDE, need: narrowest };
    return { type, disabled: false };
  });
}

/** The words on a greyed tile: why it cannot go here. */
export function pillWords(state) {
  if (!state?.disabled) return null;
  if (state.reason === TOO_WIDE) return needsWords(state.need);
  return state.reason;
}

/**
 * The tiles of the picker, in groups: `[{id, name, types: [state]}]`. The
 * theme's own types sit in the four groups by their `picker_order`, then in
 * catalog order, a type with
 * no group under text and trust; an extension's types come after, in a
 * group named after the extension. Greyed types keep their place, so a
 * type is always found where it was. Empty groups are left out.
 */
export function pickerGroups(states, catalog) {
  const own = new Map(GROUPS.map((g) => [g.id, { ...g, types: [] }]));
  const extensions = new Map();
  states.forEach((state) => {
    const def = catalog[state.type];
    if (def?.source) {
      const name = def.source_label || def.source;
      if (!extensions.has(def.source)) extensions.set(def.source, { id: `extension-${def.source}`, name, types: [] });
      extensions.get(def.source).types.push(state);
      return;
    }
    const group = own.get(def?.picker_group) || own.get(DEFAULT_GROUP);
    group.types.push(state);
  });
  const theirs = [...extensions.values()].sort((a, b) => a.name.localeCompare(b.name));
  // Within a group, by `picker_order` (the most used first); types without one follow in catalog order.
  const rank = (state) => {
    const order = catalog[state.type]?.picker_order;
    return Number.isInteger(order) && order > 0 ? order : Infinity;
  };
  const groups = [...own.values(), ...theirs].filter((g) => g.types.length);
  groups.forEach((g) => {
    g.types = g.types.map((state, i) => [state, i]).sort(([a, i], [b, j]) => (rank(a) - rank(b)) || (i - j)).map(([state]) => state);
  });
  return groups;
}

/** Whether a type answers a search: its name or the name of one of its layouts. */
export function matches(def, query) {
  const q = String(query || '').trim().toLowerCase();
  if (!q) return true;
  if (String(def?.label || '').toLowerCase().includes(q)) return true;
  return choicesOf(def).some((c) => String(c.label || '').toLowerCase().includes(q));
}

/** Each layout of a type with whether it fits a spot `span` twelfths wide, and what it needs when not. */
export function layoutsFor(type, def, span, rules) {
  return choicesOf(def).map((c) => {
    const need = spanOf(type, c.variant, rules);
    return { ...c, fits: need <= span, need };
  });
}

/* ------------------------------------------------------------------------
   What a replacement loses
   ------------------------------------------------------------------------ */

const TEXT_TYPES = new Set(['text', 'textarea', 'richtext']);
const RECORD_WORDS = {
  'product-picker': 'chosen products',
  'category-picker': 'chosen categories',
  'brand-picker': 'chosen brands',
  'blog-post-picker': 'chosen posts',
  'page-picker': 'chosen pages',
  'menu-picker': 'chosen menu',
};

const filled = (value) => (Array.isArray(value) ? value.length > 0 : value !== null && value !== undefined && value !== '' && value !== false);

/**
 * What the merchant put in a section that a replacement loses, as the
 * words of a sentence ("3 photos and their text", "chosen products"), or
 * null when it holds nothing of its own: photos, text other than the
 * schema's own, and chosen records, in the section and its blocks.
 */
export function replacedWords(section, def) {
  if (!section || !def) return null;
  let photos = 0;
  let text = false;
  const records = [];
  const read = (settings, specs) => {
    (specs || []).forEach((spec) => {
      const value = settings?.[spec.key];
      if (!filled(value)) return;
      if (spec.type === 'image') photos += 1;
      else if (TEXT_TYPES.has(spec.type) && String(value).trim() !== '' && value !== spec.default) text = true;
      else if (RECORD_WORDS[spec.type] && !records.includes(RECORD_WORDS[spec.type])) records.push(RECORD_WORDS[spec.type]);
    });
  };
  read(section.settings, def.settings);
  const blockTypes = def.blocks?.types || [];
  (section.block_order || Object.keys(section.blocks || {})).forEach((bid) => {
    const block = section.blocks?.[bid];
    if (!block) return;
    read(block.settings, blockTypes.find((t) => t.type === block.type)?.settings);
  });
  const parts = [];
  if (photos === 1) parts.push('photo');
  else if (photos > 1) parts.push(`${photos} photos`);
  if (text) parts.push(photos === 0 ? 'text' : photos === 1 ? 'its text' : 'their text');
  parts.push(...records);
  if (!parts.length) return null;
  if (parts.length === 1) return parts[0];
  return `${parts.slice(0, -1).join(', ')} and ${parts[parts.length - 1]}`;
}

/** The section-level color scheme setting of a type, or null. */
export const schemeSetting = (def) => (def?.settings || []).find((s) => s.type === 'color-scheme') || null;
