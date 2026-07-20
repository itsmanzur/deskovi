# Deskovi Inbound Events Contract v1

SaaS → WordPress connector (`itsdesk`) delivery of agent activity.

**Auth:** HMAC with `delivery_secret` issued at connect exchange (same header set as connection-v1).

Base (WP): `POST {site_url}/wp-json/itsdesk/v1/saas/events`

## Headers

| Header | Meaning |
|--------|---------|
| `X-Deskovi-Timestamp` | Unix seconds |
| `X-Deskovi-Nonce` | Unique per request |
| `X-Deskovi-Body-Hash` | `sha256` hex of raw body |
| `X-Deskovi-Site-Id` | `site_uuid` |
| `X-Deskovi-Signature` | `base64(hmac_sha256(canonical, delivery_secret))` |
| `X-Deskovi-Idempotency-Key` | Stable event id |

Canonical string (LF-separated):

```
{timestamp}
{nonce}
{body_hash}
{site_id}
{idempotency_key}
POST
/wp-json/itsdesk/v1/saas/events
```

Reject expired timestamps, replayed nonces, bad signatures.

## Body

```json
{
  "type": "ticket.message.created|ticket.status.updated",
  "ticket": {
    "remote_id": "tkt_…",
    "local_ticket_id": "tkt_local_…",
    "status": "open|pending|resolved|closed"
  },
  "message": {
    "remote_id": "msg_…",
    "author": "agent",
    "body": "…",
    "internal": false,
    "created_at": "ISO-8601",
    "attachments": []
  }
}
```

- Skip delivering customer-visible payload when `message.internal` is true (SaaS should not enqueue those).
- `attachments[]` items (phase 3): `{ "id", "filename", "mime", "size", "url" }` with short-lived signed `url`.

## WP behaviour

- Resolve ticket by `remote_id` then `local_ticket_id`.
- Append message idempotently (`remote_message_id` / message `remote_id`).
- Set `agent_last_reply_at` for non-internal agent messages.
- Status updates must **not** re-outbound to SaaS (`source=saas`).
