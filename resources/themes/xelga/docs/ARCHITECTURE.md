# Xelga: architecture

This is the design of Xelga, VentaCart's bundled storefront theme, and its theme editor. Read `PRODUCT.md` first for who the theme is for and its principles.

Words used in this document:

- **Section**: one band of a page (Main banner, Featured products). It has a type, settings and, optionally, blocks.
- **Block**: a repeatable item inside a section (a slide, a promise, a footer menu column).
- **Document**: one saved unit of editor state. There are three kinds: the theme settings, a section group (header or footer, shared by every page) and a page template.
- **Template**: the ordered sections of one page type (home, product, category, and so on), or a custom template that can be assigned to individual products, categories, brands or pages.
- **Draft** and **published**: every document has a published version that shoppers see and, optionally, a draft that only the editor preview shows.
- **Catalog**: the set of section schemas, theme setting groups and font pairs. It is the single source of truth for forms, validation and rendering.

## 1. Constraints this design follows

1. Xelga never writes core tables. Everything the editor saves lives in `xelga_*` tables created by the theme's own migrations and dropped when the theme is uninstalled (`"storage": "own"` in `theme.json`).
2. Core business rules win. Products, prices, stock, visibility per customer group, specials, shipping and payment rules are read through core models, scopes and services: `Product` (with its global `CustomerGroupVisibilityScope`), `App\Support\Pricing::display()`, `PageContext`, `NavTree`, `PaymentFamilies`, `StoreIdentity`, `ProductAttributes`, `Reviews`. No raw `DB::table('products')`, no `withoutGlobalScope`. Buying goes only through `cart.add`, `cart.update`, `cart.remove` and the wishlist routes.
3. Core must work, and pass its tests, with no Xelga present. Core never imports a Xelga class; anything Xelga needs from core is a generic core feature with its own tests in core.
4. The project's public code rules apply to every file: no brand or competitor names in identifiers, "Variants" not options, "Brands" not manufacturers, US spelling, neutral test fixtures, one finished migration per feature, no CSS inside Blade.
5. Simple before clever. One shared draft per document, JSON documents rather than a normalized section table, server rendering for anything data driven, no HTML caching in the first release.

## 2. Package layout

The theme ships with VentaCart at `resources/themes/xelga` and is loaded by the theme engine like any theme: its `src/` is autoloaded at runtime by the extension kernel, not by Composer. Namespace `Themes\Xelga`, slug `xelga`, table prefix `xelga_`.

```
resources/themes/xelga/
  theme.json                      identity, provider, editor route, storage: own, regions
  PRODUCT.md
  docs/ARCHITECTURE.md
  src/
    XelgaServiceProvider.php      routes, views, permissions, middleware, listeners; never writes data on boot
    Catalog/
      Catalog.php               loads and caches every schema file; the one reader of the catalog
      SectionSchema.php           one section type: settings, blocks, variants, presets, limits, allowed templates
      SettingSchema.php           one setting: type, default, bounds, options, live mode
      CatalogLinter.php         checks every schema file (used by tests and by `php artisan xelga:lint`)
    Settings/
      Sanitizer.php               value in, clean value out, per setting type (section 4)
      RichTextPolicy.php          the narrow HTML allowlist for richtext settings
      LinkValue.php               structured links: resolve to a URL at render time
      ContrastCheck.php           WCAG contrast for color schemes
    Documents/
      Document.php                Eloquent model for xelga_documents
      Revision.php                Eloquent model for xelga_revisions
      Assignment.php              Eloquent model for xelga_assignments
      DocumentWriter.php          the only code that writes documents: validate, sanitize, save draft
      Publisher.php               publish, discard, restore revision; one transaction
      Shape.php                   structural validation of a document's JSON (ids, order, limits)
    Rendering/
      TemplateResolver.php        page type + subject -> template handle
      DocumentRepository.php      loads the documents a request needs, draft or published, in one query
      SectionRenderer.php         renders a document's sections with their data
      SectionData.php             interface for per-section data providers
      Tokens.php                  builds the tokens stylesheet from theme settings
    Sections/                     one data provider per data-driven section type
      FeaturedProducts.php, ProductSpotlight.php, CategoryTiles.php, BrandLogos.php,
      BlogPosts.php, ShopTheLook.php, Header.php, Footer.php, ProductMain.php, CategoryMain.php, ...
    Preview/
      PreviewSession.php          opens, checks and closes the draft preview window
      PreviewMode.php             middleware: decides per request whether drafts are shown
      SectionOnly.php             middleware: returns one section's HTML from a full preview render
    StoreTemplates/
      StoreTemplates.php          reads the shipped starting stores
      StoreTemplateApplier.php    applies one to the draft, and undoes that
      Portable.php                ids <-> slugs for export, import and store templates
    Http/
      Controllers/EditorController.php     the editor shell and its boot data
      Controllers/DocumentController.php   read, save draft, publish, discard, revisions
      Controllers/PickerController.php     read-only lookups for pickers
      Controllers/StoreTemplateController.php
      Controllers/TokensController.php     the tokens stylesheet
      Requests/SaveDocumentRequest.php, PublishRequest.php, PickerRequest.php
    Console/LintCatalog.php     php artisan xelga:lint
  schemas/
    sections/{type}.json          one file per section type (section 3)
    theme-settings.json           the theme settings groups
    font-pairs.json               the matched font pairs, with local font files
  views/                          prepended to the view finder while Xelga is live
    layouts/store.blade.php       draws header group, page content, footer group, required regions
    home.blade.php, category/index.blade.php, product/show.blade.php, ...   every view ThemeContract requires
    sections/{type}.blade.php     one view per section type
    blocks/{section-type}/{block-type}.blade.php   block views
    snippets/                     shared pieces: product-card, price, picture, link, icon, rating, badge
    partials/product-card.blade.php   required by the contract; includes snippets.product-card
    editor/shell.blade.php        the full-screen editor page
  css/
    theme.css                     the storefront bundle (imports base, components and every section file)
    base/, components/, sections/{type}.css
    generated/ranges.css          attribute selectors for finite range settings (built by bin/ranges.mjs)
    pages/checkout.css, pages/account.css   page-only bundles, loaded by those views
  js/
    theme.js                      storefront behavior: section initializers, cart, wishlist, drawers
    sections/{type}.js            imported by theme.js
  editor/
    css/panel.css, js/panel.js    the editor app (Vite entries the theming build already discovers)
    css/frame.css, js/frame.js    the preview half of the bridge, loaded only in preview mode
    js/modules/                   state.js, operations.js, bridge.js, forms/, pickers/, api.js
  assets/
    icons.svg                     the one icon sprite
    fonts/                        self-hosted woff2 files for every font pair (SIL OFL)
  store-templates/{slug}/
    store.json                    portable documents for one starting store (section 9)
    images/                       that store's photos
  database/migrations/
    2026_11_20_100000_create_xelga_theme_tables.php
  routes/admin.php, routes/web.php
  bin/ranges.mjs                  generates css/generated/ranges.css from the schemas
  tests/
    Unit/, Feature/, Browser/
```

### 2.1 theme.json

```json
{
  "name": "Xelga",
  "version": "2.0.0",
  "author": "VentaVerse",
  "description": "A storefront theme with its own click-to-edit editor: sections, color schemes, matched fonts and eight starting stores.",
  "thumbnail": "thumbnail.png",
  "provider": "Themes\\Xelga\\XelgaServiceProvider",
  "editor": "admin.xelga.editor",
  "storage": "own",
  "widgets": false,
  "regions": {
    "header_top": { "label": "Above the header" },
    "content_top": { "label": "Above the page content" },
    "content_bottom": { "label": "Below the page content" },
    "footer_top": { "label": "Above the footer" }
  }
}
```

- The version starts at 2.0.0 so a store that ever recorded an earlier `xelga` version sees an update rather than a downgrade.
- No `settings` key: Xelga's settings live in its own schema files and tables, not in `theme_settings.*`.
- The four required regions are drawn in `layouts/store.blade.php` with `<x-storefront.region>`. With own storage nothing is placed there, so they render empty and cost nothing. They are kept so the contract holds.

### 2.2 Build

VentaCart's `vite.config.js` discovers these entries for every theme: `css/theme.css`, `js/theme.js`, `editor/css/panel.css`, `editor/js/panel.js`, `editor/css/frame.css`, `editor/js/frame.js`, and every `css/pages/*.css`. `npm run build` in the VentaCart folder builds them with the rest of the store.

For a theme sold as a download, the theme contract describes a `build/` folder with the built entries and a Vite manifest. The `build/` folder holds only CSS, JS, fonts, raster images and JSON; the icon sprite is inlined into the layout as a Blade include rather than shipped as an `.svg` file, because the uploader refuses `.svg` in `build/`.

### 2.3 Storefront CSS and JS

- Plain CSS with custom properties and BEM-style classes (`.s-hero`, `.s-hero__media`), in `css/`. No Tailwind: the look is driven by runtime tokens and data attributes, which a utility build cannot purge safely.
- Theme and scheme tokens come from a generated stylesheet served by `TokensController` (section 6.4), never from an inline `<style>`.
- Section settings reach CSS as data attributes (`data-variant`, `data-pad`, `data-cols="4"`). Every `range` setting is finite (min, max, step), so `bin/ranges.mjs` writes one attribute selector per value into `css/generated/ranges.css` (`.s[data-cols="4"]{--cols:4}`). This keeps the house rule of no CSS in HTML without losing numeric settings.
- Plain JavaScript, no framework on the storefront, one initializer per section that returns a cleanup function, so the editor can re-run it after a live change. Cart and wishlist calls post to core's routes with the CSRF token and update the header count from the JSON answer.
- Accessibility: real buttons and links, a visible focus ring, carousels with pause and labelled controls, `prefers-reduced-motion`, drawers with focus trap and Escape, prices read as one phrase, form errors tied to fields.

## 3. The catalog

### 3.1 Where schemas live

One JSON file per section type in `schemas/sections/{type}.json`, beside its view `views/sections/{type}.blade.php`, its CSS `css/sections/{type}.css` and, if it has behavior, `js/sections/{type}.js`.

`Catalog` reads every file once and caches the compiled result in `bootstrap/cache/xelga-catalog.php`, keyed by the theme version plus the newest file time (file times are checked only when `app.debug` is on). The editor receives the compiled catalog in its boot payload, so adding a section type or a variant means adding a schema file and a view: no editor code.

Rejected: a schema block inside the Blade file. It needs a custom Blade parser and is invisible to tools; a JSON file next to the view is just as local and is readable by PHP, the linter and the editor alike.

### 3.2 Section schema shape

```json
{
  "type": "hero",
  "label": "Main banner",
  "description": "The large banner at the top of the page.",
  "group": "template",
  "templates": ["home", "page", "category", "brand", "blog", "*custom"],
  "limit": null,
  "main": false,
  "class": "s-hero",
  "variants": [
    { "value": "full-image", "label": "Full-width photo", "description": "One photo across the page with text on top." }
  ],
  "settings": [
    { "key": "variant", "type": "select", "label": "Layout", "default": "full-image", "options": [...], "control": "cards" },
    { "key": "overlay", "type": "range", "label": "Darken photo", "default": 35, "min": 0, "max": 80, "step": 5, "unit": "%" },
    { "key": "heading_size", "type": "select", "label": "Heading size", "default": "large", "options": [...], "advanced": true }
  ],
  "blocks": {
    "min": 1, "max": 5,
    "types": [ { "type": "slide", "label": "Slide", "limit": null, "settings": [...] } ]
  },
  "presets": [
    { "key": "slideshow", "label": "Slideshow with three slides", "settings": { "variant": "slideshow" },
      "blocks": [ { "type": "slide", "settings": {} }, { "type": "slide" }, { "type": "slide" } ] }
  ]
}
```

Keys a section schema may carry:

| key | meaning |
|---|---|
| `templates` | the template handles the section may be added to; `*custom` means custom templates of those types; `*` everywhere. Header and footer group sections use `group` instead. |
| `limit` | most instances per document (header: 1). |
| `main` | the page's main section (product information, category listing). Cannot be removed, duplicated or hidden; exactly one per template of its type. |
| `presets` | named starting points the Add section panel offers; a preset is settings plus blocks. Without presets the panel offers the section's variants with defaults. |
| `blocks.types[].limit` | most blocks of that type in one section. |
| setting `live` | how the preview applies a change: `text`, `attr`, `css`, or `render` (server re-render, section 7.5). Inferred when absent: picker types, `url` on a data-driven section, and any setting named in the section's `render_keys` are `render`; text types are `text`; everything else `attr`. |
| setting `visible_if` | unchanged from the draft: shown only when every listed key matches. |
| setting `options_source` | options filled at boot from core data: `blog_categories`, `menus`, `font_pairs`. |

Every label is plain words about the visible result. Settings marked `advanced` sit behind the Advanced disclosure.

A theme settings group (`schemas/theme-settings.json`) may carry `edited_in: { "section": "header", "after": "show_wishlist" }`. Its fields then show in that section's panel, after the named field, and the group is left out of the Theme settings list. Only where they show changes: the values stay theme settings, stored, sanitized, saved (with the settings document's own `draft_version`), published and undone with the settings document. The editor marks those fields `store: 'theme'` in the section's form, so a change commits a `theme` operation, not a `set` on the section. The linter checks that the section exists, that `after` is one of its settings and that no key is used by both. Header behavior (cart icon, the number on it, the side the phone menu opens from) is edited this way, in the Header section.

### 3.3 Section types

The home page section types: `announcement-bar`, `header`, `hero`, `usp-bar`, `category-tiles`, `featured-products`, `product-spotlight`, `image-with-text`, `promo-banners`, `shop-the-look`, `brand-logos`, `testimonials`, `rich-text`, `blog-posts`, `footer`.

Main sections, one per page type (`"main": true`). Each is drawn in every template of its page type: the editor cannot remove or hide it, and `Storefront::withMainSection()` puts the shipped one back if a stored document lost it. Its blocks are the page's parts, so the merchant can reorder and hide them.

| type | template | blocks | settings |
|---|---|---|---|
| `product-main` | product, custom | gallery, brand, title, rating, price (sale badge, countdown), short-description, variants, stock, volume-pricing, quantity-and-buy (wishlist, back in stock), delivery-note (three lines), share | photo layout (thumbnails below or left, stacked, grid), color variants as swatches or buttons, other variants as buttons or dropdown, full screen zoom, buy bar (phones, every screen, off) |
| `product-details` | product, custom | description, specifications (core attribute groups), text panel (e.g. a size guide, optionally linked from the variants) | tabs or accordion, first panel open |
| `product-reviews` | product, custom | none | heading, reviews shown first (Show more for the rest), how reviews are collected, show when empty; hidden while the reviews extension is off |
| `related-products` | product, custom | none | source (same categories, same brand), count, grid or swipe row, per row |
| `recently-viewed` | every template | none | count, grid or swipe row, per row; hidden until the shopper opened a product |
| `breadcrumbs` | every page but home | none | show the current page |
| `category-main` | category, custom | none | description, image, subcategories (chips, photo tiles, hidden), filters (sidebar or drawer), per row, per row on phones, grid or list and whether shoppers switch, page numbers or load more |
| `brand-main` | brand, custom | none | logo, description, product count, then as `category-main` |
| `brand-list-main` | brand-list | none | heading, per row, logos, counts |
| `search-main` | search | link (shown when nothing matches) | search box, suggested searches, per row, grid or list |
| `cart-main` | cart | none | free shipping note, coupon field, shipping estimate, cash on delivery note, payment methods, empty cart text and link |
| `blog-main` | blog | heading, search, categories, posts | grid or list, per row, date, category, summary |
| `post-main` | blog-post, custom | title, image, body, tags | text and photo width |
| `page-main` | page, custom | title, pages menu, body | menu beside or text only |
| `contact-main` | contact | heading, details, form (core's contact form unchanged) | details beside or above |
| `not-found-main` | not-found | heading, search, links | |

Settings core already owns stay in core and are only read: which listing filters show (core's sidebar filter setting), products per page (core's listing setting, shown read-only in the editor), the default sort, and the blog's page size. The product reviews "Write a review" button is not drawn: core collects reviews only through the tokened link it emails buyers. Breadcrumbs are their own section on the shop pages and part of the main section on the blog, post, page, contact and not-found pages.

The cart drawer is part of the layout, not a section: theme settings Cart and checkout display choose what Add to cart does (open the drawer, show a short message, or go to the cart page), whether the drawer offers the coupon field, and its note. It opens from the header's cart icon on every page except the cart and checkout, reads core's `cart.mini`, and changes the cart only through `cart.update`, `cart.remove` and the coupons extension's `checkout.coupon.apply` / `checkout.coupon.remove` (signed-in shoppers only, as core requires).

Checkout, the order confirmation, sign-in, registration, password reset and the account pages are fixed views. They follow theme settings (fonts, schemes, buttons, radii) and show the header and footer groups, but have no editable template. These pages carry money, addresses and passwords; composition there adds risk and no merchant value.

### 3.4 Settings that defer to core

| setting | how it works | why |
|---|---|---|
| `product-spotlight` countdown | the countdown reads `DisplayPrice::endsAt` of the product's special and hides when there is none or it is more than a year away | the special's end is a core price rule; a typed date can disagree with what the cart charges |
| payment icons | one toggle `show_payment_icons`; the icons shown are `PaymentFamilies::enabledFamilies()` | which payments a store takes is core's setting |
| phone, email, address | read from the store record (`StoreIdentity`, `stores` row); the editor shows them read-only with a link to Settings | one place for the store's contact details |
| `header.menu`, footer `menu` blocks | `menu-picker` stores the `nav_menus` key; menus are edited in core's Content > Menus screen | menus are core data |
| every `url` setting | structured link value (section 4.2), such as `{"type":"search"}` or `{"type":"home"}` | slugs change; a typed address goes stale |
| `blog-posts.blog_category` | `options_source: blog_categories`; the section hides itself when the blog extension is off | the blog is an extension |

The `announcement-bar` messages are the theme's own content (core keeps one announcement line; the theme's bar rotates up to three). On first install the first message is seeded from core's announcement line when a store still has one, read only.

Theme settings `social-contact` keeps the social links and opening hours (display text the store record does not hold).

## 4. Setting types, validation and sanitizing

All writes go through `DocumentWriter`, which runs `Shape` (structure) then `Sanitizer` (values). Structural errors answer 422 with field paths. Value problems are corrected (clamped, defaulted, stripped) and the response lists them under `adjusted` so the editor updates its state and can tell the merchant.

| type | stored as | server rule |
|---|---|---|
| `text` | string | `SafeInput::text($v, max)`, max 255 unless the schema says less; no line breaks |
| `textarea` | string | `SafeInput::text($v, max, multiline: true)`, max 2000 |
| `richtext` | string (HTML) | `RichTextPolicy`: Symfony HtmlSanitizer allowing only `p, br, strong, em, a[href], ul, ol, li`; `href` must pass `SafeInput::link`; links to other hosts get `rel="noopener"`; max 5000 characters after cleaning. Stricter than core `RichText` on purpose: section text never needs images, tables or inline styles. |
| `image` | string, public storage path | `SafeInput::storagePath`; file must exist on the public disk; extension one of jpg, jpeg, png, webp, avif, gif. SVG refused (script risk on the store's own origin). |
| `url` | link value | see 4.2 |
| `select` | string | must be one of `options` (or of the boot-time `options_source` list); else default |
| `range` | int or float | `SafeInput::number`, clamped to min and max, snapped to step |
| `toggle` | bool | strict boolean |
| `color-scheme` | string | an existing scheme id in the theme settings document, else `scheme-1` |
| `product-picker` | int, or list of int when `multiple` | ids that exist (admin context sees every product); de-duplicated; cut to `max` |
| `category-picker` | int | existing category id |
| `brand-picker` | int | existing brand id (new type for brand tiles that link to a brand) |
| `menu-picker` | string | an existing `nav_menus` key |
| `page-picker`, `blog-post-picker` | int | existing ids |
| `scheme-colors` | object | per role a 6-digit hex (`SafeInput::color`); see 4.3 |
| `font-pair` select | string | one of `font-pairs.json` |

Text is never stored escaped; it is escaped on output with `{{ }}`. Rich text is the only value printed with `{!! !!}`, and only after `RichTextPolicy` ran on write. Store template application and import go through the same writer, so no path skips sanitizing.

Document limits: 40 sections per template, block counts from the schema, 256 KB of JSON per document, ids matching `^[a-z0-9-]{1,40}$` and unique within the document.

### 4.1 Defaults and forward compatibility

Stored settings hold only what the merchant set. At render time each section's settings are merged over the schema defaults, so a new setting in a theme update needs no data migration. A setting removed from a schema is ignored on render and dropped the next time the document is saved. A section whose type no longer exists renders nothing for shoppers and shows as "This section is no longer available" in the editor, with Remove.

### 4.2 Link values

```json
{ "type": "category", "id": 12 }
{ "type": "product", "id": 87 }
{ "type": "url", "href": "https://example.com/size-guide" }
{ "type": "home" }    // also: search, cart, account, wishlist, blog, brands, contact
```

`LinkValue::resolve()` turns a value into a URL with core's route names at render time. A target that no longer exists or is hidden from the shopper resolves to null and the link or button is not drawn. `href` must pass `SafeInput::link` (a path, an anchor, http, https, mailto or tel).

### 4.3 Color schemes

The theme settings document holds 3 to 8 schemes, each with an id, a label and nine roles (`bg`, `text`, `muted`, `accent`, `accent-text`, `button`, `button-text`, `border`, `card`). `ContrastCheck` computes contrast for text on bg, muted on bg, button-text on button and accent-text on accent. Below 4.5:1 for text and button pairs (3:1 for muted) the draft saves with a warning shown beside the color; Publish refuses until fixed, naming the scheme and the pair. Warning while editing and refusal at publish keeps the guardrail without fighting a merchant halfway through choosing a color.

## 5. Data model

Core is single-store in practice: catalog tables carry `store_id`, but settings, menus and layouts do not, and nothing resolves a store per request. Xelga therefore has no `store_id`. If core gains per-request stores, a later migration adds `store_id` to `xelga_documents` and `xelga_assignments` with the existing store as default and changes the unique key to `(store_id, handle)`.

One migration, `database/migrations/2026_11_20_100000_create_xelga_theme_tables.php`, creates all three tables. Its `down()` drops them, which is what uninstall runs.

### 5.1 xelga_documents

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| handle | varchar(64) unique | `settings`, `header`, `footer`, `home`, `product`, `category`, `brand`, `brand-list`, `search`, `cart`, `blog`, `blog-post`, `page`, `contact`, `not-found`, or a custom template `product.gift-guide` |
| kind | varchar(16) | `settings`, `group`, `template` |
| page_type | varchar(32) null | for templates: the page type it serves |
| label | varchar(80) null | the name a custom template shows |
| published | json null | the live content; null means never published |
| published_version | int unsigned default 0 | bumped on every publish |
| published_at | timestamp null | |
| published_by | bigint null | admin user id (no foreign key to a core table) |
| draft | json null | null means no unpublished changes |
| draft_version | int unsigned default 0 | bumped on every draft save; the concurrency token |
| draft_base_version | int unsigned null | the `published_version` the draft started from |
| draft_updated_by | bigint null | |
| draft_updated_at | timestamp null | |
| timestamps | | |

Rejected: a row per section and per block. Every editor read and write is a whole page; reordering would rewrite many rows; draft and publish would copy row sets; revisions would need copies of both tables. Nothing needs to query inside a section except "which documents use this image or product", which a scan of a few dozen small JSON documents answers.

Rejected: a separate drafts table. Draft and published sit in the same row so publishing is one `UPDATE` per document and the two can never refer to different documents.

### 5.2 Document content

Settings document:

```json
{ "schema": 1,
  "values": { "font_pair": "archivo-public", "card_ratio": "square", "density": "comfortable" },
  "schemes": [ { "id": "scheme-1", "label": "Light", "colors": { "bg": "#ffffff", "text": "#16181d" } } ] }
```

Group and template documents:

```json
{ "schema": 1,
  "order": ["hero-k2p9", "products-a81c"],
  "sections": {
    "hero-k2p9": {
      "type": "hero", "label": "Main banner", "disabled": false,
      "settings": { "variant": "slideshow", "overlay": 35 },
      "block_order": ["slide-x1", "slide-x2"],
      "blocks": {
        "slide-x1": { "type": "slide", "label": "Slide 1", "disabled": false,
                      "settings": { "image": "xelga/banner-1.jpg", "heading": "Everything for your next build",
                                    "button_link": { "type": "category", "id": 4 } } }
      }
    }
  } }
```

A map plus an order list keeps ids stable and makes move, remove and duplicate one-line operations. `schema` is the document format version; `Shape` upgrades older formats on read.

### 5.3 xelga_revisions

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| document_id | fk xelga_documents, cascade delete | |
| version | int unsigned | the `published_version` it became |
| content | json | the published content at that moment |
| source | varchar(16) | `publish`, `restore`, `store-template`, `import` |
| created_by | bigint null | |
| created_at | timestamp | |

Index `(document_id, version)`. The newest 30 per document are kept; older ones are pruned inside the publish transaction.

### 5.4 xelga_assignments

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| subject_type | varchar(16) | `product`, `category`, `brand`, `page`, `blog_post` |
| subject_id | bigint unsigned | the core row's id |
| document_id | fk xelga_documents, cascade delete | must be a custom template of the same page type |
| timestamps | | |

Unique `(subject_type, subject_id)`. Assignments take effect at once and always point at the custom template's published content; a custom template never published falls back to the page type's template. The provider listens for core models' `deleted` events and removes matching rows; the resolver also ignores rows whose subject no longer exists.

Custom templates (the editor calls them special layouts) exist for `product`, `category`, `brand`, `page` and `blog-post` (`Handles::CUSTOMIZABLE`). The handle is the page type, a dot and a name of lower case words joined by hyphens, at most 48 characters (`product.gift-guide`). `TemplateManager` is the only code that makes, renames or deletes one:

- **Make**: a copy of the page type's template or of another custom template of the same page type, draft included, saved as a draft (shoppers see it only after Publish). Sections a custom template may not hold (`*custom` missing from their `templates`) are left out and named in the answer. Names are unique per page type, ignoring case; at most 30 per page type.
- **Rename and delete** take effect at once, not through the draft. Delete removes the row, its revisions and its assignments in one transaction.
- **Assign** names up to 50 subjects of one kind and a handle, or a null handle to clear. Subjects must exist in core (read through core's models) and the handle must be a custom template of the subject's page type. One upsert keyed on the unique subject moves a subject from another layout; the answer lists what moved.
- **Discard** (Publisher) deletes custom templates that were never published, with their assignments: they have nothing live to go back to.

A page type's main section (`"main": true`, found in the page type's shipped default template) cannot be removed or hidden from any template of that page type, custom ones included; `Shape` refuses a draft without exactly one, or with it hidden. Its blocks can be hidden and reordered.

### 5.5 Media

Images are files in core's media library (`MediaLibrary`, admin routes `admin.media.browse` and `admin.media.upload`, permissions `media.view` and `media.create`). Documents store the storage path. Xelga keeps no media table. Store templates copy their photos into the library under `xelga/{store-slug}/`.

### 5.6 Concurrency

There is one shared draft per document, not one per admin: the store has one draft, which is what the editor's "draft changes to this store" copy describes.

- Every draft save sends the `draft_version` it was based on. The writer runs `UPDATE ... WHERE id = ? AND draft_version = ?`. Zero rows changed means someone else saved first: the answer is 409 with the current draft, who saved it and when. The editor offers "Load their changes" (your unsaved operations are kept in undo history and can be re-applied) or "Replace with mine".
- Presence: the editor posts a heartbeat every 30 seconds (`admin.xelga.presence`); the cache keeps who is editing for 60 seconds. When someone else is in the editor, the top bar shows "Maria Santos is also editing". This is advice, not a lock: a crashed browser never blocks the store.
- Publish locks the affected rows (`lockForUpdate`) and checks each document's `draft_version` against what the editor last saw. A mismatch answers 409 and nothing is published.

## 6. Rendering pipeline

### 6.1 Per request

1. Core's controller runs as usual (`HomeController@index` returns `view('home')`, `ProductController@show` publishes `PageContext`), so every core rule has already been applied to the data.
2. The theme's page view (for example `views/product/show.blade.php`) renders `<x-store-layout>` with `@include('snippets.template', ['pageType' => 'product', 'subject' => $product, 'page' => PageContext::all()])`. `page` is exactly the view data core's controller handed the page (an explicit `compact()` of the controller's variables on the other page types); it reaches every section as `RenderContext::$page`, so a main section shows what core worked out for this shopper and never queries around it.
3. `TemplateResolver` picks the handle: in the editor's preview, a custom template named by `?xelga_template=` (the editor's special layout preview, any subject of that page type); else an assignment for the subject (`xelga_assignments`, subject types product, category, brand, page and blog_post) that points at a custom template of the same page type with published content (a draft counts in the preview); else the page type's template. One query reads the assignment with its document, and the repository keeps that row so it is not read twice.
4. `DocumentRepository` loads `settings`, `header`, `footer` and the template in one query (`whereIn('handle', ...)`), memoized for the request. It returns `draft ?? published` when `PreviewMode::active()`, else `published`. A missing or never-published template falls back to the shipped default in `store-templates/_defaults/{handle}.json`, so a fresh install renders a sensible page before anything is published.
5. `SectionRenderer` walks `order`, skips `disabled` sections and blocks, merges settings over defaults, calls the section's data provider when one exists (`Themes\Xelga\Sections\{StudlyType}` implementing `SectionData::resolve(array $settings, array $blocks, RenderContext $ctx): array`), and renders `sections.{type}` with `$section`, `$settings`, `$blocks` and the provider's data. Each section is wrapped in comment markers `<!--xelga:{id}-->` and `<!--/xelga:{id}-->` that `SectionOnly` uses.
6. A section that throws while rendering is logged and replaced by nothing for shoppers (an error box in preview), so one bad section never takes the page down.

### 6.2 Data comes from core only

| need | source |
|---|---|
| product lists (newest, best sellers, on sale, category, brand, hand-picked) | `App\Support\ProductFeed` |
| price and sale display, countdown end | `Pricing::display($product)` and `DisplayPrice` |
| product page data | `PageContext::all()` published by `ProductController::show` |
| category listing, filters, sort | `PageContext` published by `CategoryController::show` |
| menus | `NavTree::for($key)` |
| card attributes | `ProductAttributes::card()` (already eager loaded by core listings) |
| ratings | `Reviews::enabled()` and the review aggregates core loads |
| payment icons | `PaymentFamilies::enabledFamilies()` |
| store name, logo, contact | `StoreIdentity`, the store record |
| wishlist state, cart count | the bundle `x-store-layout` shares |
| blog posts and categories | the blog extension's models, only when the extension is enabled |
| phone numbers | `Phone::display()` |
| product page prices per quantity and variant | `CartService::resolvePrice()` at one and at each quantity where one of the group's specials starts (core's `$priceTiers`), per variant from its regular price; the browser only picks the row for the quantity typed |
| volume tiers | core's `$priceTiers` for the shopper's group (guests: the default group); nothing shows to a group without tiers |
| low stock level | `LowStock::threshold()` |
| back in stock | the stock alerts extension's `notify-stock.store` (name, email, captcha), only while its route exists |
| related products | the controller's related or same-brand list when it loaded one, else `ProductFeed` (category or brand, random), so visibility applies |
| recently viewed | product ids in an encrypted cookie the product page queues, read back through `ProductFeed` ids (hidden or switched-off products drop out) |
| cart lines, flags, subtotal | `CartController@index` view data; changes through core's cart routes, after which the section is drawn again from the real page |
| coupon on the cart | the coupons extension's `CouponService::validate()`, read only, as core's checkout calls it |
| free shipping note | the free shipping extension's rules that have no condition but a minimum order |
| shipping estimate | `cart.estimate-shipping` with core's address lookups (`storefront.address.*`) |
| not-found page | the provider's renderable for `NotFoundHttpException` draws `xelga::not-found` (status 404) while Xelga is live, for HTML GET requests outside the admin; core's page stays for everything else |

Section data providers receive ids from settings and load models through those classes. Products always go through the `Product` model so the customer group visibility scope applies: a hidden hand-picked product simply does not appear.

### 6.3 Caching and invalidation

- Compiled catalog: file cache, rebuilt when the theme version changes.
- Published documents: not cached beyond the request. Loading them is one indexed query; with the database cache driver these stores use, a cache read would be a query too.
- Tokens stylesheet: served at `GET /xelga/tokens/{version}.css` (`xelga.tokens`), where `version` is the settings document's `published_version`. It answers with `Cache-Control: public, max-age=31536000, immutable`; a publish bumps the version, so the URL changes and nothing needs purging.
- Section HTML: not cached in the first release. Product sections depend on customer group, currency, wishlist and specials that start and end by the minute; a wrong cached price costs more than the milliseconds saved. If it is ever added, it is keyed by customer group and currency and invalidated by core model events.

### 6.4 Tokens stylesheet

`Tokens::css(array $settings): string` writes `:root{...}` from the theme settings (each setting's `target` in `theme-settings.json`: `token`, `attr`, `font-pair`, `scheme-colors`), one `.scheme-N{...}` rule per scheme, and `@font-face` rules for the chosen pair pointing at `assets/fonts/`. Attribute targets (`data-card-style` and the like) are printed on `<html>` by the layout. In preview mode the layout links `/xelga/tokens/draft.css?n={nonce}`, rendered from the draft, `no-store`, only answered inside a valid preview session.

Fonts are self-hosted: a live store should not send every shopper's address to a third party or wait on another host before text renders. Each pair ships latin woff2 files only (heading and body, the weights the pair uses), the heading file is preloaded, and `font-display: swap` is set.

### 6.5 Performance budget (a Raspberry Pi 4 with local MySQL and a warm opcache)

| measure | budget |
|---|---|
| server time, home with 13 sections | 200 ms median |
| database queries, home | 30 or fewer, none per product card |
| CSS sent on first view | 40 KB gzip including tokens |
| storefront JS | 30 KB gzip, deferred |
| largest contentful paint, phone profile | 2.5 s; hero image preloaded with width and height |
| layout shift | 0.05 or less |

A feature test asserts the query count per page type; a browser check records the rest per release.

## 7. Editor

### 7.1 Technology

The editor is a vanilla JavaScript app, split into ES modules and built by Vite as `editor/js/panel.js` and `editor/css/panel.css`: forms, operation history, sidebar, inspector, phone sheet and keyboard handling, with every document saved through the server.

Rejected: rebuilding it in Alpine like core's admin. The editor is a stateful app with an operation log, live bridge and drag reorder; Alpine suits small widgets on server-rendered pages. 

Rejected: React or Vue. A new runtime and toolchain inside a theme package, for a UI that already works without one.

### 7.2 Shell, route and permission

`GET {admin}/extensions/theme/xelga/editor` named `admin.xelga.editor` renders `views/editor/shell.blade.php`: a full-screen page outside the admin chrome, with the boot payload as JSON (`@json` of an array built in the controller): the compiled catalog, the theme settings schema, font pairs, the store's schemes, the page list (`pages`: every page type with its preview address on a sample record, and the fixed pages checkout, `auth` and account with the theme setting groups that style them), the custom templates (`templates`, each with a preview address carrying `xelga_template`), the version of every draft (`drafts`), the current user, the CSRF token, route URLs, and the preview frame URL.

The provider registers:

```php
AdminPermissions::map(['admin.xelga.*' => 'themes.edit']);
```

Every Xelga admin route sits in a group with `web`, `auth`, core's admin middleware and `throttle`. `themes.edit` lets a user edit, save and publish. Image upload and browsing use core's media routes and therefore core's media permissions; a user without them sees "Ask an admin for media access" in the image picker.

The editor is reachable from Themes ("Customize" on Xelga), since `theme.json` names it as the theme's editor.

### 7.3 Admin routes

All under `{admin}/extensions/theme/xelga`, names `admin.xelga.*`, JSON in and out.

| method and path | name | does | throttle per user |
|---|---|---|---|
| GET `/editor` | `editor` | shell; opens no preview window | 60/min |
| POST `/preview` / DELETE | `preview.open` / `preview.close` | open this admin's preview window (answers the nonce and preview address), or close it on Exit | 20/min |
| GET `/documents/{handle}` | `documents.show` | draft and published content, versions, who saved | 240/min |
| PUT `/documents/{handle}/draft` | `documents.draft` | save the whole draft (`content`, `draft_version`) | 120/min |
| DELETE `/documents/draft` | `documents.discard` | discard every draft | 10/min |
| POST `/publish` | `publish` | publish every document that has a draft (`versions: {handle: draft_version}`) | 10/min |
| GET `/documents/{handle}/revisions` | `revisions.index` | list | 60/min |
| POST `/documents/{handle}/revisions/{revision}/restore` | `revisions.restore` | copies a revision into the draft | 10/min |
| GET `/templates` | `templates.index` | every custom template with its use count and assigned ids (first 200), and `drafts: {handle: draft_version}` for every document with a draft | 240/min |
| POST `/templates` | `templates.store` | new custom template (`page_type`, `label`, `from`) from a page type's template or another of its custom templates; answers 201 with the template, its document and `left_out` | 20/min |
| PATCH `/templates/{handle}` / DELETE | `templates.update` / `templates.destroy` | rename (`label`) or delete a custom template (and its assignments and revisions) | 20/min |
| PUT `/assignments` | `assignments.update` | `subject_type`, `subject_ids` (1 to 50), `handle` (null clears); answers `assigned`, `cleared`, `moved` | 60/min |
| POST `/presence` | `presence` | heartbeat | 4/min |
| GET `/pick/{kind}` | `pick` | picker lookups, `kind` in products, categories, brands, menus, pages, blog-posts, blog-categories, links | 120/min |
| POST `/store-templates/{slug}/apply` | `store-templates.apply` | writes that store into the drafts (the list ships in the boot payload) | 10/min |
| POST `/store-templates/undo` | `store-templates.undo` | puts back the drafts from before the last apply | 10/min |
| GET `/export` / POST `/import` | `export` / `import` | the theme state as a portable JSON file | 5/min |

Pickers return only what the picker shows: id, name, image thumbnail, price display string, active flag; 20 results per call, search by name through core's `NameSearch`. They run in the admin context, where core's visibility scope shows every product, so a merchant can pick a product reserved for a customer group; the storefront then shows it only to that group.

### 7.4 Bridge protocol, version 1

Messages are objects `{source: 'editor'|'theme', v: 1, type, ...}` sent with `postMessage` and the store's own origin as `targetOrigin`. Each side drops messages whose `event.origin` is not that origin or whose `event.source` is not the expected window. `frame.js` (the preview half) is loaded only in preview mode.

Theme to editor:

| type | payload | when |
|---|---|---|
| `ready` | `pageData`, `page: {type, handle, subject}`, `url` | page loaded; `pageData` lists sections and blocks with ids, types and labels |
| `select` | `sectionId`, `blockId` or null, `key` or null | click on a section, block or an element with `data-setting` |
| `navigated` | `url`, `page` | the merchant followed a link inside the preview; the editor switches to that page's template, or the custom template `page.handle` names. The followed address carries the preview nonce from the current one, since browsers send `Sec-Fetch-Dest` only on secure origins |
| `duplicated` | `sectionId`, `newId` | after `duplicate` (kept from the draft) |
| `error` | `sectionId`, `message` | a section failed to render or initialize |

Editor to theme:

| type | payload | notes |
|---|---|---|
| `highlight` | `sectionId`, `blockId?` | hover outline, as in the draft |
| `select` | `sectionId`, `blockId?` | persistent outline, scroll into view |
| `field` | `sectionId`, `blockId?`, `key` or null | new: outline the elements one field changes (replaces the editor drawing boxes inside the frame's document) |
| `setting` | `sectionId`, `blockId?`, `key`, `value` | live apply per the setting's `live` mode |
| `theme-setting` | `key`, `value` | token, attr, font-pair, scheme-colors, bind, toggle-each, icon (as the draft) |
| `move` | `sectionId`, `toIndex` | index within the section's group |
| `remove` / `restore` | `sectionId`, `toIndex?` | as the draft |
| `duplicate` | `sectionId`, `newId`, `html` | new: the duplicate's HTML comes from the server so ids inside it are correct |
| `insert` | `sectionId`, `group`, `index`, `html` | new: add section; replaces copying markup from a sample store |
| `replace` | `sectionId`, `html` | new: swap a section for a server re-render, then re-run its initializer |
| `block-insert` | `sectionId`, `blockId`, `index`, `html` | new: add a block |
| `block-remove` / `block-restore` | `sectionId`, `blockId`, `index?` | new |
| `block-move` | `sectionId`, `blockId`, `toIndex` | new |
| `rename` | `sectionId`, `blockId?`, `label` | new: updates the outline label |

Every message that changes structure is followed by the frame posting a fresh `pageData`, so the sidebar and the preview cannot drift.

### 7.5 Server re-render

When a change needs data or new markup (a `render` setting, a variant whose markup differs, add section, add block, duplicate), the editor:

1. applies the operation to its in-memory draft and flushes the draft save at once (no debounce);
2. requests the current preview URL with `?xelga_section={id}&xelga_preview={nonce}`; the URL must be on the editor's own origin, the request does not follow redirects (`redirect: 'manual'`), and the answer is used only when its final URL is on that origin and it carries `X-Xelga-Section` equal to the requested id. Anything else (a store redirect rule on the page, a sign-in redirect) is refused and the whole preview reloads instead;
3. `SectionOnly` middleware lets the real core controller and theme views render the full page from the draft, then cuts out the HTML between that section's markers and answers it alone (`no-store`);
4. the editor posts `replace` or `insert` with the HTML.

Rendering the real page means product, category and cart sections get the exact data and rules a shopper's page would, with no second code path. The cost is a full page render per such change (about 200 ms on a small server); text, toggles, schemes and spacing still apply instantly in the browser.

### 7.6 Saving, publishing, undo

- **Undo and redo** stay in the browser, per editor visit: an operation list with forward and backward steps.
- **Autosave**: every operation marks its document dirty; dirty documents are saved 1.5 seconds after the last change, and at once before a server re-render, when switching page and when the tab is hidden (`fetch` with `keepalive`). The status reads "Saving", "Draft saved 2:41 pm", or "Not saved: retrying".
- **Save draft** flushes now. Nothing is stored in `localStorage`.
- **Publish** confirms, then posts every document with a draft. The server validates strictly (contrast, structure), copies draft to published in one transaction, writes revisions, clears drafts and returns new versions. The preview reloads from published content.
- **Discard draft** deletes every draft after confirmation, and custom templates never published.
- **Pages**: switching page (the page picker, or Alt and click in the preview) saves first and stays put when that fails; undo history starts again per page.
- **Header and footer**: the section list shows one of two lists. A page's list holds only its own sections (and the Layout row where the page has special layouts). The header and footer groups are one design on every page, so they have their own list, "Header and footer", the first entry of the page picker, with the address `?edit=header-footer` (beside `page` and `layout`). Choosing it previews the home page; when the home page is already shown, only the list changes. Clicking the header or footer in the preview of any page switches to that list and opens the section, and clicking a page section switches back; neither reloads the preview or clears undo history, and the only signal is a short tint on the page picker and a screen-reader announcement. Undo and redo switch to the list the change belongs to (theme settings changes leave the list as it is). Checkout, sign in and account have no list and stay unclickable. Publish and Discard send the version of every draft, also of pages not opened in this visit (`drafts` in the boot payload and every template answer).
- **Preview size**: the frame is a real viewport, width and height (desktop: the editor window's own size, at least 1280 wide; tablet 834 x 1194; phone 390 x 844), scaled down to fit the canvas both ways, so anything sized to the window draws as on that screen.
- **Revisions**: "Earlier versions" in the More menu lists publishes by date and person; Restore copies one into the draft so it can be checked before publishing. Nothing goes live without Publish.

### 7.7 Pickers

| setting type | picker | backed by |
|---|---|---|
| `image` | media library browser and upload | core `admin.media.browse`, `admin.media.upload` |
| `product-picker` | search with thumbnails, multi-select with drag order | `admin.xelga.pick` kind `products` |
| `category-picker` | tree with search | kind `categories` |
| `brand-picker` | search | kind `brands` |
| `menu-picker` | list with "Edit menus" link to core | kind `menus` |
| `url` | one field: search across products, categories, brands, pages, posts, plus "web address" | kind `links` |

## 8. Preview mode

`PreviewSession::open()` runs when the editor app, once started, posts to `preview.open` (CSRF token, throttled); loading the shell itself opens, rotates or reopens nothing, so a link from another site cannot. It stores in the session `xelga_preview = {nonce, until}` (two hours, extended by each heartbeat). `PreviewMode` middleware (appended to the `web` group by the provider) marks a storefront request as preview only when all hold:

1. an admin is signed in (`auth('web')`) and has `themes.edit`;
2. the session window is open;
3. either the request carries `xelga_preview` equal to the session nonce, or it is a frame navigation (`Sec-Fetch-Dest: iframe`).

Rule 3 keeps an admin's ordinary tabs on the published store while the editor is open, and survives redirects inside the frame (for example to the sign-in page). Preview responses send `Cache-Control: no-store, private`, `X-Robots-Tag: noindex` and `Content-Security-Policy: frame-ancestors 'self'`. When Xelga is not the live theme, the preview lays it over the live theme for that request only; afterwards the theme settings are read again and the live theme's views put first, so a long-running worker never serves the next shopper with Xelga. The shell closes the window on Exit. A shopper can never see a draft: without an admin session none of the rules can pass.

The preview shows what a guest sees, because core's visibility scope treats an admin browsing the storefront as a guest. A "Preview as customer group" option is a later idea, not in scope.

## 9. Store templates

The eight starting stores have plain names, and the slug is the name: `gadgets` (Gadgets), `clothes`, `furniture`, `beauty`, `coffee`, `outdoor`, `music` and `parts`. No store carries a brand of its own, so the merchant's store name is the one the header and footer show, and no shipped file names a real store.

A starting store brings the look and the home page (header, footer and home sections), never products. The categories and products its sections name are the merchant's own, matched by slug; a slug the catalog lacks leaves that setting empty and the section shows its empty state. Core's own installer catalog is what a fresh store's products come from.

A fresh install starts from the `gadgets` store template: its documents are the shipped defaults in `store-templates/_defaults`, and the default settings carry `based_on: {look: gadgets, sections: gadgets}`. Its sections name the categories and products core's installer catalog seeds on a fresh VentaCart install, by the same slugs, so its home page fits the catalog a new store already has. The defaults keep no ids: while a store reads them, the categories and products the Gadgets store names are looked up by slug in the store's own catalog, so a fresh install's spotlight, category tiles and buttons show the installer catalog, and a slug the catalog lacks leaves that setting as the shipped default. A `based_on` naming a store that no longer ships reads as Gadgets, as any unknown slug does. The editor offers the eight as "Start from a store", Gadgets first: the first row of Theme settings shows the store the draft is based on ("Based on the Coffee store", or "Look from the Beauty store" after a look-only apply) and opens the cards in the settings panel; choosing a card asks, in the same panel, "Look and home page" or "Look only".

Each store is `store-templates/{slug}/store.json` plus `images/` and `thumbnail.jpg` (the card picture, built into the editor bundle). A store file is portable: categories, products, brands and pages are named by slug, photos as `images/{file}`.

```json
{ "format": 1, "name": "Coffee", "line": "Specialty beans and brewing gear", "position": 5,
  "documents": { "settings": { "values": {...}, "schemes": [...] }, "header": {...}, "footer": {...}, "home": {...} } }
```

- picker setting: `"light-roast"` or `["benguet-highland-washed", ...]`; link: `{"type": "category", "slug": "light-roast"}`.
- `images/` holds only the photos the store's sections show. The fresh install copies Gadgets' photos into the media library for the shipped defaults, and Clothes' photos, which the section presets show.
- The settings document holds only the look: the groups Colors and schemes, Typography, Buttons, Product cards and Layout and spacing (`StoreTemplates::LOOK_GROUPS`). Contact, header behavior and cart settings stay the merchant's.

Applying one (`POST store-templates/{slug}/apply`, `bring` all or look, `versions`), in `StoreTemplateApplier`:

1. refuses at once when a `draft_version` the editor sent is stale (409, nothing written, no photo copied);
2. copies the store's photos into the media library under `media/xelga/{slug}/`; a file already there is never replaced, so a second apply copies nothing;
3. in one transaction, with the rows locked: the settings draft (merchant values kept, the look replaced wholly, `based_on` set); for "Look and home page" also the header, footer and home drafts, with slugs resolved by `Portable` through core's models. A slug this store lacks leaves the setting empty; the answer lists it under `missing`;
4. writes every draft through `DocumentWriter` with the versions sent, so validation and sanitizing are the same as any save; never publishes.

Undo (`POST store-templates/undo` with the token the apply answered) puts the exact drafts back, byte for byte, for the admin who applied, once, within two hours, and only while nobody changed those drafts since (else 409). A document the apply created is deleted again. The way back is kept in the cache, like presence.

In the editor, a section whose picker is empty (category tiles, hand-picked or category products, the spotlight product, shop-the-look hotspots) carries an amber "Pick a category" / "Pick products" tag in the section list, and the preview draws a dashed empty state over it (bridge message `empty`) until something is picked.

`php artisan xelga:lint` checks every store: documents pass `Shape` with nothing corrected, the look stays readable, it lists no sample products, every slug it names is a slug, and every photo in `images/` ships and is used by its sections, the shipped defaults or the section presets (a photo nothing shows fails the lint).

Export and import use the same portable format for the whole theme state, so a designer can move a finished store to a client's install.

## 10. Security

| area | rule |
|---|---|
| authorization | every admin route maps to `themes.edit` through `AdminPermissions::map`; media uses core's media permissions; pickers are read-only and return minimal fields |
| CSRF | all writes under the `web` group with the token; the editor sends `X-CSRF-TOKEN` |
| validation | Form Requests for request shape; `Shape` and `Sanitizer` for documents (section 4); 256 KB body limit on document saves |
| output | `{{ }}` everywhere; `{!! !!}` only for `richtext` values cleaned on write; values inside script data through `@json`/`@js`; JSON-LD with escaped `@@context` |
| rich text | narrow allowlist, links through `SafeInput::link`; no raw HTML, no custom CSS or script setting in this release (those would need core's `storefront.code` permission) |
| uploads | through core's media library and its checks; image settings refuse SVG and anything outside public storage |
| links | `SafeInput::link`; no `javascript:` or `data:` |
| preview | section 8; draft responses `no-store`; section-only answers also `X-Content-Type-Options: nosniff`; the window is opened by a POST from the editor app (never by loading a page), new nonce each time; window two hours, extended by heartbeat |
| bridge | strict origin and source checks on both sides; only known message types; HTML from `insert` and `replace` only comes from the store's own section-only preview responses (same origin, no redirects followed, `X-Xelga-Section` matching, section 7.5); `frame.js` cleans that HTML with allowlists before use: elements that run or load code (`script`, `iframe`, `object`, `embed`, `link`, `style`, `base`, `meta`, SVG `animate`/`set`/`foreignObject`) are removed, every `on*` attribute and `srcdoc` dropped, URL attributes normalized (control characters and spaces removed, lower case) and kept only for relative addresses, `#`, `http:`, `https:`, `mailto:`, `tel:`, and raster `data:image/...;base64` on `src`; an SVG `use` may only point into the page and a form only at the store |
| rate limits | per route, section 7.3 |
| mass assignment | models declare `$fillable`; controllers never pass request arrays to models |
| audit | publish, discard, restore and store template apply each write one structured `Log::info` entry: `event: theme_editor`, `action`, `user_id`, `handles`, and per handle the versions after the change. Core's `ActivityLogger` records customer activity only, so the application log is the record |
| uninstall | drops every `xelga_*` table; the Themes screen warns that theme work is lost and links to Export |

## 11. Testing

- Theme tests live in `resources/themes/xelga/tests`, in their own suite: `php artisan test --testsuite=Xelga` (in-memory SQLite like core).
- Core must keep passing without Xelga: `php artisan test --testsuite=Unit,Feature` passes with `resources/themes/xelga` removed. Core's tests run on a plain sample theme kept under `tests/fixtures/themes`.

| layer | what |
|---|---|
| catalog | `CatalogLinter` over every schema: known types, defaults pass the sanitizer, options unique, ranges finite, every section has a view and CSS file, every `render` key exists, presets valid |
| sanitizer | one test per type with hostile input: script in text, `javascript:` links, SVG images, out-of-range numbers, unknown options, oversized rich text |
| documents | shape errors answer 422; adjusted values reported; 409 on stale `draft_version`; publish is all or nothing; revisions pruned to 30; restore writes the draft only |
| rendering | every section renders with an empty store and with demo data; hidden products do not appear for guests; sale prices match `Pricing::display`; query count per page type; a failing section does not break the page |
| preview | guests, admins without `themes.edit`, expired windows and plain tabs (no iframe header, no nonce) never get draft content; `SectionOnly` returns one section only in preview |
| authorization | every Xelga admin route refuses a user without `themes.edit`; pickers never return more than the listed fields |
| contract | `ThemeContract::check()` passes for the package; uninstall drops every `xelga_*` table |
| browser | Playwright in `tests/Browser`: open editor, click hero in preview, change heading, see it live, save, reload, see draft, publish, see it on the storefront in a private window; keyboard-only run of the same; axe checks on the seven sample home pages at 390, 834 and 1280 px |

Fixtures use neutral names (Demo Store, Juan Dela Cruz, `DEMO-001`, example.com).

## 12. Decisions and rejected alternatives

| decision | rejected alternative | why |
|---|---|---|
| JSON documents, one row per page template, draft and published in the same row | normalized sections and blocks tables | every read and write is a whole page; publish and revisions become simple copies; no query needs to look inside sections |
| one shared draft per document with optimistic versions and presence | one draft per admin | per-admin drafts collide at publish and hide each other's work |
| undo in the browser, revisions on publish | server-side undo log | undo must be instant; revisions answer "go back to last week", which is a different need |
| full server render of the real page, cut to one section, for data-driven changes | a separate render endpoint that rebuilds page data | the real controller is the only place core's rules and page data are guaranteed; a second path would drift |
| vanilla JS editor | Alpine; React or Vue | adds no runtime to the admin |
| plain CSS with custom properties, numbers through data attributes and generated selectors | Tailwind; inline `style` custom properties | runtime tokens do not suit a purged utility build; the house rule forbids CSS in HTML |
| tokens stylesheet from a versioned route | inline `<style>` in the layout; a file written to storage on publish | no CSS in HTML; versioned URL caches forever; no files to keep in sync across servers |
| no HTML caching in the first release | section fragment cache | prices and visibility vary per shopper; correctness first |
| self-hosted fonts | Google Fonts links | shopper privacy and no third-party wait before text shows |
| session preview window plus nonce or iframe navigation | signed URL per page | signed per-path URLs break when the merchant clicks around inside the preview |
| fixed checkout and account pages | composable checkout | money, addresses and passwords; no merchant value worth the risk |
| countdown and payment icons from core | a typed date and payment toggles | core rules win; the display can never disagree with the cart |
| ids in stored documents, slugs in portable files | slugs everywhere | ids survive renames on the store; slugs let templates move between stores |
| no `store_id` now | adding it speculatively | core resolves no store per request; one later migration adds it if that changes |

## 13. Risks

| risk | effect | mitigation |
|---|---|---|
| full-page re-render is slow on small servers for data settings | editor feels sluggish on product source changes | only `render` settings use it; show a section-level loading state; fall back to a lighter path only if over 500 ms |
| catalog changes after stores have saved documents | broken or missing settings | defaults merged at render, unknown keys ignored, `schema` format version with upgrades in `Shape`, linter in CI |
| shared draft surprises a second admin | lost work | 409 conflict with both versions, presence banner, undo history kept after loading theirs |
| `Sec-Fetch-Dest` missing in an old browser | preview frame shows published content after a redirect | nonce on the first load and on every editor-initiated request; the editor detects a `ready` without draft marker and reloads with the nonce |
| merchant deletes a photo or product used by a section | empty spot on the live store | sections render nothing for missing references; editor shows "Photo missing" |
| uninstall drops the merchant's work | lost store design | warning on the Themes screen with Export; revisions |
