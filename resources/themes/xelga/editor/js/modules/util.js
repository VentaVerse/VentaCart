/*
 * Small pure helpers: copying values, words, times and color contrast.
 */

export const clone = (value) => (value === undefined ? undefined : JSON.parse(JSON.stringify(value)));
export const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

export const humanize = (text) => {
  const s = String(text || '').replace(/[-_]+/g, ' ').trim();
  return s ? s[0].toUpperCase() + s.slice(1) : '';
};

export const fileName = (path) => String(path || '').split('/').pop();

export const plural = (n, word, many = `${word}s`) => `${n} ${n === 1 ? word : many}`;

/** "2:41 pm" today, "12 Nov, 2:41 pm" on another day. */
export function formatTime(value) {
  if (!value) return '';
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  const time = date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }).toLowerCase();
  const today = new Date();
  if (date.toDateString() === today.toDateString()) return time;
  const day = date.toLocaleDateString([], { day: 'numeric', month: 'short', year: date.getFullYear() === today.getFullYear() ? undefined : 'numeric' });
  return `${day}, ${time}`;
}

export function debounce(fn, wait) {
  let timer = 0;
  const run = (...args) => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => fn(...args), wait);
  };
  run.cancel = () => window.clearTimeout(timer);
  return run;
}

export const isHex = (value) => /^#[0-9a-f]{6}$/i.test(String(value || ''));

export function luminance(hex) {
  const n = String(hex).replace('#', '');
  const [r, g, b] = [0, 2, 4].map((i) => {
    const c = parseInt(n.slice(i, i + 2), 16) / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contrast(a, b) {
  if (!isHex(a) || !isHex(b)) return 21;
  const [l1, l2] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return (l1 + 0.05) / (l2 + 0.05);
}

/*
 * The pairs a scheme is checked on, with the lowest ratio each may have.
 * Publishing refuses a scheme below these; the server applies the same rule.
 */
export const READABILITY_PAIRS = [
  { fg: 'text', bg: 'bg', min: 4.5, label: 'Text on background' },
  { fg: 'muted', bg: 'bg', min: 3, label: 'Secondary text on background' },
  { fg: 'button-text', bg: 'button', min: 4.5, label: 'Button text on button' },
  { fg: 'accent-text', bg: 'accent', min: 4.5, label: 'Text on accent' },
];

/** Every pair of one scheme with its ratio and whether it passes. */
export function readability(colors = {}) {
  return READABILITY_PAIRS
    .filter((p) => colors[p.fg] && colors[p.bg])
    .map((p) => {
      const ratio = contrast(colors[p.fg], colors[p.bg]);
      return { ...p, ratio, ok: ratio >= p.min };
    });
}
