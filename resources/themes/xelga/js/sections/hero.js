/**
 * Main banner.
 *
 * Only visible slides take part. Shoppers never receive hidden ones; the
 * editor preview keeps them in the page, marked hidden, so showing one again
 * is instant, but they get no arrow stop, dot, count or turn. Every layout
 * shows the first visible slide; the slideshow layout rotates them with
 * arrows, dots, keys and dragging, and by itself when autoplay is on.
 *
 * Autoplay pauses while a shopper points at or focuses the banner, and does
 * not start with reduced motion. In the editor preview the pointer is always
 * over the page, so there it keeps playing under the pointer and holds only
 * while one of this banner's slides is selected, showing that slide.
 */
import { $, $$, icon, reduceMotion, inEditorPreview, EDITOR_SELECT } from '../util.js';
import { dragGesture, dragPassed } from '../drag.js';

export function initHero(section) {
  const ac = new AbortController();
  const { signal } = ac;
  const all = $$('.hero__slide', section);
  const slides = all.filter((slide) => !slide.hidden);
  const track = $('.hero__slides', section);
  const controls = $('.hero__controls', section);
  const empty = $('[data-hero-empty]', section);

  const reset = () => {
    all.forEach((slide) => {
      slide.classList.remove('is-active', 'is-leaving', 'is-peek', 'is-instant');
      slide.removeAttribute('aria-hidden');
      slide.removeAttribute('data-pos');
      slide.removeAttribute('data-slot');
      slide.inert = false;
    });
    section.removeAttribute('data-draggable');
    section.classList.remove('is-dragging', 'is-instant');
    track?.style.removeProperty('--drag-x');
    section.style.removeProperty('--slide-time');
  };

  reset();
  slides.forEach((slide, i) => { slide.dataset.slot = String(i + 1); });
  slides[0]?.classList.add('is-active');
  if (empty) empty.hidden = slides.length > 0;
  if (controls) controls.hidden = slides.length < 2;

  if (section.dataset.variant !== 'slideshow' || slides.length < 2 || !track) {
    const dotsList = $('.hero__dots', section);
    if (dotsList) dotsList.textContent = '';
    return () => ac.abort();
  }

  const count = slides.length;
  const dotsList = $('.hero__dots', section);
  const live = $('.hero__live', section);
  const pauseBtn = $('[data-hero-pause]', section);
  const autoplay = section.dataset.autoplay === 'true';
  // How slides change (the Transition setting). Fade, Zoom and Slow zoom crossfade;
  // Slide and Cover bring the next slide in from the side, so a drag shows it coming.
  const transition = section.dataset.slideChange || 'fade';
  const peeks = transition === 'slide' || transition === 'cover';
  const interval = Math.min(10, Math.max(2, Number(section.dataset.interval) || 6)) * 1000;
  let index = 0;
  let timer = null;
  let userPaused = !autoplay || reduceMotion.matches;
  let hovering = false;
  let focused = false;
  let held = false;
  let dragging = false;

  section.dataset.draggable = 'true';
  // Slides waiting off to the side are never near the viewport, so the
  // browser would put off loading their lazy photos until they slide in
  // empty. Once the page has loaded, fetch them all.
  const loadAll = () => slides.forEach((slide) => {
    $$('img[loading="lazy"]', slide).forEach((img) => { img.loading = 'eager'; });
  });
  if (document.readyState === 'complete') loadAll();
  else window.addEventListener('load', loadAll, { once: true, signal });
  // Slow zoom runs for as long as a slide shows, plus the crossfade.
  section.style.setProperty('--slide-time', `${interval + 1000}ms`);

  if (dotsList) {
    dotsList.innerHTML = slides.map((_, i) =>
      `<li><button type="button" class="hero__dot" aria-label="Show slide ${i + 1} of ${count}"></button></li>`
    ).join('');
  }
  const dots = $$('.hero__dot', section);

  /** Puts a slide where it waits to come in, without animating it there. */
  const placeInstantly = (slide, pos) => {
    if (slide.dataset.pos === pos && !slide.classList.contains('is-leaving')) return;
    slide.classList.add('is-instant');
    slide.classList.remove('is-leaving');
    slide.dataset.pos = pos;
    void slide.offsetWidth;
    slide.classList.remove('is-instant');
  };

  /*
   * Shows slide `next`. `dir` is 1 for forward and -1 for back (taken from the
   * slide numbers when not given): the new slide comes in from that side and
   * the old one leaves the other way, so going from the last slide to the
   * first with Next still moves forward.
   */
  const go = (next, { dir = 0, announce = true } = {}) => {
    const target = ((next % count) + count) % count;
    const step = dir || (target > index ? 1 : -1);
    if (target !== index) {
      placeInstantly(slides[target], step > 0 ? 'after' : 'before');
      slides.forEach((slide) => slide.classList.remove('is-leaving'));
      slides[index].classList.add('is-leaving');
      slides[index].dataset.pos = step > 0 ? 'before' : 'after';
    }
    index = target;
    slides.forEach((slide, i) => {
      const active = i === index;
      slide.classList.toggle('is-active', active);
      slide.setAttribute('aria-hidden', String(!active));
      slide.inert = !active;
      if (active) slide.dataset.pos = 'current';
    });
    dots.forEach((dot, i) => dot.setAttribute('aria-current', String(i === index)));
    if (live && announce) live.textContent = `Slide ${index + 1} of ${count}`;
  };

  const stop = () => { window.clearInterval(timer); timer = null; };
  const start = () => {
    stop();
    const editor = inEditorPreview();
    if (userPaused || held || dragging || focused || (hovering && !editor)) return;
    timer = window.setInterval(() => go(index + 1, { dir: 1, announce: false }), interval);
  };
  const syncPauseButton = () => {
    if (!pauseBtn) return;
    pauseBtn.hidden = !autoplay;
    pauseBtn.setAttribute('aria-label', userPaused ? 'Play slideshow' : 'Pause slideshow');
    pauseBtn.innerHTML = icon(userPaused ? 'play' : 'pause');
  };

  /** The slide the editor has selected in this banner, or -1. */
  const selectedIndex = () => slides.findIndex((slide) => slide.hasAttribute('data-editor-selected'));
  const syncSelection = () => {
    const picked = selectedIndex();
    held = picked >= 0;
    if (held && picked !== index) go(picked, { announce: false });
    start();
  };

  $('[data-hero-prev]', section)?.addEventListener('click', () => { go(index - 1, { dir: -1 }); start(); }, { signal });
  $('[data-hero-next]', section)?.addEventListener('click', () => { go(index + 1, { dir: 1 }); start(); }, { signal });
  dots.forEach((dot, i) => dot.addEventListener('click', () => { go(i); start(); }, { signal }));
  pauseBtn?.addEventListener('click', () => {
    userPaused = !userPaused;
    syncPauseButton();
    start();
  }, { signal });

  section.addEventListener('mouseenter', () => { hovering = true; start(); }, { signal });
  section.addEventListener('mouseleave', () => { hovering = false; start(); }, { signal });
  section.addEventListener('focusin', (event) => {
    // In the preview a click focuses whatever it lands on; only keyboard focus pauses there.
    focused = !inEditorPreview() || event.target.matches(':focus-visible');
    start();
  }, { signal });
  section.addEventListener('focusout', (event) => {
    if (section.contains(event.relatedTarget)) return;
    focused = false;
    start();
  }, { signal });
  section.addEventListener(EDITOR_SELECT, syncSelection, { signal });

  // Dragging with a mouse, pen or finger: the slide follows the pointer.
  const settle = () => {
    section.classList.remove('is-dragging');
    track.style.removeProperty('--drag-x');
    slides.forEach((slide) => slide.classList.remove('is-peek'));
  };
  const peek = (dx) => {
    const dir = dx < 0 ? 1 : -1;
    const neighbour = slides[(index + dir + count) % count];
    slides.forEach((slide) => { if (slide !== neighbour) slide.classList.remove('is-peek'); });
    if (neighbour === slides[index]) return;
    neighbour.classList.remove('is-leaving');
    neighbour.dataset.pos = dir > 0 ? 'after' : 'before';
    neighbour.classList.add('is-peek');
  };
  dragGesture(track, {
    signal,
    onStart: () => {
      dragging = true;
      stop();
      section.classList.add('is-dragging');
    },
    onMove: (dx) => {
      if (reduceMotion.matches) return;
      track.style.setProperty('--drag-x', `${Math.round(dx)}px`);
      if (peeks) peek(dx);
    },
    onEnd: (dx, velocity) => {
      settle();
      dragging = false;
      if (dragPassed(dx, velocity, track.clientWidth || 1)) {
        const dir = dx < 0 ? 1 : -1;
        go(index + dir, { dir });
      }
      start();
    },
    onCancel: () => {
      settle();
      dragging = false;
      start();
    },
  });

  section.addEventListener('keydown', (event) => {
    if (!event.target.closest('.hero__controls')) return;
    if (event.key === 'ArrowLeft') { go(index - 1, { dir: -1 }); start(); }
    if (event.key === 'ArrowRight') { go(index + 1, { dir: 1 }); start(); }
  }, { signal });

  // Draw the starting slide in place, without a transition.
  section.classList.add('is-instant');
  slides.forEach((slide) => { slide.dataset.pos = 'after'; });
  const picked = selectedIndex();
  held = picked >= 0;
  go(Math.max(0, picked), { announce: false });
  void section.offsetWidth;
  section.classList.remove('is-instant');
  syncPauseButton();
  start();

  return () => {
    stop();
    ac.abort();
    reset();
  };
}
