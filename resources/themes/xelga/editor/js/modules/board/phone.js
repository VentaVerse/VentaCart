/*
 * The layout board on a phone. A full board cannot fit a narrow screen,
 * so below 720 pixels the page is drawn as a list, in the same frame and
 * the same three groups: the Header band, the page and the Footer band.
 * Inside the page come Top, then Content with the left side above its
 * rows and the right side below them, then Bottom: the order a phone
 * shows the store in. Each side is a group of its sections, or a box that
 * adds the first one. Each section is one line with the
 * small drawing of its layout and its name (its type under it when it has
 * a name of its own); the columns of a row sit side by side as chips at
 * their true proportions; the Add buttons always show. Settings and the
 * section picker come up as sheets over the list.
 *
 * A finger on the list scrolls it. To move a section or a row, press it
 * for a moment, then drag (createPress tells the two apart).
 */

import { h } from '../dom.js';

/**
 * The page as a list, from the board's own parts (drawn by board.js): the
 * head and the frame holding the header band, the page and the footer
 * band. The same pieces as on the board, so every control is the same
 * one; the phone styles in board.css and frame.css lay them out as lines
 * and chips.
 */
export function renderPhoneBoard({ head, frame }) {
  frame.classList.add('frame--list');
  return h('div', { class: 'board__inner board__inner--list' }, head, frame);
}

/** How long a press is held, in milliseconds, before it picks a section up. */
export const PRESS_DELAY = 350;

/** How far a finger may wander, in pixels, and still count as holding still. */
export const PRESS_SLOP = 8;

/**
 * What one press on a touch screen turns out to be, from its times and how
 * far it moved; no timers, no DOM. A finger that moves before `delay` scrolls
 * the board; a press held still for `delay` starts a drag; a short still
 * press is a tap. Once it is decided, it stays decided.
 *
 *   down(t)           the finger touches, at time t (ms)
 *   move(t, dx, dy)   it is at dx, dy from where it touched: 'scroll',
 *                     'drag', or null while it is not decided yet
 *   up(t)             it lifts: 'scroll', 'drag' or 'tap'
 *
 * The caller also calls move(t, 0, 0) when `delay` has passed with no
 * move, so a held press shows it was picked up before the finger moves.
 */
export function createPress({ delay = PRESS_DELAY, slop = PRESS_SLOP } = {}) {
  let start = 0;
  let decided = null;
  return {
    down(t) {
      start = t;
      decided = null;
    },
    move(t, dx, dy) {
      if (decided) return decided;
      if (t - start >= delay) decided = 'drag';
      else if (Math.hypot(dx, dy) > slop) decided = 'scroll';
      return decided;
    },
    up(t) {
      if (decided) return decided;
      return t - start >= delay ? 'drag' : 'tap';
    },
  };
}
