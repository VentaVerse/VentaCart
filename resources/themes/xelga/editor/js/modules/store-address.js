/*
 * The address the board's frame names at its top, the way a browser bar
 * would: the store's own domain and the page's path. A layout shared by
 * many records (every product, say) has no one address, so its path ends
 * where the record's own part would start, followed by the words for any
 * record ("any product"). Nothing here touches the DOM.
 */

/** The words that stand for any record of a page type, in place of one record's address. */
export const ANY_RECORD = {
  product: 'any product',
  category: 'any category',
  brand: 'any brand',
  page: 'any page',
  'blog-post': 'any post',
};

/** A path as people read it: escaped letters shown as themselves, when they decode. */
function readable(path) {
  try {
    return decodeURI(path);
  } catch {
    return path;
  }
}

/**
 * The address for the frame's top bar: `host + path` for a page with one
 * address (the home page is the host alone), or, for a layout shared by
 * the records of a page type, the host and the path up to its last part,
 * then the words for any record of that type. '' when `url` is not a full
 * address.
 *   url       the page's address on the store, as the editor opens it
 *   shared    the layout is shared by many records
 *   pageType  the page type, for the words of a shared layout
 */
export function storeAddressFor(url, { shared = false, pageType = '' } = {}) {
  let parsed;
  try {
    parsed = new URL(String(url || ''));
  } catch {
    return '';
  }
  const path = readable(parsed.pathname);
  const any = shared ? ANY_RECORD[pageType] : null;
  if (!any) return parsed.host + (path === '/' ? '' : path);
  return `${parsed.host}${path.slice(0, path.lastIndexOf('/') + 1)} ${any}`;
}
