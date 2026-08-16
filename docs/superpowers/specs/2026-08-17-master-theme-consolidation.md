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
