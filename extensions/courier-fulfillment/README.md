# Courier Fulfillment

Books confirmed orders with a shipping courier from a dedicated admin page
(`Sales > Fulfillment`), independent of checkout. Venta's checkout never
talks to a courier — this extension is the only thing that does, and it does
so through one interface (`CourierFulfillmentInterface`) so the admin UI,
settings page, status mapping, webhook receiver, and scheduled poll never
know any courier's literal identity. QuadX is the first courier shipped this
way; everything below is what a second one actually requires.

## Add a courier: the checklist

1. **One folder**: `src/Couriers/{Name}/{Name}Fulfillment.php` implementing
   `Extensions\CourierFulfillment\Contracts\CourierFulfillmentInterface`.
   Every method it must implement:

   | Method | What it's for |
   |---|---|
   | `getKey(): string` | Machine key, stored on `orders.courier_provider` and used as the settings-key prefix (`{key}_*`) and the webhook URL segment (`/webhooks/courier/{key}`). Pick it once — renaming it later orphans stored data. |
   | `getName(): string` | Human label — settings card title, queue dropdown option, status-mapping table headers. |
   | `isEnabled(): bool` | Configured + turned on. Gates whether the courier shows up in the booking form and the scheduled poll. |
   | `getServiceTypes(): array` | Service levels for the booking form. |
   | `getParcelOptions(): array` | Parcel categories for the booking form. |
   | `getShippingPaymentOptions(): array` | Who-pays-the-courier options for the booking form. |
   | `createShipment(Order $order, array $opts): array` | Book a shipment. Returns `tracking_number`, `status`, and a `meta` array — the shape of `meta` is entirely yours; it's stored as-is in `orders.courier_meta` and handed back to you (never parsed by shared code) via `trackingLinks()`. |
   | `cancelShipment(string $trackingNumber): array` | Cancel a booking. |
   | `getTracking(string $trackingNumber): array` | Fetch current status for one shipment (used by the per-order Refresh button and the scheduled poll). |
   | `terminalStatuses(): array` | Statuses the poll stops re-checking once reached. |
   | `cancelableStatuses(): array` | Statuses from which a booking can still be canceled. |
   | `statusPhase(?string $status): array` | Coarse `{step, failed}` for the queue's 4-step visual stepper. |
   | `printLabel(string $trackingNumber, string $format): array` | Shipping-label URL. |
   | `parseWebhook(array $payload): array` | Normalize your webhook's payload shape into `{reference_id, tracking_number, status, occurred_at, remarks}` events. |
   | `settingsSchema(): array` | Declares every `settings` row (group `courier`) this courier reads/writes — generates its settings page and the save-endpoint's write-allowlist. See the interface's docblock for the field shape and the `"_mode"`-suffixed-key convention that drives mode-scoped credential groups (e.g. staging/production). |
   | `trackingLinks(Order $order): array` | Extract tracking URL(s) from `$order->courier_meta`, in **your own** shape — nothing outside your class ever parses `courier_meta`. Return `[{label, url}, …]` in preference order; the queue page just loops it and uses the first entry. |
   | `testConnection(): array` | Ping your API with the saved credentials. Backs the settings page's "Test Connection" button — return `{ok, message}`. |
   | `refreshStatusLabels(): array` | Pull your live status vocabulary + display labels, for the settings page's "Refresh from {courier}" button. Return `{ok, labels?, error?}` — `labels` is cached as `{key}_status_labels` and also used to humanize raw statuses in the queue view. |
   | `mappableStatuses(): array` | The curated subset of your status vocabulary the admin can map to a Venta order status — drives the settings page's status-mapping table rows. |

2. **One line**: register it in
   `CourierFulfillmentServiceProvider::boot()`:
   ```php
   CourierManager::register(YourCourierFulfillment::class);
   ```

That's it. Nothing else in this extension — not the controllers, not the
Blade views, not the poll command — references a courier by name.

## What appears automatically once registered

- **Hub card + own settings page**: the hub at
  `Settings > Modules > Courier Fulfillment` shows one card per registered
  courier (name, enabled state, mode chip, credential-completeness hint)
  linking to that courier's OWN page at
  `settings/modules/courier-fulfillment/{key}`, rendered entirely from
  `settingsSchema()`: fields, mode-scoped credential groups,
  encrypted-and-masked secrets, save round-trip through the write-allowlist
  derived from that courier's schema alone (saving one courier's page can
  never touch another's keys).
- **Webhook URL + token**: a `{key}_webhook_token` is generated the first
  time the hub loads, shown as a copy-able URL
  (`/webhooks/courier/{key}?token=…`) on the hub's Webhook URLs card. The
  receiving route (`CourierWebhookController`) resolves `{provider}` from the
  URL segment, checks the token, and calls your `parseWebhook()` — this is
  the DEFAULT auth for a courier with no signing scheme of its own. If your
  provider signs its webhook payloads (SPX does — see below), add a
  provider-branch in `CourierWebhookController::handle()` instead of relying
  on the shared token; the URL segment / registration mechanism is still
  generic, only the auth check differs.
- **Test Connection** button on the courier's page, wired to your `testConnection()`.
- **Order status mapping** table on the courier's page: rows from `mappableStatuses()`,
  labels from `refreshStatusLabels()` (cached), saved under
  `{key}_status_map` and applied by `StatusMapper` whenever a status arrives
  (webhook, poll, or manual Refresh) — moves the core order status + records
  history + optionally emails the customer.
- **Queue page** (`Sales > Fulfillment`): once `isEnabled()` is true, the
  courier appears as a booking-form option automatically
  (`CourierManager::enabled()`); its tracking link(s) render via
  `trackingLinks()` and its raw statuses are humanized via the cached
  `{key}_status_labels`.
- **Poll coverage**: `courier:poll-status` (scheduled via Settings >
  Automations) loops every `CourierManager::enabled()` courier and refreshes
  its in-flight, non-terminal bookings — no per-courier wiring needed.
- **ERP booking endpoint** (`POST /api/erp/fulfillment/orders/{id}/book`):
  accepts any registered courier's key in its `courier` field; defaults to
  the first enabled one if omitted.

## What does NOT appear automatically

- An actual HTTP client for your provider's API. `QuadXClient` is a plain
  internal helper, not part of the contract — write your own equivalent and
  call it from inside your `{Name}Fulfillment` class.
- Anything visual beyond the generic card/table/dropdown shapes above. If
  your courier needs a genuinely different admin affordance, that's new
  scope, not a drop-in.
- Data migration for a renamed `getKey()`. The key is the prefix for every
  stored setting (`{key}_*`) and the webhook URL segment — treat it as
  permanent once shipped.
- **Fixed (Task T3)**: provider-scoped webhook matching on tracking-number
  fallback. `Extensions\CourierFulfillment\Support\CourierWebhookIngestor`
  (the shared logic every courier's webhook path — sync or queued — routes
  through) now scopes that fallback query to
  `->where('courier_provider', $provider)` before falling back to
  `courier_tracking_number` alone, so a tracking-number collision between two
  registered couriers can no longer apply one courier's webhook event to
  another courier's order. Nothing left to do here when wiring up a new
  courier — this is automatic.
- A fast-ack webhook path (queued processing, sub-3s response). The generic
  `CourierWebhookController::handle()` above processes events INLINE,
  synchronously, before responding — fine for a courier with no documented
  response-time budget. If your provider imposes one (SPX permanently stops
  sending webhooks after repeated slow/timeout acks — see `SpxFulfillment`'s
  webhook docblocks + `CourierWebhookController`'s SPX branch for the pattern:
  verify auth, parse events, `dispatch(new ProcessCourierWebhookJob(...))
  ->afterResponse()`, return 200), that's a per-courier branch you add the
  same way, not something declared through the interface.

## Tracking links: which URL the shopper and the queue get

**Owner ruling 2026-09-03.** For QuadX / GoGo Xpress the tracker is:

| Mode | URL |
|---|---|
| production | `https://app.gogoxpress.com/track/{tracking}` |
| staging / sandbox | `https://app.staging.gogoxpress.com/track/{tracking}` |

Nothing else. The mode comes from the booking's own `courier_meta.mode`, not
from whatever the setting says today, so a parcel booked in the sandbox keeps
pointing at the sandbox tracker after the store goes live, and never the
other way round. A booking made before the mode was recorded falls back to
the configured mode, which is the limit of what can be known.

**Why this had to be ruled on.** QuadX's API returns `tracking_links` on
`fareye.co`, the logistics platform GoGo Xpress runs on rather than the brand
the shopper bought from, and on a live fulfilment those were opaque tokenised
URLs on their *dev* tracker (`trackdev.fareye.co`). The code used to prefer
those links over the URL it builds, in two places and inconsistently, so one
live parcel showed a fareye link in the fulfilment queue while the customer
held a gogoxpress one written at an earlier moment. Two addresses for one
parcel, one of which the shopper would not recognise.

**The rule for any courier, not just QuadX.** Where a courier implements
`ProvidesPublicTrackingUrl`, its own public tracker WINS over the
per-shipment links its API hands back. `FulfillmentService::resolveTrackingLink()`
(the customer's stored `orders.tracking_link`) and the courier's
`trackingLinks()` (what the queue renders) must build the same URL from the
same tracking number, so the two surfaces cannot disagree.

A courier that does *not* implement `ProvidesPublicTrackingUrl` still falls
back to its API's links, then to plain text. That path is unchanged: LBC and
SPX are unaffected by this ruling.

## What money reaches the courier

**QuadX requires `total` on every booking, prepaid included.** Its `/orders`
endpoint answers 422 with `parameters.total: "The total field is required."`
when it is missing, whatever the payment type. Per-item `amount` travels with
it.

| Courier | What carries the money | When |
|---|---|---|
| QuadX | `total` + per-item `amount` | always |
| SPX | `cod_amount` | only when `cod_collection` is 1 |

The two differ because the APIs differ, not because the adapters disagree.

**A cautionary tale, kept deliberately.** On 2026-09-03 QuadX was changed to
send money only on COD, so a prepaid parcel would not show its value on the
courier's dashboard to everyone handling it. That is a sound instinct, and SPX
genuinely works that way. But the change shipped on an assumption its own
commit note flagged as unverified against the live API, and the live API
refuses it: every prepaid order (HitPay, bank transfer, GCash) failed to book
while COD kept working, because only COD still carried a total. Restored in
`33adec6f`.

So: **a courier's API decides what it will accept, and the only way to know is
to ask it.** Before removing a field from any courier payload on privacy or
tidiness grounds, book a staging order without it and confirm the courier
accepts it. A test that fakes the response proves only that we build the
payload we meant to, never that the courier will take it.

Insurance is separate and unaffected: `declared_value` stays 0 unless the
admin ticks "Insure shipment", and it must always be sent explicitly, because
omitting it makes QuadX silently default it to the order total and auto-insure
every booking.

## UAT / staging guidance

- Most couriers offer a staging/sandbox mode with separate credentials — the
  `"_mode"`-suffixed schema-field convention exists specifically so a
  courier can expose staging vs. production credential pairs without any
  shared code knowing about "modes" as a concept. Model yours the same way
  QuadX does (`{key}_mode` + `{key}_staging_*` / `{key}_production_*`).
- Before flipping a courier live: (1) save credentials in staging mode, hit
  **Test Connection**, (2) book a real staging order from the queue page and
  confirm a tracking number comes back, (3) hit **Refresh from {courier}**
  and confirm the status-mapping table populates with real status names, (4)
  set a couple of mappings (e.g. "delivered" -> Delivered) and confirm
  `courier:poll-status` (or the per-order Refresh button) actually moves the
  order status, (5) send a test webhook (or wait for one) and confirm the
  same status move happens through that path too, (6) cancel a staging
  booking and confirm it's rebookable afterward.
- `tests/Support/FakeCourierFulfillment.php` is a minimal second courier
  registered only inside the test suite — it's the fastest way to see
  whether your new courier's settings page, tracking links, and
  status-mapping table are genuinely schema-driven rather than accidentally
  QuadX-shaped. Copy its shape as a starting point for smoke-testing a new
  courier's admin surface before wiring real API calls.
