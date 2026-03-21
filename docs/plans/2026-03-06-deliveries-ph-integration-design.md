# Deliveries PH Shipping Integration - Design Document

**Goal:** Integrate Deliveries PH (DPH) API v3 for real-time courier rate quotation at checkout, admin shipment booking, and webhook-driven delivery status tracking.

**Architecture:** Laravel service class wraps DPH API with JWT token caching. Cart/checkout queries rates from multiple couriers. Admin manually books shipments. DPH HTTP Triggers push status updates to our webhook.

---

## 1. Data Architecture

### New Table: `shipments`

| Column | Type | Description |
|---|---|---|
| id | bigint PK | |
| order_id | FK orders | |
| dph_post_id | string nullable | DPH's postId (set after booking) |
| dph_partner_id | string | Provider ID e.g. "NINJAVAN" |
| dph_partner_name | string | Display name e.g. "NinjaVan" |
| dph_status | string nullable | Raw DPH status |
| dph_sub_status | string nullable | Raw DPH sub status |
| tracking_number | string nullable | Pushed by provider via webhook |
| tracking_url | string nullable | Pushed by provider via webhook |
| shareable_link | string nullable | DPH customer tracking link |
| approximate_fee | decimal(10,2) default 0 | Quoted fee at booking |
| actual_fee | decimal(10,2) default 0 | Fee reflected upon booking |
| final_fee | decimal(10,2) default 0 | Final fee including additional charges |
| booked_at | timestamp nullable | When admin booked |
| delivered_at | timestamp nullable | When DONE status received |
| meta | json nullable | Raw DPH API response |
| timestamps | | |

### New Table: `shipment_events`

| Column | Type | Description |
|---|---|---|
| id | bigint PK | |
| shipment_id | FK shipments | |
| status | string | DPH status code |
| sub_status | string nullable | DPH sub_status |
| remarks | string nullable | Provider remarks |
| meta | json nullable | Full webhook payload |
| created_at | timestamp | When webhook received |

### Order Table Changes

- Add `dph_courier_name` (string nullable) — display name of selected courier

### New Order Statuses

Replace existing seeder with expanded set:

| Slug | Name | Stock Action | DPH Mapping |
|---|---|---|---|
| pending | Pending | — | (before payment) |
| processing | Processing | decrement | (after payment confirmed) |
| booked | Booked | — | ASSIGNED |
| confirmed | Confirmed | — | ACCEPTED |
| pickup-started | Pickup Started | — | START_PICKUP |
| picked-up | Picked Up | — | DONE_PICKUP |
| in-transit | In Transit | — | ARRIVED_AT_HUB |
| out-for-delivery | Out for Delivery | — | START_DELIVERY |
| delivered | Delivered | — | DONE |
| completed | Completed | — | (manual close) |
| failed-pickup | Failed Pickup | — | FAILED_PICKUP |
| failed-delivery | Failed Delivery | — | FAILED_DELIVERY |
| cancelled | Cancelled | increment | CANCEL |
| returning | Returning | — | IN_TRANSIT_FOR_RETURN |
| returned | Returned | increment | RETURNED |

### Settings (group: `shipping`)

- `dph_enabled` — boolean
- `dph_mode` — sandbox / production
- `dph_email` — encrypted
- `dph_password` — encrypted
- `dph_webhook_secret` — random token for webhook validation
- `dph_pickup_address` — street address text
- `dph_pickup_city` — text
- `dph_pickup_province` — text
- `dph_pickup_barangay` — text
- `dph_pickup_postal_code` — text
- `dph_pickup_lat` — decimal
- `dph_pickup_lng` — decimal
- `dph_pickup_contact_name` — text
- `dph_pickup_contact_number` — text
- `dph_pickup_contact_email` — text

### Units

- Frontend/admin: Always metric (cm for dimensions, kg for weight)
- Backend: Converts cm to inches (/ 2.54) before DPH API calls
- Weight: kg throughout (DPH uses kg)
- No unit settings needed in admin

---

## 2. Service Architecture

### Modular Shipping Provider System

Shipping is built as a **pluggable provider system**. Each shipping method is an independent module that can be enabled/disabled from admin settings. All enabled providers run simultaneously at checkout — their rates are merged into a single list for the customer.

### `ShippingProviderInterface`

Common contract all shipping modules implement:

```php
interface ShippingProviderInterface
{
    public function getKey(): string;           // e.g. 'flat_rate', 'dph'
    public function getName(): string;          // e.g. 'Standard Shipping', 'Deliveries PH'
    public function isEnabled(): bool;
    public function getRates(array $address, array $dimensions, float $weight, float $subtotal): array;
}
```

Each provider's `getRates()` returns an array of normalized options:
```php
['key' => string, 'name' => string, 'cost' => float, 'estimated_days' => ?int, 'service_type' => string, 'partner_id' => ?string, 'product_size' => ?string]
```

### Provider 1: `FlatRateShippingProvider`

Wraps the existing flat-rate logic as a proper module.

- **Settings group:** `shipping` (keys: `flat_rate_enabled`, existing `shipping_flat_rate`, `shipping_free_threshold`)
- **Enable/disable:** toggle in admin Shipping settings
- Returns free shipping (if threshold met) and/or standard flat rate
- Always available as baseline — no external API dependency

### Provider 2: `DeliveryPhShippingProvider`

Wraps `DeliveryPhService` as a shipping provider module.

- **Settings group:** `shipping` (keys: `dph_enabled`, `dph_mode`, `dph_email`, `dph_password`, etc.)
- **Enable/disable:** toggle in admin Shipping settings
- Calls DPH API for real-time rates; returns empty array on failure (graceful)
- Depends on `DeliveryPhService` for auth + API calls

### `DeliveryPhService`

Low-level DPH API client (NOT a shipping provider — used by `DeliveryPhShippingProvider` and `ShipmentController`).

**Auth management:**
- Login via `/loginV2` (JWT-based)
- Cache access token + refresh token + expiry timestamps in Laravel cache
- Key: `dph_auth`
- On API call: check expiry -> refresh if needed -> re-login if refresh expired
- Avoids QUOTA_EXCEEDED errors from repeated logins

**Methods:**
- `login(): void` — authenticate, cache tokens
- `refreshToken(): void` — refresh JWT tokens
- `getScheduledRates(array $pickup, array $delivery, ?array $dimensions, ?float $weight): array` — calls `/v3/getAvailablePartners`
- `getOnDemandRates(array $pickup, array $delivery, array $dimensions, float $weight): array` — calls `/v3/getOnDemandQuotation`
- `getQuotation(string $partnerId, array $pickup, array $delivery): array` — calls `/getQuotation` for specific provider
- `createPost(Order $order, string $partnerId, string $serviceType): array` — calls `/v3/createPost`
- `getPost(string $postId): array` — calls `/getPost`
- `cancelPost(string $postId, string $trackingNumber, string $courierId): array` — calls `/v3/cancelPost`
- `getPartners(): array` — calls `/getPartners`

**Unit conversion:**
- `cmToInches(float $cm): float` — divides by 2.54
- Applied to product length/width/height before API calls

### `ShippingQuoteService`

**Aggregator** — loops through all registered shipping providers, collects rates from enabled ones, returns merged list.

**Methods:**
- `getQuotes(array $deliveryAddress, Collection $cartItems, float $subtotal): array` — aggregates dimensions, queries all enabled providers, returns merged normalized options
- `getProviders(): array` — returns list of all registered `ShippingProviderInterface` instances

**Dimension aggregation for multi-item carts:**
- Weight: sum all items (product weight * quantity)
- Dimensions: use largest single item's dimensions (items ship in one box)

**Behavior:**
- Queries ALL enabled providers — merges results
- If a provider's API fails: log error, skip it, continue with others
- If no providers return rates: empty array (checkout shows "no shipping available")

### Future Provider: In-House Weight-Based

Not implemented now. When ready, create `WeightBasedShippingProvider implements ShippingProviderInterface` — define zones + rate table in admin, calculate from weight/dimensions. Plugs in with zero changes to checkout or cart.

---

## 3. Customer-Facing Flows

### Cart Page — Shipping Estimate

- "Estimate Shipping" expandable section below cart totals
- Province + City PSGC cascading dropdowns (lightweight, reuse existing components)
- "Get Estimates" button -> AJAX to `POST /cart/shipping-estimate`
- Shows courier list: name, price, estimated days
- Falls back to flat-rate if DPH unavailable
- Non-blocking — cart works without estimating

### Checkout — Courier Selection

- After address step (Step 1), system auto-queries DPH with customer's shipping address
- Available couriers shown as radio buttons in order summary sidebar (same location as current shipping methods)
- Each option shows: provider name, price, estimated delivery days
- Metro Manila addresses: both on-demand + scheduled options
- Outside Metro Manila: scheduled only
- Flat-rate always shown as last fallback option
- Selected courier's partnerId, serviceType, and quoted price stored on the order

### Order Confirmation

- No changes needed — already shows shipping method and cost
- Courier name appears as shipping method

---

## 4. Admin Flows

### Settings — "Shipping" Tab (new, 6th tab)

**Deliveries PH section:**
- Enable/disable toggle
- Mode: Sandbox / Production dropdown
- Email + Password (encrypted, masked with "Change" button — same pattern as HitPay)
- Webhook Secret (auto-generated, displayed for copying to DPH dashboard)
- "Test Connection" button (calls /loginV2, shows success/error)

**Store Pickup Address section:**
- Contact name, phone, email
- Street address (text)
- Province > City > Barangay (PSGC cascading dropdowns)
- Postal code
- Latitude, Longitude (text inputs)

**Fallback Shipping section:**
- Flat rate cost (moved from Checkout tab)
- Free shipping threshold (moved from Checkout tab)

### Admin Order Page — Shipment Panel

**Before booking (no shipment record):**
- "Book Shipment" button in order detail
- Opens inline panel:
  - Fetches available couriers from DPH (using order's delivery address)
  - Shows courier options with prices
  - Admin selects courier, clicks "Confirm Booking"
  - Creates shipment record, calls `/v3/createPost`
  - Updates order status to "Booked"

**After booking (shipment exists):**
- Shipment info card:
  - DPH Post ID
  - Courier name + status badge
  - Tracking number + clickable link (when available)
  - Shareable customer link
  - Fee: approximate / actual / final
- Status timeline from shipment_events
- "Cancel Shipment" button (if status allows — before pickup started)

---

## 5. Webhook

### Route

`POST /dph/webhook` — CSRF excluded, no auth middleware.

### DPH Dashboard Configuration

Webhook URL configured as HTTP Trigger in DPH dashboard with token placeholders:

```
https://domain.com/dph/webhook?secret={webhook_secret}&postId={postId}&refNo={refNo}&status={status}&subStatus={subStatus}&trackingNumber={trackingNumber}&trackingUrl={trackingUrl}&shareableLink={shareableLink}&fee_approximate={fee.approximateShipping}&fee_actual={fee.actualShipping}&fee_final={fee.finalShipping}&remarks={lastUpdateRemarks}&courierName={courierName}
```

### Validation

1. Secret token match (query param vs stored setting)
2. Shipment lookup by postId
3. Idempotency: skip if shipment already at this status + sub_status

### Processing

1. Validate secret
2. Find shipment by dph_post_id
3. Create shipment_event record
4. Update shipment: dph_status, dph_sub_status, tracking fields, fees
5. Map DPH status -> order status slug
6. Update order status + create OrderHistory entry
7. Trigger StockService::adjustForStatusChange() if applicable
8. If status is DONE: set shipment.delivered_at, update order tracking_number
9. Return 200 OK

### DPH Status -> Order Status Mapping

| DPH Status | Order Status Slug |
|---|---|
| ASSIGNED | booked |
| ACCEPTED | confirmed |
| ASSIGNING_RIDER | confirmed |
| START_PICKUP | pickup-started |
| DONE_PICKUP | picked-up |
| ARRIVED_AT_HUB | in-transit |
| START_DELIVERY | out-for-delivery |
| DONE | delivered |
| FAILED_PICKUP | failed-pickup |
| FAILED_DELIVERY | failed-delivery |
| CANCEL | cancelled |
| IN_TRANSIT_FOR_RETURN | returning |
| RETURNED | returned |
| UPDATE | (no status change, log remarks only) |

---

## 6. Error Handling

- **DPH API down at cart/checkout**: Fall back to flat-rate shipping. Log error. Customer can still checkout.
- **DPH API down at admin booking**: Show error message to admin with "Retry" option. Order stays at current status.
- **Booking fails (invalid address, not serviceable)**: Display DPH error message to admin. Don't change order status.
- **Token expiry mid-request**: Auto-refresh and retry once. If refresh fails, re-login and retry once. If still fails, throw exception.
- **Webhook duplicates**: Idempotency check prevents double-processing.
- **Webhook for unknown shipment**: Log warning, return 200 (don't retry).

---

## 7. Files to Create/Modify

### New Files
- `app/Services/Shipping/ShippingProviderInterface.php` — common contract
- `app/Services/Shipping/FlatRateShippingProvider.php` — flat-rate module
- `app/Services/Shipping/DeliveryPhShippingProvider.php` — DPH module
- `app/Services/Shipping/ShippingQuoteService.php` — aggregator
- `app/Services/DeliveryPhService.php` — low-level DPH API client
- `app/Models/Shipment.php`
- `app/Models/ShipmentEvent.php`
- `app/Http/Controllers/Storefront/DphWebhookController.php`
- `app/Http/Controllers/Admin/ShipmentController.php`
- `database/migrations/xxxx_create_shipments_table.php`
- `database/migrations/xxxx_create_shipment_events_table.php`
- `database/migrations/xxxx_add_dph_courier_name_to_orders.php`
- `database/migrations/xxxx_update_order_statuses_for_dph.php`

### Modified Files
- `app/Services/CheckoutService.php` — replace flat-rate with ShippingQuoteService
- `app/Http/Controllers/Storefront/CheckoutController.php` — pass courier options to view
- `app/Http/Controllers/Admin/SettingController.php` — handle shipping settings + encrypted DPH credentials
- `resources/views/admin/settings/index.blade.php` — add Shipping tab (with per-module enable/disable toggles)
- `resources/views/admin/orders/form.blade.php` — add shipment panel
- `resources/views/storefront/checkout/index.blade.php` — courier selection UI
- `resources/views/storefront/cart/index.blade.php` — shipping estimate section
- `routes/web.php` — new routes (webhook, shipping estimate, admin shipment actions)
