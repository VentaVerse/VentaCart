# Storefront draft: The Front Panel

Owner-confirmed. A DRAFT, not a shipped theme. Nothing in `resources/themes/` is touched.

## What the owner asked for

"I want a redesign of the front end making it more music store like. Can you create me a draft?"

Confirmed in the interview:
- **The belief to earn:** these people actually play. Run by musicians who know the gear, not a dropshipper. Curation, knowledge and real opinions are the product.
- **Assets available:** catalog shots (1,791 real images at `storage/app/public/catalog/`), in-house product photography, shop/workbench/staff photos, and artist/customer rig content. All four, so the draft is built at full fidelity.
- **Scope:** the homepage, full page, scrollable top to bottom on phone and desktop.

## Why the incumbent fails

The active theme is `stage-ready`, described in its own `theme.json` as a "dark pedalboard theme with amber glow effects, noise texture, and LED indicators". A music identity was already attempted and does not land, because `home.blade.php` is 65 lines of one repeated shape:

    <section class="py-10 md:py-14">
      <div class="container-store">
        <h2 class="section-title">...</h2>
        <grid of cards>

Every section is the same shape at the same rhythm. The pedalboard lives entirely in the CSS skin painted onto a structurally generic homepage. **The bones are stock and the music is a paint job.** That is the failure to replace, and it is also the failure mode this direction must avoid repeating.

## Direction, chosen by roll (seed f4db4458, assigned index 6) and confirmed by the owner

Challengers dealt and weighed: the 80s catalogue spread (strong form, but fused with a Philippine gear store a dense grid of price-shouting cells reads as Shopee and Lazada, the exact thing NGD wants to be believed against), the Push Pin poster (real music lineage, but says record shop rather than gear shop run by players), and four that fuse poorly, two of which are near-black-plus-one-glowing-accent, which is both the AI default and what Stage Ready already is.

No image generation was available this session, so the visualize step did not run. Recorded, not skipped silently.

### The direction contract

Place verbatim as an HTML comment, first child of `<body>`, so it survives the build:

    IMPECCABLE DIRECTION CONTRACT
    THESIS: A shop counter built into an amplifier. It refuses the stock e-commerce homepage of banner, title, grid, repeated, and it refuses the dark-with-amber-glow gear skin that painted a metaphor over exactly those bones.
    OWN-WORLD: A tube amp's front panel and chassis, load-bearing rather than decorative: brushed aluminium faceplate, engraved silkscreen legends, oxblood tolex ground, salt-and-pepper grille cloth, products on inset white spec plates, turquoise as the only signal colour and jewel red reserved for live state.
    STORY: The visitor sees a panel drawn by someone who knows what every control does, believes the shop is run by players, and reaches for the gear those players chose.
    FIRST VIEWPORT: A full-width faceplate carrying the shop badge, engraved category legends, and working controls at the right; below it the chassis opens on tolex with the week's gear inset as spec plates. No slogan, no SHOP NOW.
    FORM: Assigned candidate 6 of the grounded list, seed key f4db4458, confirmed by the owner against three named alternates.
    FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, and DESIGN.md

## The world, committed

**Colour strategy: Committed.** Oxblood carries the surface; it is not an accent scattered over neutral.

| Role | Value | Why |
|---|---|---|
| Tolex ground | deep oxblood red-brown, with grain | The chassis covering. Carries the page. |
| Faceplate | brushed aluminium, directional grain | The fixed chrome band. |
| Panel black | near-black with a warm cast | The blackface control band. |
| Silkscreen legend | warm off-white, engraved | All legends and labels. |
| Signal / active | turquoise | Real on silverface panels, and the maximum distance from Stage Ready's amber. |
| Pilot lamp | jewel red | Live state ONLY. Never decoration. |
| Grille cloth | oatmeal salt-and-pepper weave | Content panels. |
| Spec plate | warm white inset | Where products sit. |

**Dark or light, decided from the scene, not from category.** A Filipino musician on a mid-range phone, often at night, deciding on a purchase between roughly PHP 3,750 and PHP 32,000. The chassis is dark so the screen does not glare, but every product sits on an inset warm-white spec plate, which is both true to the world (silkscreened plates on a dark panel) and the only honest way to show catalog shots that were photographed on white.

**Type.** Legends and display in a condensed engraved gothic with wide tracking (Antonio is the intended pick). Body in a sturdy grotesque with real character (Chivo). Data, prices and specs in a mono that is not the admin's (Spline Sans Mono). Confirm availability and self-host; substitute only for a face with the same character, never for a system default, and never for anything on the saturated list (Inter, Space Grotesk, DM, Outfit, Fraunces, Playfair, Plex, Syne and company).

## The page

Pacing matters as much as the parts: dense panel, quiet photograph, dense grid, quiet story, closing plate.

1. **The faceplate.** Fixed top. Shop badge where an amp's badge is riveted, engraved category legends across the panel, working controls at the right: search as an input jack, cart as the pilot lamp.
2. **The channel.** The hero is the panel itself, not a slogan. The week's featured gear on a spec plate with real controls beneath it.
3. **What we plugged in this week.** The curation proof, and the load-bearing section for the belief: each spec plate carries the product, the price stamped like a panel marking, and **one line of the shop's own opinion**. A dropshipper cannot fake this section.
4. **The signal chain.** Categories rendered as the routing diagram players actually draw: guitar into overdrive into modulation into delay into amp. Each node navigates. Category nav that means something.
5. **The bench.** Real workbench and staff photography. Every unit tested before it ships.
6. **Brand badges.** Brand marks as badges riveted to the panel.
7. **Rig of the week.** A real customer board, parts listed and linked. Uses the artist content.
8. **The back panel.** Footer as the amp's rear plate: shipping, returns, contact, legal, set like voltage and impedance markings.

**Signature interaction.** The controls are the store's controls, not ornaments beside a normal toolbar. The sort knob rotates and re-sorts. The rockers filter. The pilot lamp is cart state.

**Motion: one authored moment.** On load, the amp warms up: the pilot lamp glows in, the legends light. Once, orchestrated, then still. Not scattered hover effects.

## The honest risk, to be checked at finish

Skeuomorphism as costume. Stage Ready already failed by painting gear onto stock bones. If the knobs end up decorative above an ordinary card grid, this is that same failure wearing metal. The direction only holds if the panel is genuinely load-bearing: the controls must be the actual controls, and the chassis must be the actual page structure.

## Constraints

- **A draft, delivered as one self-contained HTML file.** Inline CSS is correct HERE and only here, because the artifact must render standalone. The project's no-CSS-in-HTML rule governs the codebase; when this becomes a theme it moves into `theme.css` through Vite.
- Real content only. Real product names, real prices, real images from `storage/app/public/catalog/` (1,791 available), embedded as compressed data URIs so the file stands alone. Where a photograph the shop must supply is missing (workbench, staff, customer rigs), author a labelled placeholder in the world's own grammar and list it for the owner to replace. Never invent a claim, a testimonial, a review count or a delivery promise.
- NO EM DASHES anywhere. No instructional interface text.
- WCAG AA 4.5:1 text and 3:1 non-text. Turquoise on oxblood and legends on brushed metal are the two to measure.
- Phone first: 390 is the primary viewport, 1440 the second. The faceplate must survive 390 without becoming a hamburger and a logo.
- Untouched: `resources/themes/**`, every admin file, and anything the admin foundation sweep is editing.
