---
target: v3 settings shell layout
total_score: 29
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 2
timestamp: 2026-08-16T08-31-11Z
slug: s-views-components-admin3-settings-shell-blade-php
---
# Critique: v3 settings shell layout (post icon-rail + flush-content change)

Method: dual-agent. Score 29/40 (Good). Detector: 0 findings.

## Adjudication of the owner's dispute (content moved without instruction)
Owner directed: move settings nav beside the icon rail, keep main nav condensed. Builder also removed the centered body max-width, left-anchoring a 1080 measure. Measured: at 1440 left-anchored and centered are pixel-identical (measure fills the column); at 1920 as-built leaves ~508px dead paper right of content vs a 28px nav gutter. Full revert would float the snav ~292px off the rail at 1920 (previously rejected). Ruling: nav stays pinned; measure centers in remaining space (.v3-settings__measure { margin-inline: auto }), zero change at <=1440. Precedent: Linear/Stripe/GitLab center content right of their rails.

## Priority issues
- [P1] Left-anchored measure at wide viewports (fix: margin-inline auto).
- [P1] No form measure: field-only sections render 1035px selects; want ~800px cap (shell prop), 1080 stays for hub/tables. This is the deeper reason the layout felt wrong.
- [P2] Top band underworks: "Settings" doubled on landing, <title> "Settings: Settings:", no search/palette in band.
- [P2] Sixteen settings routes still on the v2 shell (migration sequencing).
- [P3] Mobile landing stacks picker + search + hub rows.

## Owner proposal on the table
Dual nav: fold settings sections into the slab itself (single column, remove snav). Trade-offs: 18 settings + work items in one column (long or mode-switched), loses always-visible section list beside long forms, does not itself resolve the content-anchor question.

## Keep
Double-rail concept; designed mobile degradation; band/snav text alignment; the measure cap itself.

## Heuristics
1:3 2:3 3:4 4:2 (anchor contradicts .v3-measure; two shells) 5:4 6:3 7:2 8:3 9:3 10:2 = 29/40.
