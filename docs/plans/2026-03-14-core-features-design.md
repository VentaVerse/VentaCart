# Core Features Design — Customer Impersonation, Weight-Based Shipping, Checkout Modal, Theme Refactor

**Date:** 2026-03-14
**Status:** Approved

---

## Feature 1: Customer Impersonation

### Overview
Allow admin users to "log in as" a customer from the admin panel to see the storefront from their perspective, including group-specific pricing and shipping rates.

### How It Works
- Admin views a customer in admin panel, clicks "Login as Customer"
- Admin session (web guard) is preserved, customer guard is logged in simultaneously
- Storefront shows a floating "Impersonating [Name] — Back to Admin" bar at the top
- Clicking "Back to Admin" logs out customer guard, returns to admin customer detail page
- All storefront actions (cart, checkout, orders) work as the impersonated customer
- Session-based, no DB schema changes

### Security
- Only users with active `web` (admin) guard session can impersonate
- Session keys: `impersonating_customer_id`, `impersonate_return_url`
- Orders placed during impersonation flagged in order history ("Order placed by admin")

### Files
- New: `app/Http/Controllers/Admin/ImpersonationController.php` (start/stop)
- Modify: `resources/views/admin/customers/form.blade.php` (add button)
- Modify: `resources/views/storefront/layouts/store.blade.php` (impersonation bar)
- Modify: `routes/web.php` (impersonation routes)

---

## Feature 2: Weight-Based Shipping Module

### Overview
A configurable weight-based shipping provider that calculates rates based on shipping zones, weight brackets, and customer group. Plugs into the existing `ShippingProviderInterface` alongside FlatRate and DPH.

### Shipping Zones
- Admin-defined zones (e.g., Metro Manila, Luzon, Visayas, Mindanao)
- Each zone is a collection of regions, provinces, or individual cities
- Not hardcoded to PH — admin can create any zone structure
- A "Default/Rest of World" zone catches unassigned locations
- Zones assigned by: country, region (ph_regions), province, or city

### Weight Rate Tables
- Per zone: weight brackets with flat costs
- Example: 0-1kg = ₱150, 1-3kg = ₱250, 3-5kg = ₱400
- Admin can add/remove brackets freely
- First matching bracket (weight <= bracket max) determines cost

### Customer Group Pricing
- Each zone's rate table can have overrides per customer group
- Default rates apply to all groups unless overridden
- Example: "Luzon" default 0-1kg = ₱200, "Reseller" group 0-1kg = ₱150
- A customer group can be set to "Free Shipping" for any zone

### Free Shipping Threshold
- Per zone: optional minimum order total for free shipping
- Can be set per customer group per zone
- Example: Resellers free at ₱5,000, retail free at ₱10,000

### Calculation Flow
1. Customer's address at checkout → determine zone (province → region → zone lookup)
2. Sum cart weight from products/variants
3. Find matching weight bracket in zone
4. Check customer group for rate override
5. Check free shipping threshold
6. Return rate

### Database Schema
- `shipping_zones` — id, name, is_default, sort_order, status
- `shipping_zone_regions` — zone_id, country_id, region_id (nullable), province_id (nullable), city_id (nullable)
- `shipping_zone_rates` — zone_id, customer_group_id (nullable for default), weight_from, weight_to, cost, free_shipping_threshold (nullable)

### Admin UI
- Settings > Shipping: enable/disable toggle for weight-based module
- New admin page: Shipping Zones CRUD
  - Zone list with add/edit/delete
  - Zone edit: name, assign regions/provinces, rate table editor
  - Rate table: customer group tabs, weight bracket rows (add/remove)

### Files
- New: `app/Services/Shipping/WeightBasedShippingProvider.php`
- New: `app/Http/Controllers/Admin/ShippingZoneController.php`
- New: `app/Models/ShippingZone.php`, `ShippingZoneRegion.php`, `ShippingZoneRate.php`
- New: `resources/views/admin/shipping-zones/` (index, form)
- New: migrations for 3 tables
- Modify: `routes/web.php` (admin routes)
- Modify: `app/Services/Shipping/ShippingQuoteService.php` (register new provider)

---

## Feature 3: Checkout Confirmation Modal

### Overview
Add a confirmation modal at the Payment step of checkout. When customer clicks "Place Order", a modal shows full order summary before final submission.

### Flow
Step 1 (Address) → Step 2 (Payment) → Click "Place Order" → **Modal appears** → "Confirm & Pay" → Order created

### Modal Contents
- Product list: images, names, qty, line prices
- Shipping address (formatted)
- Billing address (if different)
- Shipping method & cost
- Payment method
- Order notes (if any)
- Subtotal, shipping, total
- Two buttons: "Go Back" (closes modal), "Confirm & Pay" (submits form)

### Implementation
- Alpine.js modal — intercepts form submit, shows summary, then submits on confirm
- No backend changes — modal reads from the already-filled form fields
- Styled per active theme (dark modal for Stage Ready, light for Clean Light)

### Files
- Modify: `resources/views/storefront/checkout/index.blade.php` (add modal markup + Alpine logic)
- Modify: `resources/css/storefront.css` (modal styles — or theme CSS once refactored)

---

## Feature 4: Theme System Refactor

### Overview
Refactor from a single hardcoded storefront theme to a multi-theme system where each theme is a self-contained folder with its own views, CSS, JS, and settings schema.

### Theme Folder Structure
```
resources/themes/
├── stage-ready/
│   ├── theme.json
│   ├── css/theme.css
│   ├── js/theme.js (optional)
│   └── views/
│       ├── layouts/store.blade.php
│       ├── home/
│       ├── product/
│       ├── category/
│       ├── cart/
│       ├── checkout/
│       ├── account/
│       ├── blog/
│       └── partials/
│
└── clean-light/
    ├── theme.json
    ├── css/theme.css
    └── views/ (same structure)
```

### theme.json Schema
```json
{
  "name": "Stage Ready",
  "version": "1.0",
  "author": "NewGearDay",
  "description": "Dark pedalboard theme with amber glow effects",
  "thumbnail": "preview.png",
  "settings": {
    "setting_key": {
      "type": "select|toggle|text|color|number",
      "label": "Human Label",
      "options": [],
      "default": "value"
    }
  }
}
```

### Settings Architecture (Hybrid)
- **Common settings** (shared across themes): site_name, logo, favicon, primary/secondary colors, fonts, announcement bar, navigation, footer — always visible in admin
- **Theme-specific settings**: defined in theme.json, rendered dynamically in admin, stored with theme prefix in DB (e.g., `stage-ready.glow_intensity`)
- Switching themes preserves common settings, swaps theme-specific settings panel

### View Resolution
- Active theme's views take priority
- Fallback to base views if a file doesn't exist in the active theme
- ThemeService updated to resolve: `themes/{active_theme}/views/{path}` → fallback `storefront/{path}`

### Vite Integration
- Each theme's CSS/JS registered as separate Vite entry points
- Only active theme's assets loaded on the storefront
- Admin CSS unchanged (separate pipeline)

### Admin UI
- Theme page: theme selector dropdown at top (shows installed themes with thumbnails)
- Below selector: common settings tabs (same as current)
- Additional tab: "Theme Settings" with dynamically rendered fields from theme.json

### Initial Themes
1. **Stage Ready** — current dark pedalboard theme, moved into theme folder
2. **Clean Light** — new light theme, clean sans-serif, white cards, minimal shadows

### Files
- New: `resources/themes/stage-ready/` (move existing storefront views + CSS)
- New: `resources/themes/clean-light/` (new theme)
- Modify: `app/Services/ThemeService.php` (theme discovery, view resolution, settings schema)
- Modify: `app/View/Components/StoreLayout.php` (resolve theme views)
- Modify: `vite.config.js` (dynamic theme entry points)
- Modify: Admin theme views (theme selector + dynamic settings)

---

## Implementation Order

1. **Theme Refactor** (foundation — other features depend on theme-aware views)
2. **Customer Impersonation** (independent, quick win)
3. **Weight-Based Shipping** (needs shipping zones schema + admin UI)
4. **Checkout Confirmation Modal** (depends on theme structure being in place)
