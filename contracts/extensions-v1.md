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
| `itsdesk_notification_email` `( array $mail, string $event, array $ticket, array $context )` | filter | One outgoing email: `to`, `subject`, `body`, `headers`, `attachments` (absolute file paths, passed to `wp_mail`). `$context` = `{ audience: customer\|agent, message: the message that triggered it }`. Return an empty `to` to skip this recipient (for example when the add-on sends the mail itself). Since 1.4.0. |
| `itsdesk_notification_settings_defaults` `( array $defaults )` | filter | Add notification toggles; only keys listed here are storable. Events: `agent_new_ticket`, `agent_new_reply`, `customer_reply`, `agent_assigned`. |
| `itsdesk_inbound_sources` `( array $sources )` | filter | Registry of inbound ticket sources (read with `Itsdesk\Extensions\Registry::inbound_sources()`). |
| `itsdesk_diagnostics_report` `( array $report )` | filter | Append rows to `checks` (`name`, `value`, `status` = `pass`/`ok`/`fail`/`info`) and `errors` (`time`, `code`, `message`). |
| `itsdesk_ticket_data` `( array $ticket, array $input )` | filter | A new ticket just before it is saved. Return the same array (the `id` must be unchanged). |
| `itsdesk_widget_config` `( array $config )` | filter | The storefront widget bootstrap (`window.itsdeskWidget`). Add keys of your own (for example `pro`); plain data only, never secrets. Since 1.4.0. |
| `itsdesk_widget_enqueue_scripts` `( string $handle )` | action | The widget loader (`itsdesk-widget`) and its config are enqueued; enqueue your storefront script here with that handle as a dependency. Fires only where the widget loads (not on cart or checkout, and not when the widget is off). Since 1.4.0. |
| `itsdesk_ticket_status_changed` `( array $ticket, string $old, string $new )` | action | After a ticket's status really changed: agent action, bulk update, or Deskovi's own transition (an agent reply moves open → pending, a customer reply reopens a resolved ticket). Not fired when the status is set to the value it already had. Since 1.4.0. |
| `itsdesk_ticket_filter_ids` `( ?array $ids, array $filters )` | filter | Lets an add-on narrow the admin ticket list (and CSV export) to a set of ticket ids using its own filters, which arrive as `$filters['extra']` (the `x[name]=value` request entries, at most 5, plain text). Return `null` to leave the list as it is, an array of ids to keep only those (empty = nothing). Since 1.4.0. |
| `itsdesk_ticket_list_items` `( array $items, array $filters )` | filter | The tickets of one admin list page, so an add-on can add data to each row (for example `tags`). Since 1.4.0. |
| `itsdesk_tickets_merged` `( array $target, array $source, int $moved )` | action | A ticket was merged into another: its messages and attachments moved to `$target` and `$source` (as it was) is about to be deleted (which then fires `itsdesk_tickets_deleted`). Move the data you keep per ticket here. Since 1.4.0. |
| `itsdesk_ticket_split` `( array $new, array $original, int $moved )` | action | A ticket was split: the chosen message and everything after it moved to `$new`. No `itsdesk_ticket_created` fires (the customer opened nothing); copy the data you keep per ticket and start your own timers here. Since 1.4.0. |
| `itsdesk_ticket_imported` `( array $ticket )` | action | An importer stored a finished conversation with `TicketService::import()`. Nothing else fires for it (no created, replied or status events), so rules, timers, notifications and webhooks stay quiet. Since 1.4.0. |
| `itsdesk_ticket_scope` `( int $agent_id, int $user_id )` | filter | Limit a user to their own tickets: return that user's id (0 = no limit). Limited users see, open and change tickets assigned to them and tickets nobody has taken yet; the ticket list, CSV export and every `/tickets/{id}/...` route follow. Check with `Itsdesk\Admin\Capabilities::scope_agent_id()` and `can_access_ticket( $ticket_id )`. Since 1.4.0. |
| `itsdesk_can_access_ticket` `( bool $allowed, array $ticket, int $user_id )` | filter | Final say on whether a user may work on one ticket. Since 1.4.0. |
| `itsdesk_ticket_priority_changed` `( array $ticket, string $old, string $new )` | action | After a ticket's priority really changed. Since 1.4.0. |
| `itsdesk_order_refunded` `( array $ticket, int $order_id, float $amount, string $currency, bool $gateway_refunded )` | action | After a refund was issued from a ticket (`$gateway_refunded` false = recorded manually because the gateway cannot refund). Since 1.4.0. |
| `itsdesk_tickets_deleted` `( array $ids )` | action | After tickets (with messages and attachment files) were deleted, including by the GDPR eraser. Remove your per-ticket data here. |

### Creating tickets and replies from an add-on (trusted ingest)

An add-on that receives messages outside the web UI (for example email) calls Deskovi's services directly. Authorization is the caller's job — the services trust their arguments:

- `( new TicketService() )->create( $input, true )` — `$is_admin = true` skips the guest-session requirement **and** the order-ownership check (only "the order exists" is verified), so verify the order belongs to the sender yourself. `$input` keys: `subject` (cut to 255), `body`, `category` (must be one of Free's six), `customer_email` (≤190), `customer_name` (cut to 190), `customer_user_id`, `order_id`, `idempotency_key` (≤100; an existing key returns the existing ticket). Returns the ticket or a `WP_Error`; since 1.5.0 a ticket that could not be stored is an error, not a silent success.
- `->reply( $id, [ 'body' => … ], null, false, [ 'email' => $ticket_email, 'order_id' => 0 ] )` appends a **customer** message; `->reply( $id, [ 'body' => … ], null, true, null )` appends an **agent** message (visible, status moves open → pending). A `closed` ticket stays closed; call `update_status()` to reopen it. The new message is the last entry of the returned ticket's `messages`.
- Bodies are stored as **plain text** (all tags are stripped, including `<…>` in "On … wrote:" lines), so convert HTML and strip quoted text first.
- Extra data (message ids, source, participants) is not persisted by Deskovi; keep it in your own tables, keyed by ticket id (`itsdesk_tickets_deleted` tells you when to remove it).

### Attachments

`( new AttachmentService() )->store_from_string( $bytes, $name, $ticket_id, $message_id, 'customer' )` and `->store_from_path( $path, $name, … )` (the source file is copied, never moved) apply the same validation as web uploads: extension allow-list, content sniffing, size limit (measured on disk), per-ticket maximum. They return the attachment array or a `WP_Error`. Since 1.4.0. Files attached to an internal note are never exposed to customers (ticket attachment list and download).

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
- `components` — `Toggle`, `StatusBadge`, `MetricCard`, `SkeletonPanel`, `Icons`. Use the shared `itsdesk-*` CSS classes of the admin app; scope your own CSS (`.itsdesk-admin .myaddon-*`). Sub-tabs inside a screen: wrap buttons in `.itsdesk-tabs` (role `tablist`) and give each `.itsdesk-tab` (plus `.is-active`). Type sizes and colours are the `--dk-*` custom properties on `.itsdesk-admin` (`--dk-text-xs` … `--dk-text-hero`, `--dk-font`).

### `registerScreen( { id, label, icon?, group?, order?, render( { onToast, ctx } ) } )`

Adds a nav item and a screen. Since 1.4.0 the nav is grouped: `group` is one of `work`, `insights`, `automate`, `channels` or `settings` (missing or unknown values land in `settings`), `order` sorts inside the group (built-in screens use 10, 20, 30 …), and `icon` is a React node; `ctx.components.Icons` holds Deskovi's own icons (`IconBolt`, `IconClock`, `IconSparkle`, `IconBook`, `IconChart`, `IconMail`, `IconStar`, `IconGear` and the older ones). Re-registering the same `id` replaces it. Deskovi registers a built-in `pro` screen that tells stores without Deskovi Pro what it offers; an add-on that registers a screen with the id `pro` replaces it.

### `registerDocsFeature( { id, title, description, icon?, order?, plan?, screen? } )` and `registerDocsSection( { id, title, intro, steps?, order?, plan? } )`

Add a card to the Features tab and a guide to the Documentation tab of Docs & Features (since 1.4.0). `plan: 'pro'` lists the entry under "Deskovi Pro" and marks it as such; `screen` is the id of a screen the card links to. While no `plan: 'pro'` entry is registered, Deskovi shows its own static "available with Deskovi Pro" cards for the ids `pro-email`, `pro-automation`, `pro-sla`, `pro-selfservice`, `pro-ai` and `pro-reports`; registering a feature with one of those ids replaces that card, and registering any `plan: 'pro'` entry switches the heading from "Available with Deskovi Pro" to "Deskovi Pro". The `itsdesk_admin_config` key `proUrl` (filter `itsdesk_pro_url`, empty by default) is the link behind "Learn more" on the teaser screen.

### `registerSlot( name, { id, order?, render( props ) } )`

`props` is the slot context plus `ctx`. Re-registering the same `id` in a slot replaces it.

| Slot | Where | Context |
|---|---|---|
| `overview.top` | At the top of the Overview, under its title | `{ navigate( screenId ) }` (since 1.4.0) |
| `ticket.list.filters` | In the filter row above the ticket list | `{ extra, setExtra( key, value ) }`: add-on filter values (sent as `x[key]`, saved with views) (since 1.4.0) |
| `ticket.detail.header` | Under the ticket title and customer line | detail |
| `ticket.detail.order` | Inside the order card, before the refund card | detail |
| `ticket.detail.meta` | After the status buttons, before the message thread | detail |
| `ticket.reply.toolbar` | In the reply action row, after "Insert canned reply" | detail |
| `ticket.list.rowExtra` | Ticket list row, after the assignee badge | `{ ticket, isOpen }` |

`detail` = `{ ticket, order, currentUser: { id, name, canManageOrders }, busy, onToast, reload, refreshTicket, replyBody, setReplyBody }`. `order` is `null` when no order is linked. `reload()` reloads the ticket list; `refreshTicket()` re-fetches the open ticket.

The ticket list is a narrow card list, not a table: "columns" are row extras.

### `registerBulkAction( { id, label, order?, tone?, run( { selectedIds, tickets, onToast, reload, clearSelection, ctx } ) } )`

Adds a button to the bulk bar (shown when tickets are selected). `run` executes in the browser — call your own REST route. A rejected promise is shown as an error toast. `tone`: `secondary` (default) or `danger`.

### Reporting data

Read-only helpers on `TicketService` for add-ons that build reports (so they never query Deskovi's tables). Since 1.4.0.

- `report_rows( $from, $to )` — tickets created in `[$from, $to)` (ISO 8601 UTC strings, for example `gmdate( 'c' )`), newest last, capped at 50,000 rows. Each row: `id`, `status`, `priority`, `category`, `order_id`, `assigned_agent_id`, `created_at`, `updated_at` and `first_agent_reply_at` (the first visible agent message, or `null`). No message text.
- `open_rows()` — the same light rows (without `order_id` and `first_agent_reply_at`) for tickets that are open or pending, capped at 5,000.
- `ids_older_than( $before, $statuses, $limit = 100 )` — ids of tickets last updated before `$before` in the given statuses (cap 1,000), for data-retention add-ons. Delete them with `bulk_delete()`, which also removes messages and files and fires `itsdesk_tickets_deleted`.

### Looking up tickets by order

`( new TicketService() )->list_for_order( $order_id )` returns the tickets linked to a WooCommerce order (hydrated, newest first). Since 1.4.0.

## Storefront widget JavaScript

Enqueue your script from `itsdesk_widget_enqueue_scripts` with the `itsdesk-widget` dependency. `window.itsdesk.widget` exists as soon as the loader has run (before the panel is opened):

```js
const widget = window.itsdesk.widget;
widget.apiVersion; // 1
widget.registerSlot( 'ticket.create.aside', { id, order?, mount( host, ctx ) { /* fill host */ return () => { /* cleanup */ }; } } );
```

### `registerSlot( 'ticket.create.aside', { id, order?, mount( host, ctx ) } )`

`mount` runs once each time the customer opens the new-ticket form and receives an element that Deskovi places under the message field (it survives Deskovi's re-renders). The form is inside a shadow root: ship your own `<style>` inside `host`, and do not use `data-act` or `data-field` attributes (Deskovi reads those). Return a cleanup function if you need one. A throwing slot is isolated and logged to the console. Re-registering the same `id` replaces the earlier registration.

`ctx`:

- `config` — `window.itsdeskWidget` (including keys you added through `itsdesk_widget_config`).
- `form()` — `{ subject, body, category, order_id | null }`, the current values.
- `onChange( cb )` — called after the customer edits a field; returns an unsubscribe function.
- `api.get( path )` / `api.post( path, data )` — REST calls with the nonce and cookies attached; `path` is relative to the REST root (`itsdesk/v1/customer/orders/12`). Your own routes must do their own permission checks: Deskovi treats "a customer" as `is_user_logged_in()` or `Itsdesk\Auth\GuestSession::is_authenticated()` (a verified guest).
- `dismiss()` — leave the form without creating a ticket.

Since 1.4.0.

## Not part of v1

Order-link and refund event hooks, ticket query and category/status filters, a widget config filter and an order-action registry are not provided yet and will be added (non-breaking) when an add-on needs them.
