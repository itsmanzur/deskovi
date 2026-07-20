# Deskovi Ticket Bridge API Contract v1

WordPress connector (`itsdesk`) ↔ Deskovi SaaS conversations.

**Mode:** WP ships with local ticket store + mock outbound transport. Live SaaS implements the same payloads.

## Principles

- Conversations are canonical in SaaS; WP keeps a thin bridge cache for customer UX and retry.
- Customer ownership isolation: logged-in users only see their tickets; guests use verified session (order + email OTP).
- Every outbound event has an `idempotency_key`.
- Outbound via Action Scheduler when available — never block storefront requests.
- Attachments deferred (signed upload URLs in a later pass).

## Categories (default)

- `order` — Order question  
- `shipping` — Shipping / tracking  
- `refund` — Refund / return request  
- `product` — Product question  
- `other` — Other  

## WP admin REST (`manage_itsdesk`)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/itsdesk/v1/tickets` | List bridge tickets |
| GET | `/itsdesk/v1/tickets/{id}` | Ticket + messages |
| POST | `/itsdesk/v1/tickets` | Create ticket (admin/test) |
| POST | `/itsdesk/v1/tickets/{id}/replies` | Add reply / internal note |
| POST | `/itsdesk/v1/tickets/{id}/status` | Update status |

## WP customer REST (logged-in **or** verified guest session)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/itsdesk/v1/customer/tickets` | Own tickets only |
| POST | `/itsdesk/v1/customer/tickets` | Open ticket |
| GET | `/itsdesk/v1/customer/tickets/{id}` | Own ticket detail (marks read) |
| POST | `/itsdesk/v1/customer/tickets/{id}/replies` | Customer reply |
| GET | `/itsdesk/v1/customer/unread` | `{ count }` unread threads |

Guest verification: see [`guest-v1.md`](guest-v1.md).

## Ticket shape

```json
{
  "id": "tkt_local_xxx",
  "remote_id": null,
  "status": "open|pending|resolved|closed",
  "category": "order",
  "subject": "Where is my order?",
  "order_id": 1234,
  "customer_user_id": 5,
  "customer_email": "a@example.com",
  "customer_name": "Ada",
  "saas_url": "https://app.deskovi.com/tickets/…",
  "sync_status": "pending|synced|failed",
  "idempotency_key": "…",
  "created_at": "…",
  "updated_at": "…",
  "messages": [
    {
      "id": "msg_xxx",
      "author": "customer|agent|system",
      "body": "…",
      "internal": false,
      "created_at": "…"
    }
  ]
}
```

## SaaS outbound (future live)

`POST {saas}/api/v1/sites/{site_uuid}/tickets` — create  
`POST {saas}/api/v1/sites/{site_uuid}/tickets/{remote_id}/messages` — reply  
Signed like connection contract (timestamp, nonce, body hash, signature, idempotency_key).
