# Email Campaigns (Marketing → Email)

OpenCart's `Marketing > Mail` rebuilt in Venta, with a real editor instead of a
textarea, and wired to the newsletter flag Venta already keeps.

## What this is not

The owner does not run marketing blasts and does not intend to. This is the
OpenCart feature they had, present because it should be present, and used in
practice to write to a handful of people in a specific customer group
(Resellers has 10 members, Rei Chino Pua has 2, Salfini has 1).

Everything that exists only to make thousand-recipient sends survivable was cut
deliberately: per-recipient result rows, resumable batching, retry-failed, an
enforced compliance footer, `List-Unsubscribe` headers, opt-out reconciliation
screens. Real bulk sending is card VENT-31 and goes through a third-party provider
when it is picked up.

## Evidence that drove the design

Gathered against `ngdadm_dev` on 2026-08-19.

- `customers.newsletter` already exists (boolean, cast in `Customer.php`) and is
  populated: **1,167 opted in, 2,380 opted out, of 3,547 customers**. The
  consent field is not new work.
- It is already written from three places: the storefront registration form,
  the account profile page, and the admin customer form. Checkout is the only
  gap, and that gap belongs to card VENT-31.
- No unsubscribe mechanism of any kind exists: no route, no token, no link.
- `EmailTemplate::replacePlaceholders()` already substitutes `{key}` tokens;
  the shipped order confirmation body reads `Hi {customer_name}` and
  `#{order_id}`. The campaign composer reuses that convention rather than
  inventing one.
- TinyMCE is self-hosted at `public/vendor/tinymce/` and already used by blog
  posts and products.
- `QUEUE_CONNECTION=database`.
- Customer groups: Default 3,534, Resellers 10, Rei Chino Pua 2, No COD 0,
  Salfini 1. Group targeting matters; group *size* does not.
- `config/admin-nav.php` already carries a **Marketing** group whose `items` is
  empty by design, fed entirely from extension manifests' `admin_nav` (Coupons
  and SEO contribute pointers today).
- Venta has no guest checkout: `CheckoutService` always attaches a customer, so
  every possible recipient is a customer row with an id.

## Rulings (owner, 2026-08-19)

1. Delivery is Venta's own SMTP plus the existing queue. No provider.
2. Audiences: **Customer Group**, **All Newsletter Subscribers**, **All
   Customers**, **specific customers picked by hand**. "Customers who bought
   product X" and "All Affiliates" are out.
3. **Opt-out is absolute.** A customer with `newsletter = false` is skipped by
   every audience, including hand-picked. "All Customers" means "all customers
   who have not opted out".
4. The unsubscribe link is a **placeholder the sender chooses to insert**, not a
   footer Venta staples on. It links to the same newsletter flag.
5. Editor: TinyMCE with a placeholder toolbar, plus **reusable saved
   templates**. Not a block editor, not a drag-and-drop canvas.
6. A Mailchimp-style drag-and-drop builder was explicitly ruled too ambitious.

## Architecture

### Placement

A new extension, `extensions/email-campaigns`, mirroring the Coupons layout
(`extension.json`, `routes/web.php`, `src/Models`, `src/Services`,
`src/Http/Controllers`, `database/migrations`, `resources/views`).

It contributes one nav pointer through its manifest:

```json
"admin_nav": [
  { "group": "Marketing", "after": "coupons", "key": "email",
    "label": "Email", "route": "admin.email-campaigns.index", "icon": "customers",
    "keywords": ["blast", "newsletter", "campaign", "mail", "marketing"] }
]
```

`config/admin-nav.php` is **not** modified. The Marketing group already accepts
manifest-fed items, so the nav registry stays the single source of truth and no
nav approval is required beyond this pointer.

Under the fresh-install policy (2026-08-19) the extension carries no
`default_enabled` flag, so it ships **not installed**.

### Data model

Two tables, both owned by the extension.

**`email_campaigns`**

| Column | Notes |
|---|---|
| `id` | |
| `store_id` | 1, consistent with the rest of Venta |
| `subject` | string |
| `body` | longtext, HTML from the editor |
| `audience_type` | `group` \| `subscribers` \| `all` \| `selected` |
| `audience_params` | JSON: `{group_id}` or `{customer_ids: []}`, null otherwise |
| `status` | `draft` \| `queued` \| `sent` \| `failed` |
| `recipients_total` | count actually mailed, written at send |
| `sent_at` | nullable |
| timestamps | |

**`email_campaign_templates`**

| Column | Notes |
|---|---|
| `id`, `store_id` | |
| `name` | what the admin calls it |
| `subject` | prefilled when starting from it |
| `body` | longtext |
| timestamps | |

These are deliberately NOT merged with core's `email_templates`. That table is
transactional, fired from code by slug, and its rows are the store's property
(see `project_seeders_are_idempotent`). Merging would put "Order Confirmation"
in the campaign picker and half-written promos in the order pipeline.

### Audience resolvers

One class per audience under `src/Audiences/`, each implementing:

```php
interface Audience
{
    public function query(array $params): \Illuminate\Database\Eloquent\Builder; // of Customer
    public function label(): string;
}
```

- `CustomerGroupAudience` — `where('customer_group_id', $params['group_id'])`
- `NewsletterSubscribersAudience` — `where('newsletter', true)`
- `AllCustomersAudience` — no constraint
- `SelectedCustomersAudience` — `whereIn('id', $params['customer_ids'])`

A resolver returns a query, never a collection, so counting is cheap and the
consent filter can be appended.

### Consent, applied once

`CampaignRecipients::for(Campaign $c): Builder` calls the resolver and then
appends the filter itself:

```php
return $audience->query($params)
    ->where('newsletter', true)
    ->where('is_active', true)
    ->whereNotNull('email');
```

Applying it **after** the resolver rather than inside each one means a future
audience cannot forget it. `NewsletterSubscribersAudience` applies it twice,
harmlessly.

`is_active` and `whereNotNull('email')` are design decisions, not owner rulings:
a disabled account should not receive marketing, and a null address cannot be
mailed. Both are cheap to drop if the owner disagrees.

The compose screen shows both figures before sending, from two counts on the
same query:

```
Audience: All Customers      3,547 in audience
                             1,167 will receive
```

### Unsubscribe

A signed route registered by the extension:

```php
Route::get('/newsletter/unsubscribe/{customer}', ...)
    ->name('newsletter.unsubscribe')
    ->middleware('signed');
```

`URL::signedRoute()` produces the link; Laravel's `signed` middleware rejects a
tampered one with a 403. No token table, nothing to look up, nothing to lose.

The handler sets `newsletter = false` and renders a storefront confirmation page
carrying a re-subscribe link.

That re-subscribe link is a **separately signed URL of its own route**
(`newsletter.resubscribe`), NOT the unsubscribe URL with a query parameter
appended. Laravel signs the full query string, so appending `?resubscribe=1` to
an already-signed link invalidates the signature and the page would 403. Two
routes, two signatures.

The link reaches the mail only as the `{unsubscribe_url}` placeholder. Bodies
that do not contain it get no link, matching OpenCart.

### Placeholders

Substitution reuses core's `{key}` convention. Available per recipient:

| Token | Value |
|---|---|
| `{first_name}` `{last_name}` | customer name parts |
| `{email}` | recipient address |
| `{store_name}` | `identity.site_name` |
| `{unsubscribe_url}` | signed link for this recipient |

Rendered by a small `CampaignRenderer` rather than `EmailTemplate`, because a
campaign body is not an `EmailTemplate` row. The substitution helper is the same
shape (`str_replace('{'.$key.'}', ...)`), kept local to the extension to avoid
widening a core class for one caller.

### Sending

`SendCampaignJob implements ShouldQueue`, dispatched on the existing database
queue so a 200-recipient send does not hold the browser.

The job chunks the recipient query (chunk size 100), renders per recipient, and
sends through `Mail::to()` with the store's configured mailer. It counts
successes into `recipients_total`, sets `status = sent` and `sent_at`, and logs
individual failures without aborting the run.

`status = failed` therefore means the JOB failed, not that a recipient bounced:
an unreachable mail server, a misconfigured mailer, an exception outside the
per-recipient try. A campaign that reached some recipients and skipped others
still ends `sent`, with `recipients_total` recording how many actually went. A
single bad address must never mark the whole send failed.

The body is wrapped in a fixed table-based shell (`resources/views/emails/shell.blade.php`)
carrying the store logo, palette and address, so TinyMCE output can never emit
layout that breaks Outlook. The shell is the extension's own, not core's
`emails.template`, because that one is shaped for transactional mail.

### Admin screens

Three, all inside the extension's own home under Marketing → Email.

1. **Index** — campaigns list: subject, audience label, recipients, status, sent
   date. Actions: New, view, duplicate.
2. **Compose** — "Send test to me" mails the logged-in admin's own address and
   is exempt from the consent filter, because an admin is not a customer and has
   no newsletter flag to consult. It renders placeholders against the admin's
   own name so the preview is realistic, and `{unsubscribe_url}` renders as a
   dead `#` link in a test rather than a signed one for a customer who is not
   the recipient. Otherwise: subject, audience picker (a group select, a customer
   multi-select, or nothing depending on type), live recipient counts, TinyMCE
   body, a placeholder toolbar that inserts at the cursor, "Send test to me",
   "Save as template", "Save draft", "Send".
3. **Templates** — saved starting points: list, create from a campaign, edit,
   delete.

Standard `x-admin.*` components and the armed save bar, per
`feedback_no_autosave_toggles`. No instructional paragraphs, per
`feedback_no_instructional_ui_text`.

## Verification

Feature tests, written first:

- each resolver returns exactly the right customers for its params
- the consent filter is applied to **every** audience, hand-picked included
- an inactive customer and a customer with a null email are excluded
- the compose screen reports audience total and will-receive separately
- `{unsubscribe_url}` renders a working signed link; a tampered signature 403s
- visiting the link sets `newsletter = false`; the resubscribe link sets it back
- a body without the placeholder contains no unsubscribe link
- placeholders substitute per recipient (two recipients get their own names)
- `SendCampaignJob` mails exactly the resolved set and never twice
- a campaign records `recipients_total`, `status` and `sent_at`
- a failing recipient does not abort the run
- saved templates prefill subject and body and are separate rows from
  `email_templates`
- the extension ships **not installed** and its nav pointer appears only once
  installed

## Deliberate omissions

- No per-recipient result table. A saved campaign row records what was sent and
  to how many; a failed address goes to the log. Reinstate only if bulk sending
  ever becomes real, which is card VENT-31's problem.
- No scheduling. Send now or save a draft.
- No open or click tracking. It needs pixel and redirect infrastructure and the
  owner has no use for it.
- No A/B testing, no segments beyond the four audiences, no automation.
- No block editor. The body is HTML from TinyMCE. If the block editor is ever
  wanted, `body` becomes a JSON block list and the renderer changes; nothing
  else in this design moves.

## Relationship to card VENT-31

#126 is the third-party provider integration (Mailchimp, Mailjet, Brevo, Resend)
plus the checkout consent tickbox. This spec deliberately leaves a single send
path (`SendCampaignJob`) and a single audience seam (`CampaignRecipients::for`),
so a provider driver slots in behind them rather than replacing the feature.

## Status

Design approved by the owner 2026-08-19. Not yet implemented.
