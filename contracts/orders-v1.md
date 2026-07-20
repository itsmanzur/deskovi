# Deskovi Order Context API Contract v1

WordPress connector (`itsdesk`) ↔ Deskovi SaaS order context.

**Mode:** WP reads WooCommerce via CRUD/HPOS only. Mock outbound for status events. Live SaaS implements the same payloads later.

## Principles

- No posts/postmeta order queries — only `wc_get_order` / `wc_get_orders`.
- Customers see only their own orders; admins need `manage_itsdesk`.
- Privacy gates (`itsdesk_privacy_settings`):
  - `sync_billing_address` — include billing/shipping address blocks
  - `sync_phone` — include phone
  - `historical_import` — list window: `off` → last **30 days** for picker; `30|60|90` → that many days
- Never sync raw payment / card data.
- Status events are outbound-only via Action Scheduler — never block checkout.
- No remote refunds or destructive order actions in v1.

## WP REST (`itsdesk/v1`)

| Method | Path | Cap | Purpose |
|--------|------|-----|---------|
| GET | `/customer/orders` | logged-in | Own orders (windowed list) |
| GET | `/customer/orders/{id}` | logged-in | Own order snapshot + timeline |
| GET | `/orders/{id}` | `manage_itsdesk` | Admin order snapshot + timeline |
| GET | `/tickets/{id}/order` | `manage_itsdesk` | Linked order context for ticket |
| POST | `/tickets/{id}/order` | `manage_itsdesk` | Link/unlink `{ "order_id": 1234 \| null }` |

## List item shape

```json
{
  "id": 1234,
  "number": "1234",
  "status": "processing",
  "date_created": "2026-07-01T12:00:00+00:00",
  "currency": "USD",
  "total": "49.00"
}
```

## Snapshot + timeline shape

```json
{
  "id": 1234,
  "number": "1234",
  "status": "processing",
  "date_created": "2026-07-01T12:00:00+00:00",
  "currency": "USD",
  "total": "49.00",
  "payment_method_title": "Cash on delivery",
  "shipping_method": "Flat rate",
  "items": [
    { "name": "Desk mat", "quantity": 1, "sku": "DM-01" }
  ],
  "billing": null,
  "shipping": null,
  "phone": null,
  "timeline": [
    {
      "at": "2026-07-01T12:05:00+00:00",
      "type": "note",
      "message": "Order status changed from Pending payment to Processing."
    }
  ]
}
```

Address / phone fields are `null` when privacy toggles are off.

## Outbound event (mock / future live)

`order.status_changed`

```json
{
  "type": "order.status_changed",
  "idempotency_key": "ord_evt_…",
  "order_id": 1234,
  "from": "processing",
  "to": "completed",
  "snapshot": { },
  "occurred_at": "2026-07-19T12:00:00+00:00"
}
```

Signed like connection contract when live (timestamp, nonce, body hash, signature, idempotency_key).

## Exit criteria

- Link ticket ↔ order; admin panel shows snapshot
- Privacy off → no billing/phone in payload
- Customer REST cannot read another user’s order (403)
- Status change enqueues mock outbound (AS when available)
