/** Small helpers shared by the storefront script and the editor frame. */

export const root = document.documentElement;
export const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
export const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');

export const $ = (selector, scope = document) => scope.querySelector(selector);
export const $$ = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

/** One icon from the sprite the layout draws once per page. */
export const icon = (name, extraClass = '') =>
  `<svg class="icon ${extraClass}" aria-hidden="true"><use href="#i-${name}"></use></svg>`;

