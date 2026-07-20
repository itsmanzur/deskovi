# Deskovi Connection API Contract v1

WordPress connector (`itsdesk`) ↔ Deskovi SaaS.

**Mode:** WP currently ships with `mock` transport. Swap to `http` when SaaS implements this contract.

Base (SaaS): `{saas_url}/api/v1/connect`  
WP admin REST (local): `/wp-json/itsdesk/v1/connection/*`

## Principles

- Outbound-first from WordPress after link.
- One-time, short-lived, domain-bound authorization code.
- Site-specific asymmetric identity (no permanent plain API key in settings).
- Every sensitive request: `timestamp`, `nonce`, `body_hash`, `site_id`, `signature`, `idempotency_key`.
- Replay / expired / revoked requests must be rejected.

## Flows

### 1. Start connection

**WP → Admin UI**

`POST /wp-json/itsdesk/v1/connection/start`

Response:

```json
{
  "mode": "mock",
  "state": "opaque-csrf-state",
  "authorize_url": "https://app.deskovi.com/connect/authorize?...",
  "mock_workspaces": [
    { "id": "ws_acme", "name": "Acme Support" }
  ],
  "expires_in": 600
}
```

Live mode: merchant opens `authorize_url`, picks workspace, SaaS returns a one-time `code` to the WP callback / paste step.

### 2. Complete connection (code exchange)

`POST /wp-json/itsdesk/v1/connection/complete`

```json
{
  "state": "opaque-csrf-state",
  "code": "one-time-code",
  "workspace_id": "ws_acme"
}
```

**SaaS contract (future live transport):**

`POST {saas}/api/v1/connect/exchange`

Request:

```json
{
  "code": "...",
  "site_url": "https://store.example",
  "public_key": "-----BEGIN PUBLIC KEY-----...",
  "plugin_version": "1.0.0-dev",
  "state": "..."
}
```

Response:

```json
{
  "site_uuid": "uuid",
  "workspace_id": "ws_acme",
  "workspace_name": "Acme Support",
  "saas_url": "https://app.deskovi.com",
  "scopes": ["orders.read", "tickets.write", "diagnostics.limited"]
}
```

### 3. Health / test

`POST /wp-json/itsdesk/v1/connection/test`  
Live: signed `POST {saas}/api/v1/sites/{site_uuid}/health`

### 4. Rotate keys

`POST /wp-json/itsdesk/v1/connection/rotate`  
Live: signed rotate endpoint; old key grace window TBD on SaaS.

### 5. Disconnect

`POST /wp-json/itsdesk/v1/connection/disconnect`  
Live: signed revoke; WP wipes local identity.

## WP connection state shape

```json
{
  "status": "connected|disconnected|error",
  "mode": "mock|live",
  "workspace_id": "ws_acme",
  "workspace_name": "Acme Support",
  "site_uuid": "uuid",
  "saas_url": "https://app.deskovi.com",
  "public_key_fingerprint": "sha256:abcd…",
  "scopes": ["orders.read", "tickets.write", "diagnostics.limited"],
  "connected_at": "2026-07-19T12:00:00Z",
  "last_sync_at": null,
  "last_health_at": "2026-07-19T12:01:00Z",
  "health": "healthy|degraded|unknown|error"
}
```

Private keys never leave WordPress and never appear in REST/JS responses.
