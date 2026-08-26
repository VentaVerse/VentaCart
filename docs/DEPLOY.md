# Venta Deploy Runbook

Read this **before** running `php artisan migrate` on any live install.

This file is the only durable record of which one-off commands each install owes
and which ones have already been run. Keep it accurate. If you run a one-off,
update the ledger below in the same commit.

---

## How re-run safety actually works

This document does **not** prevent anything from running twice. It is text.
Re-run safety comes from three places, in order of reliability:

1. **Laravel's `migrations` table.** A migration already applied is never applied
   again. This is real state tracking and it is trustworthy.
2. **Idempotency built into the command.** Most one-offs below fill nulls, skip
   what already exists, or refuse outright. The "Safe to re-run" column records
   what was verified in the source, not what was assumed.
3. **This ledger.** Only as good as the last person who updated it.

Never treat a step as "already done" because it looks familiar. Check the ledger,
then verify against the database.

---

## Install ledger

Update the commit and the date whenever an install is deployed.

### NGD (newgearday.com)

| | |
|---|---|
| Deployed commit | `801bf5e` (2026-08-20) |
| Verified | 2026-08-27 |

One-offs run:

- [ ] `layout:carry-homepage-sections` — REQUIRED at next deploy
- [ ] `catalog:thumbs` — optional
- [ ] `theme:move-presets-to-storage` — optional
- [ ] `venta:adopt-master-theme` — **state UNKNOWN, verify before running**
- [ ] `store-credit:import-oc` — **state UNKNOWN, command self-guards**

### Dragon Switch (dragon-switch.com)

| | |
|---|---|
| Deployed commit | `6abf1fa` (2026-07-30) |
| Verified | 2026-08-27 |

Crossing 28 migrations, three of them irreversible. See the danger list below and
run the pre-flight checks first.

One-offs run:

- [ ] username backfill — REQUIRED, admin login breaks without it
- [ ] fulfillment-stage backfill — REQUIRED, To Pack floods without it
- [ ] `orders:backfill-snapshots` — REQUIRED before deleting any payment or courier config
- [ ] `layout:carry-homepage-sections` — REQUIRED
- [ ] `venta:adopt-master-theme` — **state UNKNOWN, verify**
- [ ] `store-credit:import-oc` — only if this store has OpenCart balances

---

## Standard deploy sequence

```bash
git pull
composer install --no-dev -o
npm ci && npm run build
php artisan migrate            # see danger list first
php artisan optimize:clear
```

Then run the one-offs this install owes, per the ledger.

Every command below accepts `--dry-run`. Use it first. Always.

---

## Irreversible migrations

These three cannot be undone by `migrate:rollback`. Take a `mysqldump` before
crossing any of them.

### `2026_08_11_100000_hard_delete_products_drop_deleted_at.php`

**Permanently deletes every soft-deleted product**, then drops the column.

```php
DB::table('products')->whereNotNull('deleted_at')->delete();
```

`down()` only re-adds the column. The rows are gone.

**Pre-flight, run this yourself before migrating. Do not ask the owner to run it:**

```bash
php artisan tinker --execute="echo DB::table('products')->whereNotNull('deleted_at')->count();"
```

- **0** — safe, continue the deploy normally.
- **anything above 0** — STOP. That many products will be destroyed permanently.
  Report the number to the owner and wait for an explicit go-ahead. Do not run
  `migrate` in the meantime.

### `2026_08_08_162937_add_username_to_users_table.php`

Adds `username` nullable with **no backfill**. Admin login authenticates by
username (`app/Http/Requests/Auth/LoginRequest.php`, `Auth::attempt`), so after
this migration **every admin is locked out** until backfilled.

Run in the same session as the migrate, before closing the terminal:

```php
User::whereNull('username')->get()->each(fn ($u) =>
    $u->forceFill(['username' => Str::slug(Str::before($u->email, '@'), '_')])->saveQuietly()
);
```

Idempotent (`whereNull`). Check for collisions if two admins share an email
local-part across domains.

### `2026_08_07_003545_drop_shipping_zone_rates_table.php`

Drops `shipping_zone_rates`. `down()` is an empty body. Confirm the install has
migrated off zone rates first.

---

## Migrations needing a data backfill

### `2026_08_08_182307_add_fulfillment_stage_to_order_statuses.php`

Defaults **every** status to `ready`. Without the backfill, cancelled and unpaid
orders flood the To Pack tab with Book buttons.

- unpaid: Unpaid, Pending, Quote Ready for Payment
- cancelled: Canceled, Canceled Reversal, Chargeback, Denied, Expired, Failed,
  Refunded, Reversed, Voided
- everything else stays `ready`

Adjustable afterwards on the Order Statuses admin page.

---

## One-off command reference

| Command | Purpose | Safe to re-run |
|---|---|---|
| `layout:carry-homepage-sections` | Carries `homepage_sections` into `layout_placements`. **Required** on any install upgrading past 2026-08-20, or the homepage renders empty. Does not touch the old table. | Yes, already-carried sections are skipped |
| `orders:backfill-snapshots` | Freezes payment titles, tracking links and country names onto historical orders. Run **before deleting any bank account, courier or payment method**, since it resolves them through live lookups. | Yes, fills nulls only, pinned by an idempotency test |
| `venta:adopt-master-theme` | Converts the active legacy theme into a preset under the one master theme. | Checks for an existing preset file first |
| `store-credit:import-oc` | Imports OpenCart `customer_transaction` balances into the store credit ledger. | **Refuses** if the ledger has rows. Only `--append` overrides, and only when the incoming rows are known to be new |
| `catalog:thumbs` | Generates missing `_thumb` images for migrated OpenCart catalog images. | Yes, skips existing unless `--force`, never thumbnails a thumbnail |
| `theme:move-presets-to-storage` | Moves merchant-saved presets out of the repo into storage. | Yes, a moved preset is no longer in the source directory |

---

## Rules

- **Never** run a one-off without `--dry-run` first.
- **Never** run `migrate` on an install more than a few weeks stale without
  reading its full migration range and taking a dump.
- Update this ledger in the same commit as the deploy.
- Seeders fill gaps and never overwrite merchant-edited data. If one would, that
  is a bug in the seeder, not something to work around here.
