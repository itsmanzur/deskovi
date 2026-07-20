# Deskovi Customer Widget Contract v1

WordPress storefront launcher (`itsdesk`) — conversations via existing customer REST.

**Mode:** Lazy Web Component on the storefront. Mock ticket/order bridge (M3/M4). No SaaS realtime in this pass.

## Principles

- **Disabled = zero assets** — no script/style enqueue when `enabled` is false.
- **Lazy panel** — only a small loader is enqueued; full UI loads after user opens the launcher.
- **Skip cart/checkout** — never load on `is_cart()` / `is_checkout()` (classic or blocks).
- **Logged-in conversations** — guest may use order+email OTP session; ticket APIs accept logged-in **or** verified guest cookie.
- **Unread** — `GET /customer/unread`; FAB badge; poll while panel open.
- **Theme-safe** — custom element + Shadow DOM; placement/theme from `itsdesk_widget_settings`.
- **Cache isolation** — bootstrap localizes public config + REST nonce for the current request user; do not embed ticket/order PII in HTML. Page caches should vary by login cookie.

## Bootstrap config (`window.itsdeskWidget`)

```json
{
  "restRoot": "https://store.example/wp-json/",
  "nonce": "…",
  "loggedIn": true,
  "loginUrl": "https://store.example/wp-login.php?redirect_to=…",
  "placement": "bottom-right|bottom-left",
  "theme": "system|light|dark",
  "assetUrl": "https://store.example/wp-content/plugins/deskovi/assets/widget/",
  "i18n": { }
}
```

## Custom element

- Tag: `<deskovi-support>`
- Host injects element in `document.body` from loader (or mounts on existing tag).

## REST reuse (`itsdesk/v1`)

| Method | Path | When |
|--------|------|------|
| GET | `/tickets/categories` | New ticket form |
| GET | `/customer/tickets` | List |
| POST | `/customer/tickets` | Create (`order_id` optional) |
| GET | `/customer/tickets/{id}` | Thread |
| POST | `/customer/tickets/{id}/replies` | Reply |
| GET | `/customer/orders` | Order selector |
| GET | `/customer/orders/{id}` | Optional detail |

Auth: cookie + `X-WP-Nonce` from bootstrap.

## Exit criteria

- Widget off → no widget network requests / scripts
- Widget on → loader on normal pages; absent on checkout
- Logged-in: list / create / reply / link order
- Logged-out: login CTA only (no ticket data fetch until login)
