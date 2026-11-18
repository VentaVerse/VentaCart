/*
 * The rules of the page menu, kept free of the DOM and the editor's state
 * so they can be tested on their own: which page types open in place to
 * their records, how a record row reads (the search marked in its name, the
 * special layout it uses, whether it is the page open now), and what each
 * key does in the menu.
 */

/* Page types whose menu row opens in place to their shared layout, a search and their records. */
export const RECORD_TYPES = {
  category: { kind: 'categories', one: 'category', many: 'categories' },
  product: { kind: 'products', one: 'product', many: 'products' },
  page: { kind: 'pages', one: 'page', many: 'pages' },
};

/** The words of an open type's search box: "Search 248 products". */
export function searchWords(total, words) {
  const n = Number(total) || 0;
  return n ? `Search ${n} ${n === 1 ? words.one : words.many}` : `Search ${words.many}`;
}

/**
 * A name split around the first place the search appears in it, ignoring
 * case: [{ text, match }]. The whole name, unmarked, when it does not appear.
 */
export function markedParts(name, query) {
  const text = String(name || '');
  const q = String(query || '').trim().toLowerCase();
  const at = q ? text.toLowerCase().indexOf(q) : -1;
  if (at < 0) return [{ text, match: false }];
  return [
    { text: text.slice(0, at), match: false },
    { text: text.slice(at, at + q.length), match: true },
    { text: text.slice(at + q.length), match: false },
  ].filter((part) => part.text !== '');
}

/**
 * The record rows of an open type: `{ id, name, parts, tag, current }`.
 *   items      records as the lookup answered them ({ id, name })
 *   query      what was typed in the search
 *   templates  the special layouts ({ pageType, label, assigned })
 *   pageType   the type the records belong to
 *   current    the id of the record the open page shows, or null
 */
export function recordRows(items, { query = '', templates = [], pageType = '', current = null } = {}) {
  return (items || []).map((item) => {
    const id = Number(item.id);
    const special = templates.find((t) => t.pageType === pageType && (t.assigned || []).map(Number).includes(id));
    return {
      id,
      name: String(item.name || ''),
      parts: markedParts(item.name, query),
      tag: special ? special.label : null,
      current: current !== null && Number(current) === id,
    };
  });
}

/**
 * What a key does in the menu, from where the focus is. Answers one of
 *   { do: 'close' }                     Escape
 *   { do: 'move', to }                  an index into the focus stops
 *   { do: 'toggle', type }              open or close a type in place
 *   { do: 'first' }                     open the first record found
 *   { do: 'search', letter }            type into the open type's search
 *   { do: 'move', to } for a letter     the next page type starting with it
 *   null                                the key is left to the browser
 *
 *   key        the key pressed
 *   index      where the focus is among the stops (the rows and the search box)
 *   count      how many stops there are
 *   inSearch   the focus is in the search box
 *   type       the record type of the row with the focus, or null
 *   expanded   the record type open now, or null
 *   hasSearch  a type is open, with its search box
 *   names      the names of every stop, for a letter's jump ('' for those that are not page types)
 *   modifier   Ctrl, Alt or the Command key is held
 */
export function menuKey({ key, index = -1, count = 0, inSearch = false, type = null, expanded = null, hasSearch = false, names = [], modifier = false } = {}) {
  if (key === 'Escape') return { do: 'close' };
  if (!count) return null;
  if (key === 'ArrowDown') return { do: 'move', to: (index + 1 + count) % count };
  if (key === 'ArrowUp') return { do: 'move', to: (index - 1 + count) % count };
  if (inSearch) return key === 'Enter' ? { do: 'first' } : null;
  if (key === 'Home') return { do: 'move', to: 0 };
  if (key === 'End') return { do: 'move', to: count - 1 };
  if (key === 'ArrowRight' && type && expanded !== type) return { do: 'toggle', type };
  if (key === 'ArrowLeft' && type && expanded === type) return { do: 'toggle', type };
  if (modifier || typeof key !== 'string' || key.length !== 1 || key === ' ') return null;
  if (hasSearch) return { do: 'search', letter: key };
  const k = key.toLowerCase();
  for (let step = 1; step <= count; step += 1) {
    const at = (index + step + count) % count;
    if ((names[at] || '').trim().toLowerCase().startsWith(k)) return { do: 'move', to: at };
  }
  return null;
}
