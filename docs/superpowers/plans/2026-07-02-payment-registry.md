# Payment Registry + Bank Transfer CRUD Factory — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Payment methods self-register through a `PaymentManager` (mirror of `CourierManager`); each active bank account becomes a first-class checkout method ("BDO", "GCash", "Maya"); the phantom generic bank method dies; a 3rd-party payment extension needs zero core edits.

**Architecture:** Explicit, greppable registration (`PaymentManager::register(X::class)` in `AppServiceProvider`) — no scanning, no magic. Factory classes expose static `instances(): array`; the registry flattens. Keys stay stable (`bank_transfer_{id}`); a new `getBaseKey()` replaces scattered `preg_replace('/_\d+$/','',...)` string surgery. Spec: `docs/superpowers/specs/2026-07-02-payment-registry-design.md`.

**Tech Stack:** Laravel 12, PHP 8.5, MySQL. Tests: PHPUnit by explicit file path, pure only (no DB).

## Global Constraints

- NEVER run DB-refreshing/seeding tests (live-copied dev DB). Pure unit tests only; the rest verifies read-only tinker/browser.
- No schema changes in this plan. No changes to `BankAccountController`/CRUD routes.
- Core methods stay in core (`app/Services/PaymentMethods/`) — registered, not extracted.
- Method keys unchanged: `bank_transfer_{id}`, `hitpay`, `cod`. Labels change (bank name only); keys never.
- Registration explicit only — grep `PaymentManager::register` must enumerate all methods.
- Commits: author Paolo, NO `Co-Authored-By`. Blade edits → `view:clear` (+`view:cache` compile check).
- Run tests: `./vendor/bin/phpunit tests/Unit/PaymentManagerTest.php tests/Unit/OrderSnapshotAccessorsTest.php` (+ extension suite when courier files change).

---

### Task 1: `PaymentManager` + `getBaseKey()` on the interface

**Files:**
- Create: `app/Services/PaymentManager.php`
- Modify: `app/Services/PaymentMethods/PaymentMethodInterface.php` (add `getBaseKey()`)
- Modify: `app/Services/PaymentMethods/{BankTransferPayment,HitPayPayment,CodPayment}.php` (implement `getBaseKey()`)
- Modify: `app/Providers/AppServiceProvider.php` (register the core three)
- Test: `tests/Unit/PaymentManagerTest.php`

**Interfaces:**
- Produces: `PaymentManager::register(string $class): void` · `PaymentManager::methods(): array` (flattens: class has static `instances()` ⇒ spread, else `new $class()`) · `PaymentManager::enabledMethods(): array` · `PaymentManager::get(string $key): ?PaymentMethodInterface` (exact key, enabled OR not — caller filters) · `PaymentManager::reset(): void` (test hook, clears registrations).
- Produces: `PaymentMethodInterface::getBaseKey(): string` — family key; `BankTransferPayment` returns `'bank_transfer'` always; HitPay/COD return their `getKey()`.

- [ ] **Step 1: Failing test** — create `tests/Unit/PaymentManagerTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\PaymentManager;
use App\Services\PaymentMethods\PaymentMethodInterface;
use App\Services\PaymentMethods\PaymentResult;
use PHPUnit\Framework\TestCase;

class FakeSingle implements PaymentMethodInterface
{
    public function getKey(): string { return 'fake_single'; }
    public function getBaseKey(): string { return 'fake_single'; }
    public function getName(): string { return 'Fake Single'; }
    public function getDescription(): string { return ''; }
    public function getImage(): ?string { return null; }
    public function isEnabled(): bool { return true; }
    public function processPayment(Order $order): PaymentResult { return new PaymentResult(true); }
}

class FakeFactoryMethod extends FakeSingle
{
    public function __construct(private int $n = 0) {}
    public function getKey(): string { return 'fake_factory_' . $this->n; }
    public function getBaseKey(): string { return 'fake_factory'; }
    public function isEnabled(): bool { return $this->n !== 2; } // #2 disabled
    public static function instances(): array { return [new self(1), new self(2)]; }
}

class PaymentManagerTest extends TestCase
{
    protected function setUp(): void { PaymentManager::reset(); }

    public function test_single_class_registers_one_method(): void
    {
        PaymentManager::register(FakeSingle::class);
        $this->assertSame(['fake_single'], array_map(fn ($m) => $m->getKey(), PaymentManager::methods()));
    }

    public function test_factory_class_spreads_instances(): void
    {
        PaymentManager::register(FakeFactoryMethod::class);
        $this->assertSame(['fake_factory_1', 'fake_factory_2'], array_map(fn ($m) => $m->getKey(), PaymentManager::methods()));
    }

    public function test_enabled_methods_filters(): void
    {
        PaymentManager::register(FakeFactoryMethod::class);
        $this->assertSame(['fake_factory_1'], array_map(fn ($m) => $m->getKey(), PaymentManager::enabledMethods()));
    }

    public function test_get_by_exact_key(): void
    {
        PaymentManager::register(FakeSingle::class);
        PaymentManager::register(FakeFactoryMethod::class);
        $this->assertSame('fake_factory_2', PaymentManager::get('fake_factory_2')?->getKey());
        $this->assertNull(PaymentManager::get('nope'));
    }
}
```
NOTE: `PaymentResult` — read `app/Services/PaymentMethods/PaymentResult.php` first and adapt the fake's constructor call to its real signature.

- [ ] **Step 2: Run, verify FAIL** — `./vendor/bin/phpunit tests/Unit/PaymentManagerTest.php` → class not found / interface missing method.

- [ ] **Step 3: Implement `app/Services/PaymentManager.php`:**
```php
<?php

namespace App\Services;

use App\Services\PaymentMethods\PaymentMethodInterface;

/**
 * The payment "sign-in sheet" (mirror of CourierManager). Methods self-register
 * from service providers — core registers its three in AppServiceProvider;
 * a 3rd-party extension calls PaymentManager::register(...) in its own provider
 * and needs NO core edits. Registration is explicit and greppable by design:
 * `grep -rn "PaymentManager::register"` enumerates every payment method.
 *
 * A registered class either IS one method (new $class()) or is a FACTORY
 * exposing static instances(): array (e.g. BankTransferPayment — one method
 * per active bank account).
 */
class PaymentManager
{
    /** @var array<int, class-string<PaymentMethodInterface>> */
    private static array $registered = [];

    public static function register(string $class): void
    {
        if (!in_array($class, self::$registered, true)) {
            self::$registered[] = $class;
        }
    }

    /** All method instances, factories flattened, registration order. @return PaymentMethodInterface[] */
    public static function methods(): array
    {
        $methods = [];
        foreach (self::$registered as $class) {
            if (method_exists($class, 'instances')) {
                foreach ($class::instances() as $m) {
                    $methods[] = $m;
                }
            } else {
                $methods[] = new $class();
            }
        }
        return $methods;
    }

    /** @return PaymentMethodInterface[] */
    public static function enabledMethods(): array
    {
        return array_values(array_filter(self::methods(), fn ($m) => $m->isEnabled()));
    }

    public static function get(string $key): ?PaymentMethodInterface
    {
        foreach (self::methods() as $m) {
            if ($m->getKey() === $key) {
                return $m;
            }
        }
        return null;
    }

    /** Test hook. */
    public static function reset(): void
    {
        self::$registered = [];
    }
}
```

- [ ] **Step 4: Interface + implementations.** Add to `PaymentMethodInterface` (after `getKey()`):
```php
    /** Family key for settings/restrictions grouping (e.g. bank_transfer_4 -> bank_transfer). */
    public function getBaseKey(): string;
```
Implementations: `BankTransferPayment` → `return 'bank_transfer';` · `HitPayPayment` → `return 'hitpay';` · `CodPayment` → `return 'cod';`

- [ ] **Step 5: Register core methods.** In `AppServiceProvider::boot()` (near the deep-address registration):
```php
        // Payment methods sign in here (see PaymentManager docblock). Extensions
        // add their own register() call in their own service provider.
        \App\Services\PaymentManager::register(\App\Services\PaymentMethods\BankTransferPayment::class);
        \App\Services\PaymentManager::register(\App\Services\PaymentMethods\HitPayPayment::class);
        \App\Services\PaymentManager::register(\App\Services\PaymentMethods\CodPayment::class);
```

- [ ] **Step 6: Tests PASS** — `./vendor/bin/phpunit tests/Unit/PaymentManagerTest.php` (4/4). Boot check: `php artisan about --only=environment`.

- [ ] **Step 7: Commit** — `git add` the 6 files; message: `feat(payments): PaymentManager sign-in sheet + getBaseKey on the method contract`

---

### Task 2: Bank Transfer becomes a factory — account-branded, no phantom

**Files:**
- Modify: `app/Services/PaymentMethods/BankTransferPayment.php` (`getPaymentOptions()` → `instances()`; `getName()`)

**Interfaces:**
- Produces: `BankTransferPayment::instances(): array` — one instance per ACTIVE `BankAccount` (`BankAccount::active()->orderBy('sort_order')->orderBy('bank_name')`), keys `bank_transfer_{id}` unchanged, **no generic fallback** (zero accounts ⇒ `[]`).
- Produces: `getName()` = the bank name alone (e.g. `Maya`) for account instances. Keep `getDisplayPrefix()`/`bank_transfer_display_name` for the generic-keyless case (used only as family label elsewhere).
- `getInstructionsForOrder()`, `isEnabled()`, `processPayment()` unchanged. `getPaymentOptions()` kept temporarily as a deprecated alias returning `instances()` (Task 3 removes its callers, then delete it in Task 3's commit).

- [ ] **Step 1: Read the class fully** (`app/Services/PaymentMethods/BankTransferPayment.php`) — note the constructor (`?int $bankAccountId`), how `getName()` builds `{prefix} — {bank_name}`, and `getPaymentOptions()` (~84-98).

- [ ] **Step 2: Implement:**
```php
    /**
     * The Bank Accounts CRUD is a method FACTORY: one first-class payment
     * method per active account. No accounts => no bank methods (the old
     * generic fallback is gone — a method with no account details was a
     * phantom). Family enable (bank_transfer_enabled) gates the whole factory.
     */
    public static function instances(): array
    {
        $family = new self();
        if (!$family->isEnabled()) {
            return [];
        }

        return BankAccount::active()
            ->orderBy('sort_order')->orderBy('bank_name')
            ->get()
            ->map(fn ($account) => new self($account->id))
            ->all();
    }
```
`getName()`: for an account instance return **just the bank name** (`$this->bankAccount->bank_name`), falling back to the current prefixed form only if the account row is missing. Keyless instance keeps the family label (`getDisplayPrefix()`).
IMPORTANT: with the family-enable check inside `instances()`, `PaymentManager::enabledMethods()` double-checks `isEnabled()` per instance — verify account instances' `isEnabled()` returns the family setting (it does today) so this stays consistent.

- [ ] **Step 3: Verify pure-ish** — `php -l`; read-only tinker: `foreach (\App\Services\PaymentMethods\BankTransferPayment::instances() as $m) echo $m->getKey()." => ".$m->getName().PHP_EOL;` → expect `bank_transfer_4 => Maya`, `bank_transfer_5 => BDO`, `bank_transfer_6 => BPI`, `bank_transfer_7 => Gcash` (per current sandbox accounts), NO plain `bank_transfer` entry.

- [ ] **Step 4: Commit** — `feat(payments): Bank Transfer is a CRUD factory — account-branded methods, phantom parent removed`

---

### Task 3: Consumers move to the registry (checkout + success status)

**Files:**
- Modify: `app/Services/CheckoutService.php` (`getAvailablePaymentMethods()` ~41-86; `getPaymentMethod()` ~88-96; success-status match ~243-256)
- Modify: `app/Services/PaymentMethods/BankTransferPayment.php` (delete the now-unused `getPaymentOptions()`)

**Interfaces:**
- Consumes: `PaymentManager::enabledMethods()/get()`, `getBaseKey()`.
- Produces: `getAvailablePaymentMethods()` returns registry methods (restriction filtering unchanged); success-status setting key = `{getBaseKey()}_success_status_id`.

- [ ] **Step 1:** Replace the three hardcoded instantiation blocks in `getAvailablePaymentMethods()` with:
```php
        $methods = \App\Services\PaymentManager::enabledMethods();
```
(keep the existing restriction-filter block untouched). Remove the now-unused `new BankTransferPayment/HitPayPayment/CodPayment` imports if no longer referenced elsewhere in the file — CHECK first (`processPayment` flows and `buildPaymentDetailsHtml` may reference classes).

- [ ] **Step 2:** `getPaymentMethod(string $key)` → `$m = \App\Services\PaymentManager::get($key); return ($m && $m->isEnabled()) ? $m : null;`

- [ ] **Step 3:** Success-status match (~243-256): replace `match (preg_replace('/_\d+$/', '', $paymentMethodKey)) {...}` with:
```php
        $baseKey = $paymentMethod->getBaseKey();
        $statusSettingKey = in_array($baseKey, ['bank_transfer', 'cod'], true) ? "{$baseKey}_success_status_id" : null;
```
WAIT — spec says generic `{baseKey}_success_status_id` for ANY method (3rd-party support). Use the generic form: `$statusSettingKey = "{$paymentMethod->getBaseKey()}_success_status_id";` and let the existing `Setting::...->value(...)` null-check handle unset keys (hitpay has no immediate-confirm path here anyway — verify hitpay's flow bypasses this block as today, and that a missing setting falls back to the default status exactly as before).

- [ ] **Step 4:** Delete `BankTransferPayment::getPaymentOptions()` (grep first: zero remaining callers).

- [ ] **Step 5: Verify** — `php -l`; tests still green (PaymentManagerTest + OrderSnapshotAccessorsTest); read-only tinker: `foreach (app(\App\Services\CheckoutService::class)->getAvailablePaymentMethods() as $m) echo $m->getKey()." => ".$m->getName().PHP_EOL;` → account-branded bank methods + HitPay + COD, no phantom. Grep acceptance: `grep -rn "new BankTransferPayment\|new HitPayPayment\|new CodPayment" app/ --include="*.php" | grep -v "PaymentMethods/"` → empty.

- [ ] **Step 6: Commit** — `refactor(payments): checkout + success status read the PaymentManager registry`

---

### Task 4: Restrictions go registry-aware (exact key wins, family fallback)

**Files:**
- Modify: `app/Services/PaymentRestrictionService.php` (~24-40, key normalization)
- Modify: `app/Http/Controllers/Admin/PaymentRestrictionController.php` (delete `PAYMENT_METHODS` const; `index()` builds from registry)
- Modify: `app/Models/PaymentMethodRestriction.php` (`getPaymentMethodLabel()` via registry first)

**Interfaces:**
- Consumes: `PaymentManager::methods()`, `getBaseKey()`.
- Produces: `isAllowed()` semantics — a restriction row matching the EXACT key wins; else the row matching the method's base key applies (today's family behavior); else allowed. Existing rows (`bank_transfer`, `hitpay`, `cod`) keep working unchanged.

- [ ] **Step 1:** `PaymentRestrictionService::isAllowed()` — replace the `preg_replace` normalization with:
```php
        $restriction = PaymentMethodRestriction::where('payment_method', $paymentKey)->first();
        if (!$restriction) {
            $method = \App\Services\PaymentManager::get($paymentKey);
            $baseKey = $method?->getBaseKey() ?? preg_replace('/_\d+$/', '', $paymentKey);
            if ($baseKey !== $paymentKey) {
                $restriction = PaymentMethodRestriction::where('payment_method', $baseKey)->first();
            }
        }
        if (!$restriction || !$restriction->is_active) {
            return true;
        }
```
(Adapt to the method's existing structure — the preg fallback covers keys not in the registry, preserving today's behavior for unknown keys.)

- [ ] **Step 2:** `PaymentRestrictionController::index()` — delete the const; build the family list from the registry:
```php
        $families = [];
        foreach (\App\Services\PaymentManager::methods() as $m) {
            $families[$m->getBaseKey()] ??= PaymentMethodRestriction::getPaymentMethodLabel($m->getBaseKey());
        }
```
then map `$methods` from `$families` (key + label + existing restriction row), preserving the existing view contract (`key`, `label`, `restriction`). `store()`/`validateRestriction()`: replace `in_: implode(self::PAYMENT_METHODS)` validation with `in_array($key, array_keys($families))`-style check via the same registry-derived list (compute once, reuse).

- [ ] **Step 3:** `PaymentMethodRestriction::getPaymentMethodLabel()` — try registry first:
```php
        $m = \App\Services\PaymentManager::get($key);
        if ($m) {
            return $m->getBaseKey() === $m->getKey() ? $m->getName() : $m->getName();
        }
```
Hmm — for a BASE key like `bank_transfer` the registry has no exact instance (accounts only); keep the existing `match` as the fallback for family keys and unknowns. Final shape: registry exact-key hit → its `getName()`; else existing match block.

- [ ] **Step 4: Verify** — `php -l` ×3; read-only tinker: `\App\Services\PaymentRestrictionService::isAllowed("bank_transfer_7", ["country_id"=>79], 100.0)` returns a bool without error; restrictions page loads (curl 302→200 via authed browser later). Tests green.

- [ ] **Step 5: Commit** — `refactor(payments): restrictions read the registry — exact-key override, family fallback`

---

### Task 5: Courier COD list reads the registry + E2E verification

**Files:**
- Modify: `extensions/courier-fulfillment/src/Http/Controllers/CourierSettingsController.php` (~107: replace the private `['bank_transfer','hitpay','cod']` copy)

**Interfaces:**
- Consumes: `PaymentManager::methods()` → base keys.

- [ ] **Step 1:** Replace the hardcoded core-three array in the COD-list builder with base keys from the registry:
```php
        $coreKeys = array_values(array_unique(array_map(fn ($m) => $m->getBaseKey(), \App\Services\PaymentManager::methods())));
```
(merged with keys-seen-in-orders exactly as today; marketplace exclusion list stays).

- [ ] **Step 2: Verify** — `php -l`; extension tests 12/12 (9 + PaymentManagerTest not in that suite — run both suites by path); read-only tinker prints the COD option keys unchanged (bank_transfer, cod, hitpay + order-seen keys).

- [ ] **Step 3: E2E (controller/browser pass):** checkout page as dev customer → payment list shows **Maya, BDO, BPI, Gcash** (bank names, no prefix, no phantom), HitPay, COD; place NO order. Admin → Payment restrictions page renders the same families as before. Fulfillment settings COD list unchanged.

- [ ] **Step 4: Commit** — `refactor(courier): COD mapping list derives core methods from the PaymentManager`

---

## Self-Review

- **Spec coverage:** §A registry+registration → T1. §B `getBaseKey` → T1. §C factory/no-phantom/bank-name labels → T2. §D.1-2 checkout+getPaymentMethod → T3. §D success status generic key → T3. §D.3-4 restrictions exact+fallback, controller dynamic, label via registry → T4. §D.5 courier COD list → T5. §D.6 dashboard SQL untouched (spec: unchanged). §E third-party acceptance = grep in T3 + registration pattern documented in PaymentManager docblock. Out-of-scope list respected (no settings-page restructure; family enable retained).
- **Placeholder scan:** Task 3 Step 3 contains an inline correction ("WAIT —") resolved to the generic form — rewrite cleanly for the implementer: FINAL instruction = use `"{$paymentMethod->getBaseKey()}_success_status_id"` with existing null-fallback. Task 4 Step 3 similarly resolved: registry exact-key hit → `getName()`, else existing match. No TBDs remain.
- **Type consistency:** `PaymentManager::{register,methods,enabledMethods,get,reset}` signatures consistent across T1/T3/T4/T5; `instances()` static convention consistent T1 (fake) / T2 (real); `getBaseKey()` used in T3/T4/T5 as defined in T1.
