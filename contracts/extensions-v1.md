# Deskovi Extension API v1

Public contract for add-on plugins (for example Deskovi Pro). Add-ons attach to the hooks and the JavaScript registry below instead of modifying Deskovi. Deskovi never calls add-on code and never contains it.

Gate on `ITSDESK_API_VERSION` (an integer, currently `1`), not on the plugin version. It changes only on a breaking change to this document.

## Loading order

Add-on plugin files are included before `plugins_loaded`, but an add-on may be included **before** Deskovi (for example `deskovi-pro/` sorts before `deskovi/`). At include time, do not touch any `Itsdesk\` class or `ITSDESK_*` constant. Register only:

```php
add_action( 'itsdesk_loaded', function ( $plugin ) {
	if ( ! defined( 'ITSDESK_API_VERSION' ) || ITSDESK_API_VERSION < 1 ) {
		return; // Show your own admin notice.
	}
	// Attach hooks here.
} );
```

`itsdesk_loaded` fires once, after Deskovi has wired itself. It does **not** fire when WooCommerce is inactive.

Use your own prefix for options, cron events and tables. Never reuse names starting with `itsdesk_` (Deskovi deletes `itsdesk_poll_inbox`, `itsdesk_imap_settings` and `itsdesk_imap_cron_schedule` on every request).

## PHP

| Hook | Type | Purpose |
|---|---|---|
| `itsdesk_loaded` `( Plugin $plugin )` | action | Deskovi is ready. |
| `itsdesk_user_can` `( bool $allowed, string $ability, int $user_id )` | filter | Widen or narrow an ability, or answer your own ability ids. Built-in abilities: `manage`, `refund`. Check with `Itsdesk\Admin\Capabilities::can( $ability )`. |
| `itsdesk_register_rest_routes` `( string $namespace )` | action | Register REST routes (inside `rest_api_init`). Use your own sub-path such as `/pro/...` or your own namespace. Deskovi's `GET /tickets/{id}` pattern would shadow a literal `/tickets/...` route registered later. |
| `itsdesk_admin_menu` `( string $page_slug )` | action | After the top-level menu is registered. |
| `itsdesk_admin_enqueue_scripts` `( string $hook_suffix )` | action | On the Deskovi admin page, right after the admin bundle is enqueued. Enqueue your bundle here with `itsdesk-admin` as a dependency. |
| `itsdesk_admin_script_deps` `( array $deps )` | filter | Dependencies of the Deskovi admin bundle. |
| `itsdesk_admin_config` `( array $config )` | filter | The `window.itsdeskAdmin` object. Append your own keys (for example capability flags). |
| `itsdesk_notification_channels` `( array $channels, string $event, array $ticket )` | filter | Add a channel: id => callable receiving `array{event, ticket, recipients, subject, body}`. The built-in `email` channel (wp_mail) is always present. A throwing channel is logged and never affects the ticket operation. |
| `itsdesk_notification_recipients` `( array $emails, string $event, array $ticket )` | filter | Recipient addresses of one notification. |
| `itsdesk_notification_email` `( array $mail, string $event, array $ticket )` | filter | One outgoing email: `to`, `subject`, `body`, `headers`. |
| `itsdesk_notification_settings_defaults` `( array $defaults )` | filter | Add notification toggles; only keys listed here are storable. Events: `agent_new_ticket`, `agent_new_reply`, `customer_reply`, `agent_assigned`. |
| `itsdesk_inbound_sources` `( array $sources )` | filter | Registry of inbound ticket sources (read with `Itsdesk\Extensions\Registry::inbound_sources()`). |
| `itsdesk_diagnostics_report` `( array $report )` | filter | Append rows to `checks` (`name`, `value`, `status` = `pass`/`ok`/`fail`/`info`) and `errors` (`time`, `code`, `message`). |
| `itsdesk_ticket_data` `( array $ticket, array $input )` | filter | A new ticket just before it is saved. Return the same array (the `id` must be unchanged). |
| `itsdesk_tickets_deleted` `( array $ids )` | action | After tickets (with messages and attachment files) were deleted, including by the GDPR eraser. Remove your per-ticket data here. |

Existing hooks (unchanged): `itsdesk_ticket_created`, `itsdesk_ticket_replied`, `itsdesk_ticket_assigned`, `itsdesk_attachment_allowed_types`, `itsdesk_attachment_max_size`, `itsdesk_attachment_max_per_ticket`.

Personal data: register your own exporter and eraser through the core `wp_privacy_personal_data_exporters` / `_erasers` filters, and add policy text with `wp_add_privacy_policy_content()`.

## Admin JavaScript

Your bundle must depend on the `itsdesk-admin` script handle so that `window.itsdesk.admin` exists. Deskovi mounts as soon as its script runs; registrations made later re-render the app. A component that throws is isolated (rendered as nothing) and logged to the console.

```js
const admin = window.itsdesk.admin;
admin.apiVersion; // 1
admin.ctx;        // { apiVersion, config, api, components }
```

### `ctx`

- `config` — `window.itsdeskAdmin` (REST root, nonce, current user, `capabilities`, plus keys added through `itsdesk_admin_config`).
- `api.get( path )`, `api.post( path, data )`, `api.del( path )`, `api.errorMessage( err )` — `path` is relative to the REST root (`/my-addon/v1/things`). The nonce is attached.
- `components` — `Toggle`, `StatusBadge`, `MetricCard`, `SkeletonPanel`, `Icons`. Use the shared `itsdesk-*` CSS classes of the admin app; scope your own CSS (`.itsdesk-admin .myaddon-*`).

### `registerScreen( { id, label, icon?, group?, order?, render( { onToast, ctx } ) } )`

Adds a nav item and a screen. `group: 'settings'` is a hint only; the nav stays flat. Re-registering the same `id` replaces it.

### `registerSlot( name, { id, order?, render( props ) } )`

`props` is the slot context plus `ctx`. Re-registering the same `id` in a slot replaces it.

| Slot | Where | Context |
|---|---|---|
| `ticket.detail.header` | Under the ticket title and customer line | detail |
| `ticket.detail.order` | Inside the order card, before the refund card | detail |
| `ticket.detail.meta` | After the status buttons, before the message thread | detail |
| `ticket.reply.toolbar` | In the reply action row, after "Insert canned reply" | detail |
| `ticket.list.rowExtra` | Ticket list row, after the assignee badge | `{ ticket, isOpen }` |

`detail` = `{ ticket, order, currentUser: { id, name, canManageOrders }, busy, onToast, reload, refreshTicket, replyBody, setReplyBody }`. `order` is `null` when no order is linked. `reload()` reloads the ticket list; `refreshTicket()` re-fetches the open ticket.

The ticket list is a narrow card list, not a table: "columns" are row extras.

### `registerBulkAction( { id, label, order?, tone?, run( { selectedIds, tickets, onToast, reload, clearSelection, ctx } ) } )`

Adds a button to the bulk bar (shown when tickets are selected). `run` executes in the browser — call your own REST route. A rejected promise is shown as an error toast. `tone`: `secondary` (default) or `danger`.

## Not part of v1

Automation/SLA event hooks (status, priority, order-link, refund), ticket query and category/status filters, widget config filter, an order-action registry and a path-based attachment ingest are not provided yet and will be added (non-breaking) when an add-on needs them.
