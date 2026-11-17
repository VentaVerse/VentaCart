/** Announcement bar: one message at a time with arrows, or a slow marquee. */
import { $, $$, reduceMotion } from '../util.js';

export function initAnnouncement(section) {
  const ac = new AbortController();
  const { signal } = ac;
  const track = $('.announce__track', section);
  if (!track) return () => ac.abort();
  const group = $('.announce__group:not(.announce__group--clone)', track);
  const messages = $$('.announce__msg:not([hidden])', group);
  let timer = null;
  let index = 0;
  let hovering = false;

  $$('.announce__group--clone', track).forEach((clone) => clone.remove());
  messages.forEach((msg) => {
    msg.classList.remove('is-active');
    msg.removeAttribute('aria-hidden');
    msg.inert = false;
  });

  const variant = section.dataset.variant;

  if (variant === 'marquee') {
    // A hidden copy follows the line, so the loop has no gap.
    const clone = group.cloneNode(true);
    clone.classList.add('announce__group--clone');
    clone.setAttribute('aria-hidden', 'true');
    clone.inert = true;
    $$('[data-block-id]', clone).forEach((el) => el.removeAttribute('data-block-id'));
    track.append(clone);
    const duration = Math.max(12, Math.round(track.scrollWidth / 2 / 40));
    track.style.setProperty('--marquee-duration', `${duration}s`);
    return () => {
      ac.abort();
      clone.remove();
    };
  }

  if (variant !== 'rotate' || messages.length < 2) return () => ac.abort();

  const show = (next) => {
    index = (next + messages.length) % messages.length;
    messages.forEach((msg, i) => {
      const active = i === index;
      msg.classList.toggle('is-active', active);
      msg.setAttribute('aria-hidden', String(!active));
      msg.inert = !active;
    });
  };
  const stop = () => {
    window.clearInterval(timer);
    timer = null;
  };
  const interval = Math.max(3, Number(section.dataset.interval) || 5) * 1000;
  const start = () => {
    stop();
    if (reduceMotion.matches || hovering) return;
    timer = window.setInterval(() => show(index + 1), interval);
  };

  $('[data-announce-prev]', section)?.addEventListener('click', () => { show(index - 1); start(); }, { signal });
  $('[data-announce-next]', section)?.addEventListener('click', () => { show(index + 1); start(); }, { signal });
  section.addEventListener('mouseenter', () => { hovering = true; stop(); }, { signal });
  section.addEventListener('mouseleave', () => { hovering = false; start(); }, { signal });
  section.addEventListener('focusin', () => { hovering = true; stop(); }, { signal });
  section.addEventListener('focusout', (event) => {
    if (section.contains(event.relatedTarget)) return;
    hovering = false;
    start();
  }, { signal });

  show(0);
  start();

  return () => {
    stop();
    ac.abort();
    messages.forEach((msg) => {
      msg.inert = false;
      msg.classList.remove('is-active');
      msg.removeAttribute('aria-hidden');
    });
  };
}
