import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/themes/**/*.blade.php',
        './extensions/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                heading: ['Bebas Neue', ...defaultTheme.fontFamily.sans],
                body: ['Space Mono', ...defaultTheme.fontFamily.mono],
                ui: 'var(--font-ui)',
                vmono: 'var(--font-mono)',
            },
            colors: {
                brand: {
                    amber: '#F5A623',
                    'amber-dark': '#D4900E',
                    'amber-light': '#FFB84D',
                    red: '#E2293D',
                    'red-dark': '#C41F32',
                },
                surface: {
                    DEFAULT: '#0D0D0D',
                    raised: '#1A1A1A',
                    hover: '#242424',
                    border: '#2A2A2A',
                },
                // The admin bridge. Every name here is frozen: 92 views and
                // 26 components write these utilities, and the design is
                // changed by repointing the variables in
                // resources/css/admin/tokens.css, never by renaming a class.
                v: {
                    canvas: 'var(--canvas)',
                    surface: 'var(--surface)',
                    sunken: 'var(--surface-sunken)',
                    hover: 'var(--surface-hover)',
                    border: 'var(--border)',
                    strong: 'var(--border-strong)',
                    text: 'var(--text)',
                    secondary: 'var(--text-secondary)',
                    muted: 'var(--text-muted)',
                    accent: 'var(--accent)',
                    'accent-hover': 'var(--accent-hover)',
                    'accent-weak': 'var(--accent-weak)',
                    'accent-text': 'var(--accent-text)',
                    // The brass mark: fills, rings and active edges, where
                    // the colour is a mark rather than a word. Text takes
                    // v-accent, which is the deeper stop that clears 4.5:1.
                    mark: 'var(--accent-mark)',
                    'mark-ink': 'var(--accent-mark-ink)',
                    danger: 'var(--danger)',
                    'danger-text': 'var(--danger-text)',
                    warning: 'var(--warning)',
                    info: 'var(--info)',
                    // The slab: the admin's own chrome, which is the one
                    // surface that does not follow the page's theme.
                    slab: 'var(--bl-slab)',
                    'slab-ink': 'var(--bl-slab-ink)',
                    'slab-hover': 'var(--bl-slab-hover)',
                    'slab-active': 'var(--bl-slab-active)',
                    'slab-rule': 'var(--bl-slab-rule)',
                    'slab-on': 'var(--bl-slab-on)',
                    // Identity, never state: which measure you are reading.
                    // Always paired with the written label.
                    cyan: 'var(--bl-a-cyan)',
                    'cyan-2': 'var(--bl-a-cyan-2)',
                    violet: 'var(--bl-a-violet)',
                    'violet-2': 'var(--bl-a-violet-2)',
                    amber: 'var(--bl-a-amber)',
                    'amber-2': 'var(--bl-a-amber-2)',
                    green: 'var(--bl-a-green)',
                    'green-2': 'var(--bl-a-green-2)',
                    rose: 'var(--bl-a-rose)',
                    'rose-2': 'var(--bl-a-rose-2)',
                    indigo: 'var(--bl-a-indigo)',
                    'indigo-2': 'var(--bl-a-indigo-2)',
                },
            },
            borderRadius: {
                'v-sm': 'var(--radius-sm)',
                'v-md': 'var(--radius-md)',
                'v-lg': 'var(--radius-lg)',
                'v-pill': 'var(--radius-pill)',
            },
            boxShadow: {
                'v-lift': 'var(--bl-lift)',
                'v-lift-2': 'var(--bl-lift-2)',
            },
            spacing: {
                'v-gutter': 'var(--bl-page-gutter)',
            },
        },
    },

    plugins: [forms],
};
