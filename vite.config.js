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

            for (const rel of [
                'css/theme.css', 'js/theme.js',
                // A theme's own editor, when it ships one.
                'editor/css/frame.css', 'editor/js/frame.js',
                'editor/css/panel.css', 'editor/js/panel.js',
            ]) {
                const entry = `resources/themes/${theme}/${rel}`;
                if (fs.existsSync(path.resolve(__dirname, entry))) entries.push(entry);
            }
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
    // A theme or extension may be a link to a folder outside the app (a
    // package being developed in its own repository). Resolve its imports
    // from the app, where the dependencies are installed, not from the
    // folder the link points to.
    resolve: {
        preserveSymlinks: true,
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/admin/admin.css',
                'resources/css/admin/layout-editor.css',
                'resources/css/storefront/no-theme.css',
                'resources/js/admin/admin.js',
                // Theme editor live preview: loaded by the storefront ONLY when an
                // authenticated admin frames it, never in a shopper's bundle.
                'resources/js/preview.js',
                // Contractual manifest keys: the shells preload these via
                // Vite::asset(), which throws on a manifest miss.
                'resources/fonts/Archivo-latin.woff2',
                'resources/fonts/JetBrainsMono.woff2',
                ...getThemeEntries(),
                ...getExtensionEntries(),
            ],
            refresh: true,
        }),
    ],
});
