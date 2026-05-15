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

## License

MIT
