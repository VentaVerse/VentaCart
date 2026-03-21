import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/themes/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                heading: ['Bebas Neue', ...defaultTheme.fontFamily.sans],
                body: ['Space Mono', ...defaultTheme.fontFamily.mono],
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
            },
        },
    },

    plugins: [forms],
};
