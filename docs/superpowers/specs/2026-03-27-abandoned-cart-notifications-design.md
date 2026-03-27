# Abandoned Cart Notifications — Design Spec

## Overview

Add email notifications for abandoned carts. A cron command (`cart:notify`) runs on a schedule set by the admin. Each run checks for abandoned carts belonging to registered customers, matches them against configurable rules, and sends one notification per customer per run. Tracks all sends and identifies customers who eventually purchased the abandoned products.

## Data Model

### New Table: `abandoned_cart_rules`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| store_id | FK → stores | |
| email_template_id | FK → email_templates | Which template to send |
| customer_group_id | FK → customer_groups, nullable | null = all groups |
| max_sends | unsigned int, default 1 | Max notifications per cart |
| min_cart_age_hours | unsigned int, default 1 | Cart must be this old before first send |
| priority | unsigned int, default 0 | Higher = checked first. One rule wins per customer |
| is_active | boolean, default true | |
| timestamps | | |

**Priority logic:** When a customer matches multiple rules (e.g., a "Resellers" rule AND an "all groups" rule), only the highest-priority rule fires. This prevents duplicate emails per cron run.

### New Table: `abandoned_cart_notifications`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| cart_id | FK → carts | |
| customer_id | FK → customers | |
| abandoned_cart_rule_id | FK → abandoned_cart_rules | Which rule triggered this |
| email_template_id | FK → email_templates | Template used (snapshot — rule may change later) |
| send_count | unsigned int, default 0 | Incremented each send |
| last_sent_at | timestamp, nullable | |
| timestamps | | |

**Unique constraint:** `[cart_id, abandoned_cart_rule_id]` — one notification record per cart per rule.

## Cron Command

**Signature:** `cart:notify`

**Logic per run:**

1. Load all active rules, ordered by `priority DESC`
2. Query abandoned carts: registered customers only, has items, `updated_at` older than now
3. For each cart:
   a. **Skip if recovered** — customer has an order (created after cart's `updated_at`) containing at least one of the same `product_id` values from the cart items
   b. **Match rule** — iterate rules by priority. First rule where:
      - `customer_group_id` is null OR matches customer's group
      - Cart age >= rule's `min_cart_age_hours`
   c. **Skip if maxed out** — check `abandoned_cart_notifications` for this cart+rule. If `send_count >= max_sends`, skip
   d. **Send email** — use `TemplateMailService::send()` with the rule's template slug
   e. **Record** — create or update `abandoned_cart_notifications` (increment `send_count`, set `last_sent_at`)
4. Log summary: total checked, sent, skipped (already maxed), skipped (recovered)

**No schedule hardcoded** — admin adds the command to `routes/console.php` or crontab at their preferred frequency.

## Email Templates

Two templates seeded via `EmailTemplateSeeder`:

### Template 1: `abandoned_cart_reminder`

- **Name:** Abandoned Cart Reminder
- **Subject:** `You left something behind, {customer_name}!`
- **Body:**
```
Hi {customer_name},

You have items waiting in your cart:

{cart_items}

Cart Total: {cart_total}

Continue shopping: {cart_url}

— {store_name}
```

### Template 2: `abandoned_cart_final`

- **Name:** Abandoned Cart — Last Chance
- **Subject:** `{customer_name}, your cart is still waiting`
- **Body:**
```
Hi {customer_name},

Just a heads up — these items are still in your cart and may sell out:

{cart_items}

Cart Total: {cart_total}

Complete your order: {cart_url}

— {store_name}
```

### Placeholders

| Placeholder | Value |
|-------------|-------|
| `{customer_name}` | Customer's full name |
| `{cart_items}` | HTML list of cart items (name, variant, qty, price) |
| `{cart_total}` | Formatted cart total |
| `{cart_url}` | Link to storefront cart page |
| `{store_name}` | Store name from settings |

**Future:** `{coupon_code}` placeholder reserved for when the coupon code system is built.

## Admin UI

### Rules Management

**Route:** `GET /admin/abandoned-carts/rules`

List view with create/edit:
- Email Template (dropdown of active templates)
- Customer Group (dropdown, "All Groups" option for null)
- Max Sends (number input)
- Min Cart Age (hours, number input)
- Priority (number input)
- Active toggle

### Notification Log

**Route:** `GET /admin/abandoned-carts/notifications`

Table columns:
- Customer (name, email)
- Cart ID (link to cart detail)
- Template Used
- Send Count
- Last Sent
- Status (active / maxed out / recovered)

Filters: template, customer group, date range

### Recovered Carts

**Route:** `GET /admin/abandoned-carts/recovered`

Table columns:
- Customer (name, email)
- Cart Items (product names)
- Notified (count, last date)
- Order ID (link to order)
- Recovery Time (time between last notification and order)

**Recovery detection:** A cart is "recovered" when the customer has an order created after `abandoned_cart_notifications.last_sent_at` that contains at least one `product_id` matching the cart items.

## Files to Create/Modify

### New Files
- `database/migrations/xxxx_create_abandoned_cart_rules_table.php`
- `database/migrations/xxxx_create_abandoned_cart_notifications_table.php`
- `app/Models/AbandonedCartRule.php`
- `app/Models/AbandonedCartNotification.php`
- `app/Console/Commands/SendAbandonedCartNotifications.php`
- `resources/views/admin/abandoned-carts/rules.blade.php`
- `resources/views/admin/abandoned-carts/notifications.blade.php`
- `resources/views/admin/abandoned-carts/recovered.blade.php`

### Modified Files
- `database/seeders/EmailTemplateSeeder.php` — add 2 templates
- `app/Http/Controllers/Admin/AbandonedCartController.php` — add rules CRUD, notifications list, recovered list
- `app/Http/Controllers/Admin/EmailTemplateController.php` — add abandoned cart placeholders to PLACEHOLDER_MAP and TEST_DATA
- `routes/web.php` — add new admin routes
- `resources/views/layouts/admin.blade.php` — update sidebar if needed (sub-items under Abandoned Carts)

## Out of Scope

- Guest cart notifications (no email available)
- Coupon code generation (depends on future coupon system — placeholder reserved)
- Cart recovery tracking via unique URL tokens (standard cart URL used instead)
- Email open/click tracking
