# Global Localization (with seamless PH depth) — Design Spec

- **Date:** 2026-06-14
- **Status:** Draft for review
- **Supersedes:** the standalone `extensions/philippine-address` module (to be deleted) and the abandoned "two Philippines countries" / "standalone PSGC" ideas.

## Goal
Ship Venta as a **global** e-commerce platform whose address localization follows the **industry standard — Country → State/Province** — for every country, while the **Philippines silently gains two deeper levels (City → Barangay)**. A non-PH store owner must **never see a PH-specific table or artifact** in the database and wonder "what is this?" The PH special case is expressed as **config + bundled data files**, not schema.

Couriers (LBC, QuadX) match on **PSGC codes**, so PH addresses must carry those codes.

## Principles
1. **Standard DB, no PH-specific tables.** Database shape is identical to any normal store.
2. **PH depth lives in files, read on-demand** — not seeded into the DB. (Benchmarked: whole-file read+filter ≈ 23 ms, transient ~30 MB, freed per request; fine at these stores' scale. No file-splitting — that's ~1,700 files of clutter for no real-world gain.)
3. **Codes on the address.** Each saved address stores the chosen names **and** PSGC codes, so display and courier sync never re-read files.
4. **No new CRUD, no generic tree.** Because deep data is files (not `zones` rows), `zones` stays flat — no `parent_id`/`level` needed.

## Architecture

### Database (unchanged shape — standard)
- `countries` — all countries.
- `zones` — **flat**, the single sub-national level (State/Region/Province) for **every** country, incl. PH's ~82 provinces. Columns stay `id, country_id, name, code, is_active`.
- `geo_zones` + `geo_zone_entries` — OpenCart-style groupings that shipping/payment/tax attach to. Unchanged.
- **No `parent_id`, no PH tables.** A foreign admin sees only these generic tables.

### Per-country deep-address config (not in the DB)
`config/localization.php` declares which countries have levels beyond State and how to source them. Only PH has an entry:
```php
return [
  'deep_address' => [
    'PH' => [
      'levels' => ['Province', 'City', 'Barangay'],   // labels for the cascade
      'province_source' => 'zones',                    // level 1 = DB zones
      'city_file'      => 'ph/muncity.json',           // level 2 = file
      'barangay_file'  => 'ph/barangay.json',          // level 3 = file
    ],
  ],
];
```
Keeping this in config (not a `countries` column) keeps the DB schema 100% generic — there isn't even a PH-named column.

### Bundled data files (moved from the extension into core)
```
database/data/
  ├─ geographic.json          (existing: countries + State/Province for all)
  └─ ph/
       ├─ muncity.json         (~1,646 cities, with PSGC codes + zip)
       └─ barangay.json        (~42,036 barangays, with PSGC codes)
```
Whole files (no splitting). These are the *source*; only `geographic.json` seeds the DB (`zones`). The PH city/barangay files are **read at request time**, never seeded.

### Address record (where the result is saved)
`customer_addresses` / `orders` store the resolved selection:
- `state` → the province name (and `zone_id` FK + `state_code` = PSGC province code).
- `city` → city name + `city_code` (PSGC).
- `barangay` → barangay name + `barangay_code` (PSGC).

The names keep current display working; the codes are what LBC/QuadX consume. (New nullable `*_code` columns; existing rows simply have null codes until re-saved/backfilled.)

## Address-entry flow (storefront, checkout, admin order entry)
The address form renders **N cascading dropdowns from the country's config**:
- Any country: `Country → State/Province` (from DB `zones`). City and below = free text (standard).
- **PH:** `Country → Province` (DB `zones`) `→ City` (file) `→ Barangay` (file).

On submit, the form stores the chosen name + code at each level.

### Lookup endpoints (read files on demand)
Core controller (replacing the extension's), same endpoint contract the form already calls:
- `GET /address-lookup/ph-cities?province=<code|name>` → read `ph/muncity.json`, filter by province, return `[{code,name}]`.
- `GET /address-lookup/ph-barangays?city=<code>` → read `ph/barangay.json`, filter by city, return `[{code,name}]`.

(Generic enough to extend later if another country ever gets deep levels; PH is the only one now.)

## Courier readiness (LBC / QuadX)
No courier code is built here — this spec only guarantees the **PSGC codes are stored on the address** (province/city/barangay). QuadX (separate `feat/quadx` branch) and any future LBC integration read those codes from the saved address. Note: PH **province** zones must carry their PSGC code — source it from the PSGC province data when seeding `zones` (today `geographic.json` has empty `code` for PH provinces), so the full chain has codes.

## Migration & cleanup
1. **Move** `extensions/philippine-address/database/data/psgc/{muncity,barangay}.json` → `database/data/ph/`.
2. **Add** `config/localization.php`.
3. **Add** the core lookup controller + routes (file-backed), and the `*_code` address columns (nullable).
4. **Seed**: ensure `zones` has PH provinces **with PSGC codes** (from the PSGC province file), alongside the existing worldwide `geographic.json` seed.
5. **Delete** `extensions/philippine-address` entirely — its service provider registration, routes, models, controllers, views, migration, seeder.
6. **Drop** the `ph_regions`, `ph_provinces`, `ph_cities`, `ph_barangays` tables (migration).
7. **Address form** (3 themes + admin order entry) points at the new lookup endpoints and stores codes.

## Backward compatibility
- Existing addresses store free-text `state/city/barangay` — they keep displaying unchanged; `*_code` is null until the address is re-saved.
- Optional, best-effort **backfill** command: resolve existing free-text names → PSGC codes by name match against the files (for courier readiness on historical addresses). Non-destructive; skip on no-match.
- `zones`, `geo_zones`, `geo_zone_entries` keep working; this refactor does not repopulate the sandbox's empty `geo_zone_entries` (separate data task).

## Out of scope
- File splitting (rejected — unneeded at this scale).
- A subdivisions CRUD / letting other countries populate deep levels (schema/config allows it later; not built).
- Generic self-referencing `zones` tree (unnecessary since deep data is files).
- PSA quarterly-refresh tooling (future; the bundled files are the current vintage ~2023).
- Building LBC/QuadX courier sync (separate; this only stores the codes they need).

## Testing / acceptance
- **Standard country (e.g. US/JP):** address form shows Country → State only; saves name. No file reads.
- **PH:** Province (DB) → City (file) → Barangay (file) cascade works; selecting each populates the next; saved address has names **and** PSGC codes.
- **Lookup endpoints:** return correct filtered lists; whole-file read stays well under perceptible latency.
- **No PH tables:** after migration, `ph_*` tables are gone; DB shows only `countries/zones/geo_zones/geo_zone_entries`.
- **Extension removed:** app boots with `extensions/philippine-address` deleted; no broken references/routes/providers.
- **Existing addresses:** still render; `*_code` null until re-saved; optional backfill populates codes where names match.

## Open decision
- **Province code source:** confirm we seed PH province PSGC codes into `zones.code` from the PSGC province file (recommended, needed for courier chain) vs leaving province uncoded and resolving province code at courier time. Recommended: seed the codes.
