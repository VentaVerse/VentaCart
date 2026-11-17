# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

A VentaCart theme (Laravel Blade views, plain JavaScript, CSS built by Vite) that ships with VentaCart at `resources/themes/xelga`. It is a self-contained theme package: its own manifest, code, tables, views, assets, editor and tests, loaded by VentaCart's theme engine like any other theme.

## Users

- **Store owners** editing their own store day to day: changing a banner, swapping featured products, adjusting a section they see on the storefront. Not designers; they think in terms of what they see on the page, not in setting names.
- **Designers or agencies** doing a store's first setup for an owner, who want deeper control (spacing, color schemes, typography, section variants) without code.

## Product Purpose

Xelga is VentaCart's bundled storefront theme, with its own theme editor. The editor works from the storefront outward: the owner clicks the thing they want to change on a live preview, and only that thing's settings open. Success is an owner fixing what bothers them on the storefront without hunting through tabs or guessing what a label means, and a designer being able to build a distinct-looking store from the same theme.

## Positioning

Simple editor, full features. Every storefront capability VentaCart has is controllable, but the default view shows a short list of plain-language settings per section; deep options sit behind an Advanced disclosure. Guardrails (color schemes, matched font pairs, spacing steps) keep any combination of settings looking finished, so a store cannot easily be made ugly.

## Operating Context

- Built for VentaCart, an open source Laravel e-commerce platform. Theme scope is what VentaCart's storefront actually has (catalog, categories, brands, variants, specials, reviews, wishlist, blog, pages, cart, checkout, customer accounts, COD and online payment, Philippine shipping and couriers). Features VentaCart does not have (product compare, gift vouchers, affiliates, returns) are out of scope.
- The editor's interaction model follows the established click-to-edit pattern (sections, blocks, per-section settings) that store owners already know from hosted platforms.

## Capabilities and Constraints

- A theme only reads VentaCart core data and never writes core tables; everything the editor saves lives in the theme's own tables.
- Core business rules (customer-group pricing and visibility, stock, checkout rules, specials, shipping and payment restrictions) always win; the theme only displays them.
- Mobile responsive is mandatory for the storefront. The editor targets desktop and tablet, with a usable phone fallback.
- Terminology: "Variants" (not options), "Brands" (not manufacturers). SKU and model are hidden from the storefront.
- Eight starting stores, named for what they sell (Gadgets, Clothes, Furniture, Beauty, Coffee, Outdoor, Music, Parts), so the merchant's own store name is the one shoppers see. A starting store brings the look and home page only, never products. A fresh install starts from Gadgets, whose sections name the categories and products VentaCart's own installer catalog seeds.

## Evidence on Hand

- VentaCart's own gadget demo photos (`resources/demo/store`), which the Gadgets store ships.
- No testimonials, customer counts, or performance claims exist; none may be invented for this theme.

## Product Principles

1. Edit what you see: every setting is reached from the storefront element it changes.
2. Plain words over CMS jargon: labels describe the visible result.
3. Guardrails before freedom: curated schemes and steps by default, raw values only under Advanced.
4. Full VentaCart coverage: if it shows on the storefront, the theme can control it; if it does not, it is not in the editor.
5. One theme, many stores: the same sections compose into stores that look nothing alike.
