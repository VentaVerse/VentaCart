<p align="center">
  <img src="docs/branding/ventacart-lockup-color.png" width="360" alt="VentaCart">
</p>

<p align="center">
  An open source e-commerce platform built on Laravel, made for stores in the Philippines and ready for the rest of the world.
</p>

---

VentaCart gives a store its catalog, cart, checkout, orders and customers in one Laravel application, with a fast storefront theme and an admin built to work on a phone. Philippine couriers, payment gateways and address lists (province, city, barangay) are built in, and everything else plugs in as an extension.

## Features

**Catalog**
- Products with variants, attributes, brands, categories and tags
- Customer groups with their own prices, specials and volume pricing
- A shared media library for every photo in the store, with folders, search and safe moves
- Stock tracking, low-stock warnings and back-in-stock emails

**Selling**
- Cart and checkout with delivery quotes from each courier, down to the barangay
- Orders, order statuses and email templates you can edit
- Coupons, store credit, abandoned-cart reminders and email campaigns
- Product reviews with moderation and review-request emails

**Shipping and payment**
- Couriers: QuadX, SPX, LBC and DHL Express, plus flat rate, weight-based and free-shipping rules
- Payments: HitPay (cards and e-wallets), bank transfer with QR codes, and cash on delivery
- Rules that limit payment methods by group, total or address

**Storefront**
- **Xelga**, the bundled theme: a visual editor with a layout board, sections, skins, colors and fonts, previewed live before publishing
- Responsive photos (WebP copies at several widths) and SEO tools: meta suggestions, redirects, a 404 log, sitemap and Search Console
- A blog, menus and content pages

**Running the store**
- Staff accounts with user groups and permissions
- Scheduled automations (currency rates, stock alerts, sitemap and more), managed from the admin
- An ERP push API for products, variants, orders and stock
- Currencies, languages and SMS through UniSMS

## Extensions

Most features beyond the core ship as extensions in [`extensions/`](extensions). Each one is a folder with its own `extension.json`, routes, migrations and views, and can be turned on or off from **Extensions** in the admin. New extensions and themes can be uploaded as a package from the admin.

## Requirements

- PHP 8.2 or newer
- MySQL 8.0+ or MariaDB
- Composer 2
- Node.js 18+ and npm

## Quick start

```bash
git clone https://github.com/VentaVerse/VentaCart.git
cd VentaCart
composer install
npm ci && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Open `http://localhost:8000/admin/setup` to create the first admin account. No login is seeded, so a store never ships with a known password. A fresh install opens with a small demo catalog, which is never added to a store that already has products.

`.env.example` uses SQLite so you can try VentaCart with nothing else installed. A live store should use MySQL or MariaDB. See [INSTALL.md](INSTALL.md) for a production setup: web server rules, permissions, the scheduler, the cache store and photo copies.

## Tests

```bash
php artisan test --testsuite=Unit,Feature   # core
php artisan test --testsuite=Xelga          # the bundled theme
```

Core is tested with no theme present, so it never depends on Xelga.

## Themes

A theme lives in `resources/themes/<name>` and reads the store through core: it never queries prices, stock or carts on its own, so customer-group pricing, specials and checkout rules always hold. Each theme keeps its own settings in its own tables, removed when the theme is uninstalled. See [docs/theme-contract.md](docs/theme-contract.md).

## Contributing

Issues and pull requests are welcome. Please run both test suites before opening a pull request, and keep comments, test data and commit messages free of store or customer details.

## License

VentaCart is open source under the [MIT license](LICENSE).
