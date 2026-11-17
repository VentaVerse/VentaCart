/**
 * Countdowns to the end of a special price. The end moment comes from core;
 * when it passes, the countdown says so and asks for a reload, because the
 * price on the page was the special one.
 */
import { $, $$ } from '../util.js';

export function initCountdowns(section) {
  const timers = $$('[data-countdown-end]', section).map((el) => {
    const end = new Date(el.dataset.countdownEnd).getTime();
    const units = ['d', 'h', 'm', 's'].reduce((acc, u) => ({ ...acc, [u]: $(`[data-unit="${u}"]`, el) }), {});
    const summary = $('.countdown__summary', el);
    let lastMinute = -1;

    const tick = () => {
      const diff = Math.max(0, end - Date.now());
      if (Number.isNaN(end) || diff <= 0) {
        el.classList.add('is-ended');
        return false;
      }
      el.classList.remove('is-ended');
      const total = Math.floor(diff / 1000);
      const parts = {
        d: Math.floor(total / 86400),
        h: Math.floor((total % 86400) / 3600),
        m: Math.floor((total % 3600) / 60),
        s: total % 60,
      };
      Object.entries(parts).forEach(([u, v]) => {
        if (units[u]) units[u].textContent = String(v).padStart(2, '0');
      });
      // Screen readers hear a calm summary once a minute, not every second.
      if (summary && parts.m !== lastMinute) {
        lastMinute = parts.m;
        summary.textContent = `Sale price ends in ${parts.d} days, ${parts.h} hours and ${parts.m} minutes`;
      }
      return true;
    };

    if (!tick()) return null;
    const id = window.setInterval(() => {
      if (!tick()) window.clearInterval(id);
    }, 1000);
    return id;
  });

  return () => timers.forEach((id) => id && window.clearInterval(id));
}
