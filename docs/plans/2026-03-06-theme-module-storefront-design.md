# Theme Module & Storefront Design

**Date:** 2026-03-06
**Status:** Approved

## Overview

A Theme Module in the admin panel that provides full control over the storefront's appearance and layout — replacing OpenCart's Journal theme. Database-driven settings editable from admin. Single codebase deployed to multiple stores, each with its own database and theme configuration.

## Architecture

**Approach B: Typed Settings Table + Separate Module Tables**

- `settings` table for flat key-value config (colors, fonts, toggles, logo)
- Dedicated tables for ordered/structured data (homepage sections, nav items, footer columns)
- `ThemeService` singleton caches all settings, invalidated on admin save
- CSS custom properties (`--color-primary`, etc.) injected into storefront layout from DB values
- Same Blade templates render differently per deployment based on DB config

## Database Schema

### `settings`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| group | varchar | identity, header, colors, fonts, product_page, category_page, badges, filters, blog |
| key | varchar | |
| value | text (JSON or string) | |
| created_at, updated_at | timestamps | |

Unique constraint on (group, key).

### `homepage_sections`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| type | varchar | banner, products, brands, category_tabs, text_block, blog_posts |
| title | varchar | Display title |
| config | JSON | Type-specific configuration |
| sort_order | int | |
| is_enabled | boolean | default true |
| created_at, updated_at | timestamps | |

### `nav_menu_items`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| label | varchar | Display text |
| type | varchar | category, custom |
| category_id | bigint nullable | FK to categories |
| url | varchar nullable | For custom links |
| new_tab | boolean | default false |
| sort_order | int | |
| is_enabled | boolean | default true |

### `footer_columns`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| title | varchar | Column heading |
| type | varchar | links, payment_icons, social, text |
| config | JSON | Type-specific content |
| sort_order | int | |
| is_enabled | boolean | default true |

### `currencies`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| code | varchar(3) | PHP, USD, EUR, etc. |
| name | varchar | Philippine Peso |
| symbol | varchar | ₱ |
| rate | decimal(12,6) | Exchange rate relative to base |
| is_default | boolean | |
| is_enabled | boolean | |
| updated_at | timestamp | |

### `tags` + `product_tag`
```
tags: id, name, slug, created_at, updated_at
product_tag: product_id (FK), tag_id (FK)
```

### `blog_posts`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| title | varchar | |
| slug | varchar unique | |
| content | longtext | |
| excerpt | text nullable | Auto-generated if blank |
| featured_image | varchar nullable | |
| blog_category_id | bigint nullable FK | |
| view_count | int default 0 | |
| is_published | boolean default false | |
| published_at | timestamp nullable | |
| meta_title | varchar nullable | |
| meta_description | text nullable | |
| created_at, updated_at | timestamps | |

### `blog_categories`
```
id, name, slug, sort_order, created_at, updated_at
```

### `blog_post_tag`
```
blog_post_id (FK), tag_id (FK)
```

## Homepage Section Types

### 1. Banner/Slider
Config: `{ width: "full|contained", slides: [{ image, link, new_tab }], autoplay: bool, speed: ms }`

### 2. Products Carousel
Config: `{ filter: "new|featured|specials|recent|category|brand|best_sellers", category_id, manufacturer_id, limit, columns_mobile, columns_tablet, columns_desktop }`

### 3. Brands Carousel
Config: `{ speed, per_page, autoplay }`

### 4. Category Tabs
Config: `{ categories: [id, id, ...], limit, columns_mobile, columns_tablet, columns_desktop }`

### 5. Text Block
Config: `{ content, alignment: "left|center|right" }`

### 6. Blog Posts
Config: `{ limit, columns_mobile, columns_tablet, columns_desktop }`

## Responsive Columns (per product section)
| Screen | Range | Default | Configurable |
|--------|-------|---------|-------------|
| Mobile | <640px | 2 | 1-3 |
| Tablet | 640-1024px | 3 | 2-4 |
| Desktop | >1024px | 6 | 3-8 |

## Admin Theme Module — Tabs

1. **Identity** — Site name, logo upload, favicon upload
2. **Colors** — Color pickers: primary, secondary, nav bg, nav text, announcement bg/text, footer bg/text
3. **Fonts** — Heading font, body font (Google Fonts dropdown)
4. **Header** — Announcement bar (toggle, text, link, colors), top bar links (add/remove/reorder)
5. **Navigation** — Menu items builder (category or custom links, reorder, toggle, new tab)
6. **Homepage** — Section manager: list with reorder, enable/disable, expand to configure. "Add Section" button for new instances.
7. **Product Page** — Toggles: ratings, stock display, manufacturer logo, badges, thumbnail position (left/below), below-product modules (related, you might like, same brand, also bought, related articles) with order
8. **Category Page** — Filter toggles (price, availability, brand, rating, on sale, tags), products per page, default columns (responsive), default sort
9. **Badges** — NEW count, HOT count, custom badges (label + color + assign to manufacturer/category/product)
10. **Footer** — Column manager (add/remove/reorder, configure each column type)
11. **Currency** — Default currency, manage currencies, ECB fetch, auto-update schedule
12. **Blog** — Posts per page, grid/list default, excerpt length (chars), view count toggle, related articles on product pages toggle

## Storefront Pages

### Homepage
- Dynamic sections from `homepage_sections` table ordered by `sort_order`
- Each section type renders its own Blade partial
- `@foreach($sections as $section) @include('storefront.sections.' . $section->type) @endforeach`

### Category Listing
- Left sidebar with togglable filters (price range slider, availability, brand, rating, on sale, tags)
- Product grid with responsive columns
- Sort by: Default, Name A-Z, Name Z-A, Price Low-High, Price High-Low, Rating, Newest
- Grid/list view toggle
- Pagination

### Product Detail
- Image gallery (thumbnail position configurable)
- Product info: title, badges, ratings, price (with sale slashing), stock, variants, qty + Add to Cart
- Custom info badges (configurable text + image, e.g., "Free Shipping", "Buy Now Pay Later")
- Manufacturer logo/link
- Description tab
- Below-product modules (configurable, reorderable): Related Products, You Might Like, From Same Brand, People Also Bought, Related Articles

### Blog
- Listing: grid/list toggle, category filter, tag filter, pagination
- Single post: featured image, title, date, view count, content, tags, related posts
- Sidebar: categories, recent posts, tags

## File Structure

```
app/Http/Controllers/Admin/
    ThemeController.php
    HomepageSectionController.php
    NavMenuController.php
    FooterColumnController.php
    CurrencyController.php
    BlogController.php
    BlogCategoryController.php

app/Http/Controllers/Storefront/
    HomeController.php
    CategoryController.php
    ProductController.php
    BlogController.php

app/Services/
    ThemeService.php
    CurrencyService.php

app/Models/
    Setting.php
    HomepageSection.php
    NavMenuItem.php
    FooterColumn.php
    Currency.php
    Tag.php
    BlogPost.php
    BlogCategory.php

resources/views/
    admin/theme/
        index.blade.php              (tabbed settings)
        sections/                    (per-type config partials)
    storefront/
        layouts/store.blade.php      (main layout: header, nav, footer)
        home.blade.php
        sections/                    (banner, products, brands, category_tabs, text_block, blog_posts)
        category/index.blade.php
        product/show.blade.php
        blog/index.blade.php
        blog/show.blade.php
```

## Supporting Systems

- **ThemeService**: Cached singleton, `theme('group.key')` helper, cache invalidated on admin save
- **CurrencyService**: ECB XML feed parsing, rate storage, scheduled auto-update
- **CSS Variables**: Injected in `<head>` from ThemeService, all storefront CSS references variables
- **Automatic Badges**: NEW (last X products by created_at), HOT (top X by order count), Sale % (active special with display_mode=sale)
- **Custom Badges**: Admin-created, label + color, assigned to manufacturer/category/product

## Deferred (Not in This Build)

- Cart + Checkout
- Payments (HitPay, bank transfer, PayPal)
- Shipping (weight-based, geo zones)
- Product reviews/ratings system
- Customer account pages (login, register, order history, wishlist)
- Newsletter module
- RSS feed
- ERP sync API
- Data migration from OpenCart
- Additional homepage section types (gallery, testimonials, contact form, etc.)
