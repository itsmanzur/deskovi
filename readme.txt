=== Deskovi ===
Contributors: getwpkit
Tags: woocommerce, helpdesk, support, tickets, customer-service
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.0
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

== Fonts ==

The admin screen uses Inter (Latin) and Hind Siliguri (Bengali), both under the SIL Open Font License 1.1. The licence texts are in assets/fonts/.

== Changelog ==

= 1.4.0 =
* New admin design: a grouped side menu (Work, Insights, Automate, Channels, Settings), a real Overview with what is waiting, new tickets per day and the latest tickets, a calmer ticket list and one set of type sizes
* New fonts: Inter for English and Hind Siliguri for Bengali, bundled with the plugin and loaded only on Deskovi's own admin screen (nothing comes from an outside server); Bengali text gets more line spacing
* The chat widget now uses your store's own font
* Docs & Features is rewritten: every feature and a guide for each, and it shows what the optional Deskovi Pro add-on offers
* Add a documented extension API (hooks, filters, an admin JavaScript registry and a storefront-widget slot) so add-on plugins can attach without modifying Deskovi; screens can be placed in a menu group, bring icons and add cards and guides to Docs & Features
* Add a central permission check (`itsdesk_user_can` filter); default behaviour is unchanged
* Add `itsdesk_ticket_status_changed`, `itsdesk_ticket_priority_changed` and `itsdesk_order_refunded` actions, read-only reporting helpers and `TicketService::list_for_order()` for add-ons
* Fix: the WordPress personal-data eraser now actually deletes a customer's tickets, messages and attachment files
* Fix: attachments belonging to internal notes are no longer listed in, or downloadable through, the customer ticket view
* Fix: creating a ticket now fails with a clear error if it could not be stored, and over-long subjects/names are cut instead of breaking the insert
* Fix: two messages stored within the same second could appear in the wrong order
* Fix: uninstall now also removes notification settings, canned replies, uploaded attachments and the capability from the Shop Manager role
* Improve: faster ticket lists and reports on large stores (new database indexes on the ticket table)
* Improve: the personal-data export now includes customer-visible messages and attachment file names
* Improve: notification emails skip an empty recipient and can carry attachments
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
