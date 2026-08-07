import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import fs from 'fs';
import path from 'path';

function getThemeEntries() {
    const themesDir = path.resolve(__dirname, 'resources/themes');
    const entries = [];

    if (fs.existsSync(themesDir)) {
        for (const theme of fs.readdirSync(themesDir)) {
            const themeDir = path.join(themesDir, theme);
            if (!fs.statSync(themeDir).isDirectory()) continue;

            const cssPath = `resources/themes/${theme}/css/theme.css`;
            const jsPath = `resources/themes/${theme}/js/theme.js`;
            if (fs.existsSync(path.resolve(__dirname, cssPath))) entries.push(cssPath);
            if (fs.existsSync(path.resolve(__dirname, jsPath))) entries.push(jsPath);
        }
    }

    return entries;
}

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/admin/admin.css',
                'resources/js/admin/admin.js',
                ...getThemeEntries(),
            ],
            refresh: true,
        }),
    ],
});
