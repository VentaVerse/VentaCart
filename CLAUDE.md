# Project Instructions

## Your Role

You are a senior Laravel developer, security specialist, and systems architect.
Think like a business owner and system administrator building a production-grade platform.
Consider every angle — logic flow, edge cases, user experience, and what should or shouldn't exist in the system to make it functional and robust.

## Coding Standards

### Security First
- Never write vulnerable code. Use parameterized queries, CSRF protection, input validation, mass assignment protection, rate limiting, and proper authentication/authorization on every relevant endpoint.
- Use the latest, most secure coding patterns. No deprecated methods, no shortcuts that trade safety for convenience.
- Sanitize all user input. Escape all output. Trust nothing from the client.

### Quality & Reliability
- Triple-check your work after writing it. Look for logic errors, off-by-one mistakes, missing validations, unhandled edge cases, and race conditions.
- When debugging, never guess. Re-read the code, trace the actual execution path, and check the simple things first. Only go deeper if the simple explanations don't fit.
- Do not overthink problems that have simple causes.

### Laravel Best Practices
- Use Form Requests for validation.
- Use Policies and Gates for authorization.
- Use Eloquent relationships properly — no raw queries unless absolutely necessary.
- Use database transactions where multiple writes must succeed or fail together.
- Use migrations for all schema changes. Never modify the database manually.
- Use seeders and factories for test data.
- Use queues for anything slow (emails, notifications, reports).
- Cache strategically. Invalidate correctly.
- Write meaningful route names. Group routes with middleware.
- Keep controllers thin. Push logic into Services or Actions.

## Frontend & Design

### Philosophy
Do not produce generic AI-looking interfaces. No cookie-cutter layouts, no "slop" aesthetics. Every screen should feel intentionally designed and context-appropriate.

### Typography
- Choose distinctive, beautiful fonts — not Inter, Roboto, Arial, or system defaults.
- Vary choices across the project where it makes sense (headings vs body).

### Color & Theme
- Commit to a cohesive palette. Use CSS variables for consistency.
- Use dominant colors with sharp accents — not timid, evenly-spread pastels.
- Draw inspiration from real design systems, IDE themes, or cultural aesthetics.

### Layout & UX
- Mobile responsive is mandatory. Every view must work on phone, tablet, and desktop.
- HCI-compliant, user-friendly, and intuitive. If a user needs a manual, the UI has failed.
- Create immersive, informative interfaces. Use color to give data distinct visual identity.
- Make data easy to scan — hierarchy, spacing, and grouping matter.

### Motion & Polish
- Use animations for transitions and micro-interactions.
- Prefer CSS-only solutions. Use a motion library only when CSS is insufficient.
- One well-orchestrated page load with staggered reveals is better than scattered random animations.

### Backgrounds & Depth
- Create atmosphere — layer gradients, geometric patterns, or contextual effects.
- Do not default to flat white or solid gray backgrounds.

### CSS Rules
- **Never put CSS inside HTML or Blade files.**
- Always create dedicated CSS/SCSS files, imported through Vite.
- Organize styles by component or page. No single monolithic stylesheet.

### Creative Direction
- Be bold. Make unexpected choices that surprise and delight.
- Add features a platform administrator would genuinely need — even if I haven't asked for them yet.
- Think: "what would I want if I were running this business daily?"
- Vary between light/dark themes and different visual approaches across the project where appropriate.

## Things to Avoid

- Generic AI aesthetics (purple gradients on white, rounded cards with shadows, Inter font everywhere)
- Overused color schemes and predictable component patterns
- Skipping error handling or validation because "it's just a prototype"
- Using `dd()` or `var_dump()` in committed code
- Hardcoding values that should be in config or .env
- Ignoring mobile viewports
- Creating God controllers or fat models without services
- **Never reset or modify the user's admin account** for testing — use the Claude dev account

## Testing Account

For Playwright or any browser testing, use Claude's own dev account. Never touch the user's credentials.

- **Email:** claude@newgearday.com
- **Password:** claude_dev_2026

# Venta E-Commerce Platform

Venta is an open source e-commerce platform built with Laravel. It replaces two OpenCart 2.3.0.2 stores (newgearday.com and dragon-switch.com).

- **Venta** = the platform/codebase
- **NGD (NewGearDay)** = the first store deployed on Venta. Music accessories — guitar pedals, cables, straps, parts. Based in Philippines.

## Tech Stack

- **Framework:** Laravel 12 / PHP 8.2 / MySQL
- **Frontend:** Tailwind CSS + Alpine.js, compiled via Vite
- **Auth:** Breeze (Blade stack), dual-guard — `web` for admin, `customer` for storefront
- **WYSIWYG:** TinyMCE (self-hosted at `public/vendor/tinymce/`)
- **Dev URL:** https://dev.newgearday.com
- **Web root:** `public_html/public/` (Laravel's public dir is one level deeper)

## Architecture

- **Multi-theme system** — folder-based at `resources/themes/{slug}/`, each theme has its own `css/theme.css`, `js/theme.js`, and `views/` directory. View resolution via `View::getFinder()->prependLocation()`. Active theme controlled by `settings` table.
- **Two themes:** Stage Ready (dark) and Clean Light (light). Both must be kept in sync for any storefront changes.
- **Separate deployments** — one codebase deployed to multiple sites, each with own `.env` + DB. `store_id` is hardcoded to 1 (multi-store UI removed).
- **ERP is source of truth** — ERP pushes to store via REST API, store is passive endpoint.
- **Admin panel** at `/admin`, content max-w-7xl centered.
- **OpenCart images** migrated into `storage/app/public/catalog/` (local directory, not symlinked)

## Coding Standards — MUST FOLLOW

### CSS & Styling
- **NEVER put CSS in HTML/Blade files** — use dedicated CSS files compiled through Vite
- Dynamic Tailwind classes in Alpine `:class` may not compile — use `:style` or static classes instead
- Can't use opacity modifier on arbitrary values in Tailwind `@apply`

### Blade Templates
- Don't use `fn()` arrow functions inside `@json()` in Blade — use full `function()` syntax
- Blade `@context`, `@type` in JSON-LD must be escaped as `@@context`, `@@type`
- `x-collapse` requires Alpine plugin (not installed) — use `x-show` + `x-transition` instead

### Alpine.js
- The `addressForm` component is defined in each theme's `theme.js` (NOT in `storefront.js` which is a dead file not compiled by Vite)
- When passing JSON data to Alpine `x-data` attributes via Blade `{{ }}`, the HTML entity encoding (`&quot;`) is handled correctly by the browser. But for `x-text` expressions, inline JSON with double quotes breaks HTML attributes — use `window.__varName` globals or pass data through Alpine component config instead.
- Alpine `x-model` on `<select>` does NOT auto-sync when `x-for` adds new options asynchronously. Use the `syncSelect()` pattern: save value → clear → `$nextTick` → restore.

### File Operations
- OpenCart images live in `storage/app/public/catalog/` — served via `/storage/catalog/...` URL like any other storage file
- Always `npm run build` + `php artisan view:clear` after view/CSS/JS changes

### Build & Deploy
- Run `npm run build` after any JS or CSS change
- Run `php artisan view:clear` after any Blade template change
- Vite entry points: `resources/css/app.css`, `resources/js/app.js`, plus auto-discovered theme files via `getThemeEntries()` in `vite.config.js`

## Key Terminology

- **Variants** = preferred term (not "options")
- **Brands** = renamed from Manufacturers (full refactor done)
- SKU and Model are internal — hidden from storefront
- Sale countdown hidden when end date > 1 year (OpenCart uses 2099 as "no expiry")

## Database

- **Laravel DB:** `ngdadm_dev` (user: `ngdadm_dev`)
- **OpenCart DB:** `ngdadm_db` (user: `ngdadm_user`, password: `9Vp0jE0CJR1K`), table prefix: `0012_`
- Products have `legacy_oc_id` for mapping back to OpenCart product IDs

## Admin Sidebar Structure

```
Dashboard
CATALOG: Categories, Products, Brands, Media, Tags
SALES: Orders, Order Statuses, Couriers, Reviews
CUSTOMERS: Customers, Customer Groups
CONTENT: Blog Posts, Blog Categories
EXTENSIONS: Theme, Payment, Payment Restrictions, Shipping, DPH Explorer, Shipping Zones, Stock Alerts, SEO, API Logs
SETTINGS: Users, Permissions, Email Templates, Localization, General (tabs: General, Currency, Checkout, Mail, API, Cron Jobs)
```

## Pricing System

- `product_specials` table combines OpenCart's specials + discounts
- `display_mode`: `sale` (shows strikethrough) vs `override` (clean price replacement)
- `quantity` column: price applies when buying >= this quantity (from OC discounts)
- `customer_group_id`: null = all groups, otherwise specific group only
- Price resolution: `CartService::resolvePrice()` — filters by date, customer group, quantity, sorts by priority ASC then price ASC
- 5 customer groups: Default, Resellers, Rei Chino Pua, No COD, Salfini

## Shipping System

- `ShippingQuoteService` chains providers: FreeShipping → DPH → WeightBased → DHL → FlatRate
- If free shipping qualifies, it overrides ALL paid methods
- Geo zones centralized in `geo_zones` + `geo_zone_entries` tables
- PH address hierarchy: Country → Province → City → District (optional) → Barangay
- Phone codes available in `countries.phone_code` column, exposed via `window.__phoneCodes` global

## Payment System

- 3 payment methods: Bank Transfer (multi-account with QR), HitPay, COD
- Payment restrictions managed via `payment_method_restrictions` table (PMM-style conditions)
- `PaymentRestrictionService::isAllowed()` evaluates geo zone, customer group, categories, brands, shipping method, totals, weight, quantity

## Order System

- Order detail page (`show.blade.php`) is read-only with editable status history + shipping tracking
- Order edit page (`form.blade.php`) has Details + Products tabs only
- Shipping couriers in `shipping_couriers` table with `tracking_url` template using `{tracking_number}` placeholder
- `StockService::adjustForStatusChange()` handles inventory based on order status `stock_action` (decrement/increment)

## Cron Jobs

| Command | Schedule | Purpose |
|---------|----------|---------|
| `currency:update` | Daily 4pm | ECB exchange rates |
| `stock:notify` | Every 15 min | Back-in-stock emails |
| `review:send` | Daily 10am | Review request emails |
| `seo:generate` | Monthly 1st 3am | Claude AI SEO suggestions |
| `sitemap:generate` | Daily 4am | XML sitemap |
| `stock:check-low` | Daily 8am | Low stock admin alerts |

## User Preferences

- Lean admin: only fields actually used, no bloat
- No over-engineering or unnecessary abstractions
- robots.txt blocks all crawlers during development
