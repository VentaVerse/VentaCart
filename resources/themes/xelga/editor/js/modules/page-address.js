/*
 * The page open in the editor, kept in the tab's address so a reload or a
 * shared link comes back to it: `page` (its type, left out for the home
 * page), `layout` (a special layout's handle) and `record` (the id of a
 * record opened from the page menu). Nothing here touches the DOM.
 */

/** The address's query for a page, keeping every other parameter as it is. */
export function addressQuery(search, page) {
  const q = new URLSearchParams(search || '');
  ['page', 'layout', 'edit', 'record'].forEach((key) => q.delete(key));
  if (page?.type && page.type !== 'home') q.set('page', page.type);
  if (page?.layout) q.set('layout', page.handle);
  if (page?.record && page.subject?.id) q.set('record', String(page.subject.id));
  return q.toString();
}

/** The record id an address asks for, or null. */
export function recordAsked(search) {
  const raw = new URLSearchParams(search || '').get('record');
  return raw && /^[1-9][0-9]{0,17}$/.test(raw) ? Number(raw) : null;
}

/**
 * The page target for a record an address asked for: { type, layout,
 * subject, url }, on the special layout the record uses if it has one.
 * Null when the page shows no records, or the store cannot show this one
 * (switched off, deleted): the page then opens on its shared layout.
 *   page       the page type the address named (with subjectType)
 *   id         the record's id
 *   lookup     (subjectType, id) => { subject, url }, throwing when it cannot
 *   templates  the special layouts ({ handle, pageType, assigned })
 */
export async function recordTarget({ page, id, lookup, templates = [] }) {
  if (!page?.subjectType || !id) return null;
  let found;
  try {
    found = await lookup(page.subjectType, id);
  } catch (error) {
    if (error?.kind === 'unauthenticated' || error?.kind === 'forbidden') throw error;
    return null;
  }
  if (!found?.subject || !found.url) return null;
  const layout = templates.find((t) => t.pageType === page.type && (t.assigned || []).map(Number).includes(Number(found.subject.id)));
  return { type: page.type, layout: layout?.handle || null, subject: found.subject, url: found.url };
}
