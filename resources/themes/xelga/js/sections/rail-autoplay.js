/**
 * Featured products, swipe row layout: moves one product at a time by itself
 * when autoplay is on, always forward (the row loops, so after the last
 * product comes the first again), and lets a mouse or pen drag the row
 * (fingers already swipe it natively).
 *
 * Autoplay pauses while a shopper points at or focuses the row, holds after
 * the shopper presses pause, and does not start with reduced motion. The
 * arrows and the row's own scrolling live in rail.js, shared with other
 * sections.
 */
import { $, icon, reduceMotion } from '../util.js';
import { dragGesture, dragPassed } from '../drag.js';
import { moveRail } from './rail.js';

export function initRailAutoplay(section) {
  const ac = new AbortController();
  const { signal } = ac;
  const track = $('.rail-track', section);
  const pauseBtn = $('[data-rail-pause]', section);
  const isRow = section.dataset.variant === 'carousel';
  const autoplay = isRow && section.dataset.autoplay === 'true';
  if (pauseBtn) pauseBtn.hidden = !autoplay;

  const reset = () => {
    section.removeAttribute('data-draggable');
    section.classList.remove('is-dragging');
  };
  if (!track || !isRow) {
    reset();
    return () => ac.abort();
  }

  const interval = Math.min(10, Math.max(2, Number(section.dataset.interval) || 5)) * 1000;
  let timer = null;
  let userPaused = !autoplay || reduceMotion.matches;
  let hovering = false;
  let focused = false;
  let pressing = false;

  /** One product's width plus the gap. */
  const unit = () => {
    const item = track.firstElementChild;
    if (!item) return track.clientWidth;
    return item.getBoundingClientRect().width + (parseFloat(getComputedStyle(track).columnGap) || 0);
  };
  const maxScroll = () => track.scrollWidth - track.clientWidth;
  const behavior = () => (reduceMotion.matches ? 'auto' : 'smooth');

  const advance = () => {
    if (maxScroll() <= 2) return;
    moveRail(track, unit());
  };

  const stop = () => { window.clearInterval(timer); timer = null; };
  const start = () => {
    stop();
    if (userPaused || pressing || focused || hovering) return;
    timer = window.setInterval(advance, interval);
  };
  const syncPauseButton = () => {
    if (!pauseBtn) return;
    pauseBtn.setAttribute('aria-label', userPaused ? 'Start moving' : 'Pause moving');
    pauseBtn.setAttribute('aria-pressed', String(userPaused));
    pauseBtn.innerHTML = icon(userPaused ? 'play' : 'pause');
  };

  pauseBtn?.addEventListener('click', () => {
    userPaused = !userPaused;
    syncPauseButton();
    start();
  }, { signal });

  section.addEventListener('mouseenter', () => { hovering = true; start(); }, { signal });
  section.addEventListener('mouseleave', () => { hovering = false; start(); }, { signal });
  section.addEventListener('focusin', () => {
    focused = true;
    start();
  }, { signal });
  section.addEventListener('focusout', (event) => {
    if (section.contains(event.relatedTarget)) return;
    focused = false;
    start();
  }, { signal });

  // A finger swiping the row starts the wait for the next move again.
  track.addEventListener('pointerdown', () => { pressing = true; stop(); }, { signal });
  const release = () => { pressing = false; start(); };
  track.addEventListener('pointerup', release, { signal });
  track.addEventListener('pointercancel', release, { signal });

  // Mouse and pen drag the row; a release snaps to a product edge.
  let from = 0;
  dragGesture(track, {
    signal,
    pointerTypes: ['mouse', 'pen'],
    onStart: () => {
      from = track.scrollLeft;
      section.classList.add('is-dragging');
    },
    onMove: (dx) => {
      track.scrollLeft = from - dx;
    },
    onEnd: (dx, velocity) => {
      // Snapping stays off until the row has come to rest, or it would jump.
      const settle = () => section.classList.remove('is-dragging');
      track.addEventListener('scrollend', settle, { once: true, signal });
      window.setTimeout(settle, 800);
      const step = unit();
      const at = from / step;
      let target = Math.round(track.scrollLeft / step);
      if (dragPassed(dx, velocity, step, 0.2)) target = dx < 0 ? Math.max(target, Math.floor(at) + 1) : Math.min(target, Math.ceil(at) - 1);
      track.scrollTo({ left: Math.max(0, Math.min(maxScroll(), target * step)), behavior: behavior() });
    },
    onCancel: () => section.classList.remove('is-dragging'),
  });

  const sync = () => {
    if (maxScroll() > 2) section.dataset.draggable = 'true';
    else section.removeAttribute('data-draggable');
  };
  const observer = new ResizeObserver(sync);
  observer.observe(track);
  sync();
  syncPauseButton();
  start();

  return () => {
    stop();
    ac.abort();
    observer.disconnect();
    reset();
  };
}
