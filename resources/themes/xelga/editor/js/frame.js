/*
 * The store preview: the theme editor's Preview frame, and any tab opened
 * from it. Loaded only in preview mode.
 *
 * Browsers send no sign that a page belongs to the preview, so a page only
 * shows the draft when its address carries the preview window's nonce.
 * Links and searches that stay on this store take it along, so the tab
 * keeps showing the draft while the merchant browses. Other sites never
 * receive it.
 */

const ORIGIN = window.location.origin;

/* The preview window's query parameter (PreviewMode::QUERY on the server). */
const PREVIEW_PARAM = 'xelga_preview';

const nonceHere = () => new URLSearchParams(window.location.search).get(PREVIEW_PARAM);

/** The address a link or form on this page should use to stay in the preview, or null to leave it as it is. */
function previewAddress(raw) {
  const nonce = nonceHere();
  if (!nonce) return null;
  let url;
  try {
    url = new URL(raw, window.location.href);
  } catch {
    return null;
  }
  if (url.origin !== ORIGIN || url.searchParams.has(PREVIEW_PARAM)) return null;
  // A jump within this page needs no new request.
  if (url.hash && url.pathname === window.location.pathname && url.search === window.location.search) return null;
  url.searchParams.set(PREVIEW_PARAM, nonce);
  return url;
}

function keepPreviewInTab() {
  if (!nonceHere()) return;
  // Rewritten as the link is about to be used, so a new tab or a copied
  // address keeps the draft too.
  const onLink = (event) => {
    const link = event.target.closest?.('a[href]');
    if (!link) return;
    const url = previewAddress(link.getAttribute('href'));
    if (url) link.href = url.href;
  };
  ['pointerdown', 'focusin', 'click', 'auxclick', 'contextmenu'].forEach((type) => document.addEventListener(type, onLink, true));
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || (form.method || 'get').toLowerCase() !== 'get') return;
    if (!previewAddress(form.action || window.location.href) || form.elements.namedItem(PREVIEW_PARAM)) return;
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = PREVIEW_PARAM;
    input.value = nonceHere();
    form.append(input);
  }, true);
}

keepPreviewInTab();
