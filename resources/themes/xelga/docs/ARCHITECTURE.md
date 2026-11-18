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
  theme.json                      identity, provider, editor route, storage: own
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
    layouts/store.blade.php       draws header group, page content, footer group
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
    css/frame.css                 the framed page and its bands, address bar and Preview frame (imported by panel.css)
    js/frame.js                   loaded in the store pages shown in Preview: keeps the preview key on links
    js/modules/                   state.js, operations.js, preview.js, board/, forms/, pickers/, api.js
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
  "provider": "Themes\\Xelga\\XelgaServiceProvider",
  "editor": "admin.xelga.editor",
  "storage": "own"
}
```

- The version starts at 2.0.0 so a store that ever recorded an earlier `xelga` version sees an update rather than a downgrade.
- No `settings` key: Xelga's settings live in its own schema files and tables, not in `theme_settings.*`.

### 2.2 Build

VentaCart's `vite.config.js` discovers these entries for every theme: `css/theme.css`, `js/theme.js`, `editor/css/panel.css` (which imports the board frame styles), `editor/js/panel.js`, `editor/js/frame.js`, and every `css/pages/*.css`. `npm run build` in the VentaCart folder builds them with the rest of the store.

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
  "picker_group": "banners",
  "picker_order": 10,
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
| `templates` | the template handles the section may be added to; `*custom` means custom templates of those types; `*extension` every template of an extension page type (section 14.4); `*` everywhere. Header and footer group sections use `group` instead. |
| `extension_blocks` | `true` lets extension blocks that name this section in their `targets` join it (section 14.3). `product-main` takes them. |
| `icon` | the editor's icon for the type, a name in the editor's sprite. |
| `limit` | most instances per document (header: 1). |
| `main` | the page's main section (product information, category listing): the page content, the first kind of locked section. Cannot be removed, duplicated, hidden, replaced or moved; exactly one per template of its type. |
| `fixed` | a header or footer section that cannot be removed, moved out of its band, duplicated or replaced; it can be hidden (the second kind of locked section, beside `main`). The header and footer carry it. A stored page that hides one keeps it hidden. |
| `shared_groups` | the header or footer bands the type may also be added to (`["header", "footer"]` for the announcement bar). A band section is stored in the band's own document and shows on every page. |
| `limit_per_area` | with `limit`, counts the limit once per area of a page with areas (Top, Content, Bottom) and once per band, instead of once per page. A page that stored more than the limit is never refused. |
| `own_row` | the type always keeps a one-column row of its own: it is not offered for a column or a side, and a stored page that put one there has it moved to a row of its own after that row. |
| `content_only` | the type belongs in Content, beside the sides, and is not offered in Top or Bottom; a stored page that has one there has it moved to the start or end of Content. |
| `presets` | named starting points the section picker offers as the type's layouts; a preset is settings plus blocks. Without presets the picker offers the section's variants with defaults. |
| `picker_group` | the group of tiles the section picker shows the type in: `banners` (Banners and photos), `products`, `categories` (Categories and brands) or `text` (Text and trust). Every theme section that can be added names one; the linter refuses any other value. An extension's sections are grouped under the extension's title instead. |
| `picker_order` | where the type sits within its picker group: a whole number above 0, lower first (the most used types lead). Types without one follow in catalog order. |
| `blocks.types[].limit` | most blocks of that type in one section. |
| `span` | `{"min": N}`: the narrowest place the section fits, in twelfths of the page: 3, 4, 6, 8 or 12. Absent means 12, full width only. A variant may carry its own `"span": N`, which wins while that variant is chosen (a wide layout of a section that otherwise fits a narrow column). Main sections never go below 12. The linter refuses other values. See section 5.2 for how it is measured. |
| setting `live` | how the preview applies a change: `text`, `attr`, `css`, or `render` (server re-render, section 7.5). Inferred when absent: picker types, `url` on a data-driven section, and any setting named in the section's `render_keys` are `render`; text types are `text`; everything else `attr`. |
| setting `visible_if` | unchanged from the draft: shown only when every listed key matches. |
| setting `options_source` | options filled at boot from core data: `blog_categories`, `menus`, `font_pairs`. |

The catalog stamps an extension's section with `source` (the extension's name), `source_label` (its title) and `data_class`, and gives it the class `s-{extension}--{name}`; none of these come from the file (section 14.2).

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

Two section types are made for a page side or a narrow column (`span.min` 3). Filters carries `content_only`, so it is offered beside the page content and never in Top or Bottom; it may sit in a side or in a row of Content, and one moved out of Top or Bottom goes to the start or end of Content (section 5.2):

- `filters` (Filters), category pages and their custom templates, at most one: the category page's filter form in a section of its own, with the filters core's category controller offers for the products it lists. While it is drawn, the listing leaves out its own filter column. Below 990px, where sides stack, the form sits in a box behind a Filter bar.
- `category-list` (Category list), every page type but the cart, at most one: the store's categories as a list of links, top level only or with subcategories, the current category marked.

Two more types are general: `announcement-bar` (Announcement bar) has `own_row`, `limit` 1 with `limit_per_area`, and `shared_groups` for the header and footer, so a page may carry one in each area and each band one more, each in a row of its own and at least 6 twelfths wide; `divider` (Divider, `span.min` 3, every template) is a line or space between sections, a plain section in any area, column or side.

Settings core already owns stay in core and are only read: which listing filters show (core's sidebar filter setting, kept as the fallback for a category page that has no Filters section), products per page (core's listing setting, shown read-only in the editor), the default sort, and the blog's page size. The product reviews "Write a review" button is not drawn: core collects reviews only through the tokened link it emails buyers. Breadcrumbs are their own section on the shop pages and part of the main section on the blog, post, page, contact and not-found pages.

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

The theme settings document holds 3 to 8 schemes, each with an id and nine roles (`bg`, `text`, `muted`, `accent`, `accent-text`, `button`, `button-text`, `border`, `card`). `ContrastCheck` computes contrast for text on bg, muted on bg, button-text on button and accent-text on accent. Below 4.5:1 for text and button pairs (3:1 for muted) the draft saves with a warning shown beside the color; Publish refuses until fixed, naming the scheme and the pair. Warning while editing and refusal at publish keeps the guardrail without fighting a merchant halfway through choosing a color.

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

Rows, areas and sides: a template may also carry `rows` and `sides`, both optional (`Documents\Layout`). On a page with page content each row also says which area it is in: `top`, `content` or `bottom`.

```json
{ "schema": 1,
  "order": ["list-c3d4", "hero-k2p9", "products-a81c", "text-e5f6"],
  "rows": [
    { "id": "row-a1b2", "split": "12", "area": "top", "columns": [["hero-k2p9"]], "settings": {} },
    { "id": "row-b7", "split": "8-4", "area": "content", "columns": [["products-a81c"], ["text-e5f6"]],
      "settings": { "width": "page", "scheme": "scheme-2", "space": "md" } }
  ],
  "sides": { "left": ["list-c3d4"], "right": [] },
  "sections": { } }
```

- **Rows.** A row has one to four columns. Its `split` is one of seven, in twelfths of the row: `12` One column, `6-6` Halves, `8-4` Wide left, `4-8` Wide right, `4-4-4` Thirds, `3-6-3` Wide middle, `3-3-3-3` Quarters (`Layout::SPLITS`). A column holds up to 6 sections, one under the other; a page holds up to 40 rows. A row of two or more columns has four settings: Columns (`split`), Width (`width`: `page` or `full`, default `page`), Background (`scheme`: empty for none, or a color scheme id) and Space between columns (`space`: `sm`, `md` or `lg`, default `md`). A one-column row holds one section, has no settings and draws no wrapper: the section's own settings cover it.
- **Areas.** A page type with a main section (every template but Home and the cart, and an extension's page type that has one; `Layout::sidesAllowed()`) has three areas in reading order: Top (`top`), Content (`content`) and Bottom (`bottom`) (`Layout::AREAS`). Top and Bottom run the whole page width. A row without `area` takes its place on the page: Top above the main section's row, Content for that row, Bottom below it; a stored page that has sides and no areas keeps every row in Content, as the sides used to stand beside every row. Home and the cart have no areas: they are rows only.
- **Sides.** A left and a right side, each three twelfths of the page wide (`Layout::SIDE_SPAN`) with up to 6 sections, stand beside Content only; Top and Bottom stay the whole page wide. A side is on when it holds a section. The content between the sides is 12, 9 or 6 twelfths wide (`Layout::contentSpan()`); the header and footer never have sides.
- **The effective span.** A column's real width is its part of the content between the sides: part x content / 12. A section's `span.min` is checked against that, so it never draws narrower than it was made for. A section that needs 8 twelfths fits the wide column of an 8-4 row on a page without sides (8), but not beside one side (8 x 9 / 12 = 6). In Content every row is checked, one-column rows too, except the main section, which takes the whole content area. Top and Bottom are measured on the whole page. A side is measured at 3, so only sections with `span.min` 3 go in a side. Main sections never sit in a narrow column or a side.
- **Never refused.** `Layout::normalize()` runs on every save and every read and never refuses a stored page. A section in no row (a section an extension placement just inserted, or one added by an older editor) gets a one-column row of its own after its neighbor in `order`, in the neighbor's area. A section that no longer fits Content moves to Top (when it sat above the page content) or Bottom (when below), with the rows between it and that area; a main section in a column or a side moves to its own row. The save answer reports every move in `adjusted`. A theme update that narrows a section can so never break a stored page.
- **Order.** `order` stays in every document as the reading order, derived from the rows and sides: Top's rows, then the left side, Content's rows (each row's columns left to right), the right side, then Bottom's rows. That is also the order of the page's HTML, so a phone stacks the page the way it reads. A document with neither `rows` nor `sides` reads as one full-width row per section in `order`, with the areas the main section gives them, which is how a page was stored before rows existed, and draws exactly as before; rows that say no more than `order` are dropped on save.
- **Upgrading a stored page.** A page stored with sides and no `area` on any row had its sides beside every row, so every row becomes Content and the page draws as before; its sections that a narrow Content cannot hold then leave to Top or Bottom as above. Home and the cart never have sides: the sides of a stored one move to rows at the end of the page, and the save answer reports it.
- **Extension placements** (`Extensions\Placements`) write no rows. A placed section is put into `order` after its anchor, and the rule above gives it a one-column row there, in the anchor's area: right after the main section it starts Bottom, and `"otherwise": "end"` ends Bottom. A placement is never in a side or a column. While an extension is away, its page templates are kept exactly as stored, sides and rows included.

Extension keys (section 14): a template may carry `placed`, the ids of the extension placements it has been given (at most 100, never shrinking on save). A section or block of an extension carries `source`, set by `Shape` from the type's prefix. One whose extension is away, or one a placement just inserted, also carries `unchecked: true`: its values were stored without checks, so the renderer cleans them before any view sees them, and the next save checks it in full.

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
5. `SectionRenderer` draws a page template's sides first, three twelfths each: only a side that shows something to this shopper is drawn and takes room, so Content is 12, 9 or 6 twelfths wide. The rows are drawn next, each section with the span it really has (`RenderContext::$span`, part x area / 12; Top and Bottom rows at 12, Content rows at the content width, a side's sections at 3), which sizes its photos and caps its cards a line. A one-column row draws its section alone; a row of more columns draws `.xr` with `data-split`, `data-width`, `data-space` and its scheme, and a `.xr__col` per column. With sides, the template draws Top's rows, then `.xp[data-sides]` holding the left `section.xp__side[aria-label]`, `.xp__content[data-content]` with Content's rows and the right side, then Bottom's rows, all inside the layout's plain `<main id="main-content">`. Sides are named sections, not `aside`: an aside inside main is not a top-level complementary landmark. Without a drawn side, every row is drawn one after another the whole page wide, with no wrapper. From 990px the sides stand beside Content; below 990px they stack, the left side above Content and the right side below it, and Content draws as on a page without sides. Rows of three or four columns go two to a line from 750px and every column takes the whole line below 750px. No column, side, `main` or wrapper sets `container-type`: a section that answers to its own width sets it on itself, and the linter checks this for Xelga's sections.
6. Section by section, `SectionRenderer` skips `disabled` sections and blocks, merges settings over defaults, calls the section's data provider when one exists (`Themes\Xelga\Sections\{StudlyType}` implementing `SectionData::resolve(array $settings, array $blocks, RenderContext $ctx): array`; an extension section's declared `data_class`), and renders `sections.{type}` (an extension section's `views/sections/{type}.blade.php` in its parts folder; an extension block's own view, inside the theme's block wrapper) with `$section`, `$settings`, `$blocks` and the provider's data. Each section is wrapped in comment markers `<!--xelga:{id}-->` and `<!--/xelga:{id}-->` that `SectionOnly` uses.
7. A section that throws while rendering is logged and replaced by nothing for shoppers (an error box in preview), so one bad section never takes the page down.

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

The editor is a vanilla JavaScript app, split into ES modules and built by Vite as `editor/js/panel.js` and `editor/css/panel.css`: the layout board, the settings panel, the section picker, Preview, operation history and keyboard handling, with every document saved through the server.

Rejected: rebuilding it in Alpine like core's admin. The editor is a stateful app with an operation log, a live Preview frame and drag reorder; Alpine suits small widgets on server-rendered pages. 

Rejected: React or Vue. A new runtime and toolchain inside a theme package, for a UI that already works without one.

### 7.2 Shell, route and permission

`GET {admin}/extensions/theme/xelga/editor` named `admin.xelga.editor` renders `views/editor/shell.blade.php`: a full-screen page outside the admin chrome, with the boot payload as JSON (`@json` of an array built in the controller): the compiled catalog, the theme settings schema, font pairs, the store's schemes, the page list (`pages`: every page type with its preview address on a sample record, and the fixed pages checkout, `auth` and account with the theme setting groups that style them), the custom templates (`templates`, each with a preview address carrying `xelga_template`), the version of every draft (`drafts`), the current user, the CSRF token, route URLs, and the Preview settings (the parameter names and the nonce once the window is open).

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
| GET `/editor` | `editor` | shell; opens no Preview window | 60/min |
| POST `/preview` / DELETE | `preview.open` / `preview.close` | open this admin's Preview window (answers the nonce and the address of the page to show), or close it on Exit | 20/min |
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
| GET `/subject` | `subject` | the storefront address of one record (`type`, `id`) a guest can open, to draw a page on it | 120/min |
| POST `/store-templates/{slug}/apply` | `store-templates.apply` | writes that store into the drafts (the list ships in the boot payload) | 10/min |
| POST `/store-templates/undo` | `store-templates.undo` | puts back the drafts from before the last apply | 10/min |
| GET `/export` / POST `/import` | `export` / `import` | the theme state as a portable JSON file | 5/min |

Pickers return only what the picker shows: id, name, image thumbnail, price display string, active flag; 20 results per call, search by name through core's `NameSearch`. They run in the admin context, where core's visibility scope shows every product, so a merchant can pick a product reserved for a customer group; the storefront then shows it only to that group.

### 7.4 Preview

Board and Preview are two views of the same page, switched in the middle of the top bar (`preview.js`). Preview is the store page in a frame on the store's own origin, its address carrying the preview window's nonce, at the width of a computer (at least 1280 pixels, scaled down to fit), a tablet (834) or a phone (390). Clicks in it behave as on the store and never open settings; `frame.js`, loaded only in preview mode, keeps the nonce on links and GET forms that stay on the store. Switching keeps the place: the open section, or else the section at the top of one view, is put at the top of the other, below the store's header when that header stays at the top. Neither view animates, and the one not shown stays laid out, so it keeps its scroll.

Changes show in Preview as they are made, by each setting's live mode (`Catalog::liveMode`): a plain text is written into the elements marked `data-setting` at once; any other section or block setting redraws that one section from the saved draft (7.5); a theme token reloads the draft tokens stylesheet; anything else, and any change of the page's structure, loads the page again where it was scrolled. While the board is shown the work waits, and runs when Preview comes back. In Preview only, Recently viewed with nothing in it shows the store's latest products as stand-ins, so it can be seen and styled; shoppers never get them.

### 7.5 Server re-render

When a change needs data or new markup (a `render` setting, a variant whose markup differs, add section, add block, duplicate), the editor:

1. applies the operation to its in-memory draft and flushes the draft save at once (no debounce);
2. requests the address Preview shows with `?xelga_section={id}&xelga_preview={nonce}`; the address must be on the editor's own origin, the request does not follow redirects (`redirect: 'manual'`), and the answer is used only when its final URL is on that origin and it carries `X-Xelga-Section` equal to the requested id. Anything else (a store redirect rule on the page, a sign-in redirect) is refused and the whole page in the frame loads again instead;
3. `SectionOnly` middleware lets the real core controller and theme views render the full page from the draft, then cuts out the HTML between that section's markers and answers it alone (`no-store`);
4. the editor parses the HTML without running it and swaps it in for that section in the Preview frame, then starts the section's own script.

Rendering the real page means product, category and cart sections get the exact data and rules a shopper's page would, with no second code path. The cost is a full page render per such change (about 200 ms on a small server); a plain text is written into the frame at once, and a theme color or font reloads only the draft tokens stylesheet. This runs only while Preview is shown; on the board the changes wait and are applied when Preview comes back. A change to the page's structure (a section added, moved, replaced or removed, a row or a side) loads the page in the frame again.

### 7.6 Saving, publishing, undo

- **Undo and redo** stay in the browser, per editor visit: an operation list with forward and backward steps.
- **Autosave**: every operation marks its document dirty; dirty documents are saved 1.5 seconds after the last change, and at once before a server re-render, when switching page and when the tab is hidden (`fetch` with `keepalive`). The status reads "Saving", "Draft saved 2:41 pm", or "Not saved: retrying".
- **The draft saves itself**; there is no Save draft button, and Ctrl+S saves at once. Nothing is stored in `localStorage`.
- **Publish** confirms, then posts every document with a draft. The server validates strictly (contrast, structure), copies draft to published in one transaction, writes revisions, clears drafts and returns new versions. Preview then shows the published page.
- **Discard draft** deletes every draft after confirmation, and custom templates never published.
- **Pages**: switching page (the page heading's menu, section 7.8) saves first and stays put when that fails; undo history starts again per page.
- **Header and footer**: the header and footer are two bands on the board, above and below every page, marked as shown on every page. Their sections are the header and footer documents, so a change made while a product page is open shows on the home page too. A band is edited in place on whichever page is open: its sections, Add and the settings panel work as for any section, and the panel says "Changes here show on every page.". Changing page leaves the bands as they are. Checkout, sign in and account have no board of their own; the theme settings that style them are in the Theme panel. Publish and Discard send the version of every draft, also of pages not opened in this visit (`drafts` in the boot payload and every template answer).
- **Preview size**: the frame is a real viewport, width and height (desktop: the editor window's own size, at least 1280 wide; tablet 834 x 1194; phone 390 x 844), scaled down to fit the frame both ways, so anything sized to the window draws as on that screen.
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

### 7.8 The layout board

The editor is one full-screen layout board (`editor/js/modules/board/`): the page drawn as one framed store page on a warm canvas, at most as wide as the store. A bar at the top of the frame shows the page's address (`store-address.js`; for a layout shared by many records the record part reads "any product"). Inside the frame, the header band sits on top and the footer band at the bottom, each with its own tint and marked "on every page"; the page is between them. On a page with areas the page is in three areas named once on a rule, Top, Content and Bottom; the left and right side stand beside Content only, each at its full three twelfths whether empty (a box with "Add section") or filled, and the rows in Content draw at the content width the sides leave (section 5.2). Top and Bottom keep the whole width. Home and the cart have rows only, with no area names and no sides. Each section is a plain box of even height. The board never loads the store; Preview (section 7.4) does.

- **The page's heading.** The page being edited is chosen where its name is: the heading at the left above the board (and above the store in Preview), its name with a chevron and beside it the layout it uses ("Uses the category layout"). It opens a menu of page types; Category, Product and Page open in place to their shared layout, a search with the count of their records, and the records, each tagged with the special layout it uses; the special layouts are listed at the foot (`page-picker.js`, rules in `board/page-menu.js`). Arrows move, Right and Left open and close a type, a letter goes into the open search or jumps to a type, Enter in the search opens the first match, Escape closes. The heading scrolls with the view; on a phone it is a full-width button and the menu a sheet.
- **Selecting and settings.** The settings panel at the right is always open, so the board keeps one width. Clicking a section selects it and its settings replace what the panel showed, at once. The panel shows a section or a block, a row of two or more columns (Columns, Width, Background, Space between columns), the theme settings or earlier versions; with nothing selected (a page opened, a click on the empty board, Escape, or Theme pressed again) it shows Page settings: the layout the page uses, what shares it, Make a layout for {record} only, Make a special layout, and a special layout's name, the records using it and Delete. The page's name above a title leads back to Page settings. A one-column row has no row settings: its section's settings cover it. Each section on the board shows its name bar over the drawing of its layout.
- **Names.** Each section's name bar reads its name, then its type in a quieter style, then its layout ("Summer sale · Main banner · Slideshow"). The type is not repeated when the name is the type's own label. In a narrow column the layout is dropped first, and below about 300 pixels the type moves under the name; the full text shows on hover. The drawer's header shows the name and, under it, the type with its icon and where the section sits.
- **Adding.** Sections are added where they will show: an Add line between rows and at the start and end of each area (a row of columns, or one section full width), an Add under each column, in each side, and in an empty area or side. The header band has an Add section button above its fixed section; the footer band shows a line on hover (a button on touch and narrow screens). Each opens the section picker for that place.
- **The section picker** is a modal window. Types are tiles in groups (Banners and photos, Products, Categories and brands, Text and trust, then one group per extension), ordered by `picker_order` within a group, with a search over type and layout names. Choosing a tile shows that type's layouts beside the tiles; one click on a layout adds the section there and opens its settings, and a type with one layout adds on the tile itself. Types that cannot go in the spot stay in their place in the group, grayed, with the reason on the tile; so does a layout too wide for the spot. The reasons are: it needs a row of its own (`own_row`, at a column or side), it goes beside the page content (`content_only`, in Top or Bottom), it is already in that area, band or page (`limit`, counted per area when `limit_per_area`), or the spot is narrower than its span. "Where" words name the place ("Goes in Top, below Breadcrumbs"), and a map of the page marks it. Opened from a band's Add, the picker is a smaller window titled "Add to the header" (or footer) with no search; it offers only the types that name that band in `shared_groups`, and a band with one type opens straight to its layouts. From the Add line between rows, "A row of columns" comes first, its layouts being the seven splits.
- **Change.** Every section that is not locked has a Change control on its name bar. It opens the same picker in replace mode (Change in place), aware of the section's own area, band or column: the chosen type takes the old section's spot with its own defaults and the old section's color scheme, after a warning when content would be replaced, and Undo puts the old section back. The type now in the spot shows grayed as "Now here"; a new layout of the same type is chosen in its settings.
- **Locked sections.** The page's main section shows a lock and "Page content": it has no Change, Hide, Duplicate or Remove and cannot be moved, though its settings and blocks stay editable. The Header and Footer show a lock and "Fixed": they can be hidden and edited, and cannot be moved or removed. Locked boxes have no drag handle; Alt and an arrow on one says so in a message. A section is locked when its catalog entry has `main` or `fixed` (`isLocked`).
- **Moving.** Sections and rows move by drag and drop, or with Alt and the arrow keys (into and out of columns and sides, too, and Alt and Up or Down carries a row across areas). A move that does not fit the spot is refused with the reason in a message that also goes to screen readers: while something is held, the places it cannot go fade, a band fades for a page section and the page fades for a band section. A section from a band never goes onto the page and the other way round: "{name} shows on every page. To show a bar on this page only, add one here." A move that pushes sections out of Content to fit the sides says where they went. On opening a page whose stored sections were moved to fit, one message lists them (`adjusted`, set as text, never HTML).
- **Bands.** The header and footer sections are drawn in their bands and edited in place (section 7.6). The Announcement bar may be added to a band and removed from it in the panel, one per band; on the page one is allowed per area (Top, Content and Bottom), each in a row of its own. A section in a band shows the link mark and the panel notice "Changes here show on every page."; in the panel a page section reads where it sits ("In Top, this page only", "In the left side", "Header, on every page").
- **Color schemes** have no names on screen. A scheme is shown by its colors and by where it is used across the store: a count on each swatch ("Used 3 times") and, in the scheme editor, the sections and rows that use it, page by page, each a link to its place on the board. The server counts the uses from the saved drafts (`SchemeUsage`). A scheme's stored label stays in the settings document. Every scheme is checked for readability while it is edited: a low pair is a warning in the editor and a refusal at Publish.
- **Phones.** Below 720 pixels a full board cannot fit, so the page is drawn as a list in the order a phone shows the store: header, the rows of Top, then within Content the left side, the page content and the right side, then the rows of Bottom, footer. A Filters section shows the flag "In the Filter button", as the storefront moves it behind a button there. Each section is one line with the small drawing of its layout and its name; the columns of a row sit side by side as chips at their true proportions; the Add and Change buttons always show. The drawer and the picker come up as sheets. A finger on the list scrolls it; to move a section or a row, press it for a moment, then drag.

## 8. Preview mode

`PreviewSession::open()` runs when the editor app, once started, posts to `preview.open` (CSRF token, throttled); loading the shell itself opens, rotates or reopens nothing, so a link from another site cannot. It stores in the session `xelga_preview = {nonce, until}` (two hours, extended by each heartbeat). `PreviewMode` middleware (appended to the `web` group by the provider) marks a storefront request as preview only when all hold:

1. an admin is signed in (`auth('web')`) and has `themes.edit`;
2. the session window is open;
3. either the request carries `xelga_preview` equal to the session nonce, or it is a frame navigation (`Sec-Fetch-Dest: iframe`).

Rule 3 keeps an admin's ordinary tabs on the published store while the editor is open, and survives redirects inside the frame (for example to the sign-in page). Preview responses (the Preview frame and any tab opened from it) send `Cache-Control: no-store, private`, `X-Robots-Tag: noindex` and `Content-Security-Policy: frame-ancestors 'self'`. When Xelga is not the live theme, the preview lays it over the live theme for that request only; afterwards the theme settings are read again and the live theme's views put first, so a long-running worker never serves the next shopper with Xelga. The editor closes the window on Exit. A shopper can never see a draft: without an admin session none of the rules can pass.

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

In the editor, a section whose picker is empty (category tiles, hand-picked or category products, the spotlight product, shop-the-look hotspots) carries an amber "Pick a category" / "Pick products" tag on the board and in its settings panel until something is picked.

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
| Preview frame | the editor reads and writes the frame only while it is on the store's own origin; HTML for one redrawn section comes only from the store's own section-only responses (same origin, no redirects followed, `X-Xelga-Section` matching, section 7.5) and is parsed inertly before the matching section in the frame is replaced; `frame.js` adds the preview key only to links and GET forms that stay on the store |
| rate limits | per route, section 7.3 |
| mass assignment | models declare `$fillable`; controllers never pass request arrays to models |
| audit | publish, discard, restore and store template apply each write one structured `Log::info` entry: `event: theme_editor`, `action`, `user_id`, `handles`, and per handle the versions after the change. Core's `ActivityLogger` records customer activity only, so the application log is the record |
| uninstall | drops every `xelga_*` table; the Themes screen warns that theme work is lost and links to Export |

## 11. Testing

- Theme tests live in `resources/themes/xelga/tests`, in their own suite: `php artisan test --testsuite=Xelga` (in-memory SQLite like core).
- Editor logic has Node tests in `resources/themes/xelga/tests/js` (`node --test`): the layout model and its fixtures shared with the PHP tests, the board, drag and keyboard moves, the section picker, the page menu, labels, the phone board, Preview and the panel.
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
| browser | Playwright in `tests/Browser`: open editor, open the board, select the hero, change its heading, switch to Preview and see it, save, reload, see the draft, publish, see it on the storefront in a private window; keyboard-only run of the same; axe checks on the seven sample home pages at 390, 834 and 1280 px |

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
| `Sec-Fetch-Dest` missing in an old browser | preview frame shows published content after a redirect | nonce on the first load and on every editor-initiated request |
| merchant deletes a photo or product used by a section | empty spot on the live store | sections render nothing for missing references; editor shows "Photo missing" |
| uninstall drops the merchant's work | lost store design | warning on the Themes screen with Export; revisions |

## 14. Extensions

How a VentaCart extension adds to Xelga without editing it: section types, blocks inside Xelga's sections, page templates and default placements. This is Xelga's side of the extension hooks, API 1; core's side (pages of extension page types, cart groups, the checkout page, order panels, product card lines, the account menu, the `extension.*` frames) is in `docs/theme-contract.md`, section 9.

### 14.1 Words

- **Extension parts**: the folder an extension gives Xelga. One per extension.
- **Extension section / block**: a section or block type from extension parts, named `{extension}.{name}` (`marketplace.seller-card`). The dot keeps every extension type apart from Xelga's own, which never hold one.
- **Extension template**: a Xelga template of a page type an extension registered with core (`shop`).
- **Dormant**: an extension section or block stored in a document while its extension is switched off or removed.
- **Placement**: an extension's statement that one of its sections or blocks belongs somewhere by default.

### 14.2 Registration and the parts folder

An extension listens for `xelga.extend` (`Extensions\Registrar::EVENT`) in its provider's `boot()` and calls `$xelga->parts($name, $path)` and, for each of its page types, `$xelga->previewUrl($type, fn () => ?string)`. `Extensions\Extensions` (scoped per request, dropped by `Storefront::forgetRequestState()`) fires the event the first time the catalog is read, which is after every provider booted, and keeps the accepted parts as `ExtensionParts`. Each check below is logged once and skips the part; nothing throws:

- the name is `^[a-z][a-z0-9-]{0,39}$`, not registered twice, and `ExtensionManager::state()` is `enabled`;
- the folder's real path lies inside the extension's own folder (as discovery reports it), and nothing in it is a link;
- `xelga.json` says `"api": 1`; its `stylesheet`, when given, is a `.css` file under the extension's `resources/`.

Layout: `xelga.json`, `schemas/sections/{type}.json`, `schemas/blocks/{type}.json`, `views/sections/{type}.blade.php`, `views/blocks/{type}.blade.php`, `templates/{page type}.json`, `placements.json`. The views folder is also the view namespace `xelga-{extension}` for the extension's own partials. A preview address is used only when it is a page of this store.

The catalog (`Catalog::compile()`) reads Xelga's schemas, then each extension's, and refuses (logs) a section whose type is not `{its extension}.{name}` named after its file, whose group is not `template`, that uses `scheme-colors` or an unknown setting type, or whose `data_class` is outside the extension's namespace or does not implement `SectionData`. The cache key is the theme version, `|`, then `name@version` of every registered extension (and, in debug, each parts folder's newest file time and count), so switching or updating an extension compiles once.

### 14.3 Extension blocks

A block schema: `type`, `label`, `description`, `icon`, `settings`, `limit` (1 to 4, default 1), optional `data_class`, and `targets`, the Xelga section types it may join. It joins a target only when that section's schema says `"extension_blocks": true`; the section's `blocks.max` grows by the blocks' limits (at most 8). From there it is a block type of the section like Xelga's own: `Shape` checks it, the editor lists it under the extension's name in Add block, and it is dragged among Xelga's blocks. `SectionRenderer` draws it with its own view (inputs `$block`, `$settings`, `$data` from its data class, which receives the block's settings and the section's `RenderContext`, `$context`, `$section`), inside `snippets/extension-block` (`.xblock .b-{extension}--{name}` with the block ids the editor selects by). A section view that takes extension blocks prints `$block['html']` for a block with a `source` (`product-main` does).

### 14.4 Extension templates

`Handles::extensionTemplates()` lists, by handle, the page types an extension registered with core (`StorefrontPage::isExtensionType()`) and ships `templates/{type}.json` for; a type that would take a Xelga handle is refused. They are `known()`, of kind `template`, their own page type and base (`Handles::baseFor()`). `Defaults` reads their shipped document from the parts folder; `Shape` requires their main section like any template's; `BootPayload::pages()` lists them after Xelga's pages with `extension: true`, `group` (the extension's title) and the preview address. `StorefrontPage::render()` draws them with `views/extension-page.blade.php`, whose sections read the controller's data through `RenderContext`; the breadcrumbs section reads the extension's trail. A template whose extension is away is not known: `Publisher::publish()`, `discard()` and `TemplateManager::summary()` leave its row and draft alone until the extension returns.

### 14.5 Dormant sections and blocks

`Shape` keeps an extension section whose type the catalog lacks, when a stored document (the draft, the published version, the shipped default, or the revision being restored) already holds that id with that type, exactly as stored (label, hidden flag, settings, block order and blocks; ids still checked; at most 16 KB each), with `source` from the type's prefix and `unchecked: true`; an extension block whose type no registered extension has is kept the same way inside its section when it was stored there, and takes no room against the section's block limits. Any other section or block of an absent extension is refused, and extension types are refused in the header and footer. `SectionRenderer` checks again, for any stored document, that an extension section sits in a template its schema allows (a main one only on its own page type) within its type's limit, and draws a section's extension blocks within their limits and the section's maximum. Dormant sections draw nothing for shoppers and, in the preview, `snippets/section-missing` names the extension ("Marketplace is switched off." or "Marketplace is not installed."); the layout board marks the section "Not shown" and offers only move and remove. Copying a template keeps them. Back on, the renderer cleans their values before the view (the `unchecked` path), and the next save checks them in full and drops the flag. Nothing is written when an extension is switched off, on, or removed.

### 14.6 Placements

`Extensions\Placements::compile()` checks each entry of `placements.json` at catalog time: a key (`[a-z0-9-]`, unique), either a `section` of the extension (not a main one) with the page types it goes on (`templates`, and `custom_templates` for their special layouts) and an optional `preset`, or a `block` of the extension with the theme `section` that takes it; `after` or `before` a type, and `otherwise` (`end`, `start`, `skip`). `Placements::overlay()` runs on every template a page reads (`DocumentRepository::content()`) and on what the editor edits (`DocumentPresenter::state()`): each placement the document has not recorded in `placed` inserts its section (id `p-` + 10 hex characters of the SHA-1 of the placement id, preset settings, `unchecked`) or its block, unless the page already has that type (then it is only recorded), or the insert would break the 40-section limit, a type's limit or where the type is allowed (then it waits). An inserted section gets a full-width row of its own after its neighbor (`Layout::normalize()`, section 5.2), so a default placement never depends on a span, a column or a side. `DocumentWriter` keeps every stored id and adds a saved one only when it is a placement the catalog has now or was already stored (`Placements::merge()`), so a removed section or block never returns, also after switching the extension off and on, and nobody can fill the record with made-up ids. Only a full record drops ids of extensions that are no longer installed.

### 14.7 Editor

The section picker shows Xelga's types in its four groups, then each extension's types in a group of their own under its `source_label`; an extension's `picker_group` and `picker_order` are not read. Add block lists Xelga's block types first, then each extension's under its `source_label`. The page picker lists extension templates after Xelga's pages under the extension's name. The boot payload's `extensions` (every installed extension, switched on or off, with its title and `on` or `off`; one not listed is not installed) lets the board name the extension of a dormant section. Extension section HTML reaches the editor only through `SectionOnly` and the frame's existing allowlist; API 1 has no extension section scripts. The storefront layout loads each extension's stylesheet after Xelga's bundle (`Extensions::stylesheetTags()`): from the store's build, else from the build the package ships (`App\Support\PackageVite`), else the file as it ships through `ExtensionStylesheetController` (route `xelga.extension-css`, addressed by a fingerprint of the file, cached for a year, only while the extension is on). The last case is logged once per catalog compile, so an extension installed from an upload is styled without a rebuild.

### 14.8 Product card lines

Core's `ProductCardLines` (contract section 9.9) reaches every shared card through `ProductCards::for($products, $swatches, $lines)`, which asks core once for the whole list and puts each product's lines in the card as `ext`; `snippets/product-card` prints them in `.card__ext` right under the title, before card details, color dots, rating and price. `$lines` is `CardLines::wanted($context)` for sections (always in the editor's preview, else the theme setting `show_extension_details`, Product cards > Show details from extensions, default on), null on fixed pages (the setting), and false where a product is drawn without the shared card (product spotlight, shop the look, the account dashboard's wishlist list), so no extension is asked for lines nobody sees. With no line registered, `CardLines::wanted()` answers false before reading any setting, so a store without such an extension runs no extra query. The setting also prints `data-card-ext` on `<html>`, so the editor hides and shows the lines live. `partials/product-card` (core's own pages) draws one product and never asks per card: it prints lines only when the page passes `$cardLines` from one `ProductCardLines::for()` call.

The places covered: category, brand and search listings (and their Load more, which imports server-drawn cards), featured products rows, related products, recently viewed and the wishlist page. No product card is drawn by script; the cart drawer lists cart lines.

### 14.9 Checks

`php artisan xelga:lint --extension={name}` (`CatalogLinter::checkExtension()`) applies Xelga's section checks to the extension's sections and blocks, plus: the type and file rules, views present and not links, a block's targets being Xelga sections that take extension blocks, a CSS rule in the extension's stylesheet for every range value (write them with `node bin/ranges.mjs --schemas <parts>/schemas/sections --out <file>`, which also covers `schemas/blocks`), template defaults (a registered page type, one main section of the extension's own, no store records, Shape-clean), and placements (each valid and allowed where it goes).

### 14.10 Tests

`tests/fixtures/extensions/sample-extension` uses every hook: the section types `sample-extension.card` and `sample-extension.main`, the block `sample-extension.seller`, the page type `sample_page` with its template, a section and a block placement, plus core's cart grouper, checkout page, order panels, a product card line and account link. `Tests\Support\UsesSampleExtension` links it into `extensions/` for a test, installs, switches off, back on, and removes it. Xelga's tests for it are `tests/Feature/XelgaExtension*Test.php`, `XelgaCartGroupsTest.php` and `XelgaProductCardLinesTest.php`.

