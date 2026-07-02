# Payment Methods: Sign-In Sheet + Bank Transfer as CRUD Factory — Design Spec

- **Date:** 2026-07-02
- **Status:** Approved in discussion (Tier 3); awaiting spec review
- **Depends on:** `2026-07-02-order-snapshots-design.md` (snapshots make method deletion history-safe; build A first)
- **Owner's rule:** completely modular, decoupled, readable — a 3rd-party dev adds a payment method without touching core. Venta will be open-sourced.
- **Agreed limits (anti-overengineering guardrails):**
  - Registration is **explicit and greppable** (`PaymentManager::register(X::class)` in a named provider). No filesystem scanning, no config-array magic, no marketplace/drag-drop — those are distribution luxuries, not launch requirements.
  - **Core methods stay in core** (`app/Services/PaymentMethods/`) — they just register through the same door extensions use. Modular ≠ everything-is-a-plugin.

## Why (current defects, verified)
1. **Hardcoded method list** — `CheckoutService::getAvailablePaymentMethods()` (`CheckoutService.php:41-86`) instantiates the three classes directly; `PaymentRestrictionController::PAYMENT_METHODS` (`:15`) hardcodes the same three keys; `CourierSettingsController` keeps a third private copy (`:107`). A 3rd-party method requires core edits — fails the owner's rule. (Couriers already solved this: `CourierManager`.)
2. **Phantom parent method** — `BankTransferPayment::getPaymentOptions()` (`:84-98`) falls back to a generic `bank_transfer` instance when no accounts exist: a method with no account details. Kill it: zero active accounts ⇒ zero bank methods offered.
3. **Identity** — accounts display as "Bank Transfer — BDO"; owner chose account-branded checkout (decision **B**): the method IS the account ("BDO", "GCash", "Maya").

## A. `PaymentManager` (mirror of `CourierManager`)
`app/Services/PaymentManager.php`:
- `register(string $class): void` — stores the class. Docblock states where registrations live.
- `methods(): array` — flatten all registered classes into `PaymentMethodInterface` instances: if the class has a static `instances(): array`, spread its result (factory-style, e.g. Bank Transfer's per-account methods); otherwise `new $class()`. Order: registration order; factories control their own internal order (bank accounts by `sort_order`).
- `enabledMethods(): array` — `methods()` filtered by `isEnabled()`.
- `get(string $key): ?PaymentMethodInterface` — exact key lookup across `methods()`.
- **Registration site:** `AppServiceProvider::boot()` (or a dedicated `PaymentServiceProvider` if `boot()` is crowded):
  ```php
  PaymentManager::register(BankTransferPayment::class);
  PaymentManager::register(HitPayPayment::class);
  PaymentManager::register(CodPayment::class);
  ```
  A 3rd-party extension adds one identical line in its own provider. `grep PaymentManager::register` answers "what payment methods exist?".

## B. Interface additions (small, defaulted)
`PaymentMethodInterface` gains:
- `getBaseKey(): string` — the method *family* key (defaults to `getKey()`; Bank Transfer account-instances return `'bank_transfer'`). Consumed by success-status resolution and restriction fallback, replacing scattered `preg_replace('/_\d+$/','',...)` string surgery (`CheckoutService.php:243`, `PaymentRestrictionService.php:37`).
- (Existing methods gain trivial implementations; no behavioral change for HitPay/COD.)

## C. Bank Transfer becomes a pure CRUD factory
- `BankTransferPayment::instances()`: one instance per **active** `BankAccount` (existing `active()` scope, `sort_order`). **No generic fallback** — the phantom parent dies.
- Keys unchanged: `bank_transfer_{accountId}` (stable; restrictions/status settings/history all survive).
- `getName()` = **the bank name alone** ("BDO", "GCash", "Maya") — decision B. The `bank_transfer_display_name` setting remains as the *family* label (used by restrictions UI grouping and anywhere the family is named), no longer a checkout prefix.
- `getImage()` may return the account's QR thumbnail if the checkout design wants it later (not required now).
- Family enable (`bank_transfer_enabled` setting) still gates the whole factory; each account's `is_active` gates its instance — parent toggle = master switch, account toggle = per-method switch. The Bank Accounts CRUD (existing `BankAccountController`, unchanged routes) is the factory's admin: **creating an account creates a method; deactivating hides it; deleting removes it** — and order history doesn't care (Spec A).

## D. Consumers move to the registry
1. `CheckoutService::getAvailablePaymentMethods()` — replace the three hardcoded blocks with `PaymentManager::enabledMethods()`; restriction filtering unchanged. `getPaymentMethod($key)` → `PaymentManager::get($key)` (+ enabled check). Checkout POST validation unchanged (unknown key still rejected downstream).
2. **Success status** — `CheckoutService.php:243-256` match keyed on `getBaseKey()`: setting key `{baseKey}_success_status_id` (drops the hardcoded match arms; hitpay stays webhook-resolved as today).
3. **Restrictions** — `PaymentRestrictionService::isAllowed()`: exact-key restriction row wins; else fall back to the base-key row (today's family behavior preserved — existing rows keep working). `PaymentRestrictionController::index()` builds its method list from `PaymentManager::methods()` grouped by base key (delete the const): every family shows; per-account rows are *possible* but the UI groups them under the family by default — no wall of bank accounts.
4. `PaymentMethodRestriction::getPaymentMethodLabel()` — resolve via `PaymentManager::get()` first, current match as fallback for keys no longer registered.
5. `CourierSettingsController` COD list (`:107`) — replace its private `['bank_transfer','hitpay','cod']` copy with base keys from `PaymentManager::methods()` (still merged with keys seen in orders).
6. `DashboardController.php:87` raw SQL breakdown — unchanged (keys stable; cosmetic).

## E. What a 3rd-party payment method looks like (the point of it all)
```php
// extensions/paymongo/src/PayMongoPayment.php  — implements PaymentMethodInterface
// extensions/paymongo/src/PayMongoServiceProvider.php:
PaymentManager::register(PayMongoPayment::class);
```
No core edits. Appears at checkout, in restrictions, in the COD mapping list, gets `{key}_success_status_id` support — all via the registry.

## Out of scope
- Turning COD/HitPay/Bank Transfer into extensions (explicitly rejected).
- Per-account success statuses (family-level stays).
- Checkout UI redesign beyond labels (options render as today, new names).
- Hooks/events/marketplace/drag-drop installers.
- Payment settings page restructure (family sections stay; Bank Transfer's section already IS the CRUD).

## Acceptance
- Checkout lists: BDO, BPI, Gcash, GOtyme, Maya (active accounts, by sort order), HitPay, COD — no "Bank Transfer —" prefixes, no generic bank option.
- Zero active accounts ⇒ no bank options (no phantom).
- Add an account in the CRUD ⇒ it appears at checkout immediately; deactivate ⇒ gone; delete ⇒ gone, old orders unaffected (Spec A).
- Existing restriction rows (`bank_transfer`, `hitpay`) still enforce exactly as before; a per-account row (e.g. `bank_transfer_7`) overrides its family's row for that account.
- `bank_transfer_success_status_id` still applied to every account-method order.
- A dummy extension registering a test method appears everywhere with zero core edits (the acceptance test for the owner's rule).
- `grep -rn "new BankTransferPayment\|new HitPayPayment\|new CodPayment" app/` outside PaymentMethods/ returns nothing.
