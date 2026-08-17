# Master Theme Consolidation (Phase 1 of the CMS arc)

Owner-approved plan: consolidate the three folder themes into ONE master theme
at `resources/themes/venta/`, with looks expressed as JSON presets. Then
(later phases) a new default general-store look, a full CMS editor redesign
with live preview and preset manager, and a second light theme authored purely
through that editor.

## Evidence that drove the design

- stage-ready and crimson-dusk are near-copies: 432 diff lines of views, all
  hardcoded hex; 92 CSS lines, all color values. One reconciliation axis:
  clean-light vs the dark pair.
- clean-light views already speak tokens (`var(--color-*)`); the dark pair
  hardcodes 338 hex arbitrary classes (26 distinct values). Clean-light is the
  reference implementation.
- The dark-side hex value at each diff spot identifies the intended token
  (e.g. light `border-gray-200` / dark `#2A2A2A` = the `border` token).
- CSS: ~679 genuinely different lines of ~2200 after whitespace normalization;
  a single stylesheet with token defaults plus a few effect rules covers it.
- theme.js: functionally identical in all three (comment drift only).
- Settings schema superset: card_shadow, border_radius, header_style (light) +
  glow_intensity, noise_texture, led_indicators, scroll_reveal (dark).

## Rulings

- **Clean-light gets strict visual parity** (the owner is happy with that
  site). Stage-ready needs faithful approximation only; its preset is
  replaced in Phase 4. Where the two themes made different judgment calls,
  clean-light wins.
- Old theme folders are deleted only after screenshot parity is confirmed.
- No install-specific migrations: existing DBs adopt the master via a one-off
  `venta:adopt-master-theme` command; fresh installs default to venta.

## Architecture

```
resources/themes/venta/
  theme.json        master meta, full color map (defaults = current
                    clean-light values until Phase 2), superset settings
  presets/
    clean-light.json
    stage-ready.json
    crimson-dusk.json
  css/theme.css     one stylesheet, token-driven, effect rules neutral
                    in light presets (glow alpha 0, no noise class)
  js/theme.js
  views/            39 views from clean-light + tokenization sweep
```

Preset JSON shape: `{name, description, colors, fonts: {url, heading_stack,
body_stack}, options}`. theme.json is the base contract; a preset overlays it.

Settings storage (extends the existing pattern):
- live: `colors`, `fonts`, `theme_settings` (`venta_*` keys)
- per-preset snapshots on switch: `colors__{preset}`, `fonts__{preset}`,
  `theme_settings__{preset}`
- `identity.active_theme` = `venta`, `identity.active_preset` = slug

New tokens added to the vocabulary (from diff evidence): danger_bg,
danger_border, danger_text, success_bg, success_border, success_text; font
fallback stacks per preset; `--glow-primary/--glow-secondary` computed from
primary/secondary + glow_intensity option (alpha 0 when none). Body classes:
`venta` plus `noise-bg` when the noise_texture option is on.

ThemeService gains `getInstalledPresets()`, `getActivePreset()`,
`applyPreset($slug)` (generalizes the old switchTheme snapshot/restore across
all three families). Admin Active theme panel lists presets.

## Verification

- `npm run build`, full Feature suite green.
- Playwright screenshot parity: venta+clean-light preset vs old clean-light,
  venta+stage-ready preset vs old stage-ready, across home, category, product,
  cart, login, blog, contact, sitemap at desktop and mobile widths.
- Static check: master views contain none of the light-only utilities from
  the pair list (bg-gray-*, border-gray-*, red-50 family, from-white) except
  spots where all three themes agreed.

## Contract additions made during the build

- `vars` family beside colors/fonts/options: non-color tokens emitted as
  `--{kebab-key}` (track_nav, track_heading, brand_tile_dim). Same
  snapshot/overlay layering, live group `vars`, snapshots `vars__{preset}`.
- LED dots exist in BOTH original themes (clean-light topbar currency dot,
  product stock, banner). Master renders them always; `led_indicators` off
  adds body class `v-noleds` (hides), glow_intensity > none adds `v-glow`
  (currentColor glow). Body also carries `noise-bg` and `data-reveal`.
- New color tokens: danger_bg/border/text/accent, success_bg/text/accent,
  brand_tile_bg. Master options: glow_intensity, noise_texture,
  led_indicators, scroll_reveal (the dead card_shadow/border_radius/
  header_style schemas were dropped; nothing ever consumed them).
- The layout emits ALL tokens by looping theme.json colors + vars, with DB
  overrides; glow vars computed from primary/secondary + glow alpha map.
- Fonts: presets carry heading/body NAMES + fallback stacks;
  `google_fonts_url` was write-only dead data and is deleted by adopt.

## Deliberate deviations (owner-visible)

Clean-light bug fixes shipped with the master (its old views hardcoded
DARK values in a light theme): cart shipping-estimator inputs and disabled
estimate button, dark hairlines in account/checkout/product borders. Plus
the collapsible mobile nav (dark pair's newer implementation; clean-light
still had the always-expanded dump). Stage-ready approximation: nav/heading
tracking preserved via tokens; minor weight/size differences remain.

## Parity results (2026-08-17)

- clean-light: category byte-identical; home identical except brand-carousel
  scroll position; product 3 single-row AA artifacts; all other pages were
  identical once the LED topbar dot was restored.
- stage-ready: faithful (nav tracking, tiles, footer, glow all match);
  micro-differences in font weight remain, accepted per ruling.
- Dev DB adopted: active_theme=venta, preset clean-light live (owner's
  custom IBM Plex Sans + colors restored through the snapshot layering).

## Status

- [x] Recon and diff analysis
- [x] Scaffold venta theme + theme.json + presets (clean-light, stage-ready, crimson-dusk)
- [x] Token sweep of views (utility sweeps + adjudicated spots + drift-bug fixes)
- [x] CSS merge (tokens, glow appends, effect layer, tracking vars)
- [x] ThemeService presets API + controller + admin Active look panel + seeder + venta:adopt-master-theme
- [x] Parity screenshots (clean-light strict, stage-ready approximate)
- [ ] Old theme folders deletion (awaiting owner confirmation on their sites)
- [ ] Feature suite green (running)

## Phase 2a: the layout system (2026-08-17)

Journal-style choosable layouts, owner-scoped to all four regions. Each is an
option token in theme.json settings (so presets capture it), selecting Blade
variants; every default equals the pre-existing arrangement (parity held:
zero differing rows after the header extraction).

- header_layout: marketplace (original), centered, inline, search-under.
  Shared parts under views/partials/header/parts/ (topbar, logo, hamburger,
  search with $searchClass param, cart, mobile-search, nav-items); variants
  compose them. Search folds behind an icon in centered/inline
  (.hdr-searchpanel); search-under adds a full-width band (.hdr-under__band).
- category_layout: sidebar-left, sidebar-right, offcanvas. Shared
  partials/listing-sidebar.blade.php used by category and brand pages.
- product_gallery_side (left/right via lg:flex-row-reverse) and
  product_info_style (tabs/stacked); reviews extracted to
  product/partials/reviews.blade.php so both styles share it.
- footer_layout: columns (original), centered, split. Column content
  extracted to partials/footer/parts/column-content.blade.php.

Gotchas recorded: extracted partials must not swallow wrapper close tags
(the nav-items part briefly took the band's </div> and broke Alpine scopes);
new arbitrary Tailwind classes in partials need npm run build before they
paint; nav link styling is scoped .nav-bar > div > a so the inline variant
needed .nav-bar--inline > a added to four selector groups; .search-bar sizing
was scoped under .site-header and needed :is(.site-header, .hdr-under__band).

Draft presets (draft-paper-trail, draft-house-color, draft-stockroom,
draft-boutique) now carry layout pairings. Default look decision pending
owner pick from the round-two artifact. Admin login note: username `dev`.

## Phase 2b: default look decided (2026-08-17, owner verdicts)

- Standard (white, Figtree, marketplace layout) APPROVED as Venta's default:
  preset `standard`, seeder active_preset now `standard`, master theme.json
  fonts moved to Figtree (colors already matched).
- Centered: House Color's centered header + split footer approved, navy
  program NOT; recolored white with navy only on primary actions and brass
  on sale/stars. Ships as preset `centered`.
- Stockroom layout approved; ships as preset `stockroom` unchanged.
- Paper Trail not approved (wasted vertical space above the search band);
  draft deleted. The search-under header VARIANT stays but was tightened
  (slimmer identity row and band padding).
- All draft-* presets and their dev snapshot groups removed. Owner confirmed
  understanding: colors/fonts/sizes stay editor-editable per preset; layout
  arrangements are fixed templates chosen via Theme options.

## Phase 3 (2026-08-17): editor rebuilt, live preview parked

Live preview parked by the owner (kanban #119). Shipped instead:

- Visual layout pickers: schema type "layout" renders radio tiles with
  SVG arrangement diagrams (x-admin3.layout-pick); the five layout
  options use it. Unknown option values fall back to a text tile.
- Preset manager on the Theme page: Looks table (palette chips as
  SVG swatches, Live badge, Apply/Export/Delete), Save-the-live-look
  form, JSON import with validation (unknown families dropped, slug
  alphabet closed, active preset undeletable). x-admin3.confirm now
  forwards slot fields into its form (the Apply confirm posts preset).
- Record tabs: nav items, homepage sections, and footer columns all
  have dedicated edit pages (doctrine forms); section/footer creation
  redirects straight into the new record's edit page; tabs are plain
  drag lists now. Footer's bulk editor UI retired (endpoint kept).
- Banners extension fully v3: groups index, slides list, group form,
  and the 732-line slide editor (canvas preserved; drag/resize/layer
  mutations dispatch change events so the savebar sees state-only
  edits; the media browser generalized to serve bg images and layers,
  with upload, killing the v2 single_image_picker dependency and its
  polling hack). Eight icons added to the v3 set.
- Editor gaps closed for preset authoring: fonts list carries every
  bundled-preset face, letter-spacing (vars) editable on the Fonts
  tab, status/brand-tile tokens editable on the Colors tab.
- Doctrine addition: child widgets signal the form with DOM events
  (change / v3-rebaseline) rather than proxy-chain method lookups.

## Phase 4 (2026-08-17): Handbill authored through the editor

Concept roll (key 527c72e6) assigned "daylight gig flyer" from the
music-store world; exploitation one-sheet survived as competitive
alternate (its scale courage and ink-block footer were taken as
raises), teletext declined on the light-brief pin. The preset:
paper white + ink, Anton display (prices ride the heading face at
poster scale), Libre Franklin body, one Tube-Screamer green pull for
every action, sale red only where money moves, ink-block footer,
search-under header + centered footer layouts. Authored end to end
through the editor's own endpoints (settings save, options save,
save-as-preset) as the editor's acceptance test, saved as preset
`handbill`, live look restored to clean-light after capture.
