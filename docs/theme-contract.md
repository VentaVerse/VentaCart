# Theme contract

A theme is a package that draws the storefront. Core owns the data (products, prices, menus, settings) and the routes; the theme owns every view, its stylesheet, its script and its look, and draws its pages itself. Any package that keeps this contract works with every storefront route. The contract is enforced in code by `App\Support\ThemeContract::check()` at install time; this document is the written form of the same rules.

VentaCart bundles one theme, Xelga (`resources/themes/xelga`), named in `config/themes.php` (`'bundled' => 'xelga'`). It is a theme package like any other: core never loads its classes, and core boots and passes its tests with the folder removed. A fresh store installs and runs the bundled theme; when the stored live theme's folder is gone, the storefront falls back to it; and an upload may not replace it, because its updates ship with VentaCart. Other themes can still be installed beside it.

## 1. Package

A theme lives at `resources/themes/{slug}/`. The slug is lowercase letters, digits and hyphens. The folder is the package; nothing else has to be registered.

```
resources/themes/{slug}/
  theme.json          identity, and settings for a theme's own editor
  css/theme.css       the stylesheet (compiled by Vite)
  js/theme.js         the script (optional, compiled by Vite)
  views/              every view in section 4
  build/              the compiled stylesheets and scripts, for a theme sold as a download (optional)
```

Vite discovers `css/theme.css` and `js/theme.js` on its own; run `npm run build` after adding a theme.

A theme sold as a download cannot count on the store running that build, so it may carry one: `build/manifest.json` (a Vite manifest whose keys are the theme's entries, `resources/themes/{slug}/css/theme.css` and so on) beside the files it names. Installing or updating the theme copies `build/` to `public/packages/theme-{slug}/`, keeping only stylesheets, scripts, fonts, pictures, maps and JSON, and `@vite` serves an entry from there whenever the store's own build lacks it. A store that builds its front end compiles the theme with everything else, and its own build wins. Uninstalling removes the copy. An extension may carry a `build/` the same way (`public/packages/{name}/`).

### Install, uninstall, live

Themes are discovered like extensions and recorded in the same registry under the name `theme-{slug}`. The Themes page (Themes, in the admin sidebar) lists every package on disk with Install, Use this theme and Uninstall. Installing runs the contract check and refuses a package that fails it, naming what is missing. Uninstalling rolls back every migration the theme ran (whichever batch each ran in, so tables from an earlier version go too) and removes the registry row. The folder and the theme's settings in core stay, so installing it again finds them. A theme's own extensions follow the same rule when they are uninstalled.

### Upload

An admin holding the `extensions.upload` permission ("Upload Extensions" in User Groups; Super Admin holds it, no other shipped role does, since a package runs PHP on the server) installs a theme or an extension from a `.zip` on **Extensions > Upload** (`admin.extensions.upload`), and updates it by uploading a newer one. The zip holds one folder named after the package (`my-theme/` for a theme with slug `my-theme`), with its `theme.json` or `extension.json` directly inside; an extension's `name` must equal its folder. `App\Services\PackageUploader` does the work:

1. The upload is recorded in `package_uploads` as `pending` before anything on disk changes.
2. The archive is read without unpacking it (`App\Support\PackageArchive`) and refused when it is not a zip, holds an absolute path, a backslash, a `..`, `.` or empty segment, a control character, a name with `:` `*` `?` `"` `<` `>` `|`, a trailing dot or space, a Windows device name (`CON`, `NUL`, `COM1`), two entries whose names differ only in case, a link, a device file or an encrypted entry, more entries than `extensions.upload.max_files`, a file over `max_file_mb` or more than `max_unpacked_mb` in all, or more than one top folder, or when its manifest is missing, is not JSON, or has no version of at most 40 characters (`1.2.3`, `1.2.3-beta.1`). An entry that unpacks to more bytes than the archive declares is stopped while it is written. The upload itself is capped at `max_size_mb` (`config/extensions.php`) and by PHP's own upload limits.
3. A provider (`"provider"` in the manifest) must be in a namespace of the package's own: never one of the application's, the framework's or any library Composer loads (`App\`, `Illuminate\`, `GuzzleHttp\`, `Psr\` and the rest), and never one another package's provider is in, or that holds or sits inside it. Namespaces are compared without regard to case, as PHP compares them.
4. The target folder is checked: the bundled theme, a name core keeps for its own pages under `extensions/`, a folder that is a link, a folder on disk that no earlier upload put there, and a version older than the one on disk are refused. An extension the package brings is checked like an uploaded one (name, provider, version) and must not take a name another package already uses, nor be older than the version of it installed.
5. A migration file may not share its name with one of core's, one of another package's, or one that already ran on the store from somewhere else: Laravel knows a migration by its file name alone, so it would be skipped as already run, and uninstalling the package would roll back the other one.
6. The archive is unpacked byte by byte into a hidden `.upload-*` folder beside the target. A theme is checked against the contract (`ThemeContract::check`), and a `build/` folder holding a file a web server could run or read as its settings (any part of the name being `php`, `phtml`, `phar`, `cgi` and the like, `.htaccess`, `.user.ini`, any name starting with a dot) or that can run script in the browser (`.svg`, `.html`, `.xml`) is refused.
7. The target is checked again, since unpacking takes time and a deploy may have put a folder there. Then the folder is moved into place with a rename (the previous files are moved aside), and the package is installed through `ExtensionManager::install`, the same code as the Install and Update buttons, which runs its migrations and publishes its `build/`. The extensions a theme brings that are already installed are updated with it, so their migrations run and their version is recorded.

When installing fails, the upload is recorded as `failed` and everything it changed is undone: the migrations it ran are rolled back (every batch after the one the store had before, for the package's own migration files only), the previous files are put back, or a new package is removed, and the registry rows and published builds are what they were. When the previous files cannot be put back, nothing is deleted: they stay in the hidden folder, the log says where, and the message on the screen says so. A successful install is never recorded as refused or failed.

Every upload, installed, updated, refused or failed, is recorded in `package_uploads` (who, the file name and its SHA-256, the package, the version before and after) and in the log. Text from a manifest has control characters and line breaks removed before it is shown or logged, so a package cannot forge log lines, and every value is cut to its column's length (`PackageUpload::LENGTHS`), so a strict database never refuses the record. Uploads are limited to `extensions.upload.per_minute` a minute per admin.

**Interrupted uploads.** An upload raises PHP's time limit to `extensions.upload.max_seconds` (900 by default, never lower than the server's own) and keeps going when the admin closes the page. A web server's own limits (nginx `fastcgi_read_timeout`, PHP-FPM `request_terminate_timeout`) still apply and should allow as long. When PHP stops part way anyway, on a timeout, running out of memory or another fatal error, a shutdown handler undoes the upload as above and releases the lock. When the process is killed outright, nothing runs at that moment; a journal file in the hidden folder says what the upload had done, and the next upload, or the next visit to the Upload screen, finishes the undo and closes the `pending` record.

**One change at a time.** Uploads and the Install, Update, Enable, Disable and Uninstall buttons take one lock (`App\Support\PackageLock`), an `flock()` on `storage/framework/package-changes.lock` (`extensions.upload.lock_file`). It holds across processes whatever cache store the store runs, and the operating system releases it when the process ends. A store with several web servers must keep that file on a shared disk. A change waits up to `extensions.upload.lock_wait_seconds` (10) for another one, then is refused.

**What installing runs.** Installing a package runs its code: its migrations during the upload, and from the next request on, its provider, which boots whenever the package is installed and switched on. For a theme that is true whether or not it is the live theme, so its editor and admin pages work before it goes live; a theme's provider should only register routes, views and listeners, never change data as it boots. A first install is switched on at once, the same as the Install button. Upload only packages from someone you trust.

**Folders.** Uploads need the web server to write to `resources/themes`, `extensions` and `public/packages`.

**Published builds.** Only stylesheets, scripts, fonts, raster images, source maps and JSON are copied from `build/` to `public/packages/{name}`, and a new copy replaces the old one with two renames, so a failure part way leaves the earlier copy. The folder gets an `.htaccess` that switches PHP off for Apache, and `docker/nginx.conf` serves `/packages/` as static files only. A package's Vite entries must be its own source files (`resources/themes/{slug}/...` for a theme, `extensions/{name}/...` or the theme folder that brings it for an extension); entries naming another package's or the store's sources are dropped, so one package's build can never stand in for another's.

### Updates

When a theme's files carry a higher `version` than the one installed, the theme is awaiting an update. The storefront keeps drawing with it, and the Themes and Extensions screens show "Update available" with an Update button, which records the new version and runs any migrations the new files bring (`database/migrations`). Only a theme whose new files fail the contract check stops being served until it is updated or fixed.

Exactly one installed theme is live (`identity.active_theme`). When the live theme is not installed, the storefront answers every GET with core's plain "No theme installed" page (HTTP 503); the admin keeps working, and POSTs (webhooks, callbacks) pass through.

The bundled theme is installed with the other essentials on a fresh store. A store whose live theme can no longer draw (its folder is gone, or it is not installed) is moved onto the bundled theme by the upgrade migration `2026_11_18_110000_switch_storefront_to_the_bundled_theme`.

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
  "name": "Demo Theme",
  "version": "1.0.0",
  "author": "Venta",
  "description": "…",
  "thumbnail": "thumbnail.png",
  "provider": "Themes\\Demo\\DemoThemeServiceProvider",
  "editor": "admin.demo.editor",
  "storage": "own",
  "settings": { "header_layout": { "type": "layout", "label": "Header", "default": "marketplace", "options": { … } } }
}
```

Required: `name` and `version`; every theme carries its own version, the bundled one included. Every entry in `settings` needs a `type` and a `label`. Keys from older themes (`regions`, `widgets`, `page_settings`, `grid`, `layouts/*.json`) are ignored.

### 2.1 Colors and fonts

A theme's colors and fonts are its own. It sets them in its stylesheet (as CSS custom properties on `:root` in `css/theme.css`, for example) and loads its fonts itself. Core neither stores nor prints them. A theme that lets the merchant change them does so in an editor of its own (section 2.5), keeping the values in its own storage.

### 2.2 Settings

Settings are for a theme with an editor of its own (`editor` in theme.json): each entry is a control in that editor, read as `theme_settings.{slug}_{key}` with the declared `default`. Core has no editor for them. The types a theme's editor is expected to draw:

| type | draws |
|---|---|
| `layout` | a row of illustrated choices (`options` keyed by value, each with `label` and optionally `description`) |
| `select` | a select (`options` keyed by value) |
| `toggle` | on/off (`default` `1`/`0`) |
| `text` | one line |
| `textarea` | many lines (for example `custom_css`) |
| `number` | a number (`min`, `max`, optional `unit`) |
| `color` | a color swatch |
| `font` | a font family name; empty inherits the theme's heading or body font |
| `media` | a storage path chosen from the media library |
| `box` | four numbers in px as one value, `"top right bottom left"` |
| `multi` | a set of keys from `options`, stored comma separated |
| `rules` | a JSON list of rows whose columns are named in `fields` (color rules, page backgrounds) |
| `images` | a JSON list of storage paths chosen from the media library (payment marks) |

Every setting may also carry `section`, `tab` and `heading` to say where the theme's editor shows it.

Read a setting from any view with `\App\Support\ThemeOptions::get('key', $default)`. It is memoised per request and safe in views that render before the layout.

### 2.3 Demo products and posts

A theme may install demo `products` and `posts` with `App\Services\DemoContentService::install($slug, $definition)`, which adds them only while the catalog is empty or entirely demo content. A product entry takes `name`, `price`, `sale`, `quantity`, `views`, `image` and `description`, and may also carry:

- `images`: more pictures after the main one (storage paths);
- `categories`: category names; a category the store lacks is made and removed again with the demo;
- `attributes`: `{"Origin": "Sagada"}` or `[{"group": "Roast", "name": "Level", "value": "Light", "card": true, "filter": true}]`; an attribute the store lacks is made (group default Details) and removed again with the demo.

A theme or extension adds its own extras with `DemoContentService::afterProduct(fn (Product $product, array $definition, string $slug) => …)`.

### 2.4 A theme with storage of its own

```json
{ "editor": "admin.example.panel", "storage": "own" }
```

A theme that declares `"storage": "own"` keeps its settings and page designs in tables of its own (from its `database/migrations`, named with its own prefix) and only reads core. Switching to it sets `identity.active_theme` and nothing else. Uninstalling it rolls its migrations back, so its tables go with it.

`editor` names the route of the theme's own editor; the Themes page links to it. A theme that brings code of its own (an editor, routes, listeners) names its `provider`; its `src/` is autoloaded at runtime by the extension kernel, not by Composer, and the provider boots whenever the theme is installed, live or not, so it must act only while the theme is live and never write data as it boots. Xelga is the worked example of all three.

## 3. Page types

`home`, `category`, `product`, `search`, `brand`, `blog`, `page`, `cart`, `checkout`, `account`, `auth`. `App\Support\StorefrontPageType::current()` resolves the current one from the route, so a theme can tell its pages apart.

An extension with storefront pages of its own adds a page type for them from its service provider's `boot()`:

```php
\App\Support\StorefrontPageType::register('shop', 'Shop pages', ['marketplace.shop.']);
```

The first argument is the type (lower case letters, digits and underscores; core's own types are refused), the second its label, the third the route name prefixes it covers. Core's prefixes are matched first, so an extension never takes over a core page. `StorefrontPageType::all()` lists it and `current()` returns it on those routes. A type whose extension is switched off is not registered.

The extension's controller draws such a page through `App\Support\StorefrontPage::render()`, so the live theme can lay it out:

```php
return \App\Support\StorefrontPage::render('shop', 'marketplace::shop.show', [
    'title' => $shop->name,
    'subject' => $shop,
    'breadcrumbs' => [['label' => 'Shops', 'url' => route('marketplace.shop.index')], ['label' => $shop->name]],
    'shop' => $shop,
]);
```

When the live theme provides the optional view `extension-page` (section 4), that view draws the page; otherwise the extension's own view (the second argument) gets the data as given. A page type no extension registered always gets the extension's view.

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

Optional, for extensions (section 9): `extension-page`, a page of an extension's page type. It receives `pageType` (the type), `title` (string or null), `subject` (the model the page is about, or null), `breadcrumbs` (a list of `{label, url}`, labels as plain text and links already through `SafeInput::link`, `url` null when there is none) and `page` (every value the extension passed, for the page's sections to read).

### 4.1 The layout

`layouts/store.blade.php` is rendered through the core component `x-store-layout` (`App\View\Components\StoreLayout`), which hands it `siteName`, `navTree` (the composed main menu), `currencies`, `defaultCurrency`, `activeCurrency`, `cartCount`, `cartSubtotal`, `customerLoggedIn`, `customerUser`, `wishlistIds`, `wishlistCount` and the page title. The same bundle is shared with every view the page draws.

Pages start with `<x-store-layout :title="…">` and yield their content inside it.

### 4.2 Stylesheets

`css/theme.css` and `js/theme.js` are the theme's bundles, loaded on every page. CSS that only some pages need may live in `css/pages/*.css`; each file is its own build entry, and the page's view loads it, so the shared bundle stays small:

```blade
@push('head')
    @vite('resources/themes/{slug}/css/pages/checkout.css')
@endpush
```

## 5. Reading core data

Product pages publish their data through `App\Support\PageContext` (`extract(\App\Support\PageContext::all(), EXTR_SKIP)`), which `ProductController::show` fills: `product`, `variantMatrix`, `variantTypes`, `effectivePrice`, `priceTiers`, `relatedProducts`, `sameBrandProducts`, `belowModules`, `reviewCount`, `averageRating`, and the rest of the product view's data. `CategoryController::show` publishes the category page's: `category`, `products` (the paginator), `subcategories`, `sort`, `settings`, `breadcrumbs` and the filter data. The price, variants, volume pricing and add to cart parts of a product page change together when a variant or a quantity is chosen, so a theme gives them one shared script scope.

### 5.1 Product attributes

Products carry attributes (Catalog > Attributes): named facts in groups, one value per product. Themes read them through `App\Support\ProductAttributes`:

- `ProductAttributes::card($product)`: the attributes marked "show on product cards", in order, as `[['name' => 'Origin', 'value' => 'Sagada'], …]`. Core's listings (category, search, brand, product grids, related products) eager load the `cardAttributes` relation (`ProductAttributes::CARD_RELATION`), so a grid costs no extra query.
- `$attributeGroups` on the product page (and in `PageContext`): `[['name' => 'Farm', 'attributes' => [['name' => 'Origin', 'value' => 'Sagada'], …]], …]`.
- `$availableAttributes` on category pages: the filterable attributes among the category's products, `[['slug' => 'origin', 'name' => 'Origin', 'values' => [['value' => 'Sagada', 'count' => 3], …]], …]`. A filter form sends `attr[{slug}][]={value}`; values of one attribute widen the match, different attributes narrow it.

### 5.2 Prices

A theme never picks a special itself. `App\Support\Pricing::display(Product $product, ?int $variantId = null, int $quantity = 1)` answers what the cart will charge and why, using the cart's own rule (`CartService::winningSpecial()`): among the specials that apply now to the shopper's customer group (the default group for guests), the deepest quantity tier at or below the quantity wins, then the lower priority value, then the lower price. A variant's price carries the product's special through its difference from the product price, as the cart does.

It returns an `App\Support\DisplayPrice`:

| member | meaning |
|---|---|
| `price` (float) | what the cart charges for one unit |
| `regularPrice` (float) | the product's or variant's own price, before any special |
| `special` (`ProductSpecial` or null) | the special the cart applies |
| `displayMode` (`'sale'`, `'override'` or null) | how the special asks to be shown |
| `endsAt` (Carbon or null) | the special's last moment, inclusive, in the store's time zone: count down to this, never to the end of its day |
| `endsAtIso()` | `endsAt` as ISO 8601 with the store's offset (`2026-10-31T21:30:00+08:00`), for a countdown script |
| `isSale()` | true when the regular price should be struck through beside `price` |
| `savingPercent()` | the saving as a whole percentage, 0 when none |
| `toArray()` | the same as plain values, for JSON and script data (`ends_at` is `endsAtIso()`) |

Format amounts with `money()`. A card draws:

```blade
@php $price = \App\Support\Pricing::display($product); @endphp
@if ($price->isSale())
    <s>{{ money($price->regularPrice) }}</s> {{ money($price->price) }}
@else
    {{ money($price->price) }}
@endif
```

A special runs from `date_start` to `date_end` (both datetimes, store time, either may be empty). A special saved with a date and no time covers that whole day: it starts at 00:00:00 and ends at 23:59:59. To ask whether a special applies, use `$special->isActiveAt()` or the `ProductSpecial::activeAt()` query scope rather than comparing the dates in a view.

Core's listings eager load `specials`, so a grid costs no extra query. The product page also publishes the result as `$displayPrice` (and in `PageContext`), with `$activeSpecial` set when it is a sale and `$overrideSpecial` for any other special.

### 5.3 Wishlist

The wishlist is stored for signed-in customers and kept in the session for guests; signing in merges the guest's list into the account (`App\Services\WishlistService`).

- Page: `GET /wishlist` (`wishlist.index`), view `wishlist.index` with `$products`.
- State: `GET /wishlist/state` (`wishlist.state`) answers `{"ids": [12, 7], "count": 2}`.
- Add: `POST /wishlist/items` (`wishlist.add`) with `product_id`; remove: `DELETE /wishlist/items/{product}` (`wishlist.remove`). Both need the CSRF token, are limited to 30 a minute, and answer `{"in": true|false, "count": n}`. Only an active product the shopper may see can be added.
- Every storefront view has `$wishlistIds` and `$wishlistCount` (shared by `x-store-layout`).

### 5.4 Product listings

A theme that lists products outside a category, brand or search page (a carousel, a tab, a block of its own) gets them from `App\Support\ProductFeed::get(array $criteria): Collection`, never from its own query.

| criterion | meaning |
|---|---|
| `filter` | `new` (default, newest by date available), `best_sellers` (most revenue, then most viewed), `on_sale` (an active sale special), `most_viewed`, `random`. An unknown value reads as `new`. |
| `ids` | hand-picked product ids, returned in the order given; unknown, inactive or hidden ids are dropped; an empty list returns nothing. `ProductFeed::parseIds('12, 7 31')` reads a typed list. |
| `category` | a category id; `subcategories` (default true) includes its descendants. A missing or switched-off category returns nothing. |
| `brand` | a brand id. A missing or switched-off brand returns nothing. |
| `limit` | 1 to 48, default 12, clamped. |
| `variants` | true also loads the active variants. |

Only active products are listed, through the customer-group visibility scope: a product kept for a group never reaches a guest or another group. The products carry `images`, `specials`, `brand` and the card attributes, plus `reviews_avg_rating` and `reviews_count` while reviews are enabled. Prices still come from `Pricing::display()` (section 5.2).

## 6. Menus

Menus are core data, edited at Content > Menus (`nav_menus`, nested `nav_menu_items` with ten item kinds and mega menu options). The theme draws the main menu from `$navTree` (`App\Support\NavTree::for('main')`), a list of resolved nodes with `label`, `url`, `children`, `accent`, `mega`, `banner`, `html`, and any other menu from `NavTree::for($key)`, for example the `footer` menu. A theme links its "Edit menus" button to `route('admin.menus')` behind `Route::has()`.

## 7. Behavior the theme must keep

- Prices come from `App\Support\Pricing::display()` (section 5.2); the theme never computes a price.
- Add-to-cart, variant selection and quantity post to core's `cart.add`, `cart.update` and `cart.remove`; a theme never changes a cart any other way.
- A cart page or drawer draws the groups `CartGroups::for()` returns when they are not null (section 9.2), each line once, under its group.
- The reviews block renders only when `\App\Support\Reviews::enabled()`.
- SKU and model are internal and never shown.
- Sale countdowns hide when the end date is more than a year away.
- Phone numbers print with the country dialling code (`App\Support\Phone::display`).
- Order status badges use the status's tone (`App\Support\StatusTone`).
- Every page works on phone, tablet and desktop.
- Links and pictures a merchant enters go through `App\Support\SafeInput` (a path, an anchor or an http(s), mailto or tel address; storage paths for pictures). Print them with `{{ }}`, never `{!! !!}`, and pass anything that goes into a script through `@js()`.
- No CSS or JavaScript inline in Blade; everything goes through `css/theme.css` and `js/theme.js`. The only exception is a merchant's own `custom_css` setting, which a theme prints filtered.

## 8. Checklist for a new theme

1. Start from a copy of a theme that keeps the contract: `tests/fixtures/themes/sample` is a plain one, `resources/themes/xelga` a full one with its own editor and storage. Change `name`, `version`, `description` in `theme.json`.
2. Set the theme's colors and fonts in `css/theme.css`.
3. Keep every view in section 4.
4. `npm run build`, then Themes > Install. The install check names anything missing.
5. `php artisan test --filter=ThemePackagesTest` passes with the new theme on disk (the contract test checks every theme in `resources/themes`).

## 9. Extensions inside a theme (hooks API 1)

An extension can add to the storefront without editing a theme, and a theme can show what extensions add without knowing them. Every hook below is optional on both sides: with no extension registered, every theme draws as before, and a theme that ignores a hook still works.

These names are API 1 of the hooks. Renaming or removing any of them, or changing a shape below, is a new API version.

### 9.1 Pages of an extension's page type

Section 3: the extension registers a page type and draws its pages with `StorefrontPage::render()`; a theme that wants to lay such pages out provides `extension-page` (section 4).

### 9.2 Cart groups

An extension may split the cart's lines into groups a theme draws under their own header, such as one group per seller. It implements `App\Contracts\CartLineGrouper` and registers the class from its provider's `boot()`:

```php
\App\Support\CartGroups::use(\Extensions\Marketplace\Cart\SellerGrouper::class);
```

One grouper per store: a second, different one is refused and logged. The grouper receives the cart's `CartItem` lines and returns a list of groups. Core checks the answer and hands themes `CartGroups::for($items)`, which is null for the plain cart (no grouper, a grouper that threw or answered nonsense, an empty cart) or:

```json
[
  { "key": "shop-12", "label": "Demo Store", "url": "https://example.com/shops/demo-store",
    "lines": [41, 42],
    "shipping": { "label": "Shipping from this shop", "amount": 120.0, "note": "Ships in 2 days" } },
  { "key": "other", "label": "", "url": null, "lines": [43], "shipping": null }
]
```

- Every cart line is in exactly one group, in cart order: the first group that names a line keeps it, and lines no group names are collected in a last group with the key `other` and an empty label, which a theme draws with no header.
- `key` is 1 to 40 lower case letters, digits or hyphens, unique; `other` is core's. At most 50 groups (the last one being `other`); lines of groups past that go to `other`.
- `label` is plain text up to 80 characters, `note` up to 160; `url` is a link `SafeInput::link` keeps, else null; `amount` is a non-negative number or null; `shipping` is null when it says nothing.
- Shipping in a group is information for the shopper. What the order costs is still worked out by core's checkout.

Where it reaches a theme: the `cart.index` view receives `$cartGroups`, and `GET /cart/mini` (`cart.mini`) answers `groups` beside `items`. A theme that draws a cart draws a header per group when `groups` is not null (section 7); the totals, the coupon and the checkout button are unchanged.

### 9.3 Account menu

An extension adds links to the customer's account menu from its provider's `boot()`:

```php
\App\Support\AccountMenu::add('marketplace.account.shops', 'My shops', 'store');
// add(string $route, string $label, string $icon = 'file', ?string $match = null, int $position = 100)
```

A link is a route name, never an address. `AccountMenu::links()` returns, by position (then in the order added), the links whose route exists and needs no parameters: `[{url, label, icon, match, current}]`. `label` is plain text up to 40 characters, to be printed with `{{ }}`. `icon` is one of `file`, `wallet`, `return`, `store`, `star`, `gift`; a theme draws one it lacks as `file`. `match` is the route name pattern (`{route}*` by default) and `current` is true on a route it matches, for `aria-current`.

Core's own links (dashboard, orders, addresses, wishlist, profile) are not in the list: a theme keeps drawing them, then draws these after them.

### 9.4 Checkout page

One extension may draw the checkout page itself (a checkout split by seller, say), from its provider's `boot()`:

```php
\App\Support\CheckoutPage::use(\Extensions\Marketplace\Http\CheckoutController::class);
```

While it is registered, the `checkout.index` route calls that controller: its `__invoke()`, else its public `index()`, with method injection. Only that page changes hands. The route, its middleware (signed-in customers only, not indexed) and every other checkout route (`checkout.place`, `checkout.address.save`, `checkout.shipping-rates`, `checkout.confirmation`, the payment returns) stay core's, so the order is placed, priced and paid through core's checks, CSRF included. The extension's page posts to them as core's page does.

When the class is missing, or is neither invokable nor has a public `index()`, core draws its own checkout and logs the problem. One registration per store: a second, different one is refused and logged. Unregistered, nothing changes.

The extension's view draws inside the live theme's page frame when the theme has one (section 9.6):

```blade
@extends(view()->exists('extension.page') ? 'extension.page' : 'marketplace::layouts.plain')
```

### 9.5 Order panels

An extension adds fragments to the customer's order pages from its provider's `boot()`:

```php
use App\Support\OrderPanels;

OrderPanels::add(OrderPanels::ROW_AFTER, fn (\Illuminate\Support\Collection $orders) => [/* order id => fragment */]);
OrderPanels::add(OrderPanels::DETAIL_SUMMARY, fn (\App\Models\Order $order) => new \Illuminate\Support\HtmlString('…'));
OrderPanels::add(OrderPanels::DETAIL_ACTIONS, fn (\App\Models\Order $order) => '…');
```

| slot | where a theme draws it | the callable receives | it returns |
|---|---|---|---|
| `order.row.after` | under each row of `account.orders` | the page's orders, once (so it can read what it needs in one query) | fragments keyed by order id |
| `order.detail.summary` | inside the order summary of `account.order-detail` | the `Order` | one fragment |
| `order.detail.actions` | with the actions of `account.order-detail` (back to orders, cancel) | the `Order` | one fragment |

A fragment is an `Htmlable` or a string. It is printed as given, so the extension escapes every value it puts in one. A fragment for an order not on the page, or anything that is neither, is dropped. A callable that throws is reported and left out; the page still draws. Fragments of one slot are joined in the order they were added. Unregistered, nothing is drawn.

A theme draws them with:

```blade
@php $orderPanels = \App\Support\OrderPanels::rows($orders); @endphp   {{-- once, before the rows --}}
{{ $orderPanels[$order->id] ?? '' }}                                       {{-- under a row --}}
{{ \App\Support\OrderPanels::render('order.detail.summary', $order) }}
{{ \App\Support\OrderPanels::render('order.detail.actions', $order) }}
```

The order pages are the customer's own: core's controller has already checked the order belongs to the signed-in customer before the theme draws it. A callable reads only what that customer may see.
