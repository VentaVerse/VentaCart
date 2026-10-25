# Venta Theme Contract

A theme is a package that draws the storefront. Core owns the data (products, layout rows, menus, looks, settings) and the routes; the theme owns every view, its stylesheet and its script. Any package that keeps this contract works with Layout, the looks editor, the widget catalogue and every storefront route. The contract is enforced in code by `App\Support\ThemeContract::check()` at install time; this document is the written form of the same rules.

Two themes ship with Venta and are the reference implementations: `venta` (the default) and `plaza`.

## 1. Package

A theme lives at `resources/themes/{slug}/`. The slug is lowercase letters, digits and hyphens. The folder is the package; nothing else has to be registered.

```
resources/themes/{slug}/
  theme.json          identity, colours, fonts, settings, regions, page settings
  css/theme.css       the stylesheet (compiled by Vite)
  js/theme.js         the script (optional, compiled by Vite)
  views/              every view in section 4
  presets/*.json      looks shipped with the theme (optional)
  layouts/*.json      shipped arrangements the Layout editor offers as templates (optional)
```

Vite discovers `css/theme.css` and `js/theme.js` on its own; run `npm run build` after adding a theme.

### Install, uninstall, live

Themes are discovered like extensions and recorded in the same registry under the name `theme-{slug}`. The Themes page (Extensions > Themes) lists every package on disk with Install, Use this theme and Uninstall. Installing runs the contract check and refuses a package that fails it, naming what is missing. Uninstalling removes the registry row and nothing else: the folder, the theme's layout rows and its settings stay, so installing it again finds them.

Exactly one installed theme is live (`identity.active_theme`). When the live theme is not installed, the storefront answers every GET with core's plain "No theme installed" page (HTTP 503); the admin keeps working, and POSTs (webhooks, callbacks) pass through.

The shipped default theme is installed with the other essentials on a fresh store. Stores updated from before this contract get their default and live themes registered by a continuity migration.

### 1.1 Extensions a theme brings

A theme may ship extensions of its own under `extensions/{name}/`, each with
the same `extension.json`, `src/`, `database/migrations` and routes as a
store extension. The kernel discovers them with everything else and tags
them `for_theme: {slug}`; they install, enable, disable and uninstall through
the same registry and the same admin routes. The theme's own panel lists
them; the store's Extensions page files them under the theme's name. A theme
cannot be uninstalled while one of its extensions is still installed.

## 2. theme.json

```json
{
  "name": "Plaza",
  "version": "1.0.0",
  "author": "Venta",
  "description": "…",
  "thumbnail": "thumbnail.png",
  "colors": { "primary": "#1f6f43", … },
  "fonts": { "heading": "Bricolage Grotesque", "body": "Source Sans 3", "heading_stack": "…", "body_stack": "…" },
  "settings": { "header_layout": { "type": "layout", "label": "Header", "default": "marketplace", "options": { … } } },
  "vars": { … },
  "regions": { "header_top": { "label": "Above the header" }, … },
  "page_settings": { … }
}
```

Required: `name`, `version`, `colors` with every key in section 2.1, `fonts.heading` and `fonts.body`, `regions` with every key in section 3.1. Every entry in `settings` needs a `type` and a `label`.

### 2.1 Colour vocabulary

A look (preset) is a value for each of these keys. The theme reads them as CSS custom properties named `--color-{key}` (underscores kept: `--color-text_muted`). Core writes the properties; the theme paints with them.

```
primary secondary bg text text_heading text_muted card_bg border content_text btn_text
nav_bg nav_text footer_bg footer_text footer_heading price input_bg input_border
breadcrumb_bg payment_badge_bg search_bg badge_new badge_hot star_rating sale_price
announcement_bg announcement_text logo_text badge_free_shipping card_hover dropdown_bg
sidebar_bg checkout_option_bg review_bg success error danger_bg danger_border danger_text danger_accent
```

`text_muted` on a light background must reach WCAG AA (4.5:1) against `bg`; `tests/Feature/PresetContrastTest.php` checks the shipped presets.

### 2.2 Fonts

`fonts.heading` and `fonts.body` are Google Fonts family names; `heading_stack` and `body_stack` are the fallback stacks. Core loads the families and writes `--font-heading` and `--font-body`.

### 2.3 Settings

Each entry is a control on the looks editor's Settings tab, stored as `theme_settings.{slug}_{key}` with the declared `default`. Types the editor draws:

| type | draws |
|---|---|
| `layout` | a row of illustrated choices (`options` keyed by value, each with `label` and optionally `description`) |
| `select` | a select (`options` keyed by value) |
| `toggle` | on/off (`default` `1`/`0`) |
| `text` | one line |
| `textarea` | many lines (for example `custom_css`) |
| `number` | a number (`min`, `max`, optional `unit`) |
| `color` | a colour swatch |
| `font` | a font family name; empty inherits the theme's heading or body font |
| `media` | a storage path chosen from the media library |
| `box` | four numbers in px as one value, `"top right bottom left"` |
| `multi` | a set of keys from `options`, stored comma separated |
| `rules` | a JSON list of rows whose columns are named in `fields` (colour rules, page backgrounds) |
| `images` | a JSON list of storage paths chosen from the media library (payment marks) |

A theme with an editor of its own (`editor` in theme.json) draws these with its own controls; the core looks editor draws the last five as plain inputs. Every setting may also carry `section`, `tab` and `heading` to say where the theme's editor shows it.

Read a setting from any view with `\App\Support\ThemeOptions::get('key', $default)`. It is memoised per request and safe in views that render before the layout.

### 2.4 Presets

`presets/{look}.json` holds `colors` (and optionally `fonts`) for a look. The looks editor lists them beside the merchant's own saved looks (which live in `storage/app/themes/{slug}/presets`). Shipped presets are read only.

### 2.4.1 Demo products and posts

A preset may carry `products` and `posts`, installed by `App\Services\DemoContentService` only while the catalogue is empty or entirely demo content. A product entry takes `name`, `price`, `sale`, `quantity`, `views`, `image` and `description`, and may also carry:

- `images`: more pictures after the main one (storage paths);
- `categories`: category names; a category the store lacks is made and removed again with the demo;
- `attributes`: `{"Origin": "Sagada"}` or `[{"group": "Roast", "name": "Level", "value": "Light", "card": true, "filter": true}]`; an attribute the store lacks is made (group default Details) and removed again with the demo.

A theme or extension adds its own extras with `DemoContentService::afterProduct(fn (Product $product, array $definition, string $slug) => …)`.

### 2.5 Shipped arrangements

`layouts/{name}.json` is an exported Layout template (the same format Layout exports): a list of rows with columns, style and placements. The Layout editor offers them under Templates as `theme:{name}`, and the merchant loads one into a region. A theme should ship at least a `home` arrangement so a store that switches to it has a homepage.

## 3. Regions and Layout

Layout is core. The merchant arranges rows of widgets per page type and region; the theme declares which regions it draws and draws them with the `x-storefront.region` component.

### 3.1 Required regions

Every theme declares and draws these four, so a merchant's placements survive a switch between themes:

| region | where |
|---|---|
| `header_top` | above the header, in `layouts/store.blade.php` |
| `content_top` | above the page content |
| `content_bottom` | below the page content |
| `footer_top` | above the footer, in `layouts/store.blade.php` |

`column_left` and `column_right` are optional; a theme that draws them wraps the page in `x-storefront.columns`, and the merchant's column widths (eighths) apply.

### 3.2 System regions

A region may declare its shipped contents:

```json
"header": { "label": "Header", "system": true, "page": "*", "defaults": ["system.header"] },
"product_main": { "label": "Product page", "system": true, "page": "product",
                  "defaults": [
                      { "columns": [4, 4], "cols": [["system.product_images"],
                                                    ["system.product_name", "system.product_price", "system.product_add_to_cart"]] },
                      "system.product_tabs", "system.product_related"
                  ] }
```

Until the merchant arranges such a region, the storefront renders the defaults as if they were placed, and the Layout editor shows them as "As shipped" rows that saving makes the merchant's own. `page` is the page type the region belongs to (`*` for every page). The widgets named in `defaults` are the theme's own pieces (section 5.2) or any core widget, with an optional `{ "widget": "…", "title": "…", "config": { … } }` form.

An entry with `columns` and `cols` is a row of its own: widths in eighths that add up to eight, and one list of pieces per column (an optional `responsive` takes the same `tablet` and `phone_order` lists a saved row does). Plain entries next to each other share one full-width row. `App\Support\Regions::defaultRows()` returns the rows; `defaults()` returns every piece in order. A one-category or one-level page shows the shipped rows while nothing up its chain has been arranged.

### 3.3 Rendering a region

```blade
<x-storefront.region name="content_top" />
```

renders the rows for the current page type plus the rows placed on every page. Rows with columns or a style render as `.layout-row.layout-row--{n}` with `.layout-col.layout-col--{w}` children; plain rows render bare. The theme imports the shared stylesheet at the top of `css/theme.css`:

```css
@import '../../../css/storefront/layout-rows.css';
```

Row style (background colour keyed to the vocabulary, background image, padding, text tone, full bleed) is written as custom properties on the row; the shared stylesheet applies them.

### 3.4 Page types

`home`, `category`, `product`, `search`, `brand`, `blog`, `page`, `cart`, `checkout`, `account`, `auth`, and `*` for everywhere. `App\Support\StorefrontPageType::current()` resolves the current one from the route.

A layout may also be arranged for a page more specific than a type. The keys are `*` (every page), a type (`category`), a level of categories (`category:level1` to `category:level3`), one category (`category:12`), one content page (`page:3`) and `quickview` (the product quick view fragment). `App\Support\LayoutPage` names them: `valid`, `label`, `type` (the page type a key belongs to), `chain` (from the most specific key down to `*`) and `currentKey` (the key the request is on).

A region shows every page's rows plus the rows of the most specific key in the chain that has any there: a category's own rows replace its level's, which replace all categories'. Columns follow the same chain. The core Layout editor offers the page types; a theme's own editor may offer the rest.

### 3.5 The intro area

A theme that declares an `intro` region draws the page's trail and title as arranged. Core registers `system.breadcrumbs` and `system.page_title` for it; a page view says what it is with `\App\Support\PageIntro::set($title, [['label' => …, 'url' => …], …])` and the theme's two widget views draw it. A theme with a `quickview` region (page `quickview`, default `system.quick_view`) lets the merchant arrange the quick view like any page.

## 4. Views the theme must provide

Core routes render these view names; the theme's `views/` folder is prepended to the view finder, so `home` is `views/home.blade.php`.

Always:

```
layouts.store  home  category.index  product.show  brand.index  brand.show  search.results
cart.index  checkout.index  checkout.confirmation  page.show  contact
account.dashboard  account.login  account.register  account.forgot-password  account.reset-password
account.profile  account.orders  account.order-detail  account.addresses  account.address-form
partials.product-card
```

While the extension exists on disk: `blog.index`, `blog.show` (blog); `review.submit`, `review.thankyou` (reviews).

Optional: `product.quick-view` (the quick view fragment; core answers 404 without it), `maintenance`, `sitemap`, `wishlist.index` (core draws its own inside the theme's layout with `partials.product-card`).

Every widget in the catalogue (section 5) needs its view.

### 4.1 The layout

`layouts/store.blade.php` is rendered through the core component `x-store-layout` (`App\View\Components\StoreLayout`), which hands it `siteName`, `navTree` (the composed main menu), `currencies`, `defaultCurrency`, `activeCurrency`, `cartCount`, `cartSubtotal`, `customerLoggedIn`, `customerUser`, `footerColumns`, fonts and the page title. The same bundle is shared with widget views.

Pages start with `<x-store-layout :title="…">` and yield their content inside it.

## 5. Widgets

Core keeps the catalogue (`App\Support\Widgets`, registered by `App\Storefront\Widgets\CoreWidgets` and by extensions). A widget is a key, a title, a schema for its settings, and a view name; the theme supplies the view under `views/widgets/{module}/{key}.blade.php`, receiving `$config` (the placement's settings, resolved against the schema) and `$placement`.

### 5.1 Core widgets

| key | view |
|---|---|
| `catalog.products` | `widgets/catalog/products` |
| `catalog.brands` | `widgets/catalog/brands` |
| `catalog.category_tabs` | `widgets/catalog/category_tabs` |
| `catalog.recently_viewed` | `widgets/catalog/recently_viewed` |
| `catalog.product_tabs` | `widgets/catalog/product_tabs` |
| `catalog.also_bought` | `widgets/catalog/also_bought` (product pages only) |
| `content.text` | `widgets/content/text` |
| `content.cta` | `widgets/content/cta` |
| `content.icon_list` | `widgets/content/icon_list` |
| `content.separator` | `widgets/content/separator` |
| `content.gallery` | `widgets/content/gallery` |
| `content.accordion` | `widgets/content/accordion` |
| `social.facebook` | `widgets/social/facebook` |
| `social.twitter` | `widgets/social/twitter` |
| `nav.menu` | `widgets/nav/menu` |
| `blog.posts` | `widgets/blog/posts` |
| `banners.group` (banners extension) | `widgets/banners/group` |
| `reviews.latest` (reviews extension) | `widgets/reviews/latest` |

The install check requires a view for every widget registered at the time, except system pieces for regions the theme does not declare.

### 5.2 System pieces

A theme's own parts become widgets when the theme declares the region they belong to. Core names them; the theme draws them.

| region | keys |
|---|---|
| `header` | `system.header` (the whole header as arranged), `system.topbar`, `system.logo`, `system.search`, `system.cart`, `system.nav` |
| `product_main` | `system.product_images`, `system.product_name`, `system.product_rating`, `system.product_info` (brand, free shipping note and stock, each shown or hidden by its settings), `system.product_price`, `system.product_countdown` (the sale's end), `system.product_variants`, `system.product_volume_pricing` (quantity tiers), `system.product_add_to_cart`, `system.product_tags`, `system.product_share`, `system.product_description`, `system.product_reviews`, `system.product_related`, `system.product_brand` (more from the brand); and two pieces that hold several of these at once, `system.product_buy` (gallery and buy box) and `system.product_tabs` (description and reviews) |
| `category_main` | `system.category_image`, `system.category_description`, `system.category_subcategories`, `system.category_products` (the paginated listing with its sort and view) |
| `intro` | `system.breadcrumbs` and `system.page_title` (section 3.5), which may also sit in `product_main` (breadcrumbs only) and `category_main` |

Views live at `views/widgets/system/{key}.blade.php`. Product pieces read the page's bundle from `App\Support\PageContext` (`extract(\App\Support\PageContext::all(), EXTR_SKIP)`), which `ProductController::show` publishes: `product`, `variantMatrix`, `variantTypes`, `effectivePrice`, `priceTiers`, `relatedProducts`, `sameBrandProducts`, `belowModules`, `reviewCount`, `averageRating`, and the rest of the product view's data. `CategoryController::show` publishes the category page's: `category`, `products` (the paginator), `subcategories`, `sort`, `settings`, `breadcrumbs` and the filter data.

The price, variants, volume pricing and add to cart pieces change together when a variant or a quantity is chosen. A theme that draws them as separate pieces gives them one shared script scope around the whole `product_main` region, so they keep working in whatever rows and columns the merchant puts them.

## 5.3 Product attributes

Products carry attributes (Catalog > Attributes): named facts in groups, one value per product. Themes read them through `App\Support\ProductAttributes`:

- `ProductAttributes::card($product)`: the attributes marked "show on product cards", in order, as `[['name' => 'Origin', 'value' => 'Sagada'], …]`. Core's listings (category, search, brand, product grids, related products) eager load the `cardAttributes` relation (`ProductAttributes::CARD_RELATION`), so a grid costs no extra query.
- `$attributeGroups` on the product page (and in `PageContext`): `[['name' => 'Farm', 'attributes' => [['name' => 'Origin', 'value' => 'Sagada'], …]], …]`.
- `$availableAttributes` on category pages: the filterable attributes among the category's products, `[['slug' => 'origin', 'name' => 'Origin', 'values' => [['value' => 'Sagada', 'count' => 3], …]], …]`. A filter form sends `attr[{slug}][]={value}`; values of one attribute widen the match, different attributes narrow it.

## 5.4 Wishlist

The wishlist is stored for signed-in customers and kept in the session for guests; signing in merges the guest's list into the account (`App\Services\WishlistService`).

- Page: `GET /wishlist` (`wishlist.index`), view `wishlist.index` with `$products`.
- State: `GET /wishlist/state` (`wishlist.state`) answers `{"ids": [12, 7], "count": 2}`.
- Add: `POST /wishlist/items` (`wishlist.add`) with `product_id`; remove: `DELETE /wishlist/items/{product}` (`wishlist.remove`). Both need the CSRF token, are limited to 30 a minute, and answer `{"in": true|false, "count": n}`. Only an active product the shopper may see can be added.
- Every storefront view has `$wishlistIds` and `$wishlistCount` (shared by `x-store-layout`). The default theme prints the ids as `window.__wishlist` and its `wishlistBtn` Alpine component dispatches `wishlist-updated` with `{productId, in, count}` after each change.

## 6. Menus

The main menu is composed in Layout > Menus (`nav_menus`, nested `nav_menu_items` with ten item kinds and mega menu options). The theme draws it from `$navTree` (`App\Support\NavTree::for('main')`), a list of resolved nodes with `label`, `url`, `children`, `accent`, `mega`, `banner`, `html`. Other menus are placed with the `nav.menu` widget.

## 7. Behaviour the theme must keep

- Prices come from `CartService::resolvePrice()` and the product's `effective_price`; the theme never computes a price.
- Add-to-cart, variant selection and quantity go through the `addToCart` Alpine component (theme.js) posting to `cart.add`.
- The reviews block renders only when `\App\Support\Reviews::enabled()` and the page setting `show_reviews` is on.
- SKU and model are internal and never shown.
- Sale countdowns hide when the end date is more than a year away.
- Phone numbers print with the country dialling code (`App\Support\Phone::display`).
- Order status badges use the status's tone (`App\Support\StatusTone`).
- Every page works on phone, tablet and desktop.
- No CSS or JavaScript inline in Blade; everything goes through `css/theme.css` and `js/theme.js`. The only exception is a merchant's own `custom_css` setting, which a theme prints filtered (see Plaza's `layouts/store.blade.php`).

## 8. Checklist for a new theme

1. Copy `resources/themes/venta` to `resources/themes/{slug}`; change `name`, `version`, `description`, `thumbnail` in `theme.json`.
2. Keep every colour key; change the values. Ship at least one preset.
3. Keep the four required regions; add system regions if the theme's header or product page should be rows.
4. Keep every view in section 4 and every widget view in section 5.
5. `npm run build`, then Extensions > Themes > Install. The install check names anything missing.
6. `php artisan test --filter=ThemePackagesTest` passes with the new theme on disk (the contract test checks every shipped theme).
