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
   | `settingsSchema(): array` | Declares every `settings` row (group `courier`) this courier reads/writes — generates its settings card and the save-endpoint's write-allowlist. See the interface's docblock for the field shape and the `"_mode"`-suffixed-key convention that drives mode-scoped credential groups (e.g. staging/production). |
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

- **Settings card** at `Settings > Modules > Courier Fulfillment`, one per
  registered courier, rendered entirely from `settingsSchema()`: fields,
  mode-scoped credential groups, encrypted-and-masked secrets, save
  round-trip through the derived write-allowlist.
- **Webhook URL + token**: a `{key}_webhook_token` is generated the first
  time the settings page loads, shown as a copy-able URL
  (`/webhooks/courier/{key}?token=…`) on that courier's own card. The
  receiving route (`CourierWebhookController`) is fully generic — it resolves
  `{provider}` from the URL segment, checks the token, and calls your
  `parseWebhook()`.
- **Test Connection** button on the card, wired to your `testConnection()`.
- **Order status mapping** table on the card: rows from `mappableStatuses()`,
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
- Provider-scoped webhook matching on tracking-number fallback.
  `CourierWebhookController::applyEvent` falls back to matching an order by
  `courier_tracking_number` alone (no `courier_provider` filter) when a
  webhook event lacks a usable `reference_id`. With a single courier this is
  moot; once a second courier is registered, a tracking-number collision
  across providers could apply one courier's webhook to another courier's
  order. Scope that fallback query to `->where('courier_provider', $provider)`
  before (or as part of) wiring up your courier.

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
  whether your new courier's settings card, tracking links, and
  status-mapping table are genuinely schema-driven rather than accidentally
  QuadX-shaped. Copy its shape as a starting point for smoke-testing a new
  courier's admin surface before wiring real API calls.
