# One doorway for order status changes

**Date:** 2026-09-25
**Status:** pass 1 built 2026-09-25, pass 2 outstanding

## The problem

A customer hears about their order only if the code path that moved the order
happens to remember to tell them. Each path decides for itself, so the paths
written before a channel existed say nothing through it.

Measured in the codebase today:

| Path | Moves status via | Email | SMS | Stock |
|---|---|---|---|---|
| Admin order page | `OrderStatusChanger` | tick | tick | yes |
| Fulfillment booking / manual shipment | `OrderStatusChanger` | fulfillment's list | fulfillment's list | yes |
| Courier fetch (poll + webhook) | direct write | courier map's flag | **never** | yes |
| Checkout, initial status | direct write | order confirmation | **never** | yes |
| Payment success (HitPay webhook, checkout success) | direct write | **never** | **never** | yes |
| Customer cancels | `OrderStatusChanger` | always | **never** | yes |
| ERP API status update | direct write | **never** | **never** | **never** |

Two consequences the owner reported:

- A QuadX parcel was picked up, the courier fetch moved the order to Shipped,
  and no SMS was sent even though Shipped is ticked to text. The tick is real;
  that path has no reader for it.
- Customers say they never received an email after an ERP status update. That
  endpoint writes the status by hand with `notify_customer` hardcoded false,
  and it is the path the business uses most. The same bypass means it never
  adjusts stock either.

The deeper cause: the answer to "does this status tell the customer" lives in
the courier-fulfillment extension (`NotifyMap`, plus a per-courier-status
notify flag), so anything outside that extension cannot consult it, and a store
that does not use fulfillment has no automation at all.

## The design

**One doorway.** Every status change goes through `OrderStatusChanger`, which
already writes history and adjusts stock. It announces each change. Whoever
moved the order - a human, fulfillment, the ERP, a payment webhook, checkout -
passes through the same door, and every listener hears the same announcement.

**Each channel owns its own answer.**

- **Email** ticks live on the core order status, edited on Sales, Order
  Statuses, beside `deducts_stock`, `counts_as_revenue`, `customer_can_cancel`
  and `tone`. "Does the customer hear about this" is the same kind of fact
  about a status.
- **SMS** ticks live in the UniSMS extension's own settings, because SMS costs
  money per message and the list of statuses worth paying for belongs beside
  the account that pays.

No extension depends on another. Fulfillment, erp-sync and sms-unisms each
depend only on core.

**The admin page keeps its override.** The Notify box arrives pre-ticked when
the target status is ticked, and unticking it suppresses that one message. An
explicit choice on the page wins for that action. This preserves the OpenCart
habit (a human with the order open may have a reason not to write) while making
the store's standing answer the default rather than something to remember.

**Nothing is sent when nothing changed.** Applying the status an order already
has produces no announcement. This is what stops the ERP setting Shipped and
the courier poll mapping to Shipped ten minutes later from speaking twice.

**Two ticked statuses mean two messages.** Deliberately. An order created at
Pending and paid into Processing sends two texts when both are ticked, and the
merchant's remedy is to tick one. No delay, no supersede rule, no code deciding
what the customer "really" needed.

**Sending rides the queue.** The announcement records what should go; the
worker sends it. Not for batching or delay, but because the same work now
happens inside a courier webhook (couriers expect an acknowledgement in about
three seconds) and inside the ERP's API call. A slow gateway must not hold
either open, and a failed send should retry rather than vanish.

## What changes, file by file

### Core

- `OrderStatusChanger::apply()` gains the announcement and an explicit-choice
  argument (null means "follow the store's ticks"). It already writes history
  and calls `StockService::adjustForStatusChange`.
- A new event carries: order, old status, new status, comment, and the caller's
  explicit choice when there was one.
- A listener per channel. Email's listener reads the status tick; it replaces
  the four hardcoded decisions scattered across callers.
- `order_statuses` gains `emails_customer` (boolean, default false), with the
  tick added to the Order Statuses form and table. Named for the channel it
  governs: SMS keeps its own list in UniSMS, and a future core channel would
  get its own column rather than overloading this one.
- `OrderNotificationChannel` gains a way for a channel to answer "do you want
  this status", so a channel declines by its own policy rather than every
  caller knowing about ticks.
- The order history row records what each channel did: sent or not, the reason
  when not, and the gateway's message id when there is one. We record that we
  sent and that the gateway accepted. We never claim delivery.

### Paths routed through the doorway

- `StatusMapper::apply()` (courier fetch: poll and webhook).
- `CheckoutService` initial status, and `CheckoutController`'s success status.
- `HitPayWebhookController` payment success.
- `ErpOrderController::updateStatus()`, which also starts adjusting stock as a
  consequence.

### sms-unisms

- Per-status ticks in its own settings, with the statuses listed from core.
- The message never contains a URL. The tracking comment's anchor becomes its
  text (the tracking number), not its href, and the send layer strips anything
  URL-shaped as a backstop. Philippine telcos drop messages carrying links, so
  a link does not merely annoy, it loses the message.
- An editable template, seeded as:
  `{store_name}: Order #{order_id} is now {order_status}`
  Placeholders: `{store_name}`, `{order_id}`, `{order_status}`,
  `{customer_name}`, `{tracking_number}`, `{courier_name}`, `{comment}`.
  None of them ever yields a URL.
- Manifest type becomes `notification`, so SMS gateways group in the extension
  list the way payment methods and shipping rates do.

### courier-fulfillment

- `NotifyMap` and the Notify column on Status Placement retire.
- The per-courier-status notify flag in the courier status map retires. It was
  a second answer to the same question; its only extra power was emailing on
  "picked up" but not "in transit" when both map to Shipped. If that is wanted
  later, the honest answer is a separate core status.
- Existing ticks are not migrated. The owner re-ticks; it is a handful of
  boxes.

## Order of work

**Pass 1, the reported bugs.** Doorway and announcement; the four paths routed
through it; ERP stock; the unchanged-status guard; queued sending; SMS ticks in
UniSMS; no URLs; the seeded template. After this: the ERP notifies and moves
stock, QuadX pickups text, payment success speaks.

**Pass 2, the tidy-up.** Email tick onto the core status; fulfillment's list
and courier flag retired; the manifest type.

Pass 2 only moves where a tick is stored. Nothing in it is urgent.

## Differences from the design

A handful of things landed differently than this document first described, or
were not spelled out here at all:

- The `emails_customer` tick moved onto the core order status in pass 1, not
  pass 2 as this document originally planned. Pass 1 promises that an ERP
  status update emails the customer, and there was nothing for that path to
  read without the tick already existing, so Task 1 brought it forward. What
  is left for pass 2 is only deletion, listed above.
- There is no committed carry-over migration for a live store's existing
  fulfillment ticks (`NotifyMap`, the courier map's own notify flag). The
  owner re-ticks the handful of boxes by hand on each live install instead,
  per the standing rule against shipping one-off, install-specific data
  migrations in this codebase.
- `NotifyChoice::isExplicit()` does not mean "an email tick was chosen". It
  means the caller took a position on CHANNELS specifically: only
  `explicit()` and `silent()` set a channels list (even an empty one).
  `follow()`, `emailAlways()` and `emailHandledElsewhere()` all leave it null
  and defer to each channel's own `wantsStatus()`.
- An unchanged status still announces when the caller's choice is explicit.
  `OrderStatusChanger::apply()` normally only announces when the status
  actually moved, but an operator ticking Notify (or a channel) while
  recording a tracking number on an order already on its target status still
  means to tell the customer. That decision only ever comes from a human
  with the order open, or a caller deliberately silencing everything.
- A caller may pass a `$customerComment` separate from the history row's own
  `$comment`, for a line that belongs on the record but not in the
  customer's inbox (a fulfilment audit line, for instance).
- `NotifyChoice::toldCustomerElsewhere()` answers a third question, distinct
  from `isExplicit()`: did this caller already tell the customer some other
  way even though the status email itself is suppressed. Only
  `emailHandledElsewhere()` sets it, and it is what keeps checkout's and
  HitPay's own confirmation emails reading as customer-visible
  (`notify_customer`) on the history row even though no status email went
  out for that change.
- HitPay's two payment-success paths (the webhook and the return-URL
  fallback) claim the order under a row lock before calling `apply()`, so
  two requests that both read the order before either wrote it cannot both
  announce. `SendOrderStatusNotification` implements
  `ShouldQueueAfterCommit` rather than plain `ShouldQueue` for the same
  reason: a caller may dispatch it from inside that transaction, and the
  job's `handle()` reads the history row by id to record its result against,
  so it must not run before that row's insert has committed.
- The SMS link guard matches shape, not a fixed list of endings. A token is
  dropped when it carries an `http://` or `https://` scheme, starts with
  `www.`, is a dotted host followed by a path (`bit.ly/xyz`,
  `newgearday.com.ph/track/ABC123`), or is a bare dotted host whose last
  label is a two-letter country code or one of a named set of generic
  endings (`com`, `net`, `org`, `info`, `biz`, `shop`, `store`, `online`,
  `site`, `xyz`, `app`, `dev`, `link`, `click`, `top`, `live`, `page`,
  `tech`, `blog`, `news`, `club`, `website`, `space`, `email`, `asia`).
  Working token by token and dropping the whole token, rather than trimming
  a matched substring, is what keeps a title glued to a name ("Mr.Reyes",
  "Ma.Cristina") from being treated as a host.

- A customer cancelling their own order is always emailed that it worked
  (`NotifyChoice::emailAlways()`), whatever the Cancelled status's email
  tick says; SMS follows the store's ticks like every other path.
- A history row can carry a `customer_comment` beside its `comment`. When a
  caller passes a customer-facing line that differs from the record's (the
  ERP's "Status updated via ERP API" fallback, fulfillment's "Order status
  set to X by fulfillment" suffix), the customer's email and account page
  show only the customer line, and the record keeps its own.
- Queued texts are throttled at the gateway's pace. The job asks for a
  limiter named `order-notification:{target}`, which only the UniSMS
  extension defines, and a 429 puts the job back on the queue rather than
  recording a failure.

## Testing

Test-first throughout. Per path: the status change announces, the listeners
send according to the ticks, and an explicit choice on the admin page wins.
Specifically:

- ERP status update: sends per the ticks, moves stock, and applying the same
  status twice speaks once.
- Courier fetch: a mapped status that is ticked for SMS sends one text.
- Payment success: the status change announces.
- Checkout: an initial status ticked for SMS sends one text; the order
  confirmation email is unaffected.
- Customer cancellation: the customer is always emailed that it worked,
  and SMS follows the store's ticks, so Cancelled unticked for SMS sends no
  text.
- No message composed by the SMS channel contains a URL, including when the
  comment carries a tracking link and when a merchant types one into the
  template.
- A channel that cannot reach the customer (no number, no gateway) reports it
  and changes nothing else.

The paths that touch checkout and payment webhooks are where a mistake costs
real orders, so those get a manual end-to-end run on the dev store as well,
before either pass is committed.

## Out of scope

- Delivery confirmation. UniSMS accepting a message is not the handset
  receiving it. If their API exposes a delivery-status endpoint, that is a
  later change.
- Notifications for changes that are not a status change, such as adding a
  tracking number to an order that is already Shipped.
- Moving the SMS gateway itself into core. UniSMS stays an extension behind the
  channel contract, which keeps the option of a second gateway cheap.
- Any per-call override from the ERP. The store's ticks decide, so staff cannot
  silence a customer by accident.
