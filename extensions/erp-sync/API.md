# ERP sync API: special prices and discounts

These endpoints read and replace a product's special prices and discounts. They live under `/api/v1` and belong to the `catalogue` area: a key needs the **catalogue read** ability for `GET` and **catalogue write** for `PUT`. Every call is recorded in the API sync log.

| Method and path | Ability | What it does |
|---|---|---|
| `GET /api/v1/customer-groups` | catalogue read | The store's customer groups, so a sync can map its own price lists to a `customer_group_id` |
| `GET /api/v1/products/{sku}/specials` | catalogue read | The product's special prices |
| `PUT /api/v1/products/{sku}/specials` | catalogue write | Replaces the product's special prices with the list sent |
| `GET /api/v1/products/{sku}/discounts` | catalogue read | The product's discounts |
| `PUT /api/v1/products/{sku}/discounts` | catalogue write | Replaces the product's discounts with the list sent |

`GET /api/v1/products/{sku}` also returns two new keys, `specials` and `discounts`, in the same row shape as the endpoints above. Nothing else in that response changed.

## Special price and discount

A **special price** is a promotion for every buyer, with a required end time. A **discount** is an always-on price for one customer group, or for all customer groups, with an optional minimum quantity. A buyer pays the lowest of the regular price, any running special price and the discounts they qualify for.

## Customer groups

```
GET /api/v1/customer-groups
```

```json
[
  { "id": 1, "name": "Default", "is_default": true },
  { "id": 2, "name": "Resellers", "is_default": false }
]
```

## Special prices

```
PUT /api/v1/products/DEMO-001/specials
```

```json
{ "specials": [
  { "type": "percent", "value": 20, "starts_at": "2026-11-28T00:00:00+08:00", "ends_at": "2026-11-30T23:59:59+08:00" }
] }
```

| Field | Rule |
|---|---|
| `type` | Required. `fixed` or `percent`. |
| `value` | Required. A fixed price is 0 or more. A percent is more than 0 and less than 100. |
| `starts_at` | Optional. When empty, the special starts as soon as it is saved. |
| `ends_at` | Required, and after `starts_at`. |

The response is the list as stored:

```json
{ "specials": [
  { "id": 31, "type": "percent", "value": 20.0,
    "starts_at": "2026-11-28T00:00:00+08:00", "ends_at": "2026-11-30T23:59:59+08:00",
    "price": 4000.0, "updated_at": "2026-11-22T10:15:00+08:00" }
] }
```

`price` is what the row works out to on the product's regular price.

## Discounts

```
PUT /api/v1/products/DEMO-001/discounts
```

```json
{ "discounts": [
  { "customer_group_id": 2,    "min_quantity": 1,  "type": "fixed",   "value": 4500 },
  { "customer_group_id": 2,    "min_quantity": 10, "type": "percent", "value": 15 },
  { "customer_group_id": null, "min_quantity": 20, "type": "percent", "value": 5 }
] }
```

| Field | Rule |
|---|---|
| `customer_group_id` | Required in every row: a customer group id, or `null` for all customer groups. An id that is not a customer group is a 422. |
| `min_quantity` | Required. A whole number, 1 or more. |
| `type` | Required. `fixed` or `percent`. |
| `value` | Required. Same rules as for specials. |

A customer group (or `null`) may appear only once for each `min_quantity`. The response is `{ "discounts": [...] }`, each row with `id`, `customer_group_id`, `min_quantity`, `type`, `value`, the computed `price` and `updated_at`.

## Rules for both lists

- Bodies are wrapped (`{"specials": [...]}`, `{"discounts": [...]}`), so the response of a `GET` can be sent back as a `PUT`.
- A `PUT` replaces the whole list in one transaction. Send `[]` to clear it. A missing key is a 422, so a malformed body never clears anything.
- At most 50 rows per call.
- Times are ISO 8601 with an offset, such as `2026-11-28T00:00:00+08:00` or `...Z`. A time without an offset is a 422. Times come back in the store's offset.
- The whole list is checked before anything is written. A body that fails validation is a 422 even when the SKU is unknown.
- Prices belong to the product. A variant SKU answers 404 and names the product SKU to send instead.
- A special price or discount that is not below the regular price is accepted; it simply never lowers the price.

## Ownership

A `PUT` replaces the whole list. A sync that manages a store's promotions therefore replaces specials added by hand in the admin on its next run.
