/**
 * Horizontal pointer dragging for slideshows and swipe rows.
 *
 * A drag is claimed only once the pointer has moved mostly sideways, so a
 * vertical swipe on a phone still scrolls the page (pair it with
 * `touch-action: pan-y` on the element). A claimed drag swallows the click
 * that would follow, so releasing over a link does not open it. Drags never
 * start on buttons or form fields.
 */

const CLAIM = 8; // pixels moved before a gesture counts as a drag

/**
 * @param {HTMLElement} el
 * @param {{signal: AbortSignal, pointerTypes?: string[], onStart?: () => void,
 *          onMove?: (dx: number) => void, onEnd?: (dx: number, velocity: number) => void,
 *          onCancel?: () => void}} options  velocity is in pixels per millisecond
 */
export function dragGesture(el, { signal, pointerTypes = null, onStart, onMove, onEnd, onCancel }) {
  let drag = null;

  el.addEventListener('pointerdown', (event) => {
    if (event.button !== 0 || !event.isPrimary) return;
    if (pointerTypes && !pointerTypes.includes(event.pointerType)) return;
    if (event.target.closest('button, input, select, textarea, label')) return;
    drag = { id: event.pointerId, x: event.clientX, y: event.clientY, claimed: false, lastX: event.clientX, lastT: event.timeStamp, velocity: 0 };
  }, { signal });

  el.addEventListener('pointermove', (event) => {
    if (!drag || event.pointerId !== drag.id) return;
    const dx = event.clientX - drag.x;
    const dy = event.clientY - drag.y;
    if (!drag.claimed) {
      if (Math.abs(dx) < CLAIM && Math.abs(dy) < CLAIM) return;
      if (Math.abs(dy) >= Math.abs(dx)) {
        drag = null; // mostly vertical: leave it to the page
        return;
      }
      drag.claimed = true;
      try {
        el.setPointerCapture(event.pointerId);
      } catch {
        /* The pointer may already be gone; the drag still ends on pointerup. */
      }
      window.getSelection()?.removeAllRanges();
      onStart?.();
    }
    const dt = event.timeStamp - drag.lastT;
    if (dt > 0) drag.velocity = (event.clientX - drag.lastX) / dt;
    drag.lastX = event.clientX;
    drag.lastT = event.timeStamp;
    event.preventDefault();
    onMove?.(dx);
  }, { signal });

  const finish = (event, cancelled) => {
    if (!drag || event.pointerId !== drag.id) return;
    const done = drag;
    drag = null;
    if (!done.claimed) return;
    // The click that ends a drag is not a click on what lies under the pointer.
    const swallow = (click) => {
      click.preventDefault();
      click.stopPropagation();
    };
    el.addEventListener('click', swallow, { capture: true, once: true });
    window.setTimeout(() => el.removeEventListener('click', swallow, { capture: true }), 80);
    if (cancelled) {
      onCancel?.();
      return;
    }
    // A pointer held still before release is not a flick.
    const velocity = event.timeStamp - done.lastT < 100 ? done.velocity : 0;
    onEnd?.(event.clientX - done.x, velocity);
  };

  el.addEventListener('pointerup', (event) => finish(event, false), { signal });
  el.addEventListener('pointercancel', (event) => finish(event, true), { signal });
  // Photos and links would otherwise start the browser's own drag and drop.
  el.addEventListener('dragstart', (event) => event.preventDefault(), { signal });
}

/** Whether a released drag should move on: past a share of the width, or a quick flick. */
export function dragPassed(dx, velocity, width, share = 0.15) {
  return Math.abs(dx) > width * share || (Math.abs(velocity) > 0.5 && Math.abs(dx) > 20);
}
