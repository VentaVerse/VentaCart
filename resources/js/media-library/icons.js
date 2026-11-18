/**
 * The library's icons: one inline SVG sprite added to the page once, and a
 * helper that draws an icon from it. Inline, so an icon costs no request and
 * takes the text color.
 */

const PATHS = {
    alert: '<circle cx="12" cy="12" r="8.75"/><path d="M12 7.5v5.25M12 16.25v.01"/>',
    'arrow-left': '<path d="M19.75 12H4.25M10.25 6l-6 6 6 6"/>',
    check: '<path d="M4.75 12.5l4.5 4.5 10-10"/>',
    'chevron-right': '<path d="M9.25 5.5l6.5 6.5-6.5 6.5"/>',
    close: '<path d="M6 6l12 12M18 6L6 18"/>',
    filter: '<path d="M3.75 5.75h16.5M6.75 12h10.5M10 18.25h4"/>',
    folder: '<path d="M3.25 7.25a1.5 1.5 0 0 1 1.5-1.5h4.1l2 2.25h8.4a1.5 1.5 0 0 1 1.5 1.5v8.25a1.5 1.5 0 0 1-1.5 1.5H4.75a1.5 1.5 0 0 1-1.5-1.5z"/>',
    'folder-plus': '<path d="M3.25 7.25a1.5 1.5 0 0 1 1.5-1.5h4.1l2 2.25h8.4a1.5 1.5 0 0 1 1.5 1.5v8.25a1.5 1.5 0 0 1-1.5 1.5H4.75a1.5 1.5 0 0 1-1.5-1.5z"/><path d="M12 11.25v5M9.5 13.75h5"/>',
    'grid-small': '<rect x="4" y="4" width="4.25" height="4.25" rx=".75"/><rect x="9.875" y="4" width="4.25" height="4.25" rx=".75"/><rect x="15.75" y="4" width="4.25" height="4.25" rx=".75"/><rect x="4" y="9.875" width="4.25" height="4.25" rx=".75"/><rect x="9.875" y="9.875" width="4.25" height="4.25" rx=".75"/><rect x="15.75" y="9.875" width="4.25" height="4.25" rx=".75"/><rect x="4" y="15.75" width="4.25" height="4.25" rx=".75"/><rect x="9.875" y="15.75" width="4.25" height="4.25" rx=".75"/><rect x="15.75" y="15.75" width="4.25" height="4.25" rx=".75"/>',
    'grid-large': '<rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/>',
    image: '<rect x="3.25" y="4.25" width="17.5" height="15.5" rx="1.5"/><circle cx="9" cy="9.75" r="1.5"/><path d="M3.75 17.25l4.75-4.75 3.75 3.75 2.5-2.5 5.5 5.5"/>',
    lock: '<rect x="4.75" y="10.25" width="14.5" height="10" rx="1.5"/><path d="M8.25 10.25v-3a3.75 3.75 0 0 1 7.5 0v3"/>',
    search: '<circle cx="10.75" cy="10.75" r="6.25"/><path d="M15.25 15.25l4.5 4.5"/>',
    upload: '<path d="M12 15.25V4.75M7.75 9 12 4.75 16.25 9"/><path d="M4.25 14.75v3.5a1.5 1.5 0 0 0 1.5 1.5h12.5a1.5 1.5 0 0 0 1.5-1.5v-3.5"/>',
};

const SPRITE_ID = 'ml-icons';
const SVG = 'http://www.w3.org/2000/svg';

function sprite() {
    const symbols = Object.entries(PATHS).map(([name, body]) => (
        `<symbol id="ml-i-${name}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">${body}</symbol>`
    )).join('');

    return `<svg id="${SPRITE_ID}" xmlns="${SVG}" aria-hidden="true" focusable="false" width="0" height="0">${symbols}</svg>`;
}

let added = false;

/**
 * Adds the sprite to the page the first time an icon is needed. The markup
 * is the constant above, never anything a person typed.
 */
export function ensureIcons(doc = document) {
    if (added || doc.getElementById(SPRITE_ID)) {
        added = true;
        return;
    }
    doc.body.insertAdjacentHTML('afterbegin', sprite());
    added = true;
}

/** An <svg> that draws one icon from the sprite. */
export function icon(name, extra = '') {
    const svg = document.createElementNS(SVG, 'svg');
    svg.setAttribute('class', `ml-icon ${extra}`.trim());
    svg.setAttribute('aria-hidden', 'true');
    const use = document.createElementNS(SVG, 'use');
    use.setAttribute('href', `#ml-i-${name}`);
    svg.append(use);
    return svg;
}
