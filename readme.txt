=== Deskovi ===
Contributors: getwpkit
Tags: woocommerce, helpdesk, support, tickets, customer-service
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight WooCommerce helpdesk: tickets, order context for agents, and an optional customer chat widget — all stored locally on your site.

== Description ==

Deskovi adds a self-contained support ticket system to your WooCommerce store. Customers can open tickets (logged in or as a verified guest), agents reply from a dashboard inside wp-admin, and every ticket can be linked to the relevant order automatically.

**Features**

* Ticket system with replies, status, priority, and file attachments
* WooCommerce order context for agents (HPOS-compatible)
* Issue refunds or resend the order invoice email directly from a ticket
* Agent assignment, with per-agent ticket filtering
* Bulk status changes and bulk delete on the admin ticket list
* CSV export of tickets (respects the current search/filter)
* Canned replies (macros): reusable reply templates with one-click insert and placeholder substitution
* Configurable email notifications for ticket events
* Optional customer chat widget on the storefront
* Guest email OTP verification for visitors who are not logged in
* Privacy export and erase hooks for WordPress Tools → Export / Erase Personal Data
* Admin diagnostics and activity log

All data — tickets, messages, and attachments — is stored in your own WordPress database. Deskovi does not require an external account or send data to any third-party service.

**Requires** [WooCommerce](https://wordpress.org/plugins/woocommerce/).

== Installation ==

1. Install and activate [WooCommerce](https://wordpress.org/plugins/woocommerce/) if it is not already active.
2. Upload the `deskovi` folder to `/wp-content/plugins/`, or install the zip via Plugins → Add New → Upload Plugin.
3. Activate **Deskovi** through the Plugins menu.
4. Open **Deskovi** in the WordPress admin to configure the widget and privacy settings.

== Frequently Asked Questions ==

= Does this plugin require an account? =

No. Deskovi runs entirely on your own site and does not require signing up for any external service.

= Does it work without WooCommerce? =

No. WooCommerce must be installed and active. Deskovi will not load its features until WooCommerce is available.

= Where is customer data stored? =

All ticket, message, and attachment data is stored in your own WordPress database (custom tables). Nothing is sent off-site.

= How do I remove the chat widget? =

Disable the widget from the Deskovi admin settings. When the widget is off, storefront assets are not loaded.

= How do privacy requests work? =

Deskovi hooks into WordPress personal data export and erase tools so ticket-related personal data can be included when a user requests export or erasure.

= Are file attachments secure? =

Yes. Uploaded files are stored outside direct web access and are only ever served through an authenticated request that checks the requester actually owns the related ticket.

== Source Code ==

This plugin's admin dashboard and customer widget are built from TypeScript/React source using @wordpress/scripts (webpack). The compiled, minified files shipped in assets/admin and assets/widget are generated from this source.

Full source, build tooling, and build instructions (npm install && npm run build) are publicly available at:
https://github.com/itsmanzur/deskovi

== Screenshots ==

1. Tickets — manage tickets and conversation history from wp-admin
2. Customer widget — optional storefront chat for shoppers

== Changelog ==

= 1.7.0 =
* Add a documented storefront-widget extension API for add-ons: a slot in the new-ticket form, the `itsdesk_widget_config` filter and the `itsdesk_widget_enqueue_scripts` action
* No change for stores that do not use an add-on

= 1.6.0 =
* Add `itsdesk_ticket_status_changed` and `itsdesk_ticket_priority_changed` actions (fired for every change, including bulk and automatic ones) and `TicketService::list_for_order()` so add-ons can automate on ticket and order events
* No change for stores that do not use an add-on

= 1.5.0 =
* Add `store_from_path()` / `store_from_string()` to the attachment service so add-ons can save files that did not come from a web upload (same validation; the size is now measured on disk)
* Fix: attachments belonging to internal notes are no longer listed in, or downloadable through, the customer ticket view
* Fix: creating a ticket now fails with a clear error if it could not be stored, and over-long subjects/names are cut instead of breaking the insert
* Fix: two messages stored within the same second could appear in the wrong order (message ids now sort in creation order)
* Improve: notification emails skip an empty recipient and can carry attachments; the email filter receives the audience and triggering message

= 1.4.0 =
* Add a documented extension API (hooks, filters and an admin JavaScript registry) so add-on plugins can attach without modifying Deskovi
* Add a central permission check (`itsdesk_user_can` filter); default behaviour is unchanged
* Fix: the WordPress personal-data eraser now actually deletes a customer's tickets, messages and attachment files
* Improve: the personal-data export now includes customer-visible messages and attachment file names
* Fix: uninstall now also removes notification settings, canned replies, uploaded attachments and the capability from the Shop Manager role
* Raise the minimum WordPress version to 6.6 (required by the admin app's JavaScript runtime)

= 1.3.0 =
* Add ticket priority (Low/Normal/High/Urgent)
* Add bulk actions on the admin ticket list: multi-select status change and delete
* Add CSV export of tickets, respecting the current search/assignee filter
* Add issue-refund and resend-invoice actions directly from a ticket's linked order

= 1.2.0 =
* Add server-side pagination to the admin tickets list
* Add ticket search by subject, customer name, or email
* Add server-side assignee filter (All / Unassigned / Assigned to me)

= 1.1.0 =
* Add canned replies (macros): reusable reply templates with one-click insert and placeholder substitution ({customer_name}, {customer_email}, {order_id}, {agent_name})
* Add file attachment upload UI in the agent reply composer
* Add agent assignment and per-agent ticket filtering
* Add configurable email notification settings for ticket events

= 1.0.0 =
* Initial public release
* Local ticket system with order context (HPOS-aware)
* File attachments
* Optional customer chat widget and guest OTP
* Privacy export / erase integration
* Admin diagnostics

== Upgrade Notice ==

= 1.3.0 =
Adds ticket priority, bulk status/delete actions, CSV export, and order refund/resend-invoice actions from a ticket.

= 1.0.0 =
Initial public release.
