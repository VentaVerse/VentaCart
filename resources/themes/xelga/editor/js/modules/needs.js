/*
 * Sections that wait for something to be picked. A starting store names its
 * categories and products; those this catalog does not have are left empty,
 * and so is a picker nobody has filled yet. Such a section keeps its place
 * and settings, and the board and its blocks list mark it ("Pick a
 * category") until something is picked.
 */

import { S, ctx, GROUPS, groupList, blockList, valueOf, isFixed } from './state.js';

const LABELS = {
  category: 'Pick a category',
  products: 'Pick products',
  product: 'Pick a product',
  brand: 'Pick a brand',
  none: 'No products yet',
};

const isEmpty = (v) => v === null || v === undefined || v === '' || (Array.isArray(v) && v.length === 0);

/**
 * What one section waits for: { need, blocks } with blocks a map of block
 * id to its own tag, or null when it has what it needs.
 */
function needOf(s) {
  const blocksMissing = (key, label) => {
    const blocks = new Map();
    const shown = blockList(s.id).filter((b) => !b.disabled);
    shown.forEach((b) => { if (isEmpty(valueOf(s.id, b.id, key))) blocks.set(b.id, label); });
    return { blocks, all: shown.length > 0 && blocks.size === shown.length };
  };

  switch (s.type) {
    case 'category-tiles': {
      const { blocks, all } = blocksMissing('category', LABELS.category);
      return blocks.size ? { need: all ? 'category' : null, blocks } : null;
    }
    case 'shop-the-look': {
      const { blocks, all } = blocksMissing('product', LABELS.product);
      return blocks.size ? { need: all ? 'products' : null, blocks } : null;
    }
    case 'product-spotlight':
      return isEmpty(valueOf(s.id, null, 'product')) ? { need: 'product', blocks: new Map() } : null;
    case 'featured-products': {
      const source = valueOf(s.id, null, 'source');
      if (source === 'hand-picked' && isEmpty(valueOf(s.id, null, 'products'))) return { need: 'products', blocks: new Map() };
      if (source === 'category' && isEmpty(valueOf(s.id, null, 'category'))) return { need: 'category', blocks: new Map() };
      if (source === 'brand' && isEmpty(valueOf(s.id, null, 'brand'))) return { need: 'brand', blocks: new Map() };
      if (ctx.boot?.products_exist === false) return { need: 'none', blocks: new Map() };
      return null;
    }
    default:
      return null;
  }
}

/** Every section of the page open now that waits for a pick, by id. */
export function computeNeeds() {
  const out = new Map();
  if (!S.loaded || isFixed()) return out;
  GROUPS.forEach((group) => groupList(group.key).forEach((s) => {
    if (s.disabled) return;
    const found = needOf(s);
    if (found) out.set(s.id, found);
  }));
  return out;
}

/** The short status a section or block row carries, or null. */
export function needTag(sid, bid = null) {
  const found = S.needs?.get(sid);
  if (!found) return null;
  if (bid) {
    const label = found.blocks.get(bid);
    return label ? { text: label, quiet: false } : null;
  }
  if (!found.need) return null;
  return { text: LABELS[found.need], quiet: found.need === 'none' };
}

/** Works out again which sections of the page wait for a pick. */
export function refreshNeeds() {
  S.needs = computeNeeds();
}
