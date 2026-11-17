/** Small helpers shared by the storefront script and the editor frame. */

export const root = document.documentElement;
export const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
export const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');

export const $ = (selector, scope = document) => scope.querySelector(selector);
export const $$ = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

/** One icon from the sprite the layout draws once per page. */
export const icon = (name, extraClass = '') =>
  `<svg class="icon ${extraClass}" aria-hidden="true"><use href="#i-${name}"></use></svg>`;

/**
 * Whether this page is the editor's live preview. The frame script marks the
 * root after the storefront script has started, so ask at the moment it matters.
 */
export const inEditorPreview = () => root.classList.contains('in-editor');

/**
 * Fired on a section when the editor's selection enters it, moves within it
 * or leaves it. The selected section and block carry data-editor-selected.
 */
export const EDITOR_SELECT = 'xelga:editor-select';
