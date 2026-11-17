# Venta — Installation Guide

Venta is a Laravel-based e-commerce platform. This guide covers first-time setup for a new deployment.

## Requirements

- PHP 8.2+
- MySQL 8.0+
- Composer 2.x
- Node.js 18+ / npm
- Apache with `mod_rewrite` enabled (or Nginx with equivalent rewrites)

## Installation Steps

### 1. Clone and install dependencies

```bash
git clone <repo-url> venta
cd venta
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

### 2. Environment file

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database credentials, mail settings, and `APP_URL`.

### 3. **Rename `public/htaccess.txt` to `public/.htaccess`**

The repo ships a default Apache rewrite rules file as `htaccess.txt` (visible, non-hidden) so you can see and review it. You **must** rename it before the site will route correctly:

```bash
mv public/htaccess.txt public/.htaccess
```

> **Why a rename step?** Every deployment eventually customizes `.htaccess` for its own 301 redirects, HTTPS rules, or security headers. `public/.htaccess` is git-ignored so your customizations never fight with upstream updates. `htaccess.txt` is the canonical starting point.

### 4. Database setup

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
```

### 5. Permissions

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### 6. Cron

Add to your server's crontab for scheduled tasks (currency updates, stock alerts, sitemap, etc.):

```cron
* * * * * cd /path/to/venta && php artisan schedule:run >> /dev/null 2>&1
```

All default Venta automations (currency updates, back-in-stock notifications, review requests, SEO suggestions, sitemap, low-stock alerts, abandoned-cart reminders) run through this single `schedule:run` heartbeat. Edit their cadence, toggle them on/off, or trigger them manually from the admin at **Settings → Automations**.

### 7. Production settings for speed

**Cache store.** `.env.example` sets `CACHE_STORE=database` so a fresh install works with nothing else set up, but with that store every cache read is a database query. On a live store use the `file` store (one server) or `redis` (several servers, or when Redis is already running):

```dotenv
CACHE_STORE=file      # or: redis
```

A storefront page keeps each cached value for the rest of its request, so the database store costs one query per key rather than one per use, but a file or Redis read is still several times cheaper than a query. After changing the store, run `php artisan cache:clear`.

**Smaller photos.** Storefront pages offer phones WebP copies of each photo (320 to 1920 pixels wide, kept under `storage/app/public/renditions`). Copies are made when a photo is uploaded, and a photo met without copies has them queued after the page is sent. For photos that arrived another way (an import, a copied folder, an upgrade), make them all once:

```bash
php artisan images:renditions
```

It skips photos whose copies are current, so it is safe to stop and run again. Queued copies are made by the queue, which the cron heartbeat above drains every minute.

**Caches on deploy.** Run `php artisan optimize` after each deploy (config, routes, events and views), keep `APP_DEBUG=false`, and let the web server compress HTML, CSS and JavaScript (gzip or brotli).

## Upgrading

Pull updates and run:

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=AutomationSeeder --force   # only inserts missing rows; never overwrites admin edits
php artisan view:clear
php artisan config:clear
php artisan route:clear
```

Your `public/.htaccess` is untouched by `git pull` — site-specific redirects and server rules persist across upgrades.

### Migrating from pre-Automations installs

If your crontab has **independent lines for `cart:notify` or `seo:generate`** (the old pattern from the Abandoned Cart and SEO admin pages), delete them after this upgrade — those commands now run through the universal `schedule:run` heartbeat. Leaving the old lines in place will cause duplicate executions.

```bash
crontab -e
# Delete any lines containing `cart:notify` or `seo:generate`. Save.
```

### Upgrading to the bundled Xelga theme

This release ships the Xelga theme inside VentaCart (`resources/themes/xelga`) and removes the default theme, the Layout editor, the Storefront > Header & footer screen and the Banners extension. Menus move to **Content → Menus**, and the theme directory is the single **Themes** entry in the admin sidebar.

Before you upgrade:

1. Take a full database dump (`mysqldump --single-transaction your_database > before-upgrade.sql`) and a copy of `storage/app/public`. The upgrade also writes an export of what it removes, but the dump is the real way back.
2. If the store has a theme installed that is not part of VentaCart, uninstall it from the Themes page first while its folder still exists, unless you mean to keep running it.
3. Rehearse the upgrade on a copy of the database first.

`php artisan migrate --force` then, in order:

1. turns the links of the footer columns into the store's `footer` menu (skipped when a `footer` menu exists);
2. installs Xelga and makes it the live theme, when the store's theme can no longer draw (its folder is gone, or it is not installed). The announcement line, when shown, becomes the only message of Xelga's announcement bar. If the install fails, the migration stops and nothing is removed;
3. writes `storage/app/backups/storefront-export-{time}.json` with every row it removes, then removes the Layout rows, placements, page columns, drafts, saved templates and style presets, the homepage sections, the footer columns, the announcement and top bar settings, the banner groups and slides, the default theme's settings, the two Layout permissions and the registry rows of the removed packages. It refuses to run while the store has no installed live theme.

Menus, products, categories, pages, the blog, customers, orders and prices are not touched. After the upgrade, open **Themes → Xelga → Customize**, check the look against your catalog, and pick the products and categories its home page sections show.

## License

MIT
