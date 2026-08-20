# The Front Panel

A storefront world for New Gear Day, drafted 2026-08-06 to 08-14 and recovered
on 2026-08-20 from a session transcript. It is a DRAFT, not a theme: nothing in
`resources/themes/` has ever carried it.

| File | What it is |
|---|---|
| `direction.md` | The design record: the brief, why the incumbent failed, the committed world, the direction contract, the risks, the constraints |
| `front-panel-draft.html` | The built homepage, one self-contained file with 39 real catalogue images embedded as data URIs |

Open the HTML directly in a browser. It stands alone and needs no server.

## Why it was nearly lost

Both copies of the draft (`public/front-panel-draft.html` and
`.superpowers/design/drafts/front-panel.html`, byte-identical) were gitignored,
and the design record existed only inside a chat transcript. A `git clean` or a
lost machine would have taken all three. That is the reason for this directory.

## Why it matters to the theme architecture

The record's verdict on the theme it was replacing is the same finding the
2026-08-20 folder diff reached independently, months apart:

> Every section is the same shape at the same rhythm. The pedalboard lives
> entirely in the CSS skin painted onto a structurally generic homepage.
> **The bones are stock and the music is a paint job.**

That is why switching themes only ever changed colours. The three folder themes
were one skeleton in three palettes, and the 2026-08-17 consolidation made that
explicit by turning them into presets.

The Front Panel cannot be built that way, and that is its value. A faceplate
band with engraved legends, a tolex chassis, products as inset spec plates, and
controls that genuinely sort and filter are different markup, not different
variables. A theme system that hands out colour tokens could never produce it.

**So this draft is the acceptance test for the theme contract.** Build it as a
real theme first, hardcoded, and record every variable, partial, region and
piece of data it reaches for. That list IS the contract. Designing the contract
first, against an imaginary theme, is how platforms ship interfaces nobody can
build against.

## The risk the record names against itself

> Skeuomorphism as costume. Stage Ready already failed by painting gear onto
> stock bones. If the knobs end up decorative above an ordinary card grid, this
> is that same failure wearing metal. The direction only holds if the panel is
> genuinely load-bearing.

## Known gaps

- The world was never visualised: no image generation was available in that
  session, so it is specified but not seen.
- Photography the shop must supply (workbench, staff, customer rigs) appears as
  labelled placeholders in the draft, to be replaced with real shots.
- The palette was chosen deliberately against Stage Ready's amber. Stage Ready
  no longer exists as a folder; it survives as a preset of the same name.
