# SPX (Shopee Xpress Philippines) Open API — Documentation Harvest

## Purpose

Offline grounding of the SPX Open API documentation for planning a `CourierFulfillmentInterface` adapter in Venta. Harvested because the SPX developer portal is a JS-rendered SPA (no static HTML), so it can't be scraped with a plain HTTP fetch — content was extracted live via a Playwright-driven browser session.

## Source URLs

- Guide (root): https://spx.ph/integration/en/guide/cef9f862490e49b796df946e8fb624bf
- API reference (root): https://spx.ph/integration/en/api/c27c8517d55c4fea96bcb426cfcaf140

Each section/endpoint has its own stable URL (the SPA updates the URL hash-slug on left-nav click) — individual URLs are noted inline in `guide.md` and `api.md` under each heading.

## Harvest method

Playwright MCP browser tools (`browser_navigate`, `browser_click`, `browser_evaluate`, `browser_snapshot`). For each left-nav entry: click the menu item, wait for the SPA to re-render, then extract `document.querySelector('div.doc').innerText` (the content container, identified by walking up the DOM from the page's `<h1>` — this selector holds the section body only, excluding the left-nav chrome). Content was captured verbatim, no paraphrasing. Markdown tables were reconstructed from the tab-delimited `innerText` output of the source's HTML tables.

## Harvest date

2026-08-08

## Sections harvested

**Guide (12/12 sections — all succeeded):**
1. Introduction
2. API list
3. How to call the open API
4. API authentication guide
5. API address
6. Get available addresses
7. Parameter enumeration mapping
8. Error codes mapping
9. Order status code
10. Update Order Flowchart
11. Error-prone fields
12. Suggestions of common errors

**API reference (15/15 endpoints — all succeeded):**
1. Order API: 1.1 Get Pickup Timeslot, 1.2 Track Order, 1.3 Cancel Order, 1.4 Create Order V2, 1.5 Get Order Create Result V2, 1.6 Get AWB V2, 1.7 Update Order, 1.8 Get voucher list
2. Shipping Fee API: 2.1 Check Shipping Fee, 2.2 Get Order Fee
3. Account API: 3.1 Create Account, 3.2 Check Account Credentials
4. Web Hook: 4.1 Tracking Web Hook, 4.2 Evidence Proofs Webhook
5. Address API: 5.1 Get Address file download link

**Total: 27 sections/endpoints, 0 failures.**

Note: the nav also has an "Announcement" tab alongside "Guide" and "API" — it was not part of the requested scope (guide + API reference) and was not harvested.

## Known documentation quirks (not harvesting errors — verified by direct extraction)

- `guide.md` → "API list" numbers Update Order as "1.7", while `guide.md` → "Update Order Flowchart" and `api.md` → 1.7 both refer to it in text as "API 1.9" in one spot — treat `api.md`'s endpoint list/URLs as authoritative.
- `guide.md` → "Get available addresses": the PH example table repeats `sender_city`/`deliver_city` as the API field for both the "City" and "PostCode" Excel columns (no distinct postcode field name given) — needs verification against the actual Create Order request schema before relying on it, since Create Order's `sender_post_code`/`deliver_post_code` fields (documented in `api.md` 1.4) look like the intended mapping.
- `api.md` → 1.4 Create Order V2 and 1.7 Update Order example JSON payloads both have a syntax error (missing comma before `voucher_code`/after `express_insured_value`) and 1.4's example includes undocumented fields (`deliver_country`, `deliver_town`) not present in the request-body parameter table.
- `api.md` → 4.2 Evidence Proofs Webhook: the payload parameter table's last row (`status_code_name`) has no description text in the source, and no JSON example payload is provided (unlike 4.1).
- `guide.md` → "API authentication guide": the Unix-line-ending and Windows-line-ending "expected result" hashes shown for the test vector are identical in the source text — flagged as a possible documentation copy-paste artifact.
- Two figures/diagrams (`Introduction` → "Integration process", and `Update Order Flowchart` → the flowchart itself) are rendered images, not extractable as text — noted inline in `guide.md` rather than silently omitted.

## Files in this harvest

- `guide.md` — all 12 Guide sections, verbatim
- `api.md` — all 15 API endpoints, verbatim (method, URL, headers, request/response params, types, enums, example JSON)
- `integration-brief.md` — short technical brief distilled from the above, for consumption by the integration plan
