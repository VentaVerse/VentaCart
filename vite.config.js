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

// Extensions ship their own page CSS (never inline in Blade — house rule) as
// extensions/{slug}/resources/css/*.css, each compiled as its own entry and
// pulled in by that extension's view via @push('head') + @vite(...).
function getExtensionEntries() {
    const extensionsDir = path.resolve(__dirname, 'extensions');
    const entries = [];

    if (fs.existsSync(extensionsDir)) {
        for (const ext of fs.readdirSync(extensionsDir)) {
            const cssDir = path.join(extensionsDir, ext, 'resources/css');
            if (!fs.existsSync(cssDir) || !fs.statSync(cssDir).isDirectory()) continue;

            for (const file of fs.readdirSync(cssDir)) {
                if (file.endsWith('.css')) entries.push(`extensions/${ext}/resources/css/${file}`);
            }
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
                'resources/css/admin3/admin3.css',
                'resources/js/admin3/admin3.js',
                ...getThemeEntries(),
                ...getExtensionEntries(),
            ],
            refresh: true,
        }),
    ],
});
